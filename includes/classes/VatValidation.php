<?php
// -----
// Part of the "VAT Mod - v2.0" plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
// This class derived from a similarly-named class provided here: https://github.com/herdani/vat-validation
//
class VatValidation
{
    const WSDL = "http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";
    
    private $_client = null;
    private $_valid = false;
    private $_data = array();
    
    private $debug = false;
    private $soapInstalled = false;
    
    public function __construct() 
    {
        if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED != 'true') {
            $this->debug = (defined('VAT4EU_DEBUG') && VAT4EU_DEBUG == 'true');
            
            if (!class_exists('SoapClient')) {
                trigger_error('VAT Number validation not possible, "SoapClient" class is not available.', E_USER_WARNING);;
            } else {
                $this->soapInstalled = true;
                try {
                    $this->_client = new SoapClient(self::WSDL, array('trace' => true) );
                } catch(Exception $e) {
                    $this->soapInstalled = false;
                    trigger_error("VAT Number validation not possible, VAT Translation Error: " . $e->getMessage(), E_USER_WARNING);
                }
            }
        }
    }

    public function checkVatNumber($countryCode, $vatNumber) 
    {
        $this->_valid = false;
        $this->_data = array();
        if ($this->soapInstalled) {
            $rs = $this->_client->checkVat(
                array(
                    'countryCode' => $countryCode, 
                    'vatNumber' => $vatNumber
                ) 
            );

            $this->trace("Web Service result ($countryCode, $vatNumber): " . $this->_client->__getLastResponse());    

            if ($rs->valid) {
                $this->_valid = true;
                list($denomination, $name) = explode(' ', $rs->name, 2);
                $this->_data = array(
                    'denomination' => $denomination, 
                    'name' => $name, 
                    'address' => $rs->address,
                );
            }
        }
        return $this->_valid;
    }

    public function isValid() 
    {
        return $this->_valid;
    }
    
    public function getDenomination() 
    {
        return (isset($this->_data['denomination'])) ? $this->_data['denomination'] : false;
    }
    
    public function getName() 
    {
        return (isset($this->_data['name'])) ? $this->_data['name'] : false;
    }
    
    public function getAddress() 
    {
        return (isset($this->_data['address'])) ? $this->_data['address'] : false;
    }
    
    public function isDebug() 
    {
        return ($this->debug === true);
    }
    
    private function trace($message) 
    {
        if ($this->debug) {
            error_log(date('Y-m-d H:m:i: ') . $message . PHP_EOL, 3, DIR_FS_LOGS . '/VatValidate.log');
        }
    }
}
