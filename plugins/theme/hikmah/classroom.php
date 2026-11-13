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
 * In-portal classroom: shows a BigBlueButton live class or recording inside a Hikmah page.
 *
 * The frame loads the BigBlueButton activity's own join or play address, so the activity's access
 * checks, join log and recording-played event all happen exactly as they do without this page.
 * The BigBlueButton server itself stays on its own host.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\local\exceptions\server_not_available_exception;
use mod_bigbluebuttonbn\meeting;

require_once(__DIR__ . '/../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$bn = optional_param('bn', 0, PARAM_INT);
$rid = optional_param('rid', '', PARAM_TEXT);
$rtype = optional_param('rtype', 'presentation', PARAM_ALPHANUMEXT);
$group = optional_param('group', -1, PARAM_INT);

$instance = $id ? instance::get_from_cmid($id) : ($bn ? instance::get_from_instanceid($bn) : null);
if (!$instance) {
    throw new moodle_exception('general_error_not_found', 'mod_bigbluebuttonbn', '', $id ?: $bn);
}

$cm = $instance->get_cm();
$course = $instance->get_course();
$context = $instance->get_context();
$viewurl = $instance->get_view_url();
$isrecording = $rid !== '';

require_login($course, true, $cm);

$pageparams = ['id' => $cm->id];
$frameparams = ['id' => $cm->id];
if ($isrecording) {
    $pageparams += ['rid' => $rid, 'rtype' => $rtype];
    $frameparams += ['action' => 'play', 'rid' => $rid, 'rtype' => $rtype];
} else {
    $frameparams += ['action' => 'join'];
}
if ($group >= 0) {
    $pageparams['group'] = $group;
    $frameparams['group'] = $group;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/hikmah/classroom.php', $pageparams));
$PAGE->set_pagelayout('classroom');
$PAGE->set_cacheable(false);
$PAGE->set_title(format_string($instance->get_meeting_name()) . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));

// With the in-portal classroom switched off, hand over to the activity page and its own button.
if (!get_config('theme_hikmah', 'embedclassroom')) {
    redirect($viewurl);
}

if ($isrecording) {
    require_capability('mod/bigbluebuttonbn:view', $context);
} else {
    $groupid = groups_get_activity_group($cm, true) ?: null;
    if ($groupid) {
        $instance->set_group_id($groupid);
    }
    $canjoin = $instance->can_join();
    if ($canjoin) {
        try {
            $canjoin = (bool) (new meeting($instance))->can_join();
        } catch (server_not_available_exception $e) {
            $canjoin = false;
        }
    }
    if (!$canjoin) {
        redirect($viewurl, get_string('classroom_notopen', 'theme_hikmah'), null,
            \core\output\notification::NOTIFY_INFO);
    }
}

$frameurl = new moodle_url('/mod/bigbluebuttonbn/bbb_view.php', $frameparams);

$data = [
    'classname' => format_string($instance->get_meeting_name()),
    'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
    'backurl' => $viewurl->out(false),
    'dashboardurl' => (new moodle_url('/my/'))->out(false),
    'logourl' => $OUTPUT->image_url('logo-light', 'theme_hikmah')->out(false),
    'frameurl' => $frameurl->out(false),
    'isrecording' => $isrecording,
    'motifclass' => 'hk-classroom__motif hk-motif--animate',
];

$PAGE->requires->js_call_amd('theme_hikmah/classroom', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/classroom', $data);
echo $OUTPUT->footer();
