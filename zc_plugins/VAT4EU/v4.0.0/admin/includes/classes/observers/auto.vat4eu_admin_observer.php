<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
use Zencart\Plugins\Catalog\VAT4EU\VatValidation;

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

class zcObserverVat4EuAdminObserver extends base
{
    private bool $vatIsRefundable = false;
    private string $vatNumber;
    private int $vatNumberStatus;
    private $vatNumberMessage = '';
    private bool $vatNumberError = false;

    private $addressesToFormat;

    private array $vatCountries = [];

    private bool $debug = false;
    private string $logfile;

    private int $oID;

    private bool $messageSent = false;

    // -----
    // On construction, this auto-loaded observer checks to see that the plugin is enabled and, if so:
    //
    // - Register for the notifications pertinent to the plugin's processing.
    // - Set initial values based on the plugin's configuration settings.
    //
    public function __construct()
    {
        $this->debug = (VAT4EU_DEBUG === 'true');
        if (isset($_SESSION['admin_id'])) {
            $this->logfile = DIR_FS_LOGS . '/vat4eu_adm_' . $_SESSION['admin_id'] . '.log';
        } else {
            $this->logfile = DIR_FS_LOGS . '/vat4eu_adm.log';
        }

        $this->oID = (int)($_GET['oID'] ?? 0);
        $this->vatCountries = explode(',', str_replace(' ', '', VAT4EU_EU_COUNTRIES));

        // -----
        // Determine which notifications to 'watch for', based on the active page.
        //
        $attach_array = [
            //- From /includes/classes/order.php
            'NOTIFY_ORDER_AFTER_QUERY',         //- Reconstructing a previously-placed order

            //- From admin/includes/functions/functions_addresses.php
            'NOTIFY_END_ZEN_ADDRESS_FORMAT',    //- Issued at the end of the zen_address_format function (*)
        ];

        global $current_page;
        if ($current_page === FILENAME_CUSTOMERS . '.php') {
            $attach_array = array_merge(
                $attach_array,
                [
                    'NOTIFY_ADMIN_CUSTOMERS_UPDATE_VALIDATE',   //- Allows us to check/validate any entered VAT Number
                    'NOTIFY_ADMIN_CUSTOMERS_B4_ADDRESS_UPDATE', //- Gives us the chance to insert the VAT-related fields
                    'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT',     //- The point at which the VAT Number fields are inserted
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER',    //- The point at which we add columns to the listing heading
                    'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT',   //- The point at which we insert a customer record in the listing
                ]
            );
        } elseif ($current_page === 'edit_orders.php') {
            $attach_array = array_merge(
                $attach_array,
                [
                    'NOTIFY_EO_ADDL_BILLING_ADDRESS_ROWS',      //- Allows us to add VAT-related fields to a to-be-updated order
                    'NOTIFY_EO_ADDRESS_SAVE',                   //- Enables this class to validate any VAT-related fields
                    'NOTIFY_EO_UPDATING_ADDR_FIELD',            //- Updating a non-builtin address field
                ]
            );
        }

        $this->attach($this, $attach_array);
    }

    // -----
    // This function receives control when one of its attached notifications is "fired".
    //
    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5) {
        switch ($eventID) {
            // -----
            // Issued by the common order-class after completing its base reconstruction of a 
            // previously-placed order.  We'll tack any "VAT Number" recorded in the order
            // into the class' to-be-returned array.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //  $p2 ...... The orders_id associated with the order-object
            //
            case 'NOTIFY_ORDER_AFTER_QUERY':
                global $db;

                $vat_info = $db->Execute(
                    "SELECT billing_vat_number, billing_vat_validated
                       FROM " . TABLE_ORDERS . "
                      WHERE orders_id = " . (int)$p2 . "
                      LIMIT 1"
                );
                if (!$vat_info->EOF) {
                    $class->billing['billing_vat_number'] = $vat_info->fields['billing_vat_number'];
                    $this->vatNumber = $vat_info->fields['billing_vat_number'];

                    $class->billing['billing_vat_validated'] = (int)$vat_info->fields['billing_vat_validated'];
                    $this->vatNumberStatus = (int)$vat_info->fields['billing_vat_validated'];
                }
                break;

            // -----
            // Issued by Customers :: Customers at the end of its field validation, giving us the
            // chance to validate the Vat-related fields.
            //
            // On entry:
            //
            // $p2 ... (r/w) A reference to the $error variable, set to true if the VAT is not valid.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_UPDATE_VALIDATE':
                $this->vatNumber = strtoupper(zen_db_prepare_input($_POST['vat_number']));
                if ($this->validateVatNumber(isset($_POST['vat_number_override'])) === false) {
                    global $messageStack;
                    $messageStack->add($this->vatNumberMessage, 'error');
                    $p2 = true;
                }
                break;

            // -----
            // Issued by Customers :: Customers just prior to an address-book update, allows
            // us to insert any updated value for the customer's VAT Number.
            //
            // On entry:
            //
            // $p1 ... (r/o) An associative array containing the customers_id and address_book_id to be updated.
            // $p2 ... (r/w) An associative array containing the to-be-updated address_book fields.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_B4_ADDRESS_UPDATE':
                $p2[] = [
                    'fieldName' => 'entry_vat_number',
                    'value' => $this->vatNumber,
                    'type' => 'stringIgnoreNull'
                ];
                $p2[] = [
                    'fieldName' => 'entry_vat_validated',
                    'value' => $this->vatNumberStatus,
                    'type' => 'integer'
                ];
                break;

            // -----
            // Issued by Customers :: Customers during a customer-information edit.  Allows us to
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
            // $p2 ... (r/w) An array to contain additional fields to be displayed.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT':
                $p2 = array_merge($p2, $this->formatVatNumberDisplay($p1));
                break;

            // -----
            // Issued by Customers :: Customers prior to listing generation, allows us to insert
            // additional column heading(s).
            //
            // On entry:
            //
            // $p2 ... (r/w) An array value that can be updated to include any additional heading information.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER':
                $asc_class = $desc_class = 'SortOrderHeaderLink';
                $heading_text = VAT4EU_CUSTOMERS_HEADING;
                if (strpos(($_GET['list_order'] ?? ''), 'vatnum') === 0) {
                    $heading_text = '<span class="SortOrderHeader">' . $heading_text . '</span>';
                    global $disp_order;
                    if ($_GET['list_order'] === 'vatnum-asc') {
                        $asc_class = 'SortOrderHeader';
                        $disp_order = 'a.entry_vat_number, c.customers_lastname, c.customers_firstname';
                    } else {
                        $desc_class = 'SortOrderHeader';
                        $disp_order = 'a.entry_vat_number DESC, c.customers_lastname, c.customers_firstname';
                    }
                }
                $current_parms = zen_get_all_get_params(['list_order', 'page']);
                $heading = [
                    'content' =>
                        $heading_text .
                        '<br>' .
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-asc') . '">
                            <span class="' . $asc_class . '" title="' . VAT4EU_SORT_ASC . '">' .
                                TEXT_ASC .
                            '</span>
                         </a>&nbsp;' .
                        '<a href="' . zen_href_link(FILENAME_CUSTOMERS, $current_parms . 'list_order=vatnum-desc') . '">
                            <span class="' . $desc_class . '" title="'. VAT4EU_SORT_DESC . '">' .
                                TEXT_DESC .
                            '</span>
                         </a>',
                    'class' => 'center',
                ];
                $p2[] = $heading;
                break;
 
            // -----
            // Issued by Customers :: Customers during listing generation, allows us to insert
            // additional VAT-related columns for each customer.
            //
            // On entry:
            //
            // $p1 ... (r/o) An array containing the current customer's information
            // $p2 ... (r/w) An array value to contain an updated table-column for the display.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT':
                $default_address_id = $p1['customers_default_address_id'];
                $vat_number = '';
                foreach ($p1['addresses'] as $next_address) {
                    // -----
                    // Note: Using a 'loose' comparison since some fields are cast to int and
                    // some aren't.
                    //
                    if ($next_address['address']['address_book_id'] != $default_address_id) {
                        continue;
                    }

                    // -----
                    // zc200+ includes *all* fields from the address_book table in the
                    // address.  If that entry is present, use the value from the address
                    // supplied.
                    //
                    if (isset($next_address['address']['entry_vat_number'])) {
                        $vat_number = $next_address['address']['entry_vat_number'];
                        $vat_validation_status = $next_address['address']['entry_vat_validated'];
                        break;
                    }

                    // -----
                    // Otherwise, pull the VAT number and its validation status from the database.
                    //
                    [$vat_number, $vat_validation_status] = $this->getVatInfoFromDb((int)$default_address_id);
                    break;
                }

                $vat_validated = '';
                $vat_number = (string)$vat_number;
                if ($vat_number !== '') {
                    $vat_validated = $this->showVatNumberStatus($vat_validation_status);
                }

                $vat_column = [
                    'content' => $vat_validated . $vat_number,
                    'class' => 'center'
                ];
                $p2[] = $vat_column;
                break;

            // -----
            // Issued by "Edit Orders", v5.0.0+, when rendering the billing-address block.  We'll insert the fields
            // associated with the VAT Number's display/modification.
            //
            // On entry:
            //
            // $p1 ... (r/o) A copy of the address-related fields for the current order's billing address.
            // $p2 ... (r/w) A reference to an associated array containing additional fields to be added, in the following format:
            //
            // $extra_data = [
            //     [
            //         'label' => 'The text to include for the field label',
            //         'field_id' => 'label "for" attribute, must match id of input field'
            //         'input' => 'The form-related portion of the field',
            //     ],
            //     ...
            // ];
            //
            case 'NOTIFY_EO_ADDL_BILLING_ADDRESS_ROWS':
                $vat_number = $p1['billing_vat_number'];
                $vat_validated = (int)$p1['billing_vat_validated'];
                $p2 = array_merge(
                    $p2,
                    $this->getVatNumberFormFieldsForEo(
                        $vat_number,
                        $vat_validated,
                    )
                );
                break;

            // -----
            // Issued by "Edit Orders", v5.0.0+, when saving address-related information
            // for subsequent update to the database.
            //
            // On entry:
            //
            // $p1 ... (r/o) A copy of the posted variables; the 'address_type' indicates which address
            // $p2 ... (r/w) A reference to a numerically-indexed array into which any field errors can be set:
            //
            // $non_builtin_errors = [
            //     'field_id' => 'message',
            //     ...
            // ];
            //
            case 'NOTIFY_EO_ADDRESS_SAVE':
                if ($p1['address_type'] !== 'billing') {
                    return;
                }
                $vat_number = $p1['billing_vat_number'];
                $non_builtin_errors = [];
                if (!$this->isVatCountry((int)$p1['country'])) {
                    if ($vat_number !== '') {
                        $country_iso_code_2 = $this->getCountryIsoCode2((int)$p1['country']);
                        $non_builtin_errors['vat-number'] = sprintf(VAT4EU_ENTRY_VAT_NOT_SUPPORTED, $country_iso_code_2);
                    }
                } elseif ($vat_number !== $p1['current_vat_number']) {
                    $vat_validated = (isset($p1['billing_vat_validated'])) ? VatValidation::VAT_ADMIN_OVERRIDE : VatValidation::VAT_NOT_VALIDATED;
                }
                $p2 = $non_builtin_errors;
                break;

            // -----
            // Issued by EO 5.0.0+ when an address-related field has changes. Check
            // to see if either the VAT number or its validation status is different
            // than the customer's default. If so, issue a message to let the admin
            // know.
            //
            case 'NOTIFY_EO_UPDATING_ADDR_FIELD':
                if ($p1['field_prefix'] !== 'billing_') {
                    return;
                }

                global $db;
                $customers_vat = $db->Execute(
                    "SELECT ab.entry_vat_number, ab.entry_vat_validated
                       FROM " . TABLE_ORDERS . " o
                            INNER JOIN " . TABLE_CUSTOMERS . " c
                                ON o.customers_id = c.customers_id
                            INNER JOIN " . TABLE_ADDRESS_BOOK . " ab
                                ON ab.customers_id = c.customers_id
                               AND ab.address_book_id = c.customers_default_address_id
                      WHERE o.orders_id = " . (int)$this->oID . "
                      LIMIT 1"
                );
                if ($customers_vat->EOF) {
                    return;
                }

                $customers_vat = $customers_vat->fields;
                if ($p1['field_name'] === 'billing_vat_number' && $p1['updated_value'] !== $customers_vat['entry_vat_number']) {
                    global $messageStack;
                    $messageStack->add_session(VAT4EU_EO_CUSTOMER_UPDATE_REQUIRED, 'warning');
                    $this->detach($this, ['NOTIFY_EO_UPDATING_ADDR_FIELD']);
                    return;
                }

                if ($p1['field_name'] === 'billing_vat_validated' && $p1['updated_value'] != $customers_vat['entry_vat_validated']) {
                    global $messageStack;
                    $messageStack->add_session(VAT4EU_EO_CUSTOMER_UPDATE_REQUIRED, 'warning');
                    $this->detach($this, ['NOTIFY_EO_UPDATING_ADDR_FIELD']);
                    return;
                }
                break;

            // -----
            // Issued at the end of the zen_address_format function, just prior to return.  Gives us a chance to
            // insert the "VAT Number" value, if indicated.
            //
            // This notification includes the following variables:
            //
            // $p1 ... (r/o) An associative array containing the various elements of the to-be-formatted address
            // $p2 ... (r/w) A reference to the function's return value, possibly modified by this processing.
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
    public function isVatRefundable(): bool
    {
        return $this->checkVatIsRefundable();
    }

    // -----
    // This helper function retrieves the 2-character ISO code for the specified country.
    //
    public function getCountryIsoCode2(int $countries_id): string
    {
        global $db;

        $check = $db->Execute(
            "SELECT countries_iso_code_2
               FROM " . TABLE_COUNTRIES . "
              WHERE countries_id = " . $countries_id . "
              LIMIT 1"
        );
        return ($check->EOF) ? 'Unknown' : $check->fields['countries_iso_code_2'];
    }

    // -----
    // This function returns a Font-Awesome glyph that represents the specified
    // VAT-validation status.
    //
    protected function showVatNumberStatus($vat_validation): string
    {
        switch ($vat_validation) {
            case VatValidation::VAT_ADMIN_OVERRIDE:
                $glyph = 'fa fa-thumbs-up';
                $color = 'warning';
                $title = VAT4EU_ADMIN_OVERRIDE;
                break;
            case VatValidation::VAT_VIES_OK:
                $glyph = 'fa fa-thumbs-up';
                $color = 'success';
                $title = VAT4EU_VIES_OK;
                break;
            case VatValidation::VAT_VIES_NOT_OK:
                $glyph = 'fa fa-thumbs-down';
                $color = 'danger';
                $title = VAT4EU_VIES_NOT_OK;
                break;
            default:
                $glyph = 'fa fa-thumbs-down';
                $color = 'warning';
                $title = VAT4EU_NOT_VALIDATED;
                break;
        }
        return '<i class="' . $glyph . ' text-' . $color . '" aria-hidden="true" title="' . $title . '"></i> ';
    }

    // -----
    // This function, called during form validation where a VAT Number can be entered, e.g. Customers :: Customers, returning
    // a boolean value that indicates whether (true) or not (false) the VAT Number is 'valid'.
    //
    protected function validateVatNumber(bool $vat_number_override): bool
    {
        global $current_page;

        $vat_number_length = strlen($this->vatNumber);

        $vat_ok = false;
        $this->vatNumberError = true;
        $this->vatNumberMessage = '';
        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;

        if ($current_page === 'edit_orders.php') {
            $countries_id = $_POST['update_billing_country'];
        } else {
            $countries_id = $_POST['entry_country_id'];
        }
        $country_iso_code_2 = $this->getCountryIsoCode2((int)$countries_id);
        if (!in_array($country_iso_code_2, $this->vatCountries)) {
            $this->vatNumberMessage = sprintf(VAT4EU_ENTRY_VAT_NOT_SUPPORTED, $country_iso_code_2);
            return false;
        }

        $validation = new VatValidation($country_iso_code_2, $this->vatNumber);
        $precheck_status = $validation->vatNumberPreCheck();
        switch ($precheck_status) {
            case VatValidation::VAT_NOT_SUPPLIED:
                $vat_ok = true;
                break;

            case VatValidation::VAT_REQUIRED:
                $this->vatNumberMessage = VAT4EU_ENTRY_VAT_REQUIRED;
                break;

            case VatValidation::VAT_MIN_LENGTH:
                $this->vatNumberMessage = sprintf(VAT4EU_ENTRY_VAT_MIN_ERROR, (int)VAT4EU_MIN_LENGTH);;
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
                if ($vat_number_override === true) {
                    $this->vatNumberStatus = VatValidation::VAT_ADMIN_OVERRIDE;

                } elseif ($validation->validateVatNumber()) {
                    $this->vatNumberStatus = VatValidation::VAT_VIES_OK;

                } else {
                    $vat_ok = false;
                    $this->vatNumberStatus = VatValidation::VAT_VIES_NOT_OK;
                    $this->vatNumberMessage = VAT4EU_ENTRY_VAT_VIES_INVALID;
                }
                break;

            default:
                $this->vatNumberMessage = "Unexpected return value from vatNumberPreCheck: $precheck_status, VAT number not authorized.";
                trigger_error($this->vatNumberMessage, E_USER_WARNING);
                break;
        }

        $this->debug("validateVatNumber($vat_number_override), vat_ok ($vat_ok), vatNumber ({$this->vatNumber}), vatNumberStatus ({$this->vatNumberStatus})");

        return $vat_ok;
    }

    // -----
    // This function creates (and returns) the HTML associated with a "VAT Number".
    //
    protected function formatVatNumberDisplay($cInfo): array
    {
        if (isset($_POST['vat_number'])) {
            $vat_number = $cInfo->vat_number;
            $vat_override = isset($_POST['vat_number_override']);
        } else {
            [$vat_number, $vat_validated] = $this->getVatInfoFromDb((int)$cInfo->customers_default_address_id);
            $vat_override = (((int)$vat_validated) === VatValidation::VAT_ADMIN_OVERRIDE);
        }

        return $this->getVatNumberFormFields((string)$vat_number, $vat_override);
    }

    // -----
    // This function creates and returns the VAT4EU form fields when updating
    // a customer's record.
    //
    protected function getVatNumberFormFields(string $vat_number, bool $vat_override): array
    {
        return [
            [
                'label' => VAT4EU_ENTRY_VAT_NUMBER,
                'fieldname' => 'vat-number',
                'input' => zen_draw_input_field(
                    'vat_number',
                    zen_output_string_protected((string)$vat_number),
                    zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', 50) . ' class="form-control" id="vat-number"'
                ) . $hidden_fields . $this->vatNumberMessage,
            ],
            [
                'label' => VAT4EU_ENTRY_OVERRIDE_VALIDATION,
                'fieldname' => 'vat-override',
                'input' => zen_draw_checkbox_field(
                    'vat_number_override',
                    'on',
                    $vat_override,
                    '',
                    'id="vat-override"'
                ),
            ],
        ];
    }

    // -----
    // This function creates and returns the VAT4EU form fields when editing
    // an order's address via EO.
    //
    protected function getVatNumberFormFieldsForEo(string $vat_number, int $vat_validated): array
    {
        return [
            [
                'label' => VAT4EU_ENTRY_VAT_NUMBER,
                'field_id' => 'vat-number',
                'input' => zen_draw_input_field(
                    'billing_vat_number',
                    zen_output_string_protected((string)$vat_number),
                    zen_set_field_length(TABLE_ORDERS, 'billing_vat_number', 50) . ' class="form-control" id="vat-number"'
                ) . $this->vatNumberMessage,
            ],
            [
                'label' => VAT4EU_ENTRY_OVERRIDE_VALIDATION,
                'field_id' => 'vat-validation',
                'input' => zen_draw_checkbox_field(
                    'billing_vat_validated',
                    (string)$vat_validated,
                    ($vat_validated === VatValidation::VAT_ADMIN_OVERRIDE),
                    '',
                    'id="vat-validation"'
                ),
            ],
            [
                'label' => '',
                'input' => zen_draw_hidden_field('current_vat_number', $vat_number, 'id="current-vat-number"'),
            ],
            [
                'label' => '',
                'input' => zen_draw_hidden_field('current_vat_validated', $vat_validated, 'id="current-vat-validated"'),
            ],
        ];
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
    // on a common order-class query.  Virtual orders will have an empty string for the order's delivery country, while
    // shippable orders will contain an array of country-related information.
    //
    protected function checkVatIsRefundable(): bool
    {
        global $order;

        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;
        $this->vatIsRefundable = false;
        $this->vatNumber = '';
        if (isset($order)) {
            $billing_country_id = $this->getCountryIdFromOrder($order->billing['country']);
            $delivery_country_id = $this->getCountryIdFromOrder($order->delivery['country']);
            if ($billing_country_id !== false && $this->isVatCountry((int)$billing_country_id)) {
                if ($delivery_country_id !== false && $this->isVatCountry((int)$delivery_country_id)) {
                    $this->vatNumber = (string)$order->billing['billing_vat_number'];
                    $this->vatNumberStatus = (int)$order->billing['billing_vat_validated'];
                    if ($this->vatNumberStatus === VatValidation::VAT_VIES_OK || $this->vatNumberStatus === vatValidation::VAT_ADMIN_OVERRIDE) {
                        if (VAT4EU_IN_COUNTRY_REFUND === 'true' || STORE_COUNTRY != $delivery_country_id) {
                            $this->vatIsRefundable = true;
                        }
                    }
                }
            }
            $this->debug(
                "checkVatIsRefundable for order #" . $this->oID . ", is " . (($this->vatIsRefundable === true) ? '' : 'not ') . "refundable." .  PHP_EOL . 
                "--> Billing" . PHP_EOL . var_export($order->billing['country'], true) . PHP_EOL .
                "--> Shipping" . PHP_EOL . var_export($order->delivery['country'], true)
            );
        }
        return $this->vatIsRefundable;
    }

    // -----
    // Pull the countries_id value from the supplied country field, as formatted by the storefront orders' class.
    // If the value supplied doesn't have a country-id value, then the country field supplied is
    // most likely from the delivery section of a virtual (i.e. no shipping required) order.
    //
    // Returns boolean false if the country-id cannot be determined; the integer id value otherwise.
    //
    protected function getCountryIdFromOrder($country_info)
    {
        return ($country_info['id'] ?? false);
    }

    // ------
    // This function determines, based on the admin page currently active, whether a zen_address_format
    // function call should have the VAT Number appended and, if so, appends it!
    //
    protected function formatAddress(array $address_elements, string $current_address): bool|string
    {
        global $current_page;

        // -----
        // Determine whether the address being formatted "qualifies" for the insertion of the VAT Number.
        //
        $address_out = false;

        // -----
        // Determine whether the VAT Number should be appended to the specified address, based
        // on the page from which the zen_address_format request was made.
        //
        $show_vat_number = false;
        switch ($current_page) {
            // -----
            // These pages include multiple address-blocks' display; watch for an address that
            // contains a VAT number.
            //
            // NOTE: This observer's previous "hook" into the order-class' query function has populated the order-object's
            // VAT-related fields.
            //
            case 'orders.php':
            case 'invoice.php':
            case 'packingslip.php':
            case 'edit_orders.php':
                if (isset($address_elements['address']['billing_vat_number'])) {
                    $show_vat_number = true;
                    $vat_number = $address_elements['address']['billing_vat_number'];
                    $vat_status = $address_elements['address']['billing_vat_validated'] ?? VatValidation::VAT_NOT_VALIDATED;
                }
                break;

            // -----
            // These pages include multiple address-blocks' display; all blocks should include the associated VAT
            // Number and its status.
            //
            case 'customers.php':
                if (isset($_GET['action']) && $_GET['action'] === 'list_addresses') {
                    [$vat_number, $vat_status] = $this->getVatInfoFromDb((int)$address_elements['address']['address_book_id']);
                    if (!empty($vat_number)) {
                        $show_vat_number = true;
                    }
                }
                break;

            // -----
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
        if ($show_vat_number === true) {
            $vat_number = (string)$vat_number;
            $vat_status = (int)$vat_status;
            if ($vat_number !== '') {
                $address_out = $current_address . $address_elements['cr'] . VAT4EU_DISPLAY_VAT_NUMBER . $vat_number;
                if ($vat_status !== VatValidation::VAT_VIES_OK && $vat_status !== VatValidation::VAT_ADMIN_OVERRIDE) {
                    $address_out .= VAT4EU_UNVERIFIED;
                }
            }
        }
        return $address_out;
    }

    // -----
    // This method returns the VAT number and its validation status, based on a specific
    // address-book id.
    //
    protected function getVatInfoFromDb(int $address_book_id): array
    {
        global $db;

        $result = $db->Execute(
            "SELECT entry_vat_number, entry_vat_validated
               FROM " . TABLE_ADDRESS_BOOK . "
              WHERE address_book_id = $address_book_id
              LIMIT 1"
        );
        if (!$result->EOF) {
            return [
                $result->fields['entry_vat_number'],
                $result->fields['entry_vat_validated'],
            ];
        }

        return [
            null,
            '0',
        ];
    }

    // -----
    // This function returns a boolean indicator, identifying whether (true) or not (false) the
    // country associated with the "countries_id" input qualifies for this plugin's processing.
    //
    protected function isVatCountry(int $countries_id): bool
    {
        return in_array($this->getCountryIsoCode2($countries_id), $this->vatCountries);
    }

    private function debug($message): void
    {
        if ($this->debug === true) {
            error_log(date('Y-m-d H:i:s') . ": $message\n", 3, $this->logfile);
        }
    }
}
