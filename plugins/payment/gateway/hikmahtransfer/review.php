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
 * Reviewer queue and decision page (PRD ENR-03, ENR-04). Only users with
 * paygw/hikmahtransfer:review get in; every decision is recorded with who, when and why.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_hikmahtransfer\receipt;
use paygw_hikmahtransfer\request;

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$status = optional_param('status', request::STATUS_PENDING, PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$method = optional_param('method', '', PARAM_ALPHA);
$from = optional_param('from', '', PARAM_RAW_TRIMMED);
$to = optional_param('to', '', PARAM_RAW_TRIMMED);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

require_login(null, false);
$systemcontext = context_system::instance();
require_capability('paygw/hikmahtransfer:review', $systemcontext);

$baseurl = new moodle_url('/payment/gateway/hikmahtransfer/review.php');
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url($baseurl, $id ? ['id' => $id] : []));
$PAGE->set_pagelayout('standard');
$PAGE->set_primary_active_tab('hkpaymentreviews');
$PAGE->set_title(get_string('reviewtitle', 'paygw_hikmahtransfer'));
$PAGE->set_heading(get_string('reviewtitle', 'paygw_hikmahtransfer'));
$PAGE->add_body_class('hk-paypage');

$dateformat = get_string('strftimedatetime', 'langconfig');

// Decision.
if ($id && $action !== '' && data_submitted()) {
    require_sesskey();
    $remarks = trim(optional_param('remarks', '', PARAM_TEXT));
    $detailurl = new moodle_url($baseurl, ['id' => $id]);
    try {
        switch ($action) {
            case 'approve':
                $done = request::approve($id, (int) $USER->id, $remarks);
                $message = 'decision_approved';
                break;
            case 'reject':
                $done = request::reject($id, (int) $USER->id, $remarks);
                $message = 'decision_rejected';
                break;
            case 'resubmit':
                $done = request::request_resubmission($id, (int) $USER->id, $remarks);
                $message = 'decision_resubmit';
                break;
            default:
                throw new moodle_exception('invalidparameter', 'debug');
        }
    } catch (moodle_exception $e) {
        redirect($detailurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($done) {
        redirect($baseurl, get_string($message, 'paygw_hikmahtransfer'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    // Somebody else decided first: nothing was changed.
    redirect(
        $detailurl,
        get_string('decision_stale', 'paygw_hikmahtransfer'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Detail.
if ($id) {
    $request = request::get($id);
    $learner = core_user::get_user($request->userid);

    $history = [];
    foreach (request::history((int) $request->id) as $row) {
        $actor = core_user::get_user($row->userid);
        $history[] = [
            'status' => request::status_display($row->tostatus),
            'time' => userdate($row->timecreated, $dateformat),
            'datetime' => date('c', (int) $row->timecreated),
            'actor' => $actor ? fullname($actor) : '',
            'remarks' => (string) $row->remarks,
        ];
    }

    $receipt = null;
    $canview = has_capability('paygw/hikmahtransfer:viewreceipt', $systemcontext);
    if ($canview && ($file = receipt::get_file((int) $request->id))) {
        $isimage = strpos($file->get_mimetype(), 'image/') === 0;
        $receipt = [
            'url' => receipt::url($file)->out(false),
            'downloadurl' => receipt::url($file, true)->out(false),
            'filename' => $file->get_filename(),
            'size' => display_size($file->get_filesize()),
            'isimage' => $isimage,
            'ispdf' => !$isimage,
        ];
    }

    $paid = $request->paidamount !== null ? (float) $request->paidamount : null;
    $context = [
        'id' => $request->id,
        'backurl' => $baseurl->out(false),
        'actionurl' => (new moodle_url($baseurl, ['id' => $request->id]))->out(false),
        'sesskey' => sesskey(),
        'reference' => $request->reference,
        'item' => request::describe($request),
        'learner' => $learner ? fullname($learner) : '',
        'learneremail' => $learner ? $learner->email : '',
        'amount' => payment_helper::get_cost_as_string((float) $request->amount, $request->currency),
        'paidamount' => $paid !== null ? payment_helper::get_cost_as_string($paid, $request->currency) : '',
        'amountmismatch' => $paid !== null && abs($paid - (float) $request->amount) > 0.004,
        'method' => $request->method ? get_string('method_' . $request->method, 'paygw_hikmahtransfer') : '',
        'txnid' => (string) $request->txnid,
        'paiddate' => $request->paiddate ? userdate($request->paiddate, get_string('strftimedate', 'langconfig')) : '',
        'submitted' => $request->timesubmitted ? userdate($request->timesubmitted, $dateformat) : '',
        'status' => request::status_display($request->status),
        'scanclean' => $request->scanstatus === 'clean',
        'scanskipped' => $request->scanstatus === 'notscanned',
        'receipt' => $receipt,
        'noreceipt' => !$receipt,
        'receiptdeleted' => (bool) $request->receiptdeleted,
        'canviewreceipt' => $canview,
        'candecide' => $request->status === request::STATUS_PENDING,
        'history' => $history,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('paygw_hikmahtransfer/review_detail', $context);
    echo $OUTPUT->footer();
    exit;
}

// Queue.
$statuses = [request::STATUS_PENDING, request::STATUS_RESUBMIT, request::STATUS_APPROVED, request::STATUS_REJECTED];
$where = ['r.status <> :draft'];
$sqlparams = ['draft' => request::STATUS_DRAFT];
if ($status !== 'all' && in_array($status, $statuses, true)) {
    $where[] = 'r.status = :status';
    $sqlparams['status'] = $status;
} else {
    $status = 'all';
}
if ($courseid) {
    $where[] = 'r.courseid = :courseid';
    $sqlparams['courseid'] = $courseid;
}
if (in_array($method, request::METHODS, true)) {
    $where[] = 'r.method = :method';
    $sqlparams['method'] = $method;
} else {
    $method = '';
}
$fromts = $from !== '' ? strtotime($from . ' 00:00:00') : false;
$tots = $to !== '' ? strtotime($to . ' 23:59:59') : false;
if ($fromts) {
    $where[] = 'r.timesubmitted >= :fromts';
    $sqlparams['fromts'] = $fromts;
} else {
    $from = '';
}
if ($tots) {
    $where[] = 'r.timesubmitted <= :tots';
    $sqlparams['tots'] = $tots;
} else {
    $to = '';
}
$wheresql = implode(' AND ', $where);

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {" . request::TABLE . "} r WHERE $wheresql", $sqlparams);
$records = $DB->get_records_sql(
    "SELECT r.* FROM {" . request::TABLE . "} r WHERE $wheresql ORDER BY r.timesubmitted ASC, r.id ASC",
    $sqlparams,
    $page * $perpage,
    $perpage
);

$rows = [];
foreach ($records as $record) {
    $learner = core_user::get_user($record->userid);
    $rows[] = [
        'reference' => $record->reference,
        'learner' => $learner ? fullname($learner) : '',
        'item' => request::describe($record),
        'amount' => payment_helper::get_cost_as_string((float) $record->amount, $record->currency),
        'method' => $record->method ? get_string('method_' . $record->method, 'paygw_hikmahtransfer') : '',
        'txnid' => (string) $record->txnid,
        'submitted' => $record->timesubmitted ? userdate($record->timesubmitted, $dateformat) : '',
        'status' => request::status_display($record->status),
        'url' => (new moodle_url($baseurl, ['id' => $record->id]))->out(false),
    ];
}

$courseoptions = [];
$courseids = $DB->get_fieldset_sql("SELECT DISTINCT courseid FROM {" . request::TABLE . "} WHERE courseid > 0");
if ($courseids) {
    foreach ($DB->get_records_list('course', 'id', $courseids, 'fullname', 'id, fullname') as $course) {
        $courseoptions[] = [
            'value' => $course->id,
            'label' => format_string($course->fullname),
            'selected' => (int) $course->id === $courseid,
        ];
    }
}
$statusoptions = [['value' => 'all', 'label' => get_string('filter_all', 'paygw_hikmahtransfer'), 'selected' => $status === 'all']];
foreach ($statuses as $s) {
    $statusoptions[] = [
        'value' => $s,
        'label' => get_string('status_' . $s, 'paygw_hikmahtransfer'),
        'selected' => $status === $s,
    ];
}
$methodoptions = [];
foreach (request::METHODS as $m) {
    $methodoptions[] = [
        'value' => $m,
        'label' => get_string('method_' . $m, 'paygw_hikmahtransfer'),
        'selected' => $method === $m,
    ];
}

$filterparams = array_filter(['status' => $status, 'courseid' => $courseid, 'method' => $method, 'from' => $from, 'to' => $to]);
$context = [
    'action' => $baseurl->out(false),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'total' => $total,
    'pending' => request::count_pending(),
    'statuses' => $statusoptions,
    'courses' => $courseoptions,
    'methods' => $methodoptions,
    'from' => $from,
    'to' => $to,
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($baseurl, $filterparams)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('paygw_hikmahtransfer/review_queue', $context);
echo $OUTPUT->footer();
