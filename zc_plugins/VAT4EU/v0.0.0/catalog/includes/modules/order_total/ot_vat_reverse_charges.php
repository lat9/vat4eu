<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Invalid access.');
}

class ot_vat_reverse_charges
{
    public string $title;
    public string $description;
    public string $code;
    public array $output = [];
    public int|null $sort_order;

    protected $_check;
    protected bool $isEnabled = false;

    public function __construct()
    {
        global $current_page;

        $this->code = 'ot_vat_reverse_charges';
        $this->title = (IS_ADMIN_FLAG === true && $current_page !== 'edit_orders.php') ? MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_TITLE_ADMIN : MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_TITLE;
        
        $this->description = MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_DESCRIPTION;
        $this->sort_order = defined('MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER') ? (int)MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER : null;
        if ($this->sort_order === null) {
            return false;
        }
        
        $this->isEnabled = (VAT4EU_ENABLED === 'true');

        $this->output = [];
    }

    public function process(): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        global $zcObserverVatForEuCountries, $zcObserverVat4euAdminObserver;

        $is_refundable = false;
        if (IS_ADMIN_FLAG === false && isset($zcObserverVatForEuCountries)) {
            $is_refundable = $zcObserverVatForEuCountries->isVatRefundable();
        } elseif (IS_ADMIN_FLAG == true && isset($zcObserverVat4euAdminObserver)) {
            $is_refundable = $zcObserverVat4euAdminObserver->isVatRefundable();
        }
        if ($is_refundable === true) {
            global $order;

            if ($order->info['tax'] != 0) {
                $this->output[] = [
                    'title' => '<span id="vat-reverse-charge">' . $this->title . '</span>',
                    'text' => '&nbsp;',
                    'value' => 0,
                ];
            }
        }
    }

    public function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check = $db->Execute(
                "SELECT configuration_value
                   FROM " . TABLE_CONFIGURATION . "
                  WHERE configuration_key = 'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_STATUS'
                  LIMIT 1"
            );
            $this->_check = $check->RecordCount();
        }
        return $this->_check;
    }

    public function keys(): array
    {
        return [
            'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_STATUS',
            'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER',
        ];
    }

    public function install(): void
    {
        global $db;

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('This module is installed', 'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_STATUS', 'true', '', 6, 1,'zen_cfg_select_option([\'true\'], ', now()),

                ('Sort Order', 'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER', '2000', 'Sort order of display.<br /><br /><b>Note:</b> Make sure that the value is larger than the sort-order for the <em>Total</em> total\'s display!', 6, 1, NULL, now())"
        );
    }

    public function remove(): void
    {
        global $db;

        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION . "
              WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')"
        );
    }
}
