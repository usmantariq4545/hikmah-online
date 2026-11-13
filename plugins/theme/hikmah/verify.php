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
 * Public certificate verification by code or QR link (PRD CERT-03, CERT-04). Shows only the
 * minimum needed to confirm a certificate: holder name, course, issue date and validity.
 * The lookup itself is done by tool_certificate, which also logs the verification event.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Verification is public by design.
require_once(__DIR__ . '/../../config.php');

$code = trim(optional_param('code', '', PARAM_ALPHANUM));

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/hikmah/verify.php', $code !== '' ? ['code' => $code] : []));
$PAGE->set_pagelayout('hikmah');
$PAGE->set_title(get_string('verify_title', 'theme_hikmah') . ' | ' . format_string($SITE->shortname));
$PAGE->set_heading(get_string('verify_title', 'theme_hikmah'));
$PAGE->add_body_class('hk-verifypage');

$available = class_exists('\tool_certificate\certificate') && \tool_certificate\permission::can_verify();

$data = [
    'pagemotif' => ['motifclass' => 'hk-pagehead__motif hk-motif--animate'],
    'action' => (new moodle_url('/theme/hikmah/verify.php'))->out(false),
    'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
    'code' => $code,
    'available' => $available,
    'searched' => $code !== '' && $available,
    'valid' => false,
    'expired' => false,
];

if ($data['searched']) {
    $result = \tool_certificate\certificate::verify($code);
    if (!empty($result->success)) {
        $issue = $result->issue;
        $holder = \core_user::get_user($issue->userid);
        $coursename = '';
        if (!empty($issue->courseid) && ($course = $DB->get_record('course', ['id' => $issue->courseid]))) {
            $coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
        }
        $expired = !empty($issue->expires) && $issue->expires <= time();
        $data['valid'] = !$expired;
        $data['expired'] = $expired;
        $data['result'] = [
            'holder' => $holder && !$holder->deleted ? fullname($holder) : get_string('verify_holderunknown', 'theme_hikmah'),
            'course' => $coursename,
            'certificate' => format_string($issue->certificatename),
            'issued' => userdate($issue->timecreated, get_string('strftimedate', 'langconfig')),
            'expires' => !empty($issue->expires) ? userdate($issue->expires, get_string('strftimedate', 'langconfig')) : '',
            'code' => $issue->code,
        ];
    }
    $data['invalid'] = !$data['valid'] && !$data['expired'];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/verify', $data);
echo $OUTPUT->footer();
