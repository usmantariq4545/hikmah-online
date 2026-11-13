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
 * Reconciliation preview for one session: computed status beside the register, overrides with a
 * reason, then Apply (PRD ATT-05, ATT-06). Nothing is written until Apply is pressed.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

use local_hikmahattendance\local\calculator;
use local_hikmahattendance\manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$link = manager::get_link($id);
$course = get_course($link->courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hikmahattendance:review', $context);
$canapply = has_capability('local/hikmahattendance:apply', $context);

$pageurl = new moodle_url('/local/hikmahattendance/reconcile.php', ['id' => $link->id]);
$indexurl = new moodle_url('/local/hikmahattendance/index.php', ['courseid' => $course->id]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('reconciletitle', 'local_hikmahattendance'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('hk-attpage');

$statuskeys = [calculator::STATUS_PRESENT, calculator::STATUS_PARTIAL, calculator::STATUS_ABSENT,
    calculator::STATUS_PENDING];

if ($action !== '' && data_submitted()) {
    require_sesskey();
    $notify = \core\output\notification::NOTIFY_SUCCESS;
    $message = '';

    if ($action === 'recompute') {
        manager::process_link((int) $link->id);
        $message = get_string('recomputed', 'local_hikmahattendance');
    }

    if ($action === 'save' || $action === 'apply') {
        // Overrides first, so Apply always writes what the instructor sees.
        $statuses = optional_param_array('status', [], PARAM_ALPHA);
        $reasons = optional_param_array('reason', [], PARAM_TEXT);
        $changed = 0;
        $missing = 0;
        foreach (manager::current_decisions((int) $link->id) as $decision) {
            $new = $statuses[$decision->id] ?? '';
            if (!in_array($new, $statuskeys, true) || $new === manager::effective_status($decision)) {
                continue;
            }
            $reason = trim((string) ($reasons[$decision->id] ?? ''));
            if ($reason === '') {
                $missing++;
                continue;
            }
            $changed += manager::override((int) $decision->id, $new, $reason, (int) $USER->id) ? 1 : 0;
        }
        if ($missing) {
            redirect($pageurl, get_string('error:reasonmissing', 'local_hikmahattendance', $missing), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        $message = get_string('overrides_saved', 'local_hikmahattendance', $changed);

        if ($action === 'apply') {
            require_capability('local/hikmahattendance:apply', $context);
            $result = manager::apply((int) $link->id, (int) $USER->id);
            $message = get_string('applied_result', 'local_hikmahattendance', (object) $result);
            if ($result['skippedmanual'] || $result['skippedpending']) {
                $notify = \core\output\notification::NOTIFY_WARNING;
            }
        }
    }
    redirect($pageurl, $message, null, $notify);
}

// ---- Build the preview -----------------------------------------------------------------------------
$session = $DB->get_record('attendance_sessions', ['id' => $link->sessionid]);
$registerstatuses = $session ? attendance_get_statuses($link->attendanceid, false, $session->statusset) : [];
$register = $session ? $DB->get_records('attendance_log', ['sessionid' => $session->id], '', 'studentid, statusid, remarks') : [];

$decisions = manager::current_decisions((int) $link->id);
$users = $decisions ? $DB->get_records_list('user', 'id', array_keys($decisions)) : [];
uasort($decisions, static function ($a, $b) use ($users) {
    return strcmp(fullname($users[$a->userid] ?? (object) ['firstname' => '', 'lastname' => '']),
        fullname($users[$b->userid] ?? (object) ['firstname' => '', 'lastname' => '']));
});

$fmt = static function (int $seconds): string {
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
};

$rows = [];
$counts = array_fill_keys($statuskeys, 0);
$differences = 0;
foreach ($decisions as $decision) {
    $user = $users[$decision->userid] ?? null;
    if (!$user) {
        continue;
    }
    $effective = manager::effective_status($decision);
    $counts[$effective]++;
    $evidence = $decision->evidence ? json_decode($decision->evidence, true) : [];

    $mark = '';
    $markdiffers = false;
    if (isset($register[$decision->userid]) && isset($registerstatuses[$register[$decision->userid]->statusid])) {
        $status = $registerstatuses[$register[$decision->userid]->statusid];
        $mark = $status->description;
        $expected = manager::acronym_for($effective);
        $markdiffers = $expected !== '' && $status->acronym !== $expected;
        $differences += $markdiffers ? 1 : 0;
    }

    $options = [];
    foreach ($statuskeys as $key) {
        $options[] = [
            'value' => $key,
            'label' => get_string('status_' . $key, 'local_hikmahattendance'),
            'selected' => $key === $effective,
        ];
    }
    $notes = [];
    foreach (array_merge($evidence['notes'] ?? [], $decision->note ? [$decision->note] : []) as $note) {
        $notes[] = get_string_manager()->string_exists('note_' . $note, 'local_hikmahattendance')
            ? get_string('note_' . $note, 'local_hikmahattendance') : $note;
    }

    $rows[] = [
        'id' => $decision->id,
        'name' => fullname($user),
        'attended' => $fmt((int) $decision->seconds),
        'total' => $fmt((int) $decision->denominator),
        'percent' => (int) $decision->percent,
        'threshold' => (int) $decision->threshold,
        'met' => (int) $decision->percent >= (int) $decision->threshold,
        'joined' => !empty($evidence['joined']),
        'computed' => manager::status_display($decision->computedstatus),
        'isoverridden' => !empty($decision->finalstatus),
        'reason' => (string) $decision->reason,
        'options' => $options,
        'register' => $mark,
        'registerdiffers' => $markdiffers,
        'state' => get_string('decisionstate_' . $decision->state, 'local_hikmahattendance'),
        'isapplied' => $decision->state === manager::DECISION_APPLIED,
        'notes' => implode(' · ', array_unique($notes)),
        'evidencegone' => $decision->evidence === null,
    ];
}

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$data = [
    'actionurl' => $pageurl->out(false),
    'sesskey' => sesskey(),
    'backurl' => $indexurl->out(false),
    'bbbname' => format_string((string) $DB->get_field('bigbluebuttonbn', 'name', ['id' => $link->bbbid])),
    'windowstart' => userdate($link->windowstart, $dateformat),
    'windowend' => userdate($link->windowend, get_string('strftimetime', 'langconfig')),
    'minutes' => (int) round(($link->windowend - $link->windowstart) / 60),
    'threshold' => manager::threshold_for($link),
    'state' => manager::link_state_display($link->state),
    'note' => (string) $link->note,
    'ismissing' => $link->state === manager::LINK_MISSING,
    'isunknown' => $link->state === manager::LINK_UNKNOWN,
    'iswaiting' => $link->state === manager::LINK_WAITING,
    'callbackcount' => (int) $link->callbackcount,
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'present' => $counts[calculator::STATUS_PRESENT],
    'partial' => $counts[calculator::STATUS_PARTIAL],
    'absent' => $counts[calculator::STATUS_ABSENT],
    'pending' => $counts[calculator::STATUS_PENDING],
    'differences' => $differences,
    'hasdifferences' => $differences > 0,
    'canapply' => $canapply,
    'autowrite' => manager::autowrite_enabled((int) $course->id),
    'registerurl' => $session
        ? (new moodle_url('/mod/attendance/take.php', [
            'id' => get_coursemodule_from_instance('attendance', $link->attendanceid, $course->id)->id,
            'sessionid' => $session->id,
            'grouptype' => 0,
        ]))->out(false) : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_hikmahattendance/reconcile', $data);
echo $OUTPUT->footer();
