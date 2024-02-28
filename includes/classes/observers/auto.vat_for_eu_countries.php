<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v3.2.0
//
class zcObserverVatForEuCountries extends base 
{
    private $isEnabled = false;
    private $vatIsRefundable = false;
    private $vatNumber = '';
    private $vatNumberStatus;
    private $addressBookVats;
    private $vatGathered = false;
    private $addressFormatCount = 0;
    private $vatCountries = [];
    private $debug = false;
    private $logfile;

    // -----
    // On construction, this auto-loaded observer checks to see that the plugin is enabled and, if so:
    //
    // - Register for the notifications pertinent to the plugin's processing.
    // - Create an instance of the "VAT Validation" class, for possible future use.
    // - Set initial values base on the plugin's database configuration.
    //
    public function __construct()
    {
        global $messageStack;

        // -----
        // Pull in the VatValidation class, enabling its constants to be used even if the plugin
        // isn't enabled.
        //
        if (!class_exists('VatValidation')) {
            require DIR_WS_CLASSES . 'VatValidation.php';
        }

        // -----
        // If the plugin is enabled ...
        //
        if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true') {
            $this->isEnabled = true;
            $this->debug = (defined('VAT4EU_DEBUG') && VAT4EU_DEBUG == 'true');
            if (zen_is_logged_in() && !zen_in_guest_checkout()) {
                $this->logfile = DIR_FS_LOGS . '/vat4eu_' . $_SESSION['customer_id'] . '.log';
            } else {
                $this->logfile = DIR_FS_LOGS . '/vat4eu.log';
            }

            // -----
            // Attach to the various notifications associated with this plugin's processing.  Events that have been added
            // for VAT4EU's processing are noted with a (*) within their description-comment.  Create-account notifications
            // are always attached.
            //
            $this->attach(
                $this, 
                [
                    //- From /includes/modules/create_account.php
                    'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK',                   //- Allows us to check/validate any supplied VAT Number
                    'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_ADDRESS_BOOK_RECORD',   //- Indicates that the account was created successfully

                    //- From /includes/modules/pages/shopping_cart/header_php.php
                    'NOTIFY_HEADER_END_SHOPPING_CART',          //- End of the "standard" page's processing
                ]
            );

            // -----
            // Some of the VAT4EU processing is available **only** for logged-in, non-guest customers.
            //
            if (zen_is_logged_in() && !zen_in_guest_checkout()) {
                $this->attach(
                    $this,
                    [
                        //- From /includes/classes/order.php
                        'NOTIFY_ORDER_AFTER_QUERY',                         //- Reconstructing a previously-placed order
                        'NOTIFY_ORDER_DURING_CREATE_ADDED_ORDER_HEADER',    //- Creating an order, after the main orders-table entry has been created

                        //- From /includes/modules/checkout_new_address.php
                        'NOTIFY_MODULE_CHECKOUT_NEW_ADDRESS_VALIDATION',        //- Allows us to check/validate any supplied VAT Number (*)
                        'NOTIFY_MODULE_CHECKOUT_ADDED_ADDRESS_BOOK_RECORD',     //- Indicates that the record was created successfully

                        //- From /includes/modules/checkout_address_book.php
                        'NOTIFY_MODULE_END_CHECKOUT_ADDRESS_BOOK',              //- Allows us to note any VAT Numbers associated with the customer's address book (*)

                        //- From /includes/modules/pagea/address_book/header_php.php
                        'NOTIFY_HEADER_END_ADDRESS_BOOK',                       //- Allows us to note any VAT Numbers associated with the customer's address book

                        //- From /includes/modules/pages/address_book_process/header_php.php
                        'NOTIFY_ADDRESS_BOOK_PROCESS_VALIDATION',                   //- Allows us to check/validate any supplied VAT Number (*)
                        'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD',   //- Indicates that an address-record was just updated
                        'NOTIFY_MODULE_ADDRESS_BOOK_ADDED_ADDRESS_BOOK_RECORD',     //- Indicates that an address-record was just created
                        'NOTIFY_HEADER_END_ADDRESS_BOOK_PROCESS',                   //- Allows us to gather any existing VAT number for display

                        //- From /includes/functions/functions_customers.php
                        'NOTIFY_END_ZEN_ADDRESS_FORMAT',            //- Issued at the end of the zen_address_format function (*)
                        'NOTIFY_ZEN_ADDRESS_LABEL',                 //- Issued during the zen_address_label function (*)
                    ]
                );
            }

            $this->vatCountries = explode(',', str_replace(' ', '', VAT4EU_EU_COUNTRIES));

            if (zen_is_logged_in() && !zen_in_guest_checkout()) {
                $address_id = (isset($_SESSION['billto'])) ? $_SESSION['billto'] : $_SESSION['customer_default_address_id'];
                $this->getVatNumber($_SESSION['customer_id'], $address_id);
                if (!empty($this->vatNumber) && $this->vatNumberStatus !== VatValidation::VAT_ADMIN_OVERRIDE && $this->vatNumberStatus !== VatValidation::VAT_VIES_OK) {
                    $messageStack->add('header', sprintf(VAT4EU_APPROVAL_PENDING, $this->vatNumber), 'warning');
                }
            }
        }
    }

    // -----
    // This function receives control when one of its attached notifications is "fired".
    //
    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5)
    {
        global $db;

        switch ($eventID) {
            // -----
            // Issued by the order-class after completing its base reconstruction of a 
            // previously-placed order.  We'll tack any "VAT Number" recorded in the order
            // into the class' to-be-returned array.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //  $p1 ...... An empty array
            //  $p2 ...... The orders_id being queried.
            //
            case 'NOTIFY_ORDER_AFTER_QUERY':
                $vat_info = $db->Execute(
                    "SELECT billing_vat_number, billing_vat_validated
                       FROM " . TABLE_ORDERS . "
                      WHERE orders_id = " . (int)$p2 . "
                      LIMIT 1"
                );
                if (!$vat_info->EOF) {
                    $class->billing['billing_vat_number'] = $vat_info->fields['billing_vat_number'];
                    $class->billing['billing_vat_validated'] = $vat_info->fields['billing_vat_validated'];
                }
                break;

            // -----
            // Issued by the order-class after completing its base construction of an order's
            // information.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //  $p1 ...... An associative array containing the information written to the orders table.
            //  $p2 ...... The order's id.
            //
            case 'NOTIFY_ORDER_DURING_CREATE_ADDED_ORDER_HEADER':
                $this->checkVatIsRefundable();
                if (!empty($this->vatNumber)) {
                    $db->Execute(
                        "UPDATE " . TABLE_ORDERS . "
                            SET billing_vat_number = '" . zen_db_prepare_input($this->vatNumber) . "',
                                billing_vat_validated = " . $this->vatNumberStatus . "
                          WHERE orders_id = " . (int)$p2 . "
                          LIMIT 1"
                    );
                }
                break;

            // -----
            // Issued during the create-account, address-book or checkout-new-address processing, gives us a chance to validate (if
            // required) the customer's entered VAT Number.
            //
            // Entry:
            //  $p2 ... A reference to the module's $error variable.
            //
            case 'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK':
                $message_location = 'create_account';               //- Fall through ...
            case 'NOTIFY_MODULE_CHECKOUT_NEW_ADDRESS_VALIDATION':
                if (!isset($message_location)) {
                    $message_location = 'checkout_address';
                }                                                   //- Fall through ...
            case 'NOTIFY_ADDRESS_BOOK_PROCESS_VALIDATION':
                if (!isset($message_location)) {
                    $message_location = 'addressbook';
                }
                if (!$this->validateVatNumber($message_location)) {
                    $p2 = true;
                }
                break;

            // -----
            // Issued during the create-account, address-book or checkout-new-address processing, indicates that an address record
            // has been created/updated and gives us a chance to record the customer's VAT Number.
            //
            // Entry:
            //  $p1 ... An associative array that contains the address-book entry's default data.
            //
            case 'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_ADDRESS_BOOK_RECORD':  //- Fall through ...
            case 'NOTIFY_MODULE_CHECKOUT_ADDED_ADDRESS_BOOK_RECORD':        //- Fall through ...
            case 'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD':  //- Fall through ...
            case 'NOTIFY_MODULE_ADDRESS_BOOK_ADDED_ADDRESS_BOOK_RECORD':
                $address_book_id = ($eventID === 'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD') ? $p1['address_book_id'] : $p1['address_id'];
                $vat_number = zen_db_prepare_input($_POST['vat_number']);
                $db->Execute(
                    "UPDATE " . TABLE_ADDRESS_BOOK . "
                        SET entry_vat_number = '$vat_number',
                            entry_vat_validated = " . $this->vatNumberStatus . "
                      WHERE address_book_id = $address_book_id
                        AND customers_id = " . (int)$_SESSION['customer_id'] . "
                      LIMIT 1"
                );
                break;

            // -----
            // Issued by the "address_book_process" page's header, preparing to display (or re-display
            // on error) the address-book entry form.  Gives us the opportunity to gather any pre-existing
            // VAT number for the display.
            //
            case 'NOTIFY_HEADER_END_ADDRESS_BOOK_PROCESS':
                global $vat_number;
                if (!isset($_POST['vat_number']) && isset($_GET['edit'])) {
                    $check = $db->Execute(
                        "SELECT entry_vat_number, entry_vat_validated
                           FROM " . TABLE_ADDRESS_BOOK . "
                          WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                            AND address_book_id = " . (int)$_GET['edit'] . "
                          LIMIT 1"
                    );
                    if (!$check->EOF) {
                        $vat_number = $check->fields['entry_vat_number'];
                    }
                }
                break;

            // -----
            // Issued at the end of the 'address_book' page's processing.  We'll gather up the VAT Numbers
            // associated with each of those addresses for display via future call to zen_address_format
            // in the template stage of the page.
            //
            case 'NOTIFY_HEADER_END_ADDRESS_BOOK':
                $this->gatherAccountAddressBookVatNumbers();
                break;

            // -----
            // Issued at the end of the 'checkout_address_book' module's processing.  We'll gather up the VAT Numbers
            // associated with each of those existing addresses for display via future call to zen_address_format
            // in the template stage of the rendering.
            //
            // On entry:
            //
            // $p1 ... (r/o) A copy of the SQL query to gather the customer's address-book entries.
            //
            case 'NOTIFY_MODULE_END_CHECKOUT_ADDRESS_BOOK':
                $this->gatherCheckoutAddressBookVatNumbers($p1);
                break;

            // -----
            // Issued by the "shopping_cart" page's header when it's completed its processing.  Allows us to
            // determine whether a currently-logged-in customer qualifies for a VAT refund.
            //
            case 'NOTIFY_HEADER_END_SHOPPING_CART':
                global $products, $cartShowTotal, $currencies;
                if ($this->checkVatIsRefundable() && isset($products) && is_array($products)) {
                    $debug_message = $eventID . " starts ...";
                    $products_tax = 0;
                    $currency_decimal_places = $currencies->get_decimal_places($_SESSION['currency']);
                    foreach ($products as $current_product) {
                        $current_tax = zen_calculate_tax($current_product['final_price'], zen_get_tax_rate($current_product['tax_class_id']));
                        $products_tax += $current_product['quantity'] * zen_round($current_tax, $currency_decimal_places);
                        $debug_message .= ("\t" . $current_product['name'] . '(' . $current_product['id'] . ") adds $current_tax to the overall tax ($products_tax)." . PHP_EOL);
                    }
                    $this->vatRefund = $products_tax;
                    if ($products_tax != 0) {
                        $cartShowTotal .= '<br><span class="vat-refund-label">' . VAT4EU_TEXT_VAT_REFUND . '</span> <span class="vat-refund_amt">' . $currencies->format($products_tax) . '</span>';
                    }
                    $this->debug($debug_message);
                }
                break;

            // -----
            // Issued at the end of the zen_address_format function, just prior to return.  Gives us a chance to
            // insert the "VAT Number" value, if indicated.
            //
            // On completion of the address-formatting, reset the flag that indicates that the VAT information
            // has been "gathered" to allow mixed zen_address_format/zen_address_label calls to operate properly.
            //
            // This notification includes the following variables:
            //
            // $p1 ... (r/o) An associative array containing the various elements of the to-be-formatted address
            // $p2 ... (r/w) A reference to the functions return value, possibly modified by this processing.
            //
            case 'NOTIFY_END_ZEN_ADDRESS_FORMAT':
                $this->checkVatIsRefundable();
                $updated_address = $this->formatAddress($p1, $p2);
                if ($updated_address !== false) {
                    $p2 = $updated_address;
                }
                $this->debug($eventID . ': ' . var_export($this, true));
                $this->vatGathered = false;
                break;

            // -----
            // Issued by zen_address_label after gathering the address fields for a specified customer's address.  Gives this plugin
            // the opportunity to capture any "VAT Number" associated with the associated address ... for use by
            // the function's subsequent call to zen_address_format.
            //
            // Upon completion, the 'vatGathered' flag is set to let the next access to the zen_address_format processing
            // "know" that the VAT information has already been set for the next access.
            //
            // This notification includes the following variables:
            //
            // $p1 ... (n/a)
            // $p2 ... The customers_id value
            // $p3 ... The address_book_id value
            //
            case 'NOTIFY_ZEN_ADDRESS_LABEL':
                $this->checkVatIsRefundable($p2, $p3);
                $this->vatGathered = true;
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
        global $db;
        $check = $db->Execute(
            "SELECT countries_iso_code_2
               FROM " . TABLE_COUNTRIES . "
              WHERE countries_id = " . (int)$countries_id . "
              LIMIT 1"
        );
        return ($check->EOF) ? 'Unknown' : $check->fields['countries_iso_code_2'];
    }

    // -----
    // This function, called for pages that make modifications to an account's
    // VAT Number, provides basic validation for that number.
    //
    protected function validateVatNumber($message_location)
    {
        global $vat_number, $messageStack;

        $vat_ok = false;
        $vat_number = strtoupper(zen_db_prepare_input($_POST['vat_number']));

        $countries_id = $_POST['zone_country_id'];
        $country_iso_code_2 = $this->getCountryIsoCode2($countries_id);

        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;

        $validation = new VatValidation($country_iso_code_2, $vat_number);
        $precheck_status = $validation->vatNumberPreCheck();
        switch ($precheck_status) {
            case VatValidation::VAT_NOT_SUPPLIED:
                $vat_ok = true;
                break;

            case VatValidation::VAT_REQUIRED:
                $messageStack->add($message_location, VAT4EU_ENTRY_REQUIRED_ERROR, 'error');
                break;

            case VatValidation::VAT_MIN_LENGTH:
                $messageStack->add($message_location, VAT4EU_ENTRY_VAT_MIN_ERROR, 'error');
                break;

            case VatValidation::VAT_BAD_PREFIX:
                $messageStack->add($message_location, sprintf(VAT4EU_ENTRY_VAT_PREFIX_INVALID, $country_iso_code_2, zen_get_country_name($countries_id)), 'error');
                break;

            case VatValidation::VAT_INVALID_CHARS:
                $messageStack->add_session($message_location, VAT4EU_VAT_NOT_VALIDATED, 'warning');
                break;

            case VatValidation::VAT_OK:
                $vat_ok = true;
                if ($message_location === 'create_account') {
                    $message_location = 'header';
                }
                if (VAT4EU_VALIDATION === 'Admin') {
                    $messageStack->add_session($message_location, sprintf(VAT4EU_APPROVAL_PENDING, $vat_number), 'warning');
                } else {
                    if ($validation->validateVatNumber()) {
                        $this->vatNumberStatus = VatValidation::VAT_VIES_OK;
                    } else {
                        $this->vatNumberStatus = VatValidation::VAT_VIES_NOT_OK;
                        $vat_ok = false;
                        $messageStack->add_session($message_location, VAT4EU_VAT_NOT_VALIDATED, 'warning');
                    }
                }
                break;

            default:
                trigger_error("Unexpected return value from vatNumberPreCheck: $precheck_status, VAT number not authorized.", E_USER_WARNING);
                break;
        }
        return $vat_ok;
    }

    protected function getVatNumber($customers_id, $address_id)
    {
        global $db;

        $debug_message = "getVatNumber($customers_id, $address_id)" . PHP_EOL;
        $this->vatNumber = '';
        $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;
        $check = $db->Execute(
            "SELECT entry_country_id, entry_vat_number, entry_vat_validated
               FROM " . TABLE_ADDRESS_BOOK . "
              WHERE address_book_id = " . (int)$address_id . "
                AND customers_id = " . (int)$customers_id . "
              LIMIT 1"
        );
        if (!$check->EOF) {
            $debug_message .= "\tAddress located, country #" . $check->fields['entry_country_id'] . PHP_EOL;
            if ($this->isVatCountry($check->fields['entry_country_id'])) {
                $this->vatNumber = $check->fields['entry_vat_number'];
                $this->vatNumberStatus = (int)$check->fields['entry_vat_validated'];
                $debug_message .= "\tBilling country is part of the EU, VAT Number (" . $this->vatNumber . "), validation status: " . $this->vatNumberStatus . PHP_EOL;
            }
        }

        $this->debug($debug_message . "\tReturning (" . $this->vatNumberStatus . ")" . PHP_EOL);
        return $this->vatNumberStatus;
    }

    protected function checkVatIsRefundable($customers_id = false, $address_id = false)
    {
        global $db;

        if (!$this->vatGathered) {
            $this->vatNumberStatus = VatValidation::VAT_NOT_VALIDATED;
            $this->vatIsRefundable = false;
            $this->vatNumber = '';
            $debug_message = "checkVatIsRefundable($customers_id, $address_id)" . PHP_EOL;
            if (zen_is_logged_in() && !zen_in_guest_checkout()) {
                if ($customers_id === false) {
                    $customers_id = $_SESSION['customer_id'];
                }
                if ($address_id === false) {
                    $address_id = (isset($_SESSION['billto'])) ? $_SESSION['billto'] : $_SESSION['customer_default_address_id'];
                }
                $debug_message .= "\tCustomer is logged in ($customers_id, $address_id)" . PHP_EOL;

                if ($this->getVatNumber($customers_id, $address_id) !== false) {
                    $sendto_address_id = (isset($_SESSION['sendto'])) ? $_SESSION['sendto'] : $_SESSION['customer_default_address_id'];
                    if ($sendto_address_id !== false) {
                        $debug_message .= "\tSend-to address set ..." . PHP_EOL;
                        $ship_check = $db->Execute(
                            "SELECT entry_country_id
                               FROM " . TABLE_ADDRESS_BOOK . "
                              WHERE address_book_id = " . (int)$sendto_address_id . "
                                AND customers_id = " . (int)$customers_id . "
                              LIMIT 1"
                        );
                        if (!$ship_check->EOF && $this->isVatCountry($ship_check->fields['entry_country_id'])) {
                            $debug_message .= "\tShip-to country is in the EU (" . $ship_check->fields['entry_country_id'] . ")" . PHP_EOL;
                            if ($this->vatNumberStatus === VatValidation::VAT_VIES_OK || $this->vatNumberStatus === VatValidation::VAT_ADMIN_OVERRIDE) {
                                if (VAT4EU_IN_COUNTRY_REFUND == 'true' || STORE_COUNTRY != $ship_check->fields['entry_country_id']) {
                                    $this->vatIsRefundable = true;
                                }
                            }
                        }
                    }
                }
            }
            $this->debug($debug_message . "\tReturning (" . $this->vatIsRefundable . ")" . PHP_EOL);
        }
        return $this->vatIsRefundable;
    }

    // ------
    // This function determines, based on the currently-active page, whether a zen_address_format
    // function call should have the VAT Number appended and, if so, appends it!
    //
    protected function formatAddress($address_elements, $current_address)
    {
        global $current_page_base;

        // -----
        // Determine whether the address being formatted "qualifies" for the insertion of the VAT Number.
        //
        $address_out = false;
        $use_vat_from_address_book = false;
        $show_vat_number = false;

        if (!empty($this->vatNumber) || $current_page_base === FILENAME_ADDRESS_BOOK || $current_page_base === FILENAME_CHECKOUT_PAYMENT_ADDRESS) {
            // -----
            // Determine whether the VAT Number should be appended to the specified address, based
            // on the page from which the zen_address_format request was made.
            //
            switch ($current_page_base) {
                // -----
                // These pages include possible multiple address references, pre-processed via (presumed) prior
                // invocation of the gatherAddressBookVatNumbers function to map the address-specific VAT numbers
                // to their associated address instance.
                //
                case FILENAME_ADDRESS_BOOK:
                case FILENAME_CHECKOUT_PAYMENT_ADDRESS:
                    $show_vat_number = true;
                    $this->addressFormatCount++;
                    if ($this->addressFormatCount > 1) {
                        $use_vat_from_address_book = true;
                    }
                    break;

                // -----
                // These pages include a single address reference, so the VAT Number is always displayed.  For these
                // pages, the current customer/checkout session values provide the values associated with the vatNumber
                // and its validation status.
                //
                case FILENAME_ADDRESS_BOOK_PROCESS:
                case FILENAME_CHECKOUT_PAYMENT:
                case FILENAME_CREATE_ACCOUNT_SUCCESS:
                    $show_vat_number = true;
                    break;

                // -----
                // These pages include multiple address-blocks' display; the second call to zen_address_format references
                // the billing address, so the VAT Number is displayed.
                //
                // NOTE: This observer's previous "hook" into the order-class' query function has populated the order-object's
                // VAT-related fields.
                //
                case FILENAME_ACCOUNT_HISTORY_INFO:
                case FILENAME_CHECKOUT_SUCCESS:
                    global $order;
                    $this->addressFormatCount++;
                    if ($this->addressFormatCount == 2) {
                        $show_vat_number = true;
                        $this->vatNumber = $order->billing['billing_vat_number'];
                        $this->vatNumberStatus = $order->billing['billing_vat_validated'];
                    }
                    break;

                // -----
                // These pages include multiple address-blocks' display; the first call to zen_address_format references
                // the billing address, so the VAT Number is displayed.
                //
                case FILENAME_CHECKOUT_CONFIRMATION:
                    $this->addressFormatCount++;
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
                    global $order;

                    // -----
                    // During the checkout-process page, the orders' class is generating billing and shipping
                    // address blocks (for both HTML and TEXT emails).  If the order is "virtual", only the billing 
                    // addresses are generated; otherwise, the billing addresses are associated with the third
                    // and fourth function calls.
                    //
                    if ($current_page_base === FILENAME_CHECKOUT_PROCESS) {
                        $this->addressFormatCount++;
                        if ($order->content_type !== 'virtual') {
                            if ($this->addressFormatCount === 3 || $this->addressFormatCount === 4) {
                                $show_vat_number = true;
                            }
                        } else {
                            $show_vat_number = true;
                        }
                    // -----
                    // If the "One-Page Checkout" plugin is installed, the billing address-block is the first
                    // one requested on both the main, data-gathering, and confirmation pages.
                    //
                    } elseif (defined('FILENAME_CHECKOUT_ONE') && ($current_page_base === FILENAME_CHECKOUT_ONE || $current_page_base === FILENAME_CHECKOUT_ONE_CONFIRMATION)) {
                        $this->addressFormatCount++;
                        if ($this->addressFormatCount === 1) {
                            $show_vat_number = true;
                        }
                    // -----
                    // Other pages, fire a notification to allow additional display of the VAT Number within
                    // a formatted address.
                    //
                    } else {
                        $show_vat_number = false;
                        $address_elements['address_format_count'] = $this->addressFormatCount;
                        $this->notify('NOTIFY_VAT4EU_ADDRESS_DEFAULT', $address_elements, $current_address, $show_vat_number);
                    }
                    break;
            }
            // -----
            // If the VAT Number is to be displayed as part of the address-block, append its value to the
            // end of the address, including the "unverified" tag if the number has not been validated.
            //
            if ($show_vat_number) {
                if ($use_vat_from_address_book) {
                    $vat_index = $this->addressFormatCount - 2;
                    $vat_number = $this->addressBookVats[$vat_index]['vat_number'];
                    $vat_validated = $this->addressBookVats[$vat_index]['vat_validated'];
                } else {
                    $vat_number = $this->vatNumber;
                    $vat_validated = $this->vatNumberStatus;
                }
                if (!empty($vat_number)) {
                    $address_out = $current_address . $address_elements['cr'] . VAT4EU_DISPLAY_VAT_NUMBER . $vat_number;
                    if ($vat_validated !== VatValidation::VAT_VIES_OK && $vat_validated !== VatValidation::VAT_ADMIN_OVERRIDE) {
                        $address_out .= VAT4EU_UNVERIFIED;
                    }
                }
            }
        }
        return $address_out;
    }

    // -----
    // Gather, and insert into the address-book array, the VAT numbers associated with each of the
    // customer's defined addresses.
    //
    protected function gatherAccountAddressBookVatNumbers()
    {
        global $addresses_query, $db;

        $sql_query = $addresses_query;
        $sql_query = $db->bindVars($sql_query, ':customersID', $_SESSION['customer_id'], 'integer');
        $this->gatherAddressBookVatNumbers($sql_query);
    }

    // -----
    // Gather, and insert into the address-book array, the VAT numbers associated with each of the
    // customer's defined addresses, for use during the checkout_payment_address or checkout_shipping_address
    // pages' processing.
    //
    protected function gatherCheckoutAddressBookVatNumbers($sql_query)
    {
        $this->gatherAddressBookVatNumbers($sql_query);
    }

    protected function gatherAddressBookVatNumbers($sql_query)
    {
        global $db;

        $debug_message = "gatherAddressBookVatNumbers($sql_query)." . PHP_EOL;
        
        // -----
        // Modify the base SQL query for the addresses to include the VAT number and its associated
        // validation status for each address.
        //
        // - Remove all carriage-return, new-line and tab characters, compress all consecutive spaces to a single space.
        // - Insert the VAT Number and VAT validation status fields into the query
        //
        $sql_query = strtolower(preg_replace("/\s+/", " ", $sql_query));
        $sql_query = str_replace(' from', ', entry_vat_number, entry_vat_validated from', $sql_query);
        $debug_message .= "\tModified SQL query: $sql_query" . PHP_EOL;

        $addresses = $db->Execute($sql_query);
        $this->addressBookVats = [];
        foreach ($addresses as $next_address) {
            $this->addressBookVats[] = ['vat_number' => $next_address['entry_vat_number'], 'vat_validated' => $next_address['entry_vat_validated']];
        }
        $this->debug($debug_message . var_export($this->addressBookVats, true));
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
