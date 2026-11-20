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
 * Create or edit the link between a BBB activity and an attendance session.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

use local_hikmahattendance\manager;

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hikmahattendance:review', $context);

$indexurl = new moodle_url('/local/hikmahattendance/index.php', ['courseid' => $course->id]);
$pageurl = new moodle_url('/local/hikmahattendance/link.php', ['courseid' => $course->id, 'id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('linktitle', 'local_hikmahattendance'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('hk-attpage');

$link = $id ? manager::get_link($id) : null;
if ($link && (int) $link->courseid !== (int) $course->id) {
    throw new moodle_exception('error:othercourse', 'local_hikmahattendance');
}

if ($link && $delete && data_submitted()) {
    require_sesskey();
    manager::delete_link((int) $link->id, (int) $USER->id);
    redirect($indexurl, get_string('link_deleted', 'local_hikmahattendance'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$bbbs = [];
foreach ($DB->get_records('bigbluebuttonbn', ['course' => $course->id], 'name', 'id, name') as $bbb) {
    $bbbs[$bbb->id] = format_string($bbb->name);
}
$attendances = $DB->get_records('attendance', ['course' => $course->id], 'name', 'id, name');
$linked = $DB->get_fieldset_select(manager::TABLE_LINK, 'sessionid', 'courseid = ?', [$course->id]);

// Target is either an existing session ("s<id>") or a new session in an activity ("n<attendanceid>").
$targets = [];
foreach ($attendances as $attendance) {
    $name = format_string($attendance->name);
    $targets['n' . $attendance->id] = get_string('form_newsession', 'local_hikmahattendance', $name);
    $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $attendance->id], 'sessdate DESC', 'id, sessdate',
        0, 100);
    foreach ($sessions as $session) {
        if (in_array($session->id, $linked) && (!$link || (int) $link->sessionid !== (int) $session->id)) {
            continue;
        }
        $targets['s' . $session->id] = $name . ' · ' . userdate($session->sessdate, $dateformat);
    }
}

if (!$bbbs || !$attendances) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_hikmahattendance/needs_activities', [
        'nobbb' => !$bbbs,
        'noattendance' => !$attendances,
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

$form = new \local_hikmahattendance\form\link_form($pageurl->out(false), [
    'courseid' => $course->id,
    'id' => $id,
    'bbbs' => $bbbs,
    'targets' => $targets,
    'default' => manager::default_threshold(),
]);

if ($form->is_cancelled()) {
    redirect($indexurl);
}

if ($data = $form->get_data()) {
    $kind = substr($data->target, 0, 1);
    $targetid = (int) substr($data->target, 1);
    if ($kind === 'n') {
        // Create the attendance session for this class window through mod_attendance.
        $attendance = $DB->get_record('attendance', ['id' => $targetid, 'course' => $course->id], '*', MUST_EXIST);
        $sessionid = manager::create_session($attendance, (int) $data->windowstart,
            (int) $data->windowend - (int) $data->windowstart,
            get_string('sessiondescription', 'local_hikmahattendance', $bbbs[$data->bbbid]));
        $attendanceid = (int) $attendance->id;
    } else {
        $session = $DB->get_record('attendance_sessions', ['id' => $targetid], '*', MUST_EXIST);
        $sessionid = (int) $session->id;
        $attendanceid = (int) $session->attendanceid;
    }

    $saved = manager::save_link((object) [
        'id' => $id,
        'courseid' => $course->id,
        'bbbid' => (int) $data->bbbid,
        'attendanceid' => $attendanceid,
        'sessionid' => $sessionid,
        'windowstart' => (int) $data->windowstart,
        'windowend' => (int) $data->windowend,
        'threshold' => $data->threshold,
        'cancelled' => $data->cancelled,
    ], (int) $USER->id);
    manager::process_link((int) $saved->id);
    redirect($indexurl, get_string('link_saved', 'local_hikmahattendance'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($link) {
    $form->set_data([
        'bbbid' => $link->bbbid,
        'target' => 's' . $link->sessionid,
        'windowstart' => $link->windowstart,
        'windowend' => $link->windowend,
        'threshold' => $link->threshold,
        'cancelled' => $link->cancelled,
    ]);
} else {
    $start = (int) (floor(time() / HOURSECS) * HOURSECS);
    $form->set_data(['windowstart' => $start, 'windowend' => $start + HOURSECS]);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_hikmahattendance/link', [
    'isedit' => (bool) $link,
    'form' => $form->render(),
    'deleteurl' => $link ? $pageurl->out(false) : '',
    'sesskey' => sesskey(),
    'backurl' => $indexurl->out(false),
]);
echo $OUTPUT->footer();
