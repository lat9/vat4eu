<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
///
class ot_vat_refund extends base
{
    public    $title;
    public    $output;
    protected $_check;
    protected $isEnabled = false;

    public function __construct() 
    {
        $this->code = 'ot_vat_refund';
        $this->title = MODULE_ORDER_TOTAL_VAT_REFUND_TITLE;
        $this->description = MODULE_ORDER_TOTAL_VAT_REFUND_DESCRIPTION;
        $this->sort_order = MODULE_ORDER_TOTAL_VAT_REFUND_SORT_ORDER;
        
        $this->isEnabled = (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true');

        $this->output = array();
    }

    public function process() 
    {
        if ($this->isEnabled && is_object($GLOBALS['zcObserverVatForEuCountries']) && $GLOBALS['zcObserverVatForEuCountries']->isVatRefundable()) {
            $order = $GLOBALS['order'];
            $vat_refund = $order->info['tax'];
            if ($vat_refund != 0) {
                $this->output[] = array(
                    'title' => $this->title . ':',
                    'text' => '-' . $GLOBALS['currencies']->format($vat_refund, true, $order->info['currency'], $order->info['currency_value']),
                    'value' => -$vat_refund
                );
            }
        }
    }

    public function check() 
    {
        if (!isset($this->_check)) {
            $check = $GLOBALS['db']->Execute(
                "SELECT configuration_value
                   FROM " . TABLE_CONFIGURATION . "
                  WHERE configuration_key = 'MODULE_ORDER_TOTAL_VAT_REFUND_STATUS'
                  LIMIT 1"
            );
            $this->_check = $check->RecordCount();
        }
        return $this->_check;
    }

    public function keys() 
    {
        return array(
            'MODULE_ORDER_TOTAL_VAT_REFUND_STATUS', 
            'MODULE_ORDER_TOTAL_VAT_REFUND_SORT_ORDER', 
        );
    }

    public function install() 
    {
        $GLOBALS['db']->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " 
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
             VALUES 
                ('This module is installed', 'MODULE_ORDER_TOTAL_VAT_REFUND_STATUS', 'true', '', '6', '1','zen_cfg_select_option(array(\'true\'), ', now())"
        );
        $GLOBALS['db']->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " 
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
             VALUES 
                ('Sort Order', 'MODULE_ORDER_TOTAL_VAT_REFUND_SORT_ORDER', '900', 'Sort order of display.<br /><br /><b>Note:</b> Make sure that the value is larger than the sort-order for the <em>Tax</em> total\'s display!', '6', '2', now())"
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
