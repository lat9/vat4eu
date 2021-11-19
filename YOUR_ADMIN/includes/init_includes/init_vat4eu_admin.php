<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2021 Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

define('VAT4EU_CURRENT_RELEASE', '3.1.0-beta1');
define('VAT4EU_CURRENT_UPDATE_DATE', '2021-11-18');

define('VAT4EU_CURRENT_VERSION', VAT4EU_CURRENT_RELEASE . ': ' . VAT4EU_CURRENT_UPDATE_DATE);

// -----
// Wait until an admin is logged in before seeing if any initialization steps need to be performed.
// That ensures that "someone" will see the plugin's installation/update messages!
//
if (isset($_SESSION['admin_id'])) {
    // -----
    // Create the plugin's configuration-group, if it's not already there.  That way, we'll have the
    // configuration_group_id, if needed for future configuration updates.
    //
    $configurationGroupTitle = 'VAT4EU Plugin';
    $configuration = $db->Execute(
        "SELECT configuration_group_id 
           FROM " . TABLE_CONFIGURATION_GROUP . " 
          WHERE configuration_group_title = '$configurationGroupTitle' 
          LIMIT 1"
    );
    if ($configuration->EOF) {
        $db->Execute( 
            "INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
                (configuration_group_title, configuration_group_description, sort_order, visible) 
             VALUES 
                ('$configurationGroupTitle', '$configurationGroupTitle Settings', 1, 1);"
        );
        $cgi = $db->Insert_ID(); 
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION_GROUP . " 
                SET sort_order = $cgi 
              WHERE configuration_group_id = $cgi
              LIMIT 1"
        );
    } else {
        $cgi = $configuration->fields['configuration_group_id'];
    }

    // ----
    // Perform the plugin's initial install, if not currently present.
    //
    if (!defined('VAT4EU_MODULE_VERSION')) {
        // -----
        // Create configuration items that are new to this plugin version.
        //
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, set_function) 
             VALUES 
                ('Plugin Version and Release Date', 'VAT4EU_MODULE_VERSION', '" . VAT4EU_CURRENT_VERSION . "', 'The &quot;VAT for EU Countries (VAT4EU)&quot; current version and release date.', $cgi, 10, now(), 'trim(')"
        );

        $db->Execute(
           "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
            VALUES 
                ('European Union Countries List', 'VAT4EU_EU_COUNTRIES', 'AT,BE,BG,CY,CZ,DE, DK,EE,GR,ES,FI,FR, GB,HR,HU,IE,IT,LT, LU,LV,MT,NL,PL, PT,RO,SE,SI,SK', 'This comma-separated list identifies the countries that are in the EU by their 2-character ISO codes; intervening blanks are allowed. You normally will not need to change this list; it is provided as member countries move in and out of the EU.<br /><br/><b>Default</b>: AT,BE,BG,CY,CZ,DE, DK,EE,GR,ES,FI,FR, GB,HR,HU,IE,IT,LT, LU,LV,MT,NL,PL, PT,RO,SE,SI,SK', $cgi, 15, now(), NULL, NULL)"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('Enable storefront processing?', 'VAT4EU_ENABLED', 'false', 'The <em>VAT4EU</em> processing is enabled when this setting is &quot;true&quot; and you have also set <em>Configuration-&gt;Customer Details-&gt;Company</em> to <b>true</b>.', $cgi, 20, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('VAT Number Required?', 'VAT4EU_REQUIRED', 'false', 'Should the <em>VAT Number</em> be a <b>required</b> field?', $cgi, 30, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('Minimum VAT Number Length', 'VAT4EU_MIN_LENGTH', '10', 'Identify the minimum length of an entered VAT Number, used as a pre-check for any input value. Set the value to <em>0</em> to disable this check.', $cgi, 31, now(), NULL, NULL)"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('Enable <em>VAT Refund</em> for in-country purchases?', 'VAT4EU_IN_COUNTRY_REFUND', 'false', 'Should purchases by addresses in the store\'s country be granted a <em>VAT Refund</em>?', $cgi, 35, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('<em>VAT Number</em> Validation', 'VAT4EU_VALIDATION', 'Admin', 'A <em>VAT Number</em> requires validation prior to granting the customer a VAT Refund. Choose the validation method to use for your store, one of:<br /><br /><b>Customer</b> ... validate on any customer update<br /><b>Admin</b> ... only validated by admin action.<br />', $cgi, 50, now(), NULL, 'zen_cfg_select_option(array(\'Customer\', \'Admin\'),')"
        );

        $db->Execute(
           "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
            VALUES 
                ('VAT Number: Unvalidated Indicator', 'VAT4EU_UNVERIFIED', '*', 'Identify the indicator that you want to give your customers who have entered a <em>VAT Number</em> when that number is not yet validated.<br /><br />Default: <b>*</b>', $cgi, 52, now(), NULL, NULL)"        
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES 
                ('Enable debug?', 'VAT4EU_DEBUG', 'false', 'Should the plugin\'s <em>debug</em> mode be enabled?  When enabled, each VAT validation request and response is logged to /logs/VatValidate.log.', $cgi, 1000, now(), NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
        );

        // -----
        // Add an entry to the address_book table that will hold the VAT Number associated with the address
        // and an indication as to whether that value is valid.
        //
        $db->Execute(
            "ALTER TABLE " . TABLE_ADDRESS_BOOK . "
               ADD entry_vat_number varchar(32) DEFAULT NULL AFTER entry_company,
               ADD entry_vat_validated tinyint(1) NOT NULL default 0 AFTER entry_vat_number"
        );

        // -----
        // Add entries to the orders table that will hold the VAT Number associated with the order and its
        // validation status.
        //
        $db->Execute(
            "ALTER TABLE " . TABLE_ORDERS . "
               ADD billing_vat_number varchar(32) NOT NULL DEFAULT '' AFTER billing_company,
               ADD billing_vat_validated tinyint(1) NOT NULL default 0 AFTER billing_vat_number"
        );

        // -----
        // Display a message to the current admin, letting them know that the plugin's been installed.
        //
        $messageStack->add(sprintf(VAT4EU_TEXT_MESSAGE_INSTALLED, VAT4EU_CURRENT_VERSION), 'success');

        define('VAT4EU_MODULE_VERSION', '0.0.0');

        // -----
        // Register the plugin's configuration group with the admin menus.
        //
        zen_register_admin_page('configVat4Eu', 'BOX_CONFIG_VAT4EU', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y', $cgi);
    }

    // -----
    // If the VAT4EU countries' list currently references 'EL', change those occurrences to 'GR' (the countries::countries_iso_code_2
    // value for Greece).
    //
    if (defined('VAT4EU_EU_COUNTRIES') && strpos(VAT4EU_EU_COUNTRIES, 'GR') === false && strpos(VAT4EU_EU_COUNTRIES, 'EL') !== false) {
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '" . str_replace('EL', 'GR', VAT4EU_EU_COUNTRIES) . "'
              WHERE configuration_key = 'VAT4EU_EU_COUNTRIES'
              LIMIT 1"
        );
    }

    // -----
    // Update the configuration table to reflect the current version, if it's not already set.
    //
    if (VAT4EU_MODULE_VERSION != VAT4EU_CURRENT_VERSION) {
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " 
                SET configuration_value = '" . VAT4EU_CURRENT_VERSION . "' 
              WHERE configuration_key = 'VAT4EU_MODULE_VERSION'
              LIMIT 1"
        );
        if (VAT4EU_MODULE_VERSION != '0.0.0') {
            $messageStack->add(sprintf(VAT4EU_TEXT_MESSAGE_UPDATED, VAT4EU_MODULE_VERSION, VAT4EU_CURRENT_VERSION), 'success');
        }
    }
}
