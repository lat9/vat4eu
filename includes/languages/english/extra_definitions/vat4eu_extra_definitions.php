<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//

// -----
// These two definitions are used in different spots.
//
// 1) VAT4EU_ENTRY_VAT_NUMBER is used during VAT Number "gathering" and should not be an empty string.
// 2) VAT4EU_DISPLAY_VAT_NUMBER is used when formatting an address-block with a previously-entered VAT Number.
//    If you don't want to precede the actual VAT Number with that text, just set the value to ''; otherwise,
//    remember to keep the final space so that there's separation from the text and the actual VAT Number!
//
define('VAT4EU_ENTRY_VAT_NUMBER', 'VAT Number:');
define('VAT4EU_DISPLAY_VAT_NUMBER', 'VAT Number: ');

// -----
// This definition is used by tpl_modules_vat4eu_display.php's link to the popup_vat4eu_formats page.
//
define('VAT4EU_WHATS_THIS', 'What\'s this?');

define('VAT4EU_ENTRY_VAT_MIN_ERROR', 'Your <em>VAT Number</em> must contain a minimum of ' . VAT4EU_MIN_LENGTH . ' characters.');
define('VAT4EU_ENTRY_VAT_PREFIX_INVALID', 'Your <em>VAT Number</em> must begin with <b>%1$s</b>, since the address is in <em>%2$s</em>.');
define('VAT4EU_ENTRY_REQUIRED_ERROR', 'Your <em>VAT Number</em> is a required field.');

define('VAT4EU_VAT_NOT_VALIDATED', 'We were unable to validate the <em>VAT Number</em> that you entered.  Please re-enter the value or <a href="' . zen_href_link(FILENAME_CONTACT_US, '', 'SSL') . '">contact us</a> for assistance.');
define('VAT4EU_APPROVAL_PENDING', 'Once we validate the <em>VAT Number</em> that you entered, your qualifying orders will automatically receive a <em>VAT Exemption</em>.  Please <a href="' . zen_href_link(FILENAME_CONTACT_US, '', 'SSL') . '">contact us</a> if you have any questions.');

define('VAT4EU_MESSAGE_YOUR_VAT_REFUND', 'Your order qualifies for a <em>VAT Refund</em>, in the amount of %s.');     //- The $s is the formatted monetary amount of the refund

define('VAT4EU_TEXT_VAT_REFUND', 'VAT Refund:');