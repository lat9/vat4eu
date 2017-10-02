<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

class Vat4EuAdminObserver extends base 
{
    private $isEnabled = false;
    private $vatValidated = false;
    private $vatIsRefundable = false;
    private $vatNumber = '';
    private $vatNumberStatus = 0;
    private $addressFormatCount = 0;
    private $vatCountries = array();
    private $debug = false;
    private $oID = 0;
    
    // -----
    // On construction, this auto-loaded observer checks to see that the plugin is enabled and, if so:
    //
    // - Register for the notifications pertinent to the plugin's processing.
    // - Set initial values base on the plugin's database configuration.
    //
    public function __construct() 
    {
        // -----
        // Pull in the VatValidation class, enabling its constants to be used even if the plugin
        // isn't enabled.
        //
        if (!class_exists('VatValidation')) {
            require DIR_FS_CATALOG . DIR_WS_CLASSES . 'VatValidation.php';
        }
        
        // -----
        // If the plugin is enabled ...
        //
        if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true') {
            $this->isEnabled = true;
            $this->debug = (defined('VAT4EU_DEBUG') && VAT4EU_DEBUG == 'true');
            if (isset($_SESSION['admin_id'])) {
                $this->logfile = DIR_FS_LOGS . '/vat4eu_adm_' . $_SESSION['admin_id'] . '.log';
            } else {
                $this->logfile = DIR_FS_LOGS . '/vat4eu_adm.log';
            }
            if (isset($_GET['oID'])) {
                $this->oID = (int)$_GET['oID'];
            }
            $this->attach(
                $this, 
                array(
                    //- From admin/includes/classes/order.php
                    'ORDER_QUERY_ADMIN_COMPLETE',                   //- Reconstructing a previously-placed order
                    
                    //- From /includes/classes/order.php
                    'NOTIFY_ORDER_AFTER_QUERY',                     //- Reconstructing a previously-placed order (Edit Orders)
                    
                    //- From admin/customers.php
                    'NOTIFY_ADMIN_CUSTOMERS_UPDATE_VALIDATE',       //- Allows us to check/validate any entered VAT Number
                    'NOTIFY_ADMIN_CUSTOMERS_B4_ADDRESS_UPDATE',     //- Gives us the chance to insert the VAT-related fields
                    'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT',         //- The point at which the VAT Number fields are inserted
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER',        //- The point at which we add columns to the listing heading
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_NEW_FIELDS',    //- The point at which we insert additional fields for the listing
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT',       //- The point at which we insert a customer record in the listing
                    
                    //- From admin/edit_orders.php
                    'EDIT_ORDERS_PRE_UPDATE_ORDER',                 //- Allows us to update any VAT Number associated with the order
                    'EDIT_ORDERS_ADDITIONAL_ADDRESS_ROWS',          //- Allows us to insert a "VAT Number" input field.

                    //- From admin/includes/functions/functions_customers.php
                    'NOTIFY_END_ZEN_ADDRESS_FORMAT',                //- Issued at the end of the zen_address_format function
                )
            );

            $this->vatCountries = explode(',', str_replace(' ', '', VAT4EU_EU_COUNTRIES));
        }
    }

    // -----
    // This function receives control when one of its attached notifications is "fired".
    //
    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5) {
        switch ($eventID) {
            // -----
            // Issued by the admin-level order-class after completing its base reconstruction of a 
            // previously-placed order.  We'll tack any "VAT Number" recorded in the order
            // into the class' to-be-returned array.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //  $p1 ...... An associative array, with the orders_id as a key
            //
            case 'ORDER_QUERY_ADMIN_COMPLETE':
                $vat_info = $GLOBALS['db']->Execute(
                    "SELECT billing_vat_number, billing_vat_validated
                       FROM " . TABLE_ORDERS . "
                      WHERE orders_id = " . (int)$p1['orders_id'] . "
                      LIMIT 1"
                );
                if (!$vat_info->EOF) {
                    $this->vatNumber = $class->billing['billing_vat_number'] = $vat_info->fields['billing_vat_number'];
                    $this->vatValidated = $class->billing['billing_vat_validated'] = $vat_info->fields['billing_vat_validated'];
                }
                break;
                
            // -----
            // Issued by the storefront order-class after completing its base reconstruction of a 
            // previously-placed order.  We'll tack any "VAT Number" recorded in the order
            // into the class' to-be-returned array.  This version of the class is used by
            // Edit Orders.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //  $p2 ...... The orders_id associated with the order-object
            //
            case 'NOTIFY_ORDER_AFTER_QUERY':
                $vat_info = $GLOBALS['db']->Execute(
                    "SELECT billing_vat_number, billing_vat_validated
                       FROM " . TABLE_ORDERS . "
                      WHERE orders_id = " . (int)$p2 . "
                      LIMIT 1"
                );
                if (!$vat_info->EOF) {
                    $this->vatNumber = $class->billing['billing_vat_number'] = $vat_info->fields['billing_vat_number'];
                    $this->vatValidated = $class->billing['billing_vat_validated'] = $vat_info->fields['billing_vat_validated'];
                }
                break;
                
            // -----
            // Issued by Customers->Customers at the end of its field validation, giving us the
            // chance to validate the Vat-related fields.
            //
            // On entry:
            //
            // $p2 ... (r/w) A reference to the $error variable, set to true if the VAT is not valid.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_UPDATE_VALIDATE':
                $this->vatNumberUpdateError = !$this->validateVatNumber();
                $p2 = $this->vatNumberUpdateError;
                break;
                
            // -----
            // Issued by Customers->Customers just prior to an address-book update, allows
            // us to insert any updated value for the customer's VAT Number.
            //
            // On entry:
            //
            // $p1 ... (r/o) An associative array containing the customers_id and address_book_id to be updated.
            // $p2 ... (r/w) An associative array containing the to-be-updated address_book fields.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_B4_ADDRESS_UPDATE':
                $p2[] = array('fieldName' => 'entry_vat_number', 'value' => $_POST['vat_number'], 'type' => 'stringIgnoreNull');
                $p2[] = array('fieldName' => 'entry_vat_validated', 'value' => $this->vatNumberStatus, 'type' => 'integer');
                break;
                
            // -----
            // Issued by Customers->Customers during a customer-information edit.  Allows us to
            // insert additional input fields associated with the VAT Number.
            //
            // Note that this information is displayed in one of three modes:
            //
            // 1) Initial display, using database values.
            // 2) Error display; this field's value not valid.
            // 3) Error display; some other field's value is not valid.
            //
            // On entry:
            //
            // $p1 ... (r/o) An object containing the current customer's information.
            // $p2 ... (r/w) A string to contain additional fields to be displayed.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT':
                $p2 .= $this->formatVatNumberDisplay($p1);
                break;
                
            // -----
            // Issued by Customers->Customers prior to listing generation, allows us to insert
            // additional column heading(s).
            //
            // On entry:
            //
            // $p2 ... (r/w) A string value that can be updated to include any additional heading information.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER':
                $asc_class = $desc_class = 'SortOrderHeaderLink';
                $heading_text = VAT4EU_CUSTOMERS_HEADING;
                if (isset($_GET['list_order']) && strpos($_GET['list_order'], 'vatnum') === 0) {
                    $heading_text = '<span class="SortOrderHeader">' . $heading_text . '</span>';
                    if ($_GET['list_order'] == 'vatnum-asc') {
                        $asc_class = 'SortOrderHeader';
                    } else {
                        $desc_class = 'SortOrderHeader';
                    }
                }
                $current_parms = zen_get_all_get_params(array('list_order', 'page'));
                $heading = 
                    '<td class="dataTableHeadingContent" align="center" valign="top">' . PHP_EOL .
                        $heading_text . '<br />' . PHP_EOL .
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-asc') . '"><span class="' . $asc_class . '" title="' . VAT4EU_SORT_ASC . '">Asc</span></a>&nbsp;' . PHP_EOL . 
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-desc') . '"><span class="' . $desc_class . '" title="'. VAT4EU_SORT_DESC . '">Desc</span></a>' . PHP_EOL .
                    '</td>' . PHP_EOL;
                $p2 .= $heading;
                break;
                
            // -----
            // Issued by Customers->Customers prior to listing generation, allows us to "tack on"
            // the VAT-related fields.
            //
            // On entry:
            //
            // $p2 ... (r/w) A string containing the current fields to be gathered.
            // $p2 ... (r/w) A string containing the current customers' sort-order
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_NEW_FIELDS':
                $p2 .= ', a.entry_vat_number, a.entry_vat_validated';
                if (isset($_GET['list_order']) && strpos($_GET['list_order'], 'vatnum') === 0) {
                    $p3 = 'a.entry_vat_validated ';
                    $p3 .= ($_GET['list_order'] == 'vatnum-asc') ? 'ASC' : 'DESC';
                    $p3 .= ', a.entry_vat_number DESC, c.customers_lastname, c.customers_firstname';
                }
                break;
                
            // -----
            // Issued by Customers->Customers during listing generation, allows us to insert
            // additional VAT-related columns.
            //
            // On entry:
            //
            // $p1 ... (r/o) An array containing the current customer's information
            // $p2 ... (r/w) A string value to contain an updated table-column for the display.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT':
                $vat_number = $p1['entry_vat_number'];
                $vat_validated = '';
                if ($vat_number != '') {
                    $vat_validated = $this->showVatNumberStatus($p1['entry_vat_validated']);
                }
                $vat_column =
                    '<td class="dataTableContent" align="center">' . $vat_validated . $vat_number . '</td>';
                $p2 .= $vat_column;
                break;
                
            // -----
            // Issued by "Edit Orders" just prior to updating an order's header information, allows us to
            // process any update to the VAT Number associated with the billing address.
            //
            // On entry:
            //
            // $p1 ... (r/o) Identifies the associated order number.
            // $p2 ... (r/w) Contains the SQL data array for the to-be-updated order.
            //
            case 'EDIT_ORDERS_PRE_UPDATE_ORDER':
                $this->validateVatNumber();
                $p2['billing_vat_number'] = $GLOBALS['vat_number'];
                $p2['billing_vat_validated'] = $this->vatNumberStatus;
                break;
                
            // -----
            // Issued by "Edit Orders" during its rendering of the address blocks.  Allows us to
            // insert the fields associated with the VAT Number's display/modification.
            //
            // On entry:
            //
            // $p1 ... (r/o) A copy of the current order-object.
            // $p2 ... (r/w) A reference to a string value that is updated to contain the VAT Number field.
            //
            case 'EDIT_ORDERS_ADDITIONAL_ADDRESS_ROWS':
                $vat_number = $p1->billing['billing_vat_number'];
                $vat_validated = $p1->billing['billing_vat_validated'];
                $valid_indicator = '';
                if ($vat_number != '' && $vat_validated != VatValidation::VAT_VIES_OK && $vat_validated != VatValidation::VAT_ADMIN_OVERRIDE) {
                    $valid_indicator = '&nbsp;&nbsp;' . VAT4EU_UNVERIFIED;
                }
                $vat_info =
                    '<tr>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td><strong>' . VAT4EU_ENTRY_VAT_NUMBER . '</strong></td>' . PHP_EOL .
                    '    <td>' . zen_draw_input_field('vat_number', zen_html_quotes($vat_number), 'size="45"') . $valid_indicator . '</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '</tr>' . PHP_EOL .
                    '<tr>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td><strong>' . VAT4EU_ENTRY_OVERRIDE_VALIDATION . '</strong></td>' . PHP_EOL .
                    '    <td>' . zen_draw_checkbox_field('vat_number_override', '', ($vat_validated == VatValidation::VAT_ADMIN_OVERRIDE)) . '</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '    <td>&nbsp;</td>' . PHP_EOL .
                    '</tr>' . PHP_EOL;
                $p2 .= $vat_info;
                break;
                
            // -----
            // Issued at the end of the zen_address_format function, just prior to return.  Gives us a chance to
            // insert the "VAT Number" value, if indicated.
            //
            // This notification includes the following variables:
            //
            // $p1 ... (r/o) An associative array containing the various elements of the to-be-formatted address
            // $p2 ... (r/w) A reference to the functions return value, possibly modified by this processing.
            //
            case 'NOTIFY_END_ZEN_ADDRESS_FORMAT':
                $updated_address = $this->formatAddress($p1, $p2);
                if ($updated_address !== false) {
                    $p2 = $updated_address;
                }
                $this->debug($eventID . ': ' . var_export($this, true));
                break;
                
            default:
                break;
        }
    }
    
    // -----
    // Returns a boolean status indicating whether (true) or not (false) the currently-active
    // order-class object (if present) qualifies for a VAT refund.
    //
    public function isVatRefundable()
    {
        return $this->checkVatIsRefundable();
    }
    
    // -----
    // This helper function retrieves the 2-character ISO code for the specified country.
    //
    public function getCountryIsoCode2($countries_id)
    {
        $check = $GLOBALS['db']->Execute(
            "SELECT countries_iso_code_2
               FROM " . TABLE_COUNTRIES . "
              WHERE countries_id = " . (int)$countries_id . "
              LIMIT 1"
        );
        return ($check->EOF) ? 'Unknown' : $check->fields['countries_iso_code_2'];
    }
    
    // -----
    // This function returns a Font-Awesome glyph that represents the specified
    // VAT-validation status.
    //
    protected function showVatNumberStatus($vat_validation)
    {
        switch ($vat_validation) {
            case VatValidation::VAT_ADMIN_OVERRIDE:
                $glyph = 'fa fa-thumbs-up';
                $color = 'orange';
                $title = VAT4EU_ADMIN_OVERRIDE;
                break;
            case VatValidation::VAT_VIES_OK:
                $glyph = 'fa fa-thumbs-up';
                $color = 'green';
                $title = VAT4EU_VIES_OK;
                break;
            case VatValidation::VAT_VIES_NOT_OK:
                $glyph = 'fa fa-thumbs-down';
                $color = 'red';
                $title = VAT4EU_VIES_NOT_OK;
                break;
            default:
                $glyph = 'fa fa-thumbs-down';
                $color = 'orange';
                $title = VAT4EU_NOT_VALIDATED;
                break;
        }
        return '<i class="' . $glyph . '" aria-hidden="true" title="' . $title . '" style="color: ' . $color . '"></i> ';
    }
    
    // -----
    // This function, called during form validation where a VAT Number can be entered, e.g. Customers->Customers, returning
    // a boolean value that indicates whether (true) or not (false) the VAT Number is 'valid'.
    //
    protected function validateVatNumber()
    {       
        $GLOBALS['vat_number'] = $vat_number = strtoupper(zen_db_prepare_input($_POST['vat_number']));
        $GLOBALS['vat_number_override'] = $vat_number_override = (isset($_POST['vat_number_override']));
        $vat_number_length = strlen($vat_number);       

        $vat_ok = false;
        $this->vatNumberError = true;
        $this->vatNumberMessage = '';
        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;
        
        if ($GLOBALS['current_page'] == 'edit_orders.php') {
            $countries_id = $_POST['update_billing_country'];
        } else {
            $countries_id = $_POST['entry_country_id'];
        }
        $country_iso_code_2 = $this->getCountryIsoCode2($countries_id);
        
        $validation = new VatValidation($country_iso_code_2, $vat_number);
        $precheck_status = $validation->vatNumberPreCheck();
        switch ($precheck_status) {
            case VatValidation::VAT_NOT_SUPPLIED:
                $vat_ok = true;
                break;
                
            case VatValidation::VAT_REQUIRED:
                $this->vatNumberMessage = VAT4EU_ENTRY_VAT_REQUIRED;
                break;
                
            case VatValidation::VAT_MIN_LENGTH:
                $this->vatNumberMessage = VAT4EU_ENTRY_VAT_MIN_ERROR;
                break;
                
            case VatValidation::VAT_BAD_PREFIX:
                $this->vatNumberMessage = sprintf(VAT4EU_ENTRY_VAT_PREFIX_INVALID, $country_iso_code_2, zen_get_country_name($countries_id));
                break;
                
            case VatValidation::VAT_INVALID_CHARS:
                $this->vatNumberMessage = VAT4EU_ENTRY_VAT_INVALID_CHARS;
                break;
                
            case VatValidation::VAT_OK:
                $vat_ok = true;
                $this->vatNumberError = false;
                if ($vat_number_override) {
                    $this->vatNumberStatus = VatValidation::VAT_ADMIN_OVERRIDE;
                } else {
                    if ($validation->validateVatNumber()) {
                        $this->vatNumberStatus = VatValidation::VAT_VIES_OK;
                    } else {
                        $vat_ok = false;
                        $this->vatNumberStatus = VatValidation::VAT_VIES_NOT_OK;
                        $this->vatNumberMessage = VAT4EU_ENTRY_VAT_VIES_INVALID;
                    }
                }                
                break;
                
            default:
                $this->vatNumberMessage = "Unexpected return value from vatNumberPreCheck: $precheck_status, VAT number not authorized.";
                trigger_error($this->vatNumberMessage, E_USER_WARNING);
                break;
        }
        return $vat_ok;    
    }
    
    // -----
    // This function creates (and returns) the HTML associated with a "VAT Number".
    //
    protected function formatVatNumberDisplay($cInfo)
    {
        if (isset($_POST['vat_number'])) {
            if ($this->vatNumberUpdateError) {
                $vat_field = zen_draw_input_field('vat_number', htmlspecialchars($cInfo->vat_number, ENT_COMPAT, CHARSET, true), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', 50)) . '&nbsp;' . $this->vatNumberMessage;
                if ($this->vatNumberError) {
                    $vat_override = false;
                } else {
                    $vat_override = zen_draw_checkbox_field('vat_number_override');
                }
            } else {
                $vat_field = $cInfo->vat_number . zen_draw_hidden_field('vat_number', $cInfo->vat_number);
                $vat_override = zen_draw_checkbox_field('vat_number_override', '', isset($_POST['vat_number_override']), 'readonly"');
            }
        } else {
            $info = $GLOBALS['db']->Execute(
                "SELECT entry_vat_number, entry_vat_validated
                   FROM " . TABLE_ADDRESS_BOOK . "
                  WHERE customers_id = " . (int)$cInfo->customers_id . "
                    AND address_book_id = " . (int)$cInfo->customers_default_address_id . "
                  LIMIT 1"
            );
            $vat_field = zen_draw_input_field('vat_number', $info->fields['entry_vat_number'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', 50));
            $vat_override = zen_draw_checkbox_field('vat_number_override', '', ($info->fields['entry_vat_validated'] == VatValidation::VAT_ADMIN_OVERRIDE));
        }
        
        $vat_number_display = '<tr><td class="main">' . VAT4EU_ENTRY_VAT_NUMBER . '</td><td class="main">' . $vat_field . '</td></tr>' . PHP_EOL;
        if ($vat_override !== false) {
            $vat_number_display .= '<tr><td class="main">' . VAT4EU_ENTRY_OVERRIDE_VALIDATION . '</td><td class="main">' . $vat_override . '</td></tr>' . PHP_EOL;
        }
            
        return $vat_number_display;
    }
    
    // -----
    // This function checks the currently-active "order", if present, to see if a VAT "reverse charge" (aka refund) is appropriate.
    // An order's VAT is refundable, i.e. not charged, to the buyer if all of the following conditions are true:
    //
    // 1) The customer's billing address is within the EU.
    // 2) The customer has associated a VAT Number with that billing address.
    // 3) The VAT Number has either been validated via the VIES service or has been granted an admin-override.
    // 4) The order is being delivered to an address within the EU.
    // 5) Either the country to which the order is being delivered is DIFFERENT THAN the Zen Cart store's country
    //      or the VAT4EU configuration indicates that in-country deliveries are also refundable.
    //
    // NOTE:  Since the "VAT Refundable" check occurs **ONLY** during Edit Orders' processing, the order-object is based
    // on a storefront order-class query.  Virtual orders will have an empty string for the order's delivery country, while
    // shippable orders will contain an array of country-related information.
    //
    protected function checkVatIsRefundable()
    {
        $this->vatValidated = false;
        $this->vatIsRefundable = false;
        $this->vatNumber = '';
        if (isset($GLOBALS['order'])) {
            $billing_country_id = $this->getCountryIdFromOrder($GLOBALS['order']->billing['country']);
            $delivery_country_id = $this->getCountryIdFromOrder($GLOBALS['order']->delivery['country']);
            if ($billing_country_id !== false && $this->isVatCountry($billing_country_id)) {
                if ($delivery_country_id !== false && $this->isVatCountry($delivery_country_id)) {
                    $this->vatNumber = $GLOBALS['order']->billing['billing_vat_number'];
                    $this->vatValidated = $GLOBALS['order']->billing['billing_vat_validated'];
                    if ($this->vatValidated == VatValidation::VAT_VIES_OK || $this->vatValidated == vatValidation::VAT_ADMIN_OVERRIDE) {
                        if (VAT4EU_IN_COUNTRY_REFUND == 'true' || STORE_COUNTRY != $delivery_country_id) {
                            $this->vatIsRefundable = true;
                        }
                    }
                }
            }
            $this->debug(
                "checkVatIsRefundable for order #" . $this->oID . ", is " . (($this->vatIsRefundable) ? '' : 'not ') . "refundable." .  PHP_EOL . 
                "--> Billing" . PHP_EOL . var_export($GLOBALS['order']->billing['country'], true) . PHP_EOL .
                "--> Shipping" . PHP_EOL . var_export($GLOBALS['order']->delivery['country'], true)
            );
        }
        return $this->vatIsRefundable;
    }
    
    // -----
    // Pull the countries_id value from the supplied country field, as formatted by the storefront orders' class.
    // If the value supplied is not an array or doesn't have a country-id value, then the country field supplied is
    // most likely from the delivery section of a virtual (i.e. no shipping required) order.
    //
    // Returns boolean false if the country-id cannot be determined; the integer id value otherwise.
    //
    protected function getCountryIdFromOrder($country_info)
    {
        return (is_array($country_info) && isset($country_info['id'])) ? $country_info['id'] : false;
    }
    
    // ------
    // This function determines, based on the admin page currently active, whether a zen_address_format
    // function call should have the VAT Number appended and, if so, appends it!
    //
    protected function formatAddress($address_elements, $current_address)
    {
        // -----
        // Determine whether the address being formatted "qualifies" for the insertion of the VAT Number.
        //
        $address_out = false;
        $this->addressFormatCount++;

        // -----
        // Determine whether the VAT Number should be appended to the specified address, based
        // on the page from which the zen_address_format request was made.
        //
        switch ($GLOBALS['current_page']) {                   
            // -----
            // These pages include multiple address-blocks' display; the second call to zen_address_format references
            // the billing address, so the VAT Number is displayed.
            //
            // NOTE: This observer's previous "hook" into the order-class' query function has populated the order-object's
            // VAT-related fields.
            //
            case 'orders.php':
                if ($this->addressFormatCount == 3) {
                    $show_vat_number = true;
                }
                break;
                
            case 'invoice.php':
            case 'packingslip.php':
                if ($this->addressFormatCount == 1) {
                    $show_vat_number = true;
                }
                break;
                
            // -----
            // Some of the other pages have multiple address-blocks with conditional displays, so weed them
            // out now.
            //
            // Any other page, the VAT Number is not displayed.  A notification is made, however, to enable
            // other pages that display order-related addresses to "tack on".
            //
            default:
                $show_vat_number = false;
                $address_elements['address_format_count'] = $this->addressFormatCount;
                $this->notify('NOTIFY_VAT4EU_ADDRESS_DEFAULT', $address_elements, $current_address, $show_vat_number);
                break;
        }
        // -----
        // If the VAT Number is to be displayed as part of the address-block, append its value to the
        // end of the address, including the "unverified" tag if the number has not been validated.
        //
        if ($show_vat_number) {
            $address_out = $current_address . $address_elements['cr'] . VAT4EU_DISPLAY_VAT_NUMBER . $this->vatNumber;
            if ($this->vatValidated != VatValidation::VAT_VIES_OK && $this->vatValidated != VatValidation::VAT_ADMIN_OVERRIDE) {
                $address_out .= VAT4EU_UNVERIFIED;
            }
        }
        return $address_out;
    }
    
    // -----
    // This function returns a boolean indicator, identifying whether (true) or not (false) the
    // country associated with the "countries_id" input qualifies for this plugin's processing.
    //
    protected function isVatCountry($countries_id)
    {
        return in_array($this->getCountryIsoCode2($countries_id), $this->vatCountries);
    }
    
    private function debug($message)
    {
        if ($this->debug) {
            error_log(date('Y-m-d H:i:s') . ": $message" . PHP_EOL, 3, $this->logfile);
        }
    }

}