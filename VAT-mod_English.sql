INSERT INTO configuration_group VALUES (27, "VAT-Mod", "VAT-Mod options", 27, 1);

INSERT INTO configuration VALUES ("", "Check the VAT number", "ENTRY_TVA_INTRACOM_CHECK", "true", "Check the Customer's VAT number by the europa.eu.int server", 27, 1, "", "", NULL, "zen_cfg_select_option(array('true', 'false'),");
INSERT INTO configuration VALUES ("", "VAT number of the store", "TVA_SHOP_INTRACOM", "", "Intracom VAT number:", 27, 22, "", "", NULL, NULL);
INSERT INTO configuration VALUES ("", "Minimum characters for VAT number", "ENTRY_TVA_INTRACOM_MIN_LENGTH", 10, "Required characters for VAT number (0 if you don't want checking)", 27, 17, "", "", NULL, NULL);

ALTER TABLE address_book ADD entry_tva_intracom VARCHAR(32) DEFAULT NULL AFTER entry_company;
ALTER TABLE orders ADD billing_tva_intracom VARCHAR(32) AFTER billing_company;