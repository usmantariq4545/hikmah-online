<?php
// 070 — Enrolment methods (ENR-01, ENR-02): manual, self (free, instant), approval (free, reviewed),
// cohort sync, and payment-based enrolment through the Hikmah transfer gateway.
require(__DIR__ . '/lib.php');

global $DB;

// Enabled enrolment plugins, in display order. Guest access stays off.
$wanted = ['manual', 'self', 'apply', 'fee', 'cohort'];
$available = array_keys(core_component::get_plugin_list('enrol'));
$wanted = array_values(array_intersect($wanted, $available));
hk_set('enrol_plugins_enabled', implode(',', $wanted));
foreach (array_diff(['apply'], $available) as $missing) {
    cli_writeln("  note: enrol_$missing is not installed");
}

// Self enrolment: not added to new courses automatically; administrators choose per course.
hk_set('defaultenrol', 0, 'enrol_self');
hk_set('sendcoursewelcomemessage', 1, 'enrol_self');
hk_set('defaultenrol', 1, 'enrol_manual');

// Payment gateways: only the Hikmah transfer gateway is on.
$gateways = core_component::get_plugin_list('paygw');
foreach (array_keys($gateways) as $gateway) {
    $shouldenable = $gateway === 'hikmahtransfer';
    $isenabled = array_key_exists($gateway, \core\plugininfo\paygw::get_enabled_plugins() ?? []);
    if ($shouldenable !== $isenabled) {
        \core\plugininfo\paygw::enable_plugin($gateway, (int) $shouldenable);
        hk_changed("payment gateway $gateway " . ($shouldenable ? 'enabled' : 'disabled'));
    }
}
hk_set('currency', 'PKR', 'enrol_fee');
hk_set('expiredaction', ENROL_EXT_REMOVED_SUSPENDNOROLES, 'enrol_fee');

// One payment account for Hikmah, with the transfer details from the environment.
$accountname = 'Hikmah Institute';
$system = context_system::instance();
$accountid = (int) $DB->get_field('payment_accounts', 'id', ['name' => $accountname, 'archived' => 0]);
if (!$accountid) {
    $account = \core_payment\helper::save_payment_account((object) [
        'name' => $accountname,
        'idnumber' => 'hikmah',
        'contextid' => $system->id,
        'enabled' => 1,
    ]);
    $accountid = (int) $account->get('id');
    hk_changed('payment account created');
}

$config = [
    'banktitle' => (string) hk_env('HIKMAH_BANK_TITLE', ''),
    'bankname' => (string) hk_env('HIKMAH_BANK_NAME', ''),
    'bankiban' => (string) hk_env('HIKMAH_BANK_IBAN', ''),
    'jazzcash' => (string) hk_env('HIKMAH_JAZZCASH', ''),
    'easypaisa' => (string) hk_env('HIKMAH_EASYPAISA', ''),
    'raast' => (string) hk_env('HIKMAH_RAAST', ''),
    'instructions' => (string) hk_env('HIKMAH_TRANSFER_NOTE', ''),
];
$hasmethod = (bool) \paygw_hikmahtransfer\gateway::enabled_methods($config);
if (!$hasmethod) {
    cli_writeln('  note: no transfer account details in the environment (HIKMAH_BANK_IBAN, HIKMAH_JAZZCASH, …); ' .
        'paid enrolment stays unavailable until they are set');
}
$encoded = json_encode($config);
$gatewayrecord = $DB->get_record('payment_gateways', ['accountid' => $accountid, 'gateway' => 'hikmahtransfer']);
if (!$gatewayrecord || $gatewayrecord->config !== $encoded || (int) $gatewayrecord->enabled !== (int) $hasmethod) {
    \core_payment\helper::save_payment_gateway((object) [
        'accountid' => $accountid,
        'gateway' => 'hikmahtransfer',
        'enabled' => (int) $hasmethod,
        'config' => $encoded,
    ]);
    hk_changed('transfer gateway configured on the payment account');
}

hk_done(__FILE__);
