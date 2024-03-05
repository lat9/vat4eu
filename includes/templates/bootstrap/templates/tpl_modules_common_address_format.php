<?php
/**
 * Module Template
 * 
 * BOOTSTRAP v3.5.0
 *
 * Displays address-book details/selection
 *
 * @copyright Copyright 2003-2020 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: rbarbour zcadditions.com 2019 Jun 02 Modified in v1.5.7 $
 */
// -----
// Used by the address_book_process, checkout_payment_address and checkout_shipping_address pages
//
// -----
// The common address format 'expects' to see the default values to initially populate
// within a $entry object (as returned from a $db query).  If it's not available, "fake" that out to prevent
// PHP notices during that processing.
//
if (!isset($entry) || !is_object($entry)) {
    $entry = new stdClass();
    $entry->fields = [
        'entry_gender' => '',
        'entry_firstname' => '',
        'entry_lastname' => '',
        'entry_company' => '',
        'entry_street_address' => '',
        'entry_suburb' => '',
        'entry_city' => '',
        'entry_postcode' => '',
        'entry_country_id' => STORE_COUNTRY,
    ];
}

// -----
// Adding a (hidden) span to contain a 'stBreak' identifier, to keep the 'base' Zen Cart
// jscript_addr_pulldowns.php from throwing a javascript error for that missing 'id'.
//
?>
<span class="d-none" id="stBreak">&nbsp;</span>

<div class="required-info text-right"><?php echo FORM_REQUIRED_INFORMATION; ?></div>

<?php
if (ACCOUNT_GENDER === 'true') {
    if (isset($gender)) {
        $male = ($gender === 'm');
    } else {
        $male = ($entry->fields['entry_gender'] === 'm');
    }
    $female = !$male;
?>
<div class="custom-control custom-radio custom-control-inline">
<?php echo zen_draw_radio_field('gender', 'm', '1', 'id="gender-male"') . '<label class="custom-control-label radioButtonLabel" for="gender-male">' . MALE . '</label>'; ?>
</div>
<div class="custom-control custom-radio custom-control-inline">
<?php echo zen_draw_radio_field('gender', 'f', '', 'id="gender-female"') . '<label class="custom-control-label radioButtonLabel" for="gender-female">' . FEMALE . '</label>'; ?>
</div>

<div class="p-2"></div>

<?php
}
?>
<label class="inputLabel" for="firstname"><?php echo ENTRY_FIRST_NAME; ?></label>
<?php echo zen_draw_input_field('firstname', $entry->fields['entry_firstname'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' id="firstname" placeholder="' . ENTRY_FIRST_NAME_TEXT . '"' . ((int)ENTRY_FIRST_NAME_MIN_LENGTH > 0 ? ' required' : '')); ?>
<div class="p-2"></div>

<label class="inputLabel" for="lastname"><?php echo ENTRY_LAST_NAME; ?></label>
<?php echo zen_draw_input_field('lastname', $entry->fields['entry_lastname'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' id="lastname" placeholder="' . ENTRY_LAST_NAME_TEXT . '"' . ((int)ENTRY_LAST_NAME_MIN_LENGTH > 0 ? ' required' : '')); ?>
<div class="p-2"></div>

<?php
if (ACCOUNT_COMPANY === 'true') {
?>
<label class="inputLabel" for="company"><?php echo ENTRY_COMPANY; ?></label>
<?php echo zen_draw_input_field('company', $entry->fields['entry_company'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' id="company" autocomplete="organization" placeholder="' . ENTRY_COMPANY_TEXT . '"' . ((int)ENTRY_COMPANY_MIN_LENGTH !== 0 ? ' required' : '')); ?>
<div class="p-2"></div>
<?php
//-bof-vat4eu-lat9  *** 1 of 1 ***
    include $template->get_template_dir('tpl_modules_vat4eu_display.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_vat4eu_display.php'; 
//-eof-vat4eu-lat9  *** 1 of 1 ***
?>
<?php
}
?>

<label class="inputLabel" for="street-address"><?php echo ENTRY_STREET_ADDRESS; ?></label>
<?php echo zen_draw_input_field('street_address', $entry->fields['entry_street_address'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' id="street-address" placeholder="' . ENTRY_STREET_ADDRESS_TEXT . '"' . ((int)ENTRY_STREET_ADDRESS_MIN_LENGTH > 0 ? ' required' : '')); ?>
<div class="p-2"></div>
<?php
if (ACCOUNT_SUBURB === 'true') {
?>
<label class="inputLabel" for="suburb"><?php echo ENTRY_SUBURB; ?></label>
<?php echo zen_draw_input_field('suburb', $entry->fields['entry_suburb'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' id="suburb" autocomplete="address-line2" placeholder="' . ENTRY_SUBURB_TEXT . '"'); ?>
<div class="p-2"></div>
<?php
}
?>

<label class="inputLabel" for="city"><?php echo ENTRY_CITY; ?></label>
<?php echo zen_draw_input_field('city', $entry->fields['entry_city'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' id="city" placeholder="' . ENTRY_CITY_TEXT . '"' . ((int)ENTRY_CITY_MIN_LENGTH > 0 ? ' required' : '')); ?>
<div class="p-2"></div>

<?php
// -----
// zc158 introduces a common jQuery handler for the dropdown states' selection based
// on the country chosen.  When running on a zc158 (or later) 'core', use that handler instead
// of the legacy one provided by the Bootstrap template.
//
// When running a Zen Cart version prior to zc158, make sure that the 'stateLabel' field contains
// the required text.
//
$onchange_for_zc158 = ($flag_show_pulldown_states === true && zen_get_zcversion() >= '1.5.8') ? ' onchange="update_zone(this.form);"' : '';
$state_field_label = (zen_get_zcversion() >= '1.5.8') ? $state_field_label : ENTRY_STATE;
?>
<label class="inputLabel" for="country"><?php echo ENTRY_COUNTRY; ?></label>
<?php echo zen_get_country_list('zone_country_id', $entry->fields['entry_country_id'], 'id="country"' . $onchange_for_zc158); ?>
<div class="p-2"></div>

<?php
if (ACCOUNT_STATE === 'true') {
    if ($flag_show_pulldown_states === true) {
?>
<label class="inputLabel" for="stateZone" id="zoneLabel"><?php echo ENTRY_STATE; ?></label><?php if (!empty(ENTRY_STATE_TEXT)) echo '<span class="alert">' . ENTRY_STATE_TEXT . '</span>'; ?>

<?php
      echo zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($selected_country), $zone_id, 'id="stateZone"');
?>
<div class="clearfix"></div>
<?php
    }
?>

<label class="inputLabel" for="state" id="stateLabel"><?php echo $state_field_label; ?></label>
<?php
    echo zen_draw_input_field('state', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_state', '40') . ' id="state" class="form-control" placeholder="' . ENTRY_STATE_TEXT . '"');
    if ($flag_show_pulldown_states === false) {
        echo zen_draw_hidden_field('zone_id', $zone_name, ' ');
    }
}
?>
<div class="p-2"></div>

<label class="inputLabel" for="postcode"><?php echo ENTRY_POST_CODE; ?></label>
<?php echo zen_draw_input_field('postcode', $entry->fields['entry_postcode'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' id="postcode" placeholder="' . ENTRY_POST_CODE_TEXT . '"' . ((int)ENTRY_POSTCODE_MIN_LENGTH > 0 ? ' required' : '')); ?>
<div class="p-2"></div>

<?php
if ($current_page_base === FILENAME_ADDRESS_BOOK_PROCESS && (!isset($_GET['edit']) || (int)$_SESSION['customer_default_address_id'] !== (int)$_GET['edit'])) {
?>
<div class="custom-control custom-checkbox">
    <?php echo zen_draw_checkbox_field('primary', 'on', false, 'id="primary"') . ' <label class="custom-control-label" for="primary">' . SET_AS_PRIMARY . '</label>'; ?>
</div>
<?php
}
