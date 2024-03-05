DELETE FROM configuration WHERE configuration_key LIKE 'VAT4EU_%';
DELETE FROM configuration_group WHERE configuration_group_title = 'VAT4EU Plugin' LIMIT 1;
DELETE FROM admin_pages WHERE page_key IN ('configVat4Eu');
ALTER TABLE address_book DROP COLUMN entry_vat_number;
ALTER TABLE address_book DROP COLUMN entry_vat_validated;
ALTER TABLE orders DROP COLUMN billing_vat_number;
ALTER TABLE orders DROP COLUMN billing_vat_validated;