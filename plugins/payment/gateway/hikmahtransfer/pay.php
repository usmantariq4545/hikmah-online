<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Learner page: transfer instructions, unique payment reference and the receipt form
 * (PRD ENR-02). Nothing is unlocked here; access follows only from a reviewer's approval.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_hikmahtransfer\gateway;
use paygw_hikmahtransfer\request;

$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);

$params = ['component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid];
$pageurl = new moodle_url('/payment/gateway/hikmahtransfer/pay.php', $params);

require_login(null, false);
if (isguestuser()) {
    redirect(get_login_url());
}
$systemcontext = context_system::instance();
require_capability('paygw/hikmahtransfer:submit', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('paytitle', 'paygw_hikmahtransfer'));
$PAGE->set_heading(get_string('paytitle', 'paygw_hikmahtransfer'));
$PAGE->add_body_class('hk-paypage');

// Throws when the gateway is not enabled for the payment account of this item.
$config = payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, request::GATEWAY);
$methods = gateway::enabled_methods($config);
if (!$methods) {
    throw new moodle_exception('error:nomethods', 'paygw_hikmahtransfer');
}
$successurl = payment_helper::get_success_url($component, $paymentarea, $itemid);

if (request::has_approved($component, $paymentarea, $itemid, (int) $USER->id)) {
    redirect($successurl, get_string('alreadypaid', 'paygw_hikmahtransfer'), null, \core\output\notification::NOTIFY_INFO);
}

$request = request::get_or_create_draft($component, $paymentarea, $itemid, (int) $USER->id);
$statusurl = new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $request->id]);
if ($request->status === request::STATUS_PENDING) {
    redirect($statusurl);
}

$form = new \paygw_hikmahtransfer\form\submission($pageurl->out(false), $params + [
    'methods' => $methods,
    'amount' => (float) $request->amount,
    'currency' => $request->currency,
]);

if ($form->is_cancelled()) {
    redirect($request->courseid
        ? new moodle_url('/theme/hikmah/course.php', ['id' => $request->courseid])
        : new moodle_url('/'));
}

$error = '';
if ($data = $form->get_data()) {
    try {
        request::submit((int) $request->id, $data, (int) $data->receipt, (int) $USER->id);
        redirect($statusurl, get_string('submitted_ok', 'paygw_hikmahtransfer'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        // Invalid or infected receipt: nothing was stored and the request did not move.
        $error = $e->getMessage();
    }
}

if ($request->status === request::STATUS_RESUBMIT && !$form->is_submitted()) {
    $form->set_data([
        'method' => $request->method,
        'txnid' => $request->txnid,
        'paidamount' => $request->paidamount,
        'paiddate' => $request->paiddate,
    ]);
}

$accounts = [];
$add = static function (string $method, array $rows) use (&$accounts, $methods): void {
    if (!in_array($method, $methods, true)) {
        return;
    }
    $lines = [];
    foreach ($rows as $label => $value) {
        if (trim((string) $value) !== '') {
            $lines[] = ['label' => get_string($label, 'paygw_hikmahtransfer'), 'value' => trim((string) $value)];
        }
    }
    $accounts[] = ['name' => get_string('method_' . $method, 'paygw_hikmahtransfer'), 'lines' => $lines, 'key' => $method];
};
$add('bank', [
    'config_banktitle' => $config['banktitle'] ?? '',
    'config_bankname' => $config['bankname'] ?? '',
    'config_bankiban' => $config['bankiban'] ?? '',
]);
$add('jazzcash', ['label_number' => $config['jazzcash'] ?? '']);
$add('easypaisa', ['label_number' => $config['easypaisa'] ?? '']);
$add('raast', ['label_raastid' => $config['raast'] ?? '']);

$context = [
    'item' => request::describe($request),
    'amount' => payment_helper::get_cost_as_string((float) $request->amount, $request->currency),
    'reference' => $request->reference,
    'accounts' => $accounts,
    'instructions' => trim((string) ($config['instructions'] ?? '')),
    'resubmit' => $request->status === request::STATUS_RESUBMIT,
    'remarks' => $request->status === request::STATUS_RESUBMIT ? request::latest_remarks((int) $request->id) : '',
    'error' => $error,
    'form' => $form->render(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('paygw_hikmahtransfer/pay', $context);
echo $OUTPUT->footer();
