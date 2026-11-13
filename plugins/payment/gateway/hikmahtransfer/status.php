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
 * Learner page: the state of one transfer request, or the list of the learner's requests.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_hikmahtransfer\request;

$id = optional_param('id', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    redirect(get_login_url());
}
$systemcontext = context_system::instance();

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/payment/gateway/hikmahtransfer/status.php', $id ? ['id' => $id] : []));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('statustitle', 'paygw_hikmahtransfer'));
$PAGE->set_heading(get_string('statustitle', 'paygw_hikmahtransfer'));
$PAGE->add_body_class('hk-paypage');

$dateformat = get_string('strftimedatetimeshort', 'langconfig');

if (!$id) {
    $rows = [];
    $records = $DB->get_records_select(
        request::TABLE,
        'userid = :userid AND status <> :draft',
        ['userid' => $USER->id, 'draft' => request::STATUS_DRAFT],
        'id DESC'
    );
    foreach ($records as $record) {
        $rows[] = [
            'item' => request::describe($record),
            'reference' => $record->reference,
            'amount' => payment_helper::get_cost_as_string((float) $record->amount, $record->currency),
            'submitted' => $record->timesubmitted ? userdate($record->timesubmitted, $dateformat) : '',
            'status' => request::status_display($record->status),
            'url' => (new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $record->id]))->out(false),
        ];
    }
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('paygw_hikmahtransfer/status_list', [
        'rows' => $rows,
        'hasrows' => !empty($rows),
        'catalogurl' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

$request = request::get($id);
// Learners see their own requests only; reviewers use the review queue.
if ((int) $request->userid !== (int) $USER->id) {
    require_capability('paygw/hikmahtransfer:review', $systemcontext);
}

$history = [];
foreach (request::history((int) $request->id) as $row) {
    if ($row->tostatus === request::STATUS_DRAFT) {
        continue;
    }
    $history[] = [
        'status' => request::status_display($row->tostatus),
        'time' => userdate($row->timecreated, $dateformat),
        'remarks' => (string) $row->remarks,
    ];
}

$payurl = new moodle_url('/payment/gateway/hikmahtransfer/pay.php', [
    'component' => $request->component,
    'paymentarea' => $request->paymentarea,
    'itemid' => $request->itemid,
]);
$successurl = null;
try {
    $successurl = payment_helper::get_success_url($request->component, $request->paymentarea, (int) $request->itemid);
} catch (\Throwable $e) {
    $successurl = new moodle_url('/my/');
}

$status = $request->status;
$context = [
    'item' => request::describe($request),
    'reference' => $request->reference,
    'amount' => payment_helper::get_cost_as_string((float) $request->amount, $request->currency),
    'method' => $request->method ? get_string('method_' . $request->method, 'paygw_hikmahtransfer') : '',
    'txnid' => (string) $request->txnid,
    'submitted' => $request->timesubmitted ? userdate($request->timesubmitted, $dateformat) : '',
    'status' => request::status_display($status),
    'ispending' => $status === request::STATUS_PENDING,
    'isapproved' => $status === request::STATUS_APPROVED,
    'isrejected' => $status === request::STATUS_REJECTED,
    'isresubmit' => $status === request::STATUS_RESUBMIT,
    'isdraft' => $status === request::STATUS_DRAFT,
    'remarks' => in_array($status, [request::STATUS_REJECTED, request::STATUS_RESUBMIT], true)
        ? request::latest_remarks((int) $request->id) : '',
    'history' => $history,
    'payurl' => $payurl->out(false),
    'successurl' => $successurl->out(false),
    'listurl' => (new moodle_url('/payment/gateway/hikmahtransfer/status.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('paygw_hikmahtransfer/status', $context);
echo $OUTPUT->footer();
