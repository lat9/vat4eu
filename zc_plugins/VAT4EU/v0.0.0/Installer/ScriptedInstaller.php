<?php
// -----
// Admin-level installation script for the "encapsulated" VAT4EU plugin for Zen Cart, by lat9.
// Copyright (C) 2018-2025, Vinos de Frutas Tropicales.
//
// Last updated: v4.0.3
//
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    private string $configGroupTitle = 'VAT4EU Plugin';

    protected function executeInstall()
    {
        if ($this->nonEncapsulatedVersionPresent() === true) {
            $this->errorContainer->addError('error', ZC_PLUGIN_VAT4EU_INSTALL_REMOVE_PREVIOUS, true);
            return false;
        }

        // -----
        // First, determine the configuration-group-id and install the settings.
        //
        $cgi = $this->getOrCreateConfigGroupId(
            $this->configGroupTitle,
            $this->configGroupTitle . ' Settings'
        );

        $sql =
            "INSERT IGNORE INTO " . TABLE_CONFIGURATION . " 
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES
                ('European Union Countries List', 'VAT4EU_EU_COUNTRIES', 'AT, BE, BG, CY, CZ, DE, DK, EE, GR, ES, FI, FR, HR, HU, IE, IT, LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK', 'This comma-separated list identifies the countries that are in the EU by their 2-character ISO codes; intervening blanks are allowed. You normally will not need to change this list; it is provided as member countries move in and out of the EU.<br><br><b>Default</b>: AT, BE, BG, CY, CZ, DE, DK, EE, GR, ES, FI, FR, HR, HU, IE, IT, LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK', $cgi, 15, now(), NULL, NULL),

                ('Enable storefront processing?', 'VAT4EU_ENABLED', 'false', 'The <em>VAT4EU</em> processing is enabled when this setting is &quot;true&quot; and you have also set <em>Configuration :: Customer Details :: Company</em> to <b>true</b>.', $cgi, 20, now(), NULL, 'zen_cfg_select_option([\'true\', \'false\'],'),

                ('VAT Number Required?', 'VAT4EU_REQUIRED', 'false', 'Should the <em>VAT Number</em> be a <b>required</b> field?', $cgi, 30, now(), NULL, 'zen_cfg_select_option([\'true\', \'false\'],'),

                ('Minimum VAT Number Length', 'VAT4EU_MIN_LENGTH', '10', 'Identify the minimum length of an entered VAT Number, used as a pre-check for any input value. Set the value to <em>0</em> to disable this check.', $cgi, 31, now(), NULL, NULL),

                ('Enable <em>VAT Refund</em> for in-country purchases?', 'VAT4EU_IN_COUNTRY_REFUND', 'false', 'Should purchases by addresses in the store\'s country be granted a <em>VAT Refund</em>?', $cgi, 35, now(), NULL, 'zen_cfg_select_option([\'true\', \'false\'],'),

                ('<em>VAT Number</em> Validation', 'VAT4EU_VALIDATION', 'Admin', 'A <em>VAT Number</em> requires validation prior to granting the customer a VAT Refund. Choose the validation method to use for your store, one of:<br><br><b>Customer</b> ... validate on any customer update<br><b>Admin</b> ... only validated by admin action.<br>', $cgi, 50, now(), NULL, 'zen_cfg_select_option([\'Customer\', \'Admin\'],'),

                ('VAT Number: Unvalidated Indicator', 'VAT4EU_UNVERIFIED', '*', 'Identify the indicator that you want to give your customers who have entered a <em>VAT Number</em> when that number is not yet validated.<br><br>Default: <b>*</b>', $cgi, 52, now(), NULL, NULL),

                ('Enable debug?', 'VAT4EU_DEBUG', 'false', 'Should the plugin\'s <em>debug</em> mode be enabled?  When enabled, each VAT validation request and response is logged to /logs/VatValidate.log.', $cgi, 1000, now(), NULL, 'zen_cfg_select_option([\'true\', \'false\'],')";
        $this->executeInstallerSql($sql);

        // -----
        // Record the plugin's configuration settings in the admin menus.
        //
        if (zen_page_key_exists('configVat4Eu') === false) {
            zen_register_admin_page('configVat4Eu', 'BOX_CONFIG_VAT4EU', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y');
        }

        // -----
        // If the plugin's additions to various tables aren't present, add them.
        //
        global $sniffer;
        if ($sniffer->field_exists(TABLE_ADDRESS_BOOK, 'entry_vat_number') === false) {
            // -----
            // Add an entry to the address_book table that will hold the VAT Number associated with the address
            // and an indication as to whether that value is valid.
            //
            $sql =
                "ALTER TABLE " . TABLE_ADDRESS_BOOK . "
                   ADD entry_vat_number varchar(32) DEFAULT NULL AFTER entry_company,
                   ADD entry_vat_validated tinyint(1) NOT NULL default 0 AFTER entry_vat_number";
            $this->executeInstallerSql($sql);
        }
        if ($sniffer->field_exists(TABLE_ORDERS, 'billing_vat_number') === false) {
            // -----
            // Add entries to the orders table that will hold the VAT Number associated with the order and its
            // validation status.
            //
            $sql =
                "ALTER TABLE " . TABLE_ORDERS . "
                   ADD billing_vat_number varchar(32) NOT NULL DEFAULT '' AFTER billing_company,
                   ADD billing_vat_validated tinyint(1) NOT NULL default 0 AFTER billing_vat_number";
            $this->executeInstallerSql($sql);
        }

        // -----
        // If a previous (non-encapsulated) version of the plugin is currently installed,
        // remove the now-unused configuration settings.
        //
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION . "
              WHERE configuration_key = 'VAT4EU_MODULE_VERSION'
              LIMIT 1"
        );

        parent::executeInstall();

        return true;
    }

    // -----
    // Not used, initially, but included for the possibility of future upgrades!
    //
    // Note: This (https://github.com/zencart/zencart/pull/6498) Zen Cart PR must
    // be present in the base code or a PHP Fatal error is generated due to the
    // function signature difference.
    //
    protected function executeUpgrade($oldVersion)
    {
        parent::executeUpgrade($oldVersion);
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['configVat4Eu']);

        $this->deleteConfigurationGroup($this->configGroupTitle, true);

        parent::executeUninstall();
    }

    protected function nonEncapsulatedVersionPresent(): bool
    {
        $log_messages = [];

        $file_found_message = 'Non-encapsulated admin file (%s) must be removed before this plugin can be installed.';
        if (file_exists(DIR_FS_ADMIN . 'edit_orders.php')) {
            $log_messages[] = sprintf($file_found_message, 'edit_orders.php');
        }

        $files_to_check = [
            'includes/auto_loaders/' => [
                'config.vat4eu_admin.php',
            ],
            'includes/classes/observers/' => [
                'Vat4EuAdminObserver.php',
            ],
            'includes/init_includes/' => [
                'init_vat4eu_admin.php',
            ],
            'includes/languages/english/extra_definitions/' => [
                'lang.vat4eu_extra_definitions_admin.php',
                'vat4eu_extra_definitions_admin.php',
            ],
        ];
        foreach ($files_to_check as $dir => $files) {
            $current_dir = DIR_FS_ADMIN . $dir;
            foreach ($files as $next_file) {
                if (file_exists($current_dir . $next_file)) {
                    $log_messages[] = sprintf($file_found_message, $dir . $next_file);
                }
            }
        }

        $files_to_check = [
            'includes/classes/' => [
                'VatValidation.php',
            ],
            'includes/classes/observers/' => [
                'auto.vat_for_eu_countries.php',
            ],
            'includes/extra_datafiles/' => [
                'vat4eu_filenames.php',
            ],
            'includes/languages/english/extra_definitions/' => [
                'lang.vat4eu_extra_definitions.php',
                'vat4eu_extra_definitions.php',
            ],
            'includes/languages/english/modules/order_total/' => [
                'lang.ot_vat_refund.php',
                'lang.ot_vat_reverse_charges.php',
                'ot_vat_refund.php',
                'ot_vat_reverse_charges.php',
            ],
            'includes/modules/' => [
                'order_total/ot_vat_refund.php',
                'order_total/ot_vat_reverse_charges.php',
                'pages/popup_vat4eu_formats/header_php.php',
                'pages/popup_vat4eu_formats/jscript_main.php',
            ],
            'includes/templates/template_default/' => [
                'popup_vat4eu_formats/tpl_main_page.php',
                'templates/tpl_modules_vat4eu_display.php',
            ],
        ];
        foreach ($files_to_check as $dir => $files) {
            $current_dir = DIR_FS_CATALOG . $dir;
            foreach ($files as $next_file) {
                if (file_exists($current_dir . $next_file)) {
                    $log_messages[] = sprintf($file_found_message, $dir . $next_file);
                }
            }
        }

        if (count($log_messages) !== 0) {
            trigger_error(implode("\n", $log_messages), E_USER_NOTICE);
            return true;
        }
        return false;
    }
}
