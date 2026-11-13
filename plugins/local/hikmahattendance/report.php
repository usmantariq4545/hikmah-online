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
 * Reconciliation report: evidence beside the final register mark, with CSV export
 * (PRD ATT-06, OPS-04).
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_hikmahattendance\manager;

$courseid = required_param('courseid', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hikmahattendance:viewreports', $context);

$pageurl = new moodle_url('/local/hikmahattendance/report.php', ['courseid' => $course->id]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('reporttitle', 'local_hikmahattendance'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('hk-attpage');

$records = $DB->get_records_sql(
    "SELECT d.id, d.userid, d.seconds, d.denominator, d.percent, d.threshold, d.computedstatus, d.finalstatus,
            d.state, d.reason, d.note, d.appliedtime, l.windowstart, l.windowend, l.sessionid,
            al.statusid AS registerstatusid, al.takenby AS registertakenby, st.description AS registermark
       FROM {" . manager::TABLE_DECISION . "} d
       JOIN {" . manager::TABLE_LINK . "} l ON l.id = d.linkid
  LEFT JOIN {attendance_log} al ON al.sessionid = d.sessionid AND al.studentid = d.userid
  LEFT JOIN {attendance_statuses} st ON st.id = al.statusid
      WHERE l.courseid = :courseid AND d.state <> :superseded
   ORDER BY l.windowstart DESC, d.id ASC",
    ['courseid' => $course->id, 'superseded' => manager::DECISION_SUPERSEDED]
);

$userids = array_unique(array_merge(
    array_column($records, 'userid'),
    array_filter(array_column($records, 'registertakenby'))
));
$users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];
$name = static fn($id): string => isset($users[$id]) ? fullname($users[$id]) : '';
$dateformat = get_string('strftimedatetimeshort', 'langconfig');

$rows = [];
foreach ($records as $record) {
    $final = $record->finalstatus ?: $record->computedstatus;
    $rows[] = [
        'session' => userdate($record->windowstart, $dateformat),
        'learner' => $name($record->userid),
        'attendedminutes' => round($record->seconds / 60, 1),
        'classminutes' => round($record->denominator / 60, 1),
        'percent' => (int) $record->percent,
        'threshold' => (int) $record->threshold,
        'computed' => manager::status_display($record->computedstatus),
        'final' => manager::status_display($final),
        'overridden' => !empty($record->finalstatus),
        'reason' => (string) $record->reason,
        'register' => (string) $record->registermark,
        'markedby' => $record->registertakenby ? $name($record->registertakenby) : '',
        'state' => get_string('decisionstate_' . $record->state, 'local_hikmahattendance'),
        'applied' => $record->appliedtime ? userdate($record->appliedtime, $dateformat) : '',
    ];
}

if ($download) {
    $export = new csv_export_writer();
    $export->set_filename('attendance-reconciliation-' . $course->shortname . '-' . gmdate('Ymd-His'));
    $export->add_data([
        get_string('col_session', 'local_hikmahattendance'),
        get_string('col_learner', 'local_hikmahattendance'),
        get_string('col_attendedminutes', 'local_hikmahattendance'),
        get_string('col_classminutes', 'local_hikmahattendance'),
        get_string('col_percent', 'local_hikmahattendance'),
        get_string('col_threshold', 'local_hikmahattendance'),
        get_string('col_computed', 'local_hikmahattendance'),
        get_string('col_final', 'local_hikmahattendance'),
        get_string('col_reason', 'local_hikmahattendance'),
        get_string('col_register', 'local_hikmahattendance'),
        get_string('col_markedby', 'local_hikmahattendance'),
        get_string('col_state', 'local_hikmahattendance'),
        get_string('col_applied', 'local_hikmahattendance'),
        get_string('col_exported', 'local_hikmahattendance'),
    ]);
    $exported = gmdate('Y-m-d H:i:s') . ' UTC';
    foreach ($rows as $row) {
        $export->add_data([
            $row['session'], $row['learner'], $row['attendedminutes'], $row['classminutes'], $row['percent'],
            $row['threshold'], $row['computed']['label'], $row['final']['label'], $row['reason'], $row['register'],
            $row['markedby'], $row['state'], $row['applied'], $exported,
        ]);
    }
    $export->download_file();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_hikmahattendance/report', [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'downloadurl' => (new moodle_url($pageurl, ['download' => 1]))->out(false),
    'backurl' => (new moodle_url('/local/hikmahattendance/index.php', ['courseid' => $course->id]))->out(false),
]);
echo $OUTPUT->footer();
