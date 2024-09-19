<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v3.2.0
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Invalid access.');
}

class ot_vat_reverse_charges extends base
{
    public $title;
    public $description;
    public $code;
    public $output;
    public $sort_order;

    protected $_check;
    protected $isEnabled = false;

    public function __construct()
    {
        $this->code = 'ot_vat_reverse_charges';
        $this->title = (IS_ADMIN_FLAG === true && $GLOBALS['current_page'] !== 'edit_orders.php') ? MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_TITLE_ADMIN : MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_TITLE;
        
        $this->description = MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_DESCRIPTION;
        $this->sort_order = defined('MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER') ? (int)MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER : null;
        if ($this->sort_order === null) {
            return false;
        }
        
        $this->isEnabled = (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED === 'true');

        $this->output = [];
    }

    public function process()
    {
        $is_refundable = false;
        if ($this->isEnabled === true) {
            if (IS_ADMIN_FLAG === false && is_object($GLOBALS['zcObserverVatForEuCountries'])) {
                $is_refundable = $GLOBALS['zcObserverVatForEuCountries']->isVatRefundable();
            } elseif (IS_ADMIN_FLAG == true && is_object($GLOBALS['vat4EuAdmin'])) {
                $is_refundable = $GLOBALS['vat4EuAdmin']->isVatRefundable();
            }
        }
        if ($is_refundable === true) {
            if ($GLOBALS['order']->info['tax'] != 0) {
                $this->output[] = [
                    'title' => '<span id="vat-reverse-charge">' . $this->title . '</span>',
                    'text' => '&nbsp;',
                    'value' => 0
                ];
            }
        }
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check = $GLOBALS['db']->Execute(
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

    public function install()
    {
        $GLOBALS['db']->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('This module is installed', 'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_STATUS', 'true', '', '6', '1','zen_cfg_select_option([\'true\'], ', now())"
        );
        $GLOBALS['db']->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('Sort Order', 'MODULE_ORDER_TOTAL_VAT_REVERSE_CHARGES_SORT_ORDER', '2000', 'Sort order of display.<br /><br /><b>Note:</b> Make sure that the value is larger than the sort-order for the <em>Total</em> total\'s display!', '6', '2', now())"
        );
    }

    public function remove()
    {
        $GLOBALS['db']->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION . "
              WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')"
        );
    }
}
