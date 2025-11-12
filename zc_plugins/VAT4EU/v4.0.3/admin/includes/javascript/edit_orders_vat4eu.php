<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9
// Copyright (c) 2024 Vinos de Frutas Tropicales
//
// Last modified v4.0.0 (new)
//
use Zencart\Plugins\Catalog\VAT4EU\VatValidation;
?>
<script>
$(function() {
    $('#vat-validation').on('click', function(e) {
        if (this.checked) {
            this.value = <?= VatValidation::VAT_ADMIN_OVERRIDE ?>;
        } else {
            this.value = $('#current-vat-validated').val();
        }
    });
});
</script>
