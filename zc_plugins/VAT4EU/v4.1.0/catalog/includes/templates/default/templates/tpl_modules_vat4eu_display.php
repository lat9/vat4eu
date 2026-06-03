<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2024 Vinos de Frutas Tropicales
//
// Last updated: v4.0.0
//
// Note: Now loaded via VAT4EU's observer at the end of page-loads that enable
// a logged-in customer to modify address-related information. The $vat_number, $form_field_disabled,
// $vat4eu_formats_define and $form_field_message are 'in-scope' and set by the observer.
//
if ($vat4eu_is_bootstrap === true) {
    $modal_link =
        ' <a href="javascript:void();" data-toggle="modal" data-target="#vat4eu-modal">' .
            '<i class="fas fa-solid fa-circle-info"></i>' .
        '</a>';
} else {
    $modal_link =
        ' <a href="javascript:void(0);" onclick="window.vat4eumodal.showModal();">' .
            '<i class="fas fa-solid fa-circle-info"></i>' .
        '</a>';
/*
        ' <a href="javascript:openModal(\'vat4eu-modal\');">' .
            '<i class="fas fa-solid fa-circle-info"></i>' .
        '</a>';
*/
}
?>
<div class="clearBoth"></div>
<label class="inputLabel" for="vat-number"><?= VAT4EU_ENTRY_VAT_NUMBER . $modal_link ?></label>
<?= zen_draw_input_field(
        'vat_number',
        $vat_number,
        zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_vat_number', '40') . ' id="vat-number"' . $form_field_disabled
    ) .
    ' ' . $form_field_message
?>
<div class="clearBoth p-2"></div>
<?php
// -----
// The modal VAT4EU format is rendered differently for Bootstrap than
// templates based on zc200+ responsive_classic.
//
if ($vat4eu_is_bootstrap === true) {
?>
<div class="modal" id="vat4eu-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= VAT4EU_MODAL_TITLE ?></h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php require $vat4eu_formats_define; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php
    return;
}
/*
?>
<div id="vat4eu-modal" class="imgmodal">
    <div class="imgmodal-content">
        <span onclick="closeModal('vat4eu-modal');">
            <div class="imgmodal-close">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <div class="center">
                <?php require $vat4eu_formats_define; ?>
            </div>
        </span>
    </div>
</div>
*/
?>
<dialog id="vat4eumodal">
    <div id="vat4eumodal-header">
        <h4 class="back"><?= VAT4EU_MODAL_TITLE ?></h4>
        <button class="forward" type="button" onclick="window.vat4eumodal.close();" aria-label="close">&times;</button>
        <div class="clearBoth"></div>
    </div>
 
    <?php require $vat4eu_formats_define; ?>
    <button type="button" onclick="window.vat4eumodal.close();" aria-label="close">Close</button>
</dialog>