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
 * Linked live sessions of a course, with the exceptions queue (missing callback, unknown payload,
 * pending decisions) on top (PRD ATT-07, OPS-03).
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_hikmahattendance\local\calculator;
use local_hikmahattendance\manager;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hikmahattendance:review', $context);

$pageurl = new moodle_url('/local/hikmahattendance/index.php', ['courseid' => $course->id]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('navtitle', 'local_hikmahattendance'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('hk-attpage');

$canconfigure = has_capability('local/hikmahattendance:configure', $context);

if ($action !== '' && data_submitted()) {
    require_sesskey();
    if ($action === 'autowrite' && $canconfigure) {
        manager::set_autowrite((int) $course->id, (bool) optional_param('enabled', 0, PARAM_BOOL), (int) $USER->id);
        redirect($pageurl, get_string('autowrite_saved', 'local_hikmahattendance'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'processall') {
        $n = 0;
        foreach ($DB->get_records(manager::TABLE_LINK, ['courseid' => $course->id, 'cancelled' => 0], '', 'id') as $link) {
            manager::process_link((int) $link->id);
            $n++;
        }
        redirect($pageurl, get_string('processed_n', 'local_hikmahattendance', $n), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($pageurl);
}

$dateformat = get_string('index_dateformat', 'local_hikmahattendance');
$timeformat = get_string('strftimetime', 'langconfig');
$links = $DB->get_records(manager::TABLE_LINK, ['courseid' => $course->id], 'windowstart DESC');

// Each live class by the class it belongs to ("Class 6 · Noon sakinah"); a room in General by its own name.
$bbbnames = [];
foreach (get_fast_modinfo($course)->get_instances_of('bigbluebuttonbn') as $bbbid => $cm) {
    $bbbnames[$bbbid] = $cm->sectionnum ? get_section_name($course, $cm->sectionnum) : $cm->name;
}
$rows = [];
$exceptions = 0;
foreach ($links as $link) {
    $counts = ['present' => 0, 'partial' => 0, 'absent' => 0, 'pending' => 0];
    foreach (manager::current_decisions((int) $link->id) as $decision) {
        $counts[manager::effective_status($decision)]++;
    }
    $isexception = in_array($link->state, [manager::LINK_MISSING, manager::LINK_UNKNOWN], true) || $counts['pending'] > 0;
    $exceptions += $isexception ? 1 : 0;
    $rows[] = [
        'id' => $link->id,
        'date' => userdate($link->windowstart, $dateformat),
        'endtime' => userdate($link->windowend, $timeformat),
        'minutes' => (int) round(($link->windowend - $link->windowstart) / 60),
        'bbbname' => format_string($bbbnames[$link->bbbid] ?? '—'),
        'state' => manager::link_state_display($link->state),
        'note' => (string) $link->note,
        'isexception' => $isexception,
        'present' => $counts['present'],
        'partial' => $counts['partial'],
        'absent' => $counts['absent'],
        'pending' => $counts['pending'],
        'hasdecisions' => array_sum($counts) > 0,
        'threshold' => manager::threshold_for($link),
        'reconcileurl' => (new moodle_url('/local/hikmahattendance/reconcile.php', ['id' => $link->id]))->out(false),
        'editurl' => (new moodle_url('/local/hikmahattendance/link.php', ['courseid' => $course->id, 'id' => $link->id]))
            ->out(false),
    ];
}

$data = [
    'courseid' => $course->id,
    'actionurl' => $pageurl->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'exceptions' => $exceptions,
    'hasexceptions' => $exceptions > 0,
    'linkurl' => (new moodle_url('/local/hikmahattendance/link.php', ['courseid' => $course->id]))->out(false),
    'reporturl' => has_capability('local/hikmahattendance:viewreports', $context)
        ? (new moodle_url('/local/hikmahattendance/report.php', ['courseid' => $course->id]))->out(false) : '',
    'canconfigure' => $canconfigure,
    'autowrite' => manager::autowrite_enabled((int) $course->id),
    'threshold' => manager::default_threshold(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_hikmahattendance/index', $data);
echo $OUTPUT->footer();
