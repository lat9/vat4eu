<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
$_SESSION['navigation']->remove_current_page();
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');
$define_page = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_POPUP_VAT4EU_FORMATS, 'false');