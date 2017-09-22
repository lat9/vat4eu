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
    public  $debug = array();
    
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
            $this->attach(
                $this, 
                array(
                    //- From /includes/classes/order.php
                    'ORDER_QUERY_ADMIN_COMPLETE',                   //- Reconstructing a previously-placed order
                    
                    //- From /customers.php
                    'NOTIFY_ADMIN_CUSTOMERS_UPDATE_VALIDATE',       //- Allows us to check/validate any entered VAT Number
                    'NOTIFY_ADMIN_CUSTOMERS_B4_ADDRESS_UPDATE',     //- Gives us the chance to insert the VAT-related fields
                    'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT',         //- The point at which the VAT Number fields are inserted
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER',        //- The point at which we add columns to the listing heading
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_NEW_FIELDS',    //- The point at which we insert additional fields for the listing
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT',       //- The point at which we insert a customer record in the listing

                    //- From /includes/functions/functions_customers.php
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
            // Issued by the order-class after completing its base reconstruction of a 
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
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-asc') . '"><span class="' . $asc_class . '">Asc</span></a>&nbsp;' . PHP_EOL . 
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-desc') . '"><span class="' . $desc_class . '">Desc</span></a>' . PHP_EOL .
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
                if (isset($_GET['disp_order']) && strpos($_GET['disp_order'], 'vatnum') === 0) {
                    $p3 = 'a.entry_vat_validated ';
                    $p3 .= ($_GET['disp_order'] == 'vatnum-asc') ? 'ASC' : 'DESC';
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
                $vat_validated = $p1['entry_vat_validated'];
                $vat_column =
                    '<td class="dataTableContent" align="center">' . $vat_number . '</td>';
                $p2 .= $vat_column;
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
    
    public function isVatRefundable()
    {
        return $this->checkVatIsRefundable();
    }
    
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
    // This function, called during processing where a VAT Number can be entered,
    
    //
    protected function validateVatNumber()
    {
        $vat_ok = true;
        $GLOBALS['vat_number'] = $vat_number = zen_db_prepare_input($_POST['vat_number']);
        $vat_number_length = strlen($vat_number);
        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;
        $this->vatNumberMessage = '';
        if ($vat_number != '') {
            $vat_ok = false;
            if (VAT4EU_MIN_LENGTH != '0' && $vat_number_length < VAT4EU_MIN_LENGTH) {
                $this->vatNumberMessage = VAT4EU_ENTRY_VAT_MIN_ERROR;
            } else {
                $countries_id = $_POST['entry_country_id'];
                $country_iso_code_2 = $this->getCountryIsoCode2($countries_id);
                if (strpos(strtoupper($vat_number), $country_iso_code_2) !== 0) {
                    $this->vatNumberMessage = sprintf(VAT4EU_ENTRY_VAT_PREFIX_INVALID, $country_iso_code_2, zen_get_country_name($countries_id));
                } else {
                    $validation = new VatValidation();
                    if (!$validation->checkVatNumber($country_iso_code_2, $vat_number)) {
                        $this->vatNumberMessage = VAT4EU_ENTRY_VAT_INVALID;
                    } else {
                        $vat_ok = true;
                        $this->vatNumberStatus = VatValidation::VAT_VIES_OK;
                    }
                }
            }
        }
        return $vat_ok;    
    }
    
    protected function formatVatNumberDisplay($cInfo)
    {
        if (isset($_POST['vat_number'])) {
            if ($this->vatNumberUpdateError) {
                $vat_field = zen_draw_input_field('vat_number', htmlspecialchars($cInfo->vat_number, ENT_COMPAT, CHARSET, true), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', 50)) . '&nbsp;' . $this->vatNumberMessage;
            } else {
                $vat_field = $cInfo->vat_number . zen_draw_hidden_field('vat_number', $cInfo->vat_number);
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
        }
        return '<tr><td class="main">' . VAT4EU_ENTRY_VAT_NUMBER . '</td><td class="main">' . $vat_field . '</td></tr>';
    }
    
    protected function checkVatIsRefundable()
    {
        $this->vatValidated = false;
        $this->vatIsRefundable = false;
        $this->vatNumber = '';
        if (isset($GLOBALS['order'])) {
            if ($customers_id === false) {
                $customers_id = $_SESSION['customer_id'];
            }
            if ($address_id === false) {
                $address_id = (isset($_SESSION['billto'])) ? $_SESSION['billto'] : $_SESSION['customer_default_address_id'];
            }
            $check = $GLOBALS['db']->Execute(
                "SELECT entry_country_id, entry_vat_number, entry_vat_validated
                   FROM " . TABLE_ADDRESS_BOOK . "
                  WHERE address_book_id = " . (int)$address_id . "
                    AND customers_id = " . (int)$customers_id . "
                  LIMIT 1"
            );
            if (!$check->EOF) {
                if ($this->isVatCountry($check->fields['entry_country_id'])) {
                    $this->vatNumber = $check->fields['entry_vat_number'];
                    $this->vatValidated = $check->fields['entry_vat_validated'];
                    if ($this->vatValidated == 1 && (VAT4EU_IN_COUNTRY_REFUND == 'true' || STORE_COUNTRY != $check->fields['entry_country_id'])) {
                        $this->vatIsRefundable = true;
                    }
                }
            }
        }
        return $this->vatIsRefundable;
    }
    
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
                $this->notify('NOTIFY_VAT4EU_ADDRESS_DEFAULT', $address_elements, $current_address, $show_vat_number);
                break;
        }
        // -----
        // If the VAT Number is to be displayed as part of the address-block, append its value to the
        // end of the address, including the "unverified" tag if the number has not been validated.
        //
        if ($show_vat_number) {
            $address_out = $current_address . $address_elements['cr'] . VAT4EU_ENTRY_VAT_NUMBER . ' ' . $this->vatNumber;
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
        $this->debug[] = $message;
    }

}