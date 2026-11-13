<?php
// 030 — Email self-registration and Google sign-in (AUTH-01, AUTH-02).
require(__DIR__ . '/lib.php');

global $DB;

// Email-based self-registration with confirmation; one account per email address.
hk_set('auth', 'email,oauth2');
hk_set('registerauth', 'email');
hk_set('authpreventaccountcreation', 0);
hk_set('allowaccountssameemail', 0);
hk_set('authloginviaemail', 1);
hk_set('showloginform', 1);
hk_set('loginpageautofocus', 0);
hk_set('recaptchapublickey', hk_env('RECAPTCHA_SITE_KEY', ''));
hk_set('recaptchaprivatekey', hk_env('RECAPTCHA_SECRET_KEY', ''), null, true);

// Google OAuth 2. Redirect URI for the Google Cloud client: <wwwroot>/admin/oauth2callback.php
$clientid = hk_env('GOOGLE_OAUTH_CLIENT_ID');
$clientsecret = hk_env('GOOGLE_OAUTH_CLIENT_SECRET');
$issuer = null;
foreach (\core\oauth2\api::get_all_issuers() as $candidate) {
    if ($candidate->get('servicetype') === 'google' || $candidate->get('name') === 'Google') {
        $issuer = $candidate;
        break;
    }
}

if ($clientid && $clientsecret) {
    if (!$issuer) {
        $issuer = \core\oauth2\api::create_standard_issuer('google');
        hk_changed('Google OAuth 2 service created');
    }
    $data = $issuer->to_record();
    $wanted = [
        'clientid' => $clientid,
        'clientsecret' => $clientsecret,
        'enabled' => 1,
        'showonloginpage' => \core\oauth2\issuer::EVERYWHERE,
        // Linking to an existing account needs email confirmation, which prevents duplicates
        // and account takeover through an unverified address.
        'requireconfirmation' => 1,
    ];
    $dirty = false;
    foreach ($wanted as $key => $value) {
        if ((string) $data->{$key} !== (string) $value) {
            $data->{$key} = $value;
            $dirty = true;
        }
    }
    if ($dirty) {
        \core\oauth2\api::update_issuer($data);
        hk_changed('Google OAuth 2 service configured');
    }
} else {
    cli_writeln('  note: GOOGLE_OAUTH_CLIENT_ID / GOOGLE_OAUTH_CLIENT_SECRET not set; Google sign-in is not offered');
    if ($issuer && $issuer->get('enabled')) {
        $data = $issuer->to_record();
        $data->enabled = 0;
        \core\oauth2\api::update_issuer($data);
        hk_changed('Google OAuth 2 service disabled (no credentials)');
    }
}

hk_done(__FILE__);
