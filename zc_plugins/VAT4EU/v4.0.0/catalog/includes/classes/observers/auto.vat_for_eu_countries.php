<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2025 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
use Zencart\Plugins\Catalog\VAT4EU\VatValidation;
use Zencart\Traits\InteractsWithPlugins;
use Zencart\Traits\linkCatalogStylesheet;

class zcObserverVatForEuCountries extends \base
{
    use InteractsWithPlugins;

    private $newCustomerId;
    private $vatNumberStatus;
    private $addressLabelCount = 0;
    private $orderHasShippingAddress;

    private array $vatCountries = [];

    private bool $nonGuestIsLoggedIn;
    private bool $isBootstrapTemplate;

    private bool $debug = false;
    private string $logfile;

    // -----
    // On construction, this auto-loaded observer checks to see that the plugin is enabled and, if so:
    //
    // - Register for the notifications pertinent to the plugin's processing.
    // - Load the "VAT Validation" class, for possible future use.
    // - Set initial values base on the plugin's database configuration.
    //
    public function __construct()
    {
        global $messageStack;

        // -----
        // If the plugin is not enabled ... nothing further to be done!
        //
        if (VAT4EU_ENABLED !== 'true') {
            return;
        }

        $this->detectZcPluginDetails(__DIR__);

        $this->nonGuestIsLoggedIn = (zen_is_logged_in() && !zen_in_guest_checkout());
        $this->isBootstrapTemplate = (function_exists('zca_bootstrap_active') && zca_bootstrap_active() === true);

        $this->debug = (VAT4EU_DEBUG === 'true');
        if ($this->nonGuestIsLoggedIn === true) {
            $this->logfile = DIR_FS_LOGS . '/vat4eu_' . $_SESSION['customer_id'] . '_' . date('Ymd') . '.log';
        } else {
            $this->logfile = DIR_FS_LOGS . '/vat4eu' . '_' . date('Ymd') . '.log';
        }

        $this->vatCountries = explode(',', str_replace(' ', '', VAT4EU_EU_COUNTRIES));

        // -----
        // Attach to the various notifications associated with this plugin's processing.  Create-account notifications
        // are always attached.
        //
        $this->attach(
            $this,
            [
                //- From /includes/modules/create_account.php
                'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK',                   //- Allows us to check/validate any supplied VAT Number

                //- From /includes/modules/create_account_success.php
                'NOTIFY_HEADER_END_CREATE_ACCOUNT_SUCCESS',                 //- Allows the inclusion of the VAT Number in the address

                //- From /includes/classes/Customer.php
                'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_CUSTOMER_RECORD',       //- Provides the customer_id to associate with the address-book record
                'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_ADDRESS_BOOK_RECORD',   //- Provides the address_book_id for the customer's primary address

                //- From /includes/modules/pages/shopping_cart/header_php.php
                'NOTIFY_HEADER_END_SHOPPING_CART',          //- End of the "standard" page's processing
            ]
        );

        // -----
        // If the current page provides a means for a customer to enter/change
        // an address, watch for the end-of-content notification so that the
        // VAT number entry-field can be added via the plugin's jQuery component.
        //
        $address_form_pages = [
            FILENAME_CREATE_ACCOUNT,
            FILENAME_LOGIN,
        ];
        if ($this->nonGuestIsLoggedIn === true) {
            $address_form_pages = array_merge($address_form_pages, [
                FILENAME_ADDRESS_BOOK_PROCESS,
                FILENAME_CHECKOUT_PAYMENT_ADDRESS,
                FILENAME_CHECKOUT_SHIPPING_ADDRESS,
            ]);

            if (defined('FILENAME_CHECKOUT_ONE')) {
                $address_form_pages[] = FILENAME_CHECKOUT_ONE;
            }
        }

        global $current_page_base;
        if (in_array($current_page_base, $address_form_pages)) {
            $this->attach($this, ['NOTIFY_FOOTER_END']);
            if ($this->isBootstrapTemplate === false) {
                $this->attach($this, ['NOTIFY_HTML_HEAD_CSS_BEGIN']);
            }
        }

        // -----
        // The majority of the VAT4EU processing is available **only** for logged-in, non-guest customers.
        //
        if ($this->nonGuestIsLoggedIn === true) {
            $this->attach(
                $this,
                [
                    //- From /includes/classes/order.php
                    'NOTIFY_ORDER_AFTER_QUERY',                                 //- Reconstructing a previously-placed order
                    'NOTIFY_ORDER_CART_FINISHED',                               //- Finished with the cart->order conversion, addresses available
                    'NOTIFY_ORDER_DURING_CREATE_ADDED_ORDER_HEADER',            //- Creating an order, after the main orders-table entry has been created

                    //- From /includes/modules/checkout_new_address.php
                    'NOTIFY_MODULE_CHECKOUT_NEW_ADDRESS_VALIDATION',            //- Allows us to check/validate any supplied VAT Number
                    'NOTIFY_MODULE_CHECKOUT_ADDED_ADDRESS_BOOK_RECORD',         //- Indicates that the record was created successfully

                    //- From /includes/modules/pages/address_book_process/header_php.php
                    'NOTIFY_ADDRESS_BOOK_PROCESS_VALIDATION',                   //- Allows us to check/validate any supplied VAT Number
                    'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD',   //- Indicates that an address-record was just updated
                    'NOTIFY_MODULE_ADDRESS_BOOK_ADDED_ADDRESS_BOOK_RECORD',     //- Indicates that an address-record was just created

                    //- From /includes/functions/functions_addresses.php
                    'NOTIFY_END_ZEN_ADDRESS_FORMAT',                            //- Issued at the end of the zen_address_format function
                    'NOTIFY_ZEN_ADDRESS_LABEL',                                 //- Issued during the zen_address_label function
                ]
            );

            // -----
            // If the customer's currently-selected bill-to address or their default contains
            // an unvalidated VAT Number, let the customer know.
            //
            $message_pages = [
                FILENAME_CREATE_ACCOUNT_SUCCESS,
                FILENAME_ADDRESS_BOOK,
                FILENAME_CHECKOUT_CONFIRMATION,
                FILENAME_CHECKOUT_PAYMENT,
            ];
            if (defined('FILENAME_CHECKOUT_ONE')) {
                $message_pages[] = FILENAME_CHECKOUT_ONE;
                $message_pages[] = FILENAME_CHECKOUT_ONE_CONFIRMATION;
            }
            if (!in_array($current_page_base, $message_pages)) {
                return;
            }

            $address_id = $_SESSION['billto'] ?? $_SESSION['customer_default_address_id'];
            [$vat_number, $vat_number_status] = $this->getCustomersVatNumber($_SESSION['customer_id'], $address_id);
            if ($vat_number !== '' && $vat_number_status !== VatValidation::VAT_ADMIN_OVERRIDE && $vat_number_status !== VatValidation::VAT_VIES_OK) {
                $messageStack->add('header', sprintf(VAT4EU_APPROVAL_PENDING, $vat_number), 'warning');
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
            // Issued by the order-class after completing its base conversion of the session's
            // addresses and order's products.  Based on the current session's billto address
            // the VAT Number information for that address is added for follow-on page use.
            //
            // Entry:
            //  $class ... A reference to an order-class object.
            //
            case 'NOTIFY_ORDER_CART_FINISHED':
                // -----
                // If the billto-address isn't yet set, nothing further to be done.
                //
                if (empty($_SESSION['billto'])) {
                    return;
                }
                
                // -----
                // Note whether/not the order has a shipping address: it doesn't if the
                // order's all virtual products or is a 'storepickup'.  This is needed
                // for the handling of the addresses populated in order emails via order::send_order_email.
                // That method, for whatever reason, chose to use zen_address_label instead
                // of zen_address_format with the order's recorded billing/shipping addresses to
                // format the addresses sent in the order's confirmation email.
                //
                // The flag is used by the zen_address_label handling, below, to determine which
                // (of the 4 issued) calls to the function on the checkout_process page should
                // actually include the associated VAT Number.
                //
                $this->orderHasShippingAddress = ($class->content_type !== 'virtual' && strpos($class->info['shipping_module_code'], 'storepickup') === false);

                // -----
                // If running under OPC with a temporary address for the billing, the VAT
                // Number "doesn't count", so a quick return.
                //
                if (defined('CHECKOUT_ONE_GUEST_BILLTO_ADDRESS_BOOK_ID') && ((int)CHECKOUT_ONE_GUEST_BILLTO_ADDRESS_BOOK_ID) === $_SESSION['billto']) {
                    $class->billing['billing_vat_number'] = '';
                    $class->billing['billing_vat_validated'] = VatValidation::VAT_NOT_VALIDATED;
                    return;
                }

                // -----
                // Otherwise, grab the VAT Number and its status for the currently-selected bill-to
                // address.
                //
                [$vat_number, $vat_number_status] = $this->getCustomersVatNumber($_SESSION['customer_id'], $_SESSION['billto']);
                $class->billing['billing_vat_number'] = $vat_number;
                $class->billing['billing_vat_validated'] = $vat_number_status;
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
                [$vat_number, $vat_validated_status] = $this->getCustomersVatNumber($_SESSION['customer_id'], $_SESSION['billto']);
                if (!empty($vat_number)) {
                    $db->Execute(
                        "UPDATE " . TABLE_ORDERS . "
                            SET billing_vat_number = '" . zen_db_prepare_input($vat_number) . "',
                                billing_vat_validated = $vat_validated_status
                          WHERE orders_id = " . (int)$p2 . "
                          LIMIT 1"
                    );
                }
                break;

            // -----
            // Issued at the end of the `create_account_success` page, giving us the opportunity to
            // add any just-supplied VAT number to the address' confirmation.
            //
            case 'NOTIFY_HEADER_END_CREATE_ACCOUNT_SUCCESS':
                global $addressArray;
                for ($i = 0, $n = count($addressArray); $i < $n; $i++) {
                    [$vat_number, $vat_number_status] = $this->getCustomersVatNumber($_SESSION['customer_id'], $addressArray[$i]['address_book_id']);
                    $addressArray[$i]['address']['entry_vat_number'] = $vat_number;
                }
                break;

            // -----
            // Issued during the create-account, address-book or checkout-new-address processing, gives us a chance to validate (if
            // required) the customer's entered VAT Number.
            //
            // Side effects: The call to validateVatNumber sets $this->vatNumberStatus, used by the following
            // set of notifications to store that status in the database.
            //
            // Entry:
            //  $p2 ... A reference to the module's $error variable.
            //
            case 'NOTIFY_CREATE_ACCOUNT_VALIDATION_CHECK':
                $message_location = 'create_account';
            case 'NOTIFY_MODULE_CHECKOUT_NEW_ADDRESS_VALIDATION':   //- Fall through ...
                $message_location = $message_location ?? 'checkout_address';
            case 'NOTIFY_ADDRESS_BOOK_PROCESS_VALIDATION':          //- Fall through ...
                $message_location = $message_location ?? 'addressbook';
                if ($this->validateVatNumber($message_location) === false) {
                    $p2 = true;
                }
                break;

            // -----
            // Issued during customer-account creation, indicates the customer_id that's
            // associated with the newly-added address-book record, below.
            //
            // Entry:
            //  $p1 ... An associative array that contains the 'customer_id'.
            //
            case 'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_CUSTOMER_RECORD':
                $this->newCustomerId = $p1['customer_id'];
                break;

            // -----
            // Issued during the create-account, address-book or checkout-new-address processing, indicates that an address record
            // has been created/updated and gives us a chance to record the customer's VAT Number.
            //
            // Entry:
            //  $p1 ... An associative array that contains the address-book entry's default data.
            //
            case 'NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_ADDRESS_BOOK_RECORD':
                $customer_id = $this->newCustomerId;
            case 'NOTIFY_MODULE_CHECKOUT_ADDED_ADDRESS_BOOK_RECORD':        //- Fall through ...
            case 'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD':  //- Fall through ...
            case 'NOTIFY_MODULE_ADDRESS_BOOK_ADDED_ADDRESS_BOOK_RECORD':    //- Fall through ...
                $customer_id = $customer_id ?? $_SESSION['customer_id'];
                $address_book_id = ($eventID === 'NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_ADDRESS_BOOK_RECORD') ? $p1['address_book_id'] : $p1['address_id'];
                $vat_number = zen_db_prepare_input($_POST['vat_number']);
                $db->Execute(
                    "UPDATE " . TABLE_ADDRESS_BOOK . "
                        SET entry_vat_number = '$vat_number',
                            entry_vat_validated = " . $this->vatNumberStatus . "
                      WHERE address_book_id = $address_book_id
                        AND customers_id = $customer_id
                      LIMIT 1"
                );
                break;

            // -----
            // Issued by the "shopping_cart" page's header when it's completed its processing.  Allows us to
            // determine whether a currently-logged-in customer qualifies for a VAT refund.
            //
            case 'NOTIFY_HEADER_END_SHOPPING_CART':
                global $products, $cartShowTotal, $currencies;

                if ($this->checkVatIsRefundable() === true && isset($products) && is_array($products)) {
                    $debug_message = $eventID . " starts ...";
                    $products_tax = 0;
                    $currency_decimal_places = $currencies->get_decimal_places($_SESSION['currency']);
                    foreach ($products as $current_product) {
                        $current_tax = zen_calculate_tax($current_product['final_price'], zen_get_tax_rate($current_product['tax_class_id']));
                        $products_tax += $current_product['quantity'] * zen_round($current_tax, $currency_decimal_places);
                        $debug_message .= ("\t" . $current_product['name'] . '(' . $current_product['id'] . ") adds $current_tax to the overall tax ($products_tax).\n");
                    }
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
            // This notification includes the following variables:
            //
            // $p1 ... (r/o) An associative array containing the various elements of the to-be-formatted address
            // $p2 ... (r/w) A reference to the function's return value, possibly modified by this processing.
            //
            case 'NOTIFY_END_ZEN_ADDRESS_FORMAT':
                $p2 = $this->formatAddress($p1, $p2);
                break;

            // -----
            // Issued by zen_address_label after gathering the address fields for a specified customer's address.  Gives this plugin
            // the opportunity to add any "VAT Number" associated with the associated address ... for use by
            // that function's subsequent call to zen_address_format.
            //
            // This notification includes the following variables:
            //
            // $p1 ... (n/a)
            // $p2 ... The customers_id value
            // $p3 ... The address_book_id value
            // $p4 ... The 'base' address elements gathered by zen_address_label
            //
            case 'NOTIFY_ZEN_ADDRESS_LABEL':
                // -----
                // During the 'checkout_process' page, add the VAT number information *only for* the
                // billing address. If the order has no shipping address, this is the 1st and 2nd
                // calls to zen_address_label during that page's processing; otherwise it's the
                // 3rd and 4th calls that apply to the billing address.  Two calls are issued
                // by order::send_order_email for each address to be included, one for the HTML email
                // and one for the TEXT email.
                //
                // Note: Can't just go off of the address_book_id value, since the same address
                // might be used for both shipping and billing.
                //
                global $current_page_base;
                if ($current_page_base === FILENAME_CHECKOUT_PROCESS) {
                    $this->addressLabelCount++;
                    if ($this->orderHasShippingAddress === true && $this->addressLabelCount < 3) {
                        return;
                    }
                }

                [$vat_number, $vat_number_status] = $this->getCustomersVatNumber((int)$p2, (int)$p3);
                $p4 = array_merge($p4, ['entry_vat_number' => $vat_number, 'entry_vat_validated' => $vat_number_status]);
                break;

            // -----
            // Issued during a template's html_header.php just before the CSS is output, enabling VAT4EU
            // (for non-bootstrap templates **only**) to bring in its styling for the VAT formats modal.
            //
            // $p1 ... (r/o) Contains the $current_page_base.
            //
            case 'NOTIFY_HTML_HEAD_CSS_BEGIN':
                $this->linkCatalogStylesheet('vat4eu.css', $p1);
                break;

            // -----
            // Issued at the end of a template's common/tpl_main_page.php. Load the jQuery module to insert
            // the VAT Number entry-field into an associated address-book entry. Note that this event is
            // observed **only** on 'appropriate' pages.
            //
            case 'NOTIFY_FOOTER_END':
                // -----
                // If currently on the 'checkout_one' page and gathering information for a non-billing address,
                // nothing further to be done.
                //
                global $which, $template, $current_page_base;

                if ((isset($which) && $which !== 'bill') || (isset($_POST['which']) && $_POST['which'] !== 'bill')){
                    return;
                }

                // -----
                // Locate the 'define' file that contains the VAT4EU formatting modal's
                // content. Check first to see if there's a template-override, otherwise, use
                // the plugin's 'english' content.
                //
                $vat4eu_formats_define = zen_get_file_directory(
                    DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/',
                    'define_vat4eu_formats.php'
                );
                if (!is_file($vat4eu_formats_define)) {
                    $vat4eu_formats_define = zen_get_file_directory(
                        $this->pluginManagerInstalledVersionDirectory . 'catalog/' . DIR_WS_LANGUAGES . 'english/html_includes/',
                        'define_vat4eu_formats.php'
                    );
                }

                // -----
                // Determine whether we're on the OPC data-gathering page; if so, the "VAT Number" cannot be updated
                // in that page's form (since OPC's jQuery doesn't recognize additional address-related fields
                // at this time).  Add, instead, a message pointing the customer to the address-book page where they *can*
                // provide a VAT Number update for the current order.
                //
                $form_field_disabled = '';
                $form_field_message = '';
                if (isset($which) || isset($_POST['which'])) {
                    $form_field_disabled = ' disabled';
                    $form_field_message = sprintf(VAT4EU_CHANGE_IN_ADDRESS_BOOK, zen_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $_SESSION['billto']));
                }

                // ----
                // Retrieve the HTML to be inserted after any 'company' field-entry in the
                // current form.
                //
                $vat_number = $this->getVatNumberForFormEntry($current_page_base ?? 'checkout_one');
                $vat_number = (!empty($vat_number)) ? zen_output_string_protected($vat_number) : '';
                $vat4eu_is_bootstrap = $this->isBootstrapTemplate;

                ob_start();
                require $template->get_template_dir('tpl_modules_vat4eu_display.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_vat4eu_display.php'; 
                $vat_field_entry = ob_get_contents();
                ob_end_clean();
?>
<script>
    jQuery(function() {
        jQuery('input[name="company"').prev('label').before(<?= json_encode($vat_field_entry) ?>);
    });
</script>
<?php

                break;

            default:
                break;
        }
    }

    public function isVatRefundable(): bool
    {
        return $this->checkVatIsRefundable();
    }

    public function getCountryIsoCode2($countries_id): string
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
    protected function validateVatNumber($message_location): bool
    {
        global $messageStack;

        $vat_ok = false;
        $vat_number = strtoupper(zen_db_prepare_input($_POST['vat_number']));

        $countries_id = $_POST['zone_country_id'];
        $country_iso_code_2 = $this->getCountryIsoCode2($countries_id);

        if (!in_array($country_iso_code_2, $this->vatCountries)) {
            $this->vatNumberStatus = VatValidation::VAT_OK;
            return true;
        }

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
                    $messageStack->add_session($message_location, sprintf(VAT4EU_APPROVAL_PENDING, zen_output_string_protected($vat_number)), 'warning');
                } elseif ($validation->validateVatNumber() === true) {
                    $this->vatNumberStatus = VatValidation::VAT_VIES_OK;
                } else {
                    $this->vatNumberStatus = VatValidation::VAT_VIES_NOT_OK;
                    $messageStack->add_session($message_location, VAT4EU_VAT_NOT_VALIDATED, 'warning');
                }
                break;

            default:
                trigger_error("Unexpected return value from vatNumberPreCheck: $precheck_status, VAT number not authorized.", E_USER_WARNING);
                break;
        }
        return $vat_ok;
    }

    protected function getCustomersVatNumber(int $customers_id, int $address_id): array
    {
        global $db;

        $debug_message = "getCustomersVatNumber($customers_id, $address_id)\n";
        $vat_number = '';
        $vat_number_status = VatValidation::VAT_NOT_VALIDATED;
        $check = $db->Execute(
            "SELECT entry_country_id, entry_vat_number, entry_vat_validated
               FROM " . TABLE_ADDRESS_BOOK . "
              WHERE address_book_id = $address_id
                AND customers_id = $customers_id
              LIMIT 1"
        );
        if (!$check->EOF) {
            $debug_message .= "\tAddress located, country #" . $check->fields['entry_country_id'] . "\n";
            if ($this->isVatCountry($check->fields['entry_country_id'])) {
                $vat_number = (string)$check->fields['entry_vat_number'];
                $vat_number_status = (int)$check->fields['entry_vat_validated'];
                $debug_message .= "\tBilling country is part of the EU, VAT Number ($vat_number), validation status: $vat_number_status.\n";
            }
        }

        $this->debug($debug_message . "\tReturning ($vat_number, $vat_number_status)\n");
        return [$vat_number, $vat_number_status];
    }

    protected function checkVatIsRefundable($customers_id = false, $address_id = false): bool
    {
        global $db;

        $vat_is_refundable = false;
        $debug_message = "checkVatIsRefundable($customers_id, $address_id)\n";
        if ($this->nonGuestIsLoggedIn === true) {
            if ($customers_id === false) {
                $customers_id = $_SESSION['customer_id'];
            }
            if ($address_id === false) {
                $address_id = $_SESSION['billto'] ?? $_SESSION['customer_default_address_id'];
            }
            $debug_message .= "\tCustomer is logged in ($customers_id, $address_id)\n";

            [$vat_number, $vat_number_status] = $this->getCustomersVatNumber($customers_id, $address_id);
            if ($vat_number !== '') {
                $sendto_address_id = $_SESSION['sendto'] ?? $_SESSION['customer_default_address_id'];
                if ($sendto_address_id !== false) {
                    $debug_message .= "\tSend-to address set ...\n";
                    $ship_check = $db->Execute(
                        "SELECT entry_country_id
                           FROM " . TABLE_ADDRESS_BOOK . "
                          WHERE address_book_id = " . (int)$sendto_address_id . "
                            AND customers_id = " . (int)$customers_id . "
                          LIMIT 1"
                    );
                    if (!$ship_check->EOF && $this->isVatCountry($ship_check->fields['entry_country_id']) === true) {
                        $debug_message .= "\tShip-to country is in the EU (" . $ship_check->fields['entry_country_id'] . ")\n";
                        if ($vat_number_status === VatValidation::VAT_VIES_OK || $vat_number_status === VatValidation::VAT_ADMIN_OVERRIDE) {
                            if (VAT4EU_IN_COUNTRY_REFUND === 'true' || STORE_COUNTRY != $ship_check->fields['entry_country_id']) {
                                $vat_is_refundable = true;
                            }
                        }
                    }
                }
            }
        }
        $this->debug($debug_message . "\tReturning (" . $vat_is_refundable . ")\n");

        return $vat_is_refundable;
    }

    // ------
    // This function determines, based on the currently-active page, whether a zen_address_format
    // function call should have the VAT Number appended and, if so, appends it!
    //
    protected function formatAddress(array $address_elements, string $current_address): string
    {
        global $current_page_base;

        $this->debug($current_page_base . ': ' . json_encode($address_elements, JSON_PRETTY_PRINT));

        // -----
        // Determine whether the address being formatted "qualifies" for the insertion of the VAT Number,
        // based on the current page we're on.
        //
        $address_out = $current_address;
        $show_vat_number = in_array($current_page_base, [
            FILENAME_ACCOUNT_HISTORY_INFO,
            FILENAME_ADDRESS_BOOK,
            FILENAME_CHECKOUT_CONFIRMATION,
            FILENAME_CHECKOUT_PAYMENT_ADDRESS,
            FILENAME_CHECKOUT_PAYMENT,
            FILENAME_CHECKOUT_PROCESS,
            FILENAME_CHECKOUT_SUCCESS,
            FILENAME_CREATE_ACCOUNT_SUCCESS,
        ]);

        // -----
        // If the "One-Page Checkout" plugin is installed, and we're on either its data-gathering
        // or confirmation page, the VAT Number is conditionally inserted for the billing-related
        // address-formatting.
        //
        if (defined('FILENAME_CHECKOUT_ONE')) {
            if ($current_page_base === FILENAME_CHECKOUT_ONE) {
                global $which;
                if (($which ?? '') === 'bill' || ($_POST['which'] ?? '') === 'bill') {
                    $show_vat_number = true;
                }
            } elseif ($current_page_base === FILENAME_CHECKOUT_ONE_CONFIRMATION) {
                $show_vat_number = true;
            }
        }

        // -----
        // Other pages, fire a notification to allow additional display of the VAT Number within
        // a formatted address.
        //
        // Note: For v3.2.0 and later, the 'address_format_count' element of the address is
        // no longer added!
        //
        if ($show_vat_number === false) {
            $this->notify('NOTIFY_VAT4EU_ADDRESS_DEFAULT', $address_elements, $current_address, $show_vat_number);
        }

        // -----
        // If the VAT Number is to be displayed as part of the address-block, append its value to the
        // end of the address, including the "unverified" tag if the number has not been validated.
        //
        if ($show_vat_number === true) {
            $vat_number =
                $address_elements['address']['entry_vat_number'] ??
                    $address_elements['address']['billing_vat_number'] ??
                        $address_elements['address']['vat_number'] ??
                            '';
            if (((string)$vat_number) !== '') {
                $address_out = $current_address . $address_elements['cr'] . VAT4EU_DISPLAY_VAT_NUMBER . $vat_number;

                $vat_validated =
                    $address_elements['address']['entry_vat_validated'] ??
                        $address_elements['address']['billing_vat_validated'] ??
                            $address_elements['address']['vat_validated'] ??
                                VatValidation::VAT_NOT_VALIDATED;
                $vat_validated = (int)$vat_validated;
                if ($vat_validated !== VatValidation::VAT_VIES_OK && $vat_validated !== VatValidation::VAT_ADMIN_OVERRIDE) {
                    $address_out .= VAT4EU_UNVERIFIED;
                }
            }
        }

        return $address_out;
    }

    // -----
    // This function, called by the VAT4EU addition to various address-gathering forms, determines
    // the VAT Number to be displayed in the form.  That value's source is dependent on the page
    // in action.
    //
    public function getVatNumberForFormEntry(string $current_page_base): string
    {
        $vat_number = $_POST['vat_number'] ?? null;
        if ($current_page_base === FILENAME_ADDRESS_BOOK_PROCESS) {
            global $entry;
            $vat_number = $vat_number ?? $entry->fields['entry_vat_number'] ?? '';
        } elseif (defined('FILENAME_CHECKOUT_ONE') && $current_page_base === FILENAME_CHECKOUT_ONE) {
            [$vat_number, $vat_number_status] = $this->getCustomersVatNumber($_SESSION['customer_id'], $_SESSION['billto']);
        }
        return (string)$vat_number;
    }

    // -----
    // This function returns a boolean indicator, identifying whether (true) or not (false) the
    // country associated with the "countries_id" input qualifies for this plugin's processing.
    //
    protected function isVatCountry($countries_id): bool
    {
        return in_array($this->getCountryIsoCode2($countries_id), $this->vatCountries);
    }

    private function debug($message)
    {
        if ($this->debug === true) {
            error_log(date('Y-m-d H:i:s') . ": $message\n", 3, $this->logfile);
        }
    }
}
