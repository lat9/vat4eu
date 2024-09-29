<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
// Note: Now loaded via VAT4EU's observer at the end of page-loads that enable
// a logged-in customer to modify address-related information. The $vat_number, $form_field_disabled
// and $form_field_message are 'in-scope' and set by the observer.
//
$popup_link = '<a href="javascript:popupVat4EuWindow(\'' . zen_href_link(FILENAME_POPUP_VAT4EU_FORMATS) . '\')">' . VAT4EU_WHATS_THIS . '</a>';
$popup_link = '';
/*
?>
<script>
function popupVat4EuWindow(url) {
    window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=500,height=320,screenX=150,screenY=150,top=150,left=150')
}
</script>
*/
?>
<div class="clearBoth"></div>
<label class="inputLabel" for="vat-number"><?= VAT4EU_ENTRY_VAT_NUMBER ?></label>
<?= zen_draw_input_field('vat_number', $vat_number, zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', '40') . ' id="vat-number"' . $form_field_disabled) . $popup_link . ' ' . $form_field_message ?>
<div class="clearBoth p-2"></div>
