<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
$define = [
    'VAT4EU_GB_COUNTRY_REMOVED' => 'The country \'GB\' has been removed from the VAT4EU <em>EU Countries</em> list.',

    'BOX_CONFIG_VAT4EU' => 'VAT for EU Countries',

    // -----
    // These two definitions are used in different spots.
    //
    // 1) VAT4EU_ENTRY_VAT_NUMBER is used during VAT Number "gathering" and should not be an empty string.
    // 2) VAT4EU_DISPLAY_VAT_NUMBER is used when formatting an address-block with a previously-entered VAT Number.
    //    If you don't want to precede the actual VAT Number with that text, just set the value to ''; otherwise,
    //    remember to keep the final space so that there's separation from the text and the actual VAT Number!
    //
    'VAT4EU_ENTRY_VAT_NUMBER' => 'VAT Number:',
    'VAT4EU_DISPLAY_VAT_NUMBER' => 'VAT Number: ',

    'VAT4EU_ENTRY_OVERRIDE_VALIDATION' => 'VAT Validation Override:',

    'VAT4EU_CUSTOMERS_HEADING' => 'VAT Number',

    'VAT4EU_ENTRY_VAT_MIN_ERROR' => '<span class="errorText">A <em>VAT Number</em> must be at least %u characters.</span>',
    'VAT4EU_ENTRY_VAT_PREFIX_INVALID' => '<span class="errorText">The <em>VAT Number</em> must begin with <b>%1$s</b>, since the address is in <em>%2$s</em>.</span>',
    'VAT4EU_ENTRY_VAT_INVALID_CHARS' => '<span class="errorText">Invalid characters were detected in the <em>VAT Number</em>.</span>',
    'VAT4EU_ENTRY_VAT_VIES_INVALID' => '<span class="errorText">The <em>VAT Number failed VIES validation.</span>',
    'VAT4EU_ENTRY_VAT_NOT_SUPPORTED' => '<span class="errorText">The country in this address (%s) does not support VAT numbers.</span>',
    'VAT4EU_ENTRY_VAT_REQUIRED' => '<span class="errorText">This field is required</span>',

    // -----
    // Used as in the title attribute when displaying VAT Numbers' status in Customers->Customers.
    //
    'VAT4EU_ADMIN_OVERRIDE' => 'Overridden by Admin',
    'VAT4EU_VIES_OK' => 'Validated by VIES',
    'VAT4EU_NOT_VALIDATED' => 'Requires Admin Validation',
    'VAT4EU_VIES_NOT_OK' => 'Found Invalid by VIES',

    // -----
    // Used as the title attribute for the heading sorts in Customers->Customers.
    //
    'VAT4EU_SORT_ASC' => 'Sort by Status, Asc',
    'VAT4EU_SORT_DESC' => 'Sort by Status, Desc',

    // -----
    // Issued during Edit Orders processing if the admin has changed either the VAT Number or its
    // validation status.
    //
    'VAT4EU_EO_CUSTOMER_UPDATE_REQUIRED' => 'The <em>VAT Number</em> or its status has changed <em>for this order only</em>! Edit the customer\'s information to make this change available for future purchases.',
];
return $define;
