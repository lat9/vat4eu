<?php
/**
 * Page Template
 *
 * BOOTSTRAP v3.4.0
 *
 * Loaded automatically by index.php?main_page=create_account.<br />
 * Displays Create Account form.
 *
 * @copyright Copyright 2003-2020 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte 2020 Nov 17 Modified in v1.5.7b $
 */
if ($messageStack->size('create_account') > 0) {
    echo $messageStack->output('create_account');
}
?>
<div class="required-info text-right"><?php echo FORM_REQUIRED_INFORMATION; ?></div>
<div class="card-columns">
<?php
if (DISPLAY_PRIVACY_CONDITIONS === 'true') {
?>
    <div id="privacyStatement-card" class="card mb-3">
        <h4 id="privacyStatement-card-header" class="card-header"><?php echo TABLE_HEADING_PRIVACY_CONDITIONS; ?></h4>
        <div id="privacyStatement-card-body" class="card-body p-3">
            <div id="privacyStatement-content" class="content"><?php echo TEXT_PRIVACY_CONDITIONS_DESCRIPTION;?></div>

            <div class="custom-control custom-checkbox">
                <?php echo
                    zen_draw_checkbox_field('privacy_conditions', '1', false, 'id="privacy"') . 
                    '<label class="custom-control-label" for="privacy">' . TEXT_PRIVACY_CONDITIONS_CONFIRM . '</label>'; ?>
            </div>
        </div>
    </div>
<?php
}

if (ACCOUNT_COMPANY === 'true') {
?>
    <div id="companyDetails-card" class="card mb-3">
        <h4 id="companyDetails-card-header" class="card-header"><?php echo CATEGORY_COMPANY; ?></h4>
        <div id="companyDetails-card-body" class="card-body p-3">
            <label class="inputLabel" for="company"><?php echo ENTRY_COMPANY; ?></label>
            <?php echo zen_draw_input_field('company', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' id="company" autocomplete="organization" placeholder="' . ENTRY_COMPANY_TEXT . '"' . ((int)ENTRY_COMPANY_MIN_LENGTH !== 0 ? ' required' : '')); ?>
<?php
//-bof-vat4eu-lat9  *** 1 of 1 ***
    include $template->get_template_dir('tpl_modules_vat4eu_display.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_vat4eu_display.php'; 
//-eof-vat4eu-lat9  *** 1 of 1 ***
?>
        </div>
    </div>
<?php
}
?>
    <div id="addressDetails-card" class="card mb-3">
        <h4 id="addressDetails-card-header" class="card-header"><?php echo TABLE_HEADING_ADDRESS_DETAILS; ?></h4>
        <div id="addressDetails-card-body" class="card-body p-3">
<?php
if (ACCOUNT_GENDER === 'true') {
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
            <?php echo zen_draw_input_field('firstname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' id="firstname" placeholder="' . ENTRY_FIRST_NAME_TEXT . '"' . ((int)ENTRY_FIRST_NAME_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>

            <label class="inputLabel" for="lastname"><?php echo ENTRY_LAST_NAME; ?></label>
            <?php echo zen_draw_input_field('lastname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' id="lastname" placeholder="' . ENTRY_LAST_NAME_TEXT . '"'. ((int)ENTRY_LAST_NAME_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>

            <label class="inputLabel" for="street-address"><?php echo ENTRY_STREET_ADDRESS; ?></label>
            <?php echo zen_draw_input_field('street_address', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' id="street-address" placeholder="' . ENTRY_STREET_ADDRESS_TEXT . '"'. ((int)ENTRY_STREET_ADDRESS_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>

            <?php echo zen_draw_input_field($antiSpamFieldName, '', ' size="40" id="CAAS" style="visibility:hidden; display:none;" autocomplete="off"'); ?>
<?php
if (ACCOUNT_SUBURB === 'true') {
?>
            <label class="inputLabel" for="suburb"><?php echo ENTRY_SUBURB; ?></label>
            <?php echo zen_draw_input_field('suburb', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' id="suburb" autocomplete="address-line2" placeholder="' . ENTRY_SUBURB_TEXT . '"'); ?>
            <div class="p-2"></div>
<?php
}
?>
            <label class="inputLabel" for="city"><?php echo ENTRY_CITY; ?></label>
            <?php echo zen_draw_input_field('city', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' id="city" placeholder="' . ENTRY_CITY_TEXT . '"'. ((int)ENTRY_CITY_MIN_LENGTH > 0 ? ' required' : '')); ?>
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
            <label class="inputLabel" for="country"><?php echo ENTRY_COUNTRY; ?></label><?php if (zen_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="alert">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?>
            <?php echo zen_get_country_list('zone_country_id', $selected_country, 'id="country"' . $onchange_for_zc158); ?>
            <div class="p-2"></div>
<?php
// -----
// Adding a (hidden) span to contain a 'stBreak' identifier, to keep the 'base' Zen Cart
// jscript_addr_pulldowns.php from throwing a javascript error for that missing 'id'.
//
?>
            <span class="d-none" id="stBreak">&nbsp;</span>
<?php
if (ACCOUNT_STATE === 'true') {
    if ($flag_show_pulldown_states === true) {
?>
            <label class="inputLabel" for="stateZone" id="zoneLabel"><?php echo ENTRY_STATE; ?></label><?php if (zen_not_null(ENTRY_STATE_TEXT)) echo '<span class="alert">' . ENTRY_STATE_TEXT . '</span>';?>
            <?php echo zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($selected_country), $zone_id, 'id="stateZone"'); ?>
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
            <?php echo zen_draw_input_field('postcode', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' id="postcode" placeholder="' . ENTRY_POST_CODE_TEXT . '"' . ((int)ENTRY_POSTCODE_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>
        </div>
    </div>

    <div id="contactDetails-card" class="card mb-3">
        <h4 id="contactDetails-card-header" class="card-header"><?php echo TABLE_HEADING_PHONE_FAX_DETAILS; ?></h4>
        <div id="contactDetails-card-body" class="card-body p-3">
            <label class="inputLabel" for="telephone"><?php echo ENTRY_TELEPHONE_NUMBER; ?></label>
            <?php echo zen_draw_input_field('telephone', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone', '40') . ' id="telephone" placeholder="' . ENTRY_TELEPHONE_NUMBER_TEXT . '"' . ((int)ENTRY_TELEPHONE_MIN_LENGTH > 0 ? ' required' : ''), 'tel'); ?>
<?php
if (ACCOUNT_FAX_NUMBER === 'true') {
?>
            <div class="p-2"></div>
            <label class="inputLabel" for="fax"><?php echo ENTRY_FAX_NUMBER; ?></label>
            <?php echo zen_draw_input_field('fax', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_fax', '32') . ' id="fax" placeholder="' . ENTRY_FAX_NUMBER_TEXT . '"', 'tel'); ?>
<?php
}
?>
        </div>
    </div>
<?php
if (ACCOUNT_DOB === 'true') {
?>
    <div id="verifyAge-card" class="card mb-3">
        <h4 id="verifyAge-card-header" class="card-header"><?php echo TABLE_HEADING_DATE_OF_BIRTH; ?></h4>
        <div id="verifyAge-card-body" class="card-body p-3">
            <label class="inputLabel" for="dob"><?php echo ENTRY_DATE_OF_BIRTH; ?></label>
            <?php echo zen_draw_input_field('dob','', zen_set_field_length(TABLE_CUSTOMERS, 'customers_dob', '20') . ' id="dob" placeholder="' . ENTRY_DATE_OF_BIRTH_TEXT . '"' . ((int)ENTRY_DOB_MIN_LENGTH != 0 ? ' required' : '')); ?>

        </div>
    </div>
<?php
}
?>
    <div id="loginDetails-card" class="card mb-3">
        <h4 id="loginDetails-card-header" class="card-header"><?php echo TABLE_HEADING_LOGIN_DETAILS; ?></h4>
        <div id="loginDetails-card-body" class="card-body p-3">
            <label class="inputLabel" for="email-address"><?php echo ENTRY_EMAIL_ADDRESS; ?></label>
            <?php echo zen_draw_input_field('email_address', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' id="email-address" placeholder="' . ENTRY_EMAIL_ADDRESS_TEXT . '"' . ((int)ENTRY_EMAIL_ADDRESS_MIN_LENGTH > 0 ? ' required' : ''), 'email'); ?>
            <div class="p-2"></div>
<?php
if ($display_nick_field === true) {
?>
            <label class="inputLabel" for="nickname"><?php echo ENTRY_NICK; ?></label>
            <?php echo zen_draw_input_field('nick','', zen_set_field_length(TABLE_CUSTOMERS, 'customers_nick', '32') . ' id="nickname" placeholder="' . ENTRY_NICK_TEXT . '"'); ?>
            <div class="p-2"></div>
<?php
}
?>
            <label class="inputLabel" for="password-new"><?php echo ENTRY_PASSWORD; ?></label>
            <?php echo zen_draw_password_field('password', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_password', '20') . ' id="password-new" autocomplete="off" placeholder="' . ENTRY_PASSWORD_TEXT . '"'. ((int)ENTRY_PASSWORD_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>

            <label class="inputLabel" for="password-confirm"><?php echo ENTRY_PASSWORD_CONFIRMATION; ?></label>
            <?php echo zen_draw_password_field('confirmation', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_password', '20') . ' id="password-confirm" autocomplete="off" placeholder="' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '"'. ((int)ENTRY_PASSWORD_MIN_LENGTH > 0 ? ' required' : '')); ?>
            <div class="p-2"></div>
        </div>
    </div>

    <div id="newsletterDetails-card" class="card mb-3">
        <h4 id="newsletterDetails-card-header" class="card-header"><?php echo ENTRY_EMAIL_PREFERENCE; ?></h4>
        <div id="newsletterDetails-card-body" class="card-body p-3">
<?php
if (ACCOUNT_NEWSLETTER_STATUS !== '0') {
?>
            <div class="custom-control custom-checkbox">
                <?php echo zen_draw_checkbox_field('newsletter', '1', $newsletter, 'id="newsletter-checkbox"') . '<label class="custom-control-label" for="newsletter-checkbox">' . ENTRY_NEWSLETTER . '</label>' . (zen_not_null(ENTRY_NEWSLETTER_TEXT) ? '<span class="alert">' . ENTRY_NEWSLETTER_TEXT . '</span>': ''); ?>
            </div>
<?php
}
?>
            <div class="custom-control custom-radio custom-control-inline">
                <?php echo zen_draw_radio_field('email_format', 'HTML', ($email_format === 'HTML'),'id="email-format-html"') . '<label class="custom-control-label" for="email-format-html">' . ENTRY_EMAIL_HTML_DISPLAY . '</label>'; ?> 
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <?php echo zen_draw_radio_field('email_format', 'TEXT', ($email_format === 'TEXT'), 'id="email-format-text"') . '<label class="custom-control-label" for="email-format-text">' . ENTRY_EMAIL_TEXT_DISPLAY . '</label>'; ?>
            </div>
        </div>
    </div>
<?php
if (CUSTOMERS_REFERRAL_STATUS === '2') {
?>
    <div id="ReferredToUs-card" class="card mb-3">
        <h4 id="ReferredToUs-card" class="card-header"><?php echo TABLE_HEADING_REFERRAL_DETAILS; ?></h4>
        <div id="ReferredToUs-card" class="card-body p-3">
            <label class="inputLabel" for="customers_referral"><?php echo ENTRY_CUSTOMERS_REFERRAL; ?></label>
            <?php echo zen_draw_input_field('customers_referral', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_referral', '15') . ' id="customers_referral"'); ?>
        </div>
    </div>
<?php
}
?>
</div>
