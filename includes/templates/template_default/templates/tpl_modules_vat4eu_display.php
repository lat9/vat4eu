<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v3.2.0
//
// If not enabled or in an OPC guest-checkout, don't offer the entry of the VAT number.
//
if (!defined('VAT4EU_ENABLED') || VAT4EU_ENABLED !== 'true' || zen_in_guest_checkout()) {
    return;
}

// -----
// Ditto for the integration with OPC, where the to-be-gathered addresses aren't for the billing ones.
//
global $which;
if (isset($which) && $which !== 'bill') {
    return;
}

global $zcObserverVatForEuCountries;     //- global needed for OPC AJAX actions
$vat_number = $zcObserverVatForEuCountries->getVatNumberForFormEntry($current_page_base ?? 'checkout_one');

$popup_link = '<a href="javascript:popupVat4EuWindow(\'' . zen_href_link(FILENAME_POPUP_VAT4EU_FORMATS) . '\')">' . VAT4EU_WHATS_THIS . '</a>';
?>
<script>
function popupVat4EuWindow(url) {
    window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=500,height=320,screenX=150,screenY=150,top=150,left=150')
}
</script>
<div class="clearBoth"></div>
<label class="inputLabel" for="vat-number"><?= VAT4EU_ENTRY_VAT_NUMBER ?></label>
<?= zen_draw_input_field('vat_number', (!empty($vat_number)) ? zen_output_string_protected($vat_number) : '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', '40') . ' id="vat-number"') . $popup_link ?>
<div class="clearBoth p-2"></div>

