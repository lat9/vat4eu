<?php
// -----
// Part of the VAT4EU plugin by Cindy Merkin a.k.a. lat9 (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017 Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
} 
$autoLoadConfig[200][] = array(
    'autoType'  => 'init_script',
    'loadFile'  => 'init_vat4eu_admin.php'
);
$autoLoadConfig[200][] = array(
    'autoType'  => 'class',
    'loadFile'  => 'observers/Vat4EuAdminObserver.php',
    'classPath' => DIR_WS_CLASSES
);
$autoLoadConfig[200][] = array(
    'autoType'   => 'classInstantiate',
    'className'  => 'Vat4EuAdminObserver',
    'objectName' => 'vat4EuAdmin'
);