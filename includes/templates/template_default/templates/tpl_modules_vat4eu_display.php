<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true') {
    $popup_link = '<a href="javascript:popupVat4EuWindow(\'' . zen_href_link(FILENAME_POPUP_VAT4EU_FORMATS) . '\')">' . VAT4EU_WHATS_THIS . '</a>'
?>
<script type="text/javascript">
function popupVat4EuWindow(url) {
    window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=500,height=320,screenX=150,screenY=150,top=150,left=150')
}
</script>
<div class="clearBoth"></div>
<label class="inputLabel" for="vat-number"><?php echo VAT4EU_ENTRY_VAT_NUMBER; ?></label>
<?php echo zen_draw_input_field('vat_number', $vat_number, zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', '40') . ' id="vat-number"') . $popup_link; ?>
<div class="clearBoth"></div>
<?php
}
