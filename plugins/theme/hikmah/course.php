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
 * Public course detail page with the right enrol action for the current user (PRD CRS-02).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Course details are public unless forcelogin is on.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

use theme_hikmah\local\catalog;
use theme_hikmah\local\course_detail;

$id = required_param('id', PARAM_INT);
$wantslogin = optional_param('login', 0, PARAM_BOOL);
$enrolnow = optional_param('enrol', 0, PARAM_BOOL);

if ($id == SITEID) {
    redirect(new moodle_url('/theme/hikmah/catalog.php'));
}

$course = get_course($id);
$context = context_course::instance($course->id);
$pageurl = new moodle_url('/theme/hikmah/course.php', ['id' => $course->id]);

if (!empty($CFG->forcelogin) || $wantslogin) {
    // Sends visitors to the login page and brings them straight back here.
    $SESSION->wantsurl = $pageurl->out(false);
    require_login(null, false);
    if ($wantslogin) {
        redirect($pageurl);
    }
}

// Hidden courses are only shown to people who may see hidden courses.
if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
    throw new moodle_exception('coursehidden', 'moodle', new moodle_url('/theme/hikmah/catalog.php'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('hikmah');
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('hk-coursepage');

// One-click free enrolment (ENR-01). Runs through the self-enrolment plugin's own checks.
if ($enrolnow && data_submitted()) {
    require_login(null, false);
    require_sesskey();
    $info = catalog::get_enrol_info([$course->id])[$course->id] ?? null;
    if ($info && $info->type === 'free' && $info->state === 'open' && !is_enrolled($context, $USER, '', true)) {
        $plugin = enrol_get_plugin('self');
        $instance = $info->instance;
        $can = $plugin->can_self_enrol($instance);
        if ($can === true && empty($instance->password)) {
            $plugin->enrol_self($instance);
            redirect(
                new moodle_url('/course/view.php', ['id' => $course->id]),
                get_string('enrolled_ok', 'theme_hikmah', format_string($course->fullname)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        // Enrolment key or another restriction: use Moodle's standard enrolment page.
        redirect(new moodle_url('/enrol/index.php', ['id' => $course->id]));
    }
    redirect($pageurl);
}

$data = course_detail::export($course);
$data['pagemotif'] = ['motifclass' => 'hk-pagehead__motif hk-motif--animate'];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/course', $data);
echo $OUTPUT->footer();
