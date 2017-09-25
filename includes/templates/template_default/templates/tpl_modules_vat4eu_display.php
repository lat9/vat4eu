<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
if (defined('VAT4EU_ENABLED') && VAT4EU_ENABLED == 'true') {
?>
<div class="clearBoth"></div>
<label class="inputLabel" for="vat-number"><?php echo VAT4EU_ENTRY_VAT_NUMBER; ?></label>
<?php echo zen_draw_input_field('vat_number', $vat_number, zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', '40') . ' id="vat-number"'); ?>
<div class="clearBoth"></div>
<?php
}
