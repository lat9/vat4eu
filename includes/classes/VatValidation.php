<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2020 Vinos de Frutas Tropicales
//
// This class derived from a similarly-named class provided here: https://github.com/herdani/vat-validation
//
class VatValidation
{
    const WSDL = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";
    
    // -----
    // These class constants define the possible values for the entry_vat_validated field, present
    // in the Zen Cart address_book table.
    //
    const VAT_ADMIN_OVERRIDE = 2;       //- An admin "overrode" the VIES validation    
    const VAT_VIES_OK        = 1;       //- The VAT Number was validated via VIES
    const VAT_NOT_VALIDATED  = 0;       //- The VAT Number has not been validated; initial setting when admin-validation is configured
    const VAT_VIES_NOT_OK    = -1;      //- The VAT Number was indicated to be invalid via VIES.
    
    // -----
    // These class constants are returned by the vatNumberPreCheck function, identifying the pre-check's
    // completion status.
    //
    const VAT_NOT_SUPPLIED   = 1;       //- No VAT Number was supplied (and it's not required).
    const VAT_OK             = 0;       //- The pre-checks all passed
    const VAT_MIN_LENGTH     = -1;      //- A minimum length has been set and the value's length is too short
    const VAT_BAD_PREFIX     = -2;      //- The first 2 characters of the number don't match the associated country-code
    const VAT_INVALID_CHARS  = -3;      //- There is at least one "invalid" character in the supplied VAT number
    const VAT_REQUIRED       = -4;      //- No VAT Number was supplied and it's required
    
    // -----
    // This constant defines the characters (used in preg_match) that are "valid" for a VAT Number.  Note
    // that characters that are valid for one country might not be valid for another!
    //
    // Valid characters are alphanumerics, space (' '), plus-sign ('+') and asterisk ('*').
    //
    const VAT_VALIDATION     = '/[A-Z0-9 \+*]+/';
    

    private $_client = null;
    private $_valid = false;
    
    private $vatNumber;
    private $countryCode;
    
    private $debug = false;
    private $soapInstalled = false;
    
    // -----
    // The class constructor gathers the to-be-verified country-code (2-character ISO) and
    // the associated VAT Number.  The "VAT Number" value must include any country code
    // prefix.
    //
    // Since we'll need the SOAP service to automatically validate the VAT Number, check now
    // to see that the PHP installation includes that service, logging a warning if not.
    //
    public function __construct($countryCode, $vatNumber) 
    {
        if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true') {
            $this->debug = (defined('VAT4EU_DEBUG') && VAT4EU_DEBUG == 'true');
            
            // -----
            // Greek VAT numbers start with 'EL' instead of their country-code ('GR').
            //
            $this->countryCode = ($countryCode == 'GR') ? 'EL' : $countryCode;
            $this->vatNumber = strtoupper($vatNumber);
            
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
            $this->trace("__construct($countryCode, $vatNumber)");
        }
    }
    
    // -----
    // This function performs a quick pre-check of the VAT Number, weeding out some simple errors
    // prior to requesting VIES validation.
    //
    // The return-code value returned (one of this class' constants) identifies the type of issue
    // or a successful pre-check.
    //
    public function vatNumberPreCheck()
    {
        // -----
        // If the VAT Number isn't supplied, then check to see if it's required and let the caller
        // know.
        //
        if ($this->vatNumber == '') {
            if (VAT4EU_REQUIRED == 'true') {
                $rc = self::VAT_REQUIRED;
            } else {
                $rc = self::VAT_NOT_SUPPLIED;
            }
        // -----
        // Otherwise, a VAT Number has been supplied and is checked for:
        //
        // 1) A minimum length, if so configured.
        // 2) A VAT Number  must begin with the 2-character ISO code associated with the
        //    country in its associated address.
        // 3) A valid VAT number consists of alphanumeric characters, with a couple of "special"
        //    characters allowed for some countries.
        } else {
            $vat_number_length = strlen($this->vatNumber);
            if (VAT4EU_MIN_LENGTH != '0' && $vat_number_length < VAT4EU_MIN_LENGTH) {
                $rc = self::VAT_MIN_LENGTH;
            } elseif (strpos($this->vatNumber, $this->countryCode) !== 0) {
                $rc = self::VAT_BAD_PREFIX;  
            } elseif (!preg_match(self::VAT_VALIDATION, $this->vatNumber)) {
                $rc = self::VAT_INVALID_CHARS;
            } else {
                $rc = self::VAT_OK;
            }
        }
        $this->trace("vatNumberPreCheck({$this->vatNumber}), returning $rc.");
        return $rc;
    }        

    // -----
    // This function calls, via SOAP, the VIES VAT validation service to see if the current
    // VAT Number is registered (aka valid), returning a boolean value indicating whether (true)
    // or not (false) the VIES service has validated the number.
    //
    public function validateVatNumber() 
    {
        $this->_valid = false;
        if ($this->soapInstalled) {
            $number_validated = true;
            try {
                $rs = $this->_client->checkVat(
                    array(
                        'countryCode' => $this->countryCode, 
                        'vatNumber' => substr($this->vatNumber, 2)
                    ) 
                );
            } catch(Exception $e) {
                $number_validated = false;
            }

            $this->trace("Web Service result (" . $this->countryCode . ', ' . $this->vatNumber . "): " . $this->_client->__getLastResponse());    

            if ($number_validated && $rs->valid) {
                $this->_valid = true;
            }
        }
        return $this->_valid;
    }

    // -----
    // Locally used to log any information, under the control of the VAT4EU's debug configuration.
    //
    private function trace($message) 
    {
        if ($this->debug) {
            error_log(date('Y-m-d H:m:i: ') . $message . PHP_EOL . PHP_EOL, 3, DIR_FS_LOGS . '/VatValidate.log');
        }
    }
}
