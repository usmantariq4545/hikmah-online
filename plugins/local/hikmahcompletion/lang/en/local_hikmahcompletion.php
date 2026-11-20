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
 * Strings for Hikmah certificate rules.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['applied'] = 'Certificate rules saved and applied.';
$string['col_item'] = 'Graded item';
$string['col_weight'] = 'Share of the course grade';
$string['defaultattendance'] = 'Default minimum attendance %';
$string['defaultattendance_desc'] = 'Used by courses that do not set their own minimum attendance for the certificate.';
$string['defaultgrade'] = 'Default minimum course grade %';
$string['defaultgrade_desc'] = 'Used by courses that do not set their own minimum course grade for the certificate.';
$string['editweights'] = 'Change weights in Gradebook setup';
$string['error:percent'] = 'Enter a whole number from 0 to 100, or leave it empty for the site default.';
$string['field_attendance'] = 'Minimum attendance for the certificate (%)';
$string['field_attendance_help'] = 'Share of the live classes a learner must attend, as the attendance register counts it. Leave empty for the site default ({$a}%).';
$string['field_grade'] = 'Minimum course grade for the certificate (%)';
$string['field_grade_help'] = 'Course total a learner must reach. Leave empty for the site default ({$a}%).';
$string['hikmahcompletion:configure'] = 'Set a course\'s certificate rules';
$string['lead'] = 'A learner gets this course\'s certificate only when every activity below is complete, the course grade reaches the minimum and attendance reaches the minimum. How much each quiz, assignment or the attendance register counts towards the course grade is set in Gradebook setup.';
$string['nocertificate'] = 'This course has no certificate activity yet. Add one, then these rules protect it.';
$string['noitems'] = 'Nothing in this course is graded yet.';
$string['pluginname'] = 'Hikmah certificate rules';
$string['privacy:metadata'] = 'The Hikmah certificate rules plugin stores no personal data; it stores rules for each course.';
$string['required_title'] = 'Activities to complete';
$string['rules_title'] = 'Certificate rules';
$string['save'] = 'Save and apply';
$string['sitedefault'] = 'site default';
$string['weights_title'] = 'What counts towards the course grade';
