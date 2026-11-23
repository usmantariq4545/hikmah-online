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

namespace theme_hikmah\output\core;

use moodle_url;
use stdClass;
use theme_hikmah\local\course_detail;

/**
 * Course renderer overrides for the Hikmah theme.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer extends \core_course_renderer {
    /**
     * Moodle's "Enrolment options" page (enrol/index.php).
     *
     * The Hikmah course page already shows everything about a course and the right action for it
     * (enrol free, pay, log in, closed…), so people are sent there. The only enrolment method that
     * needs Moodle's page is an application (enrol_apply) or a self-enrolment key: for those this
     * renders a short "Apply to join" page instead of repeating the whole course description.
     *
     * @param stdClass $course
     * @param array $widgets enrolment method output keyed by enrol instance id
     * @param \core\url|null $returnurl
     * @return string
     */
    public function enrolment_options(stdClass $course, array $widgets, ?\core\url $returnurl = null): string {
        global $DB;

        $detailurl = new moodle_url('/theme/hikmah/course.php', ['id' => $course->id]);

        global $USER;

        $forms = [];
        $applied = false;
        if ($widgets) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($widgets), SQL_PARAMS_NAMED);
            $instances = $DB->get_records_select('enrol', "id $insql", $params, '', 'id, enrol, password');
            foreach ($widgets as $instanceid => $html) {
                $instance = $instances[$instanceid] ?? null;
                if (!$instance) {
                    continue;
                }
                if (
                    $instance->enrol === 'apply' && $DB->record_exists_select(
                        'user_enrolments',
                        'enrolid = :enrolid AND userid = :userid AND status IN (1, 2)',
                        ['enrolid' => $instance->id, 'userid' => $USER->id]
                    )
                ) {
                    // Already applied (or just did): the course page shows "Application sent".
                    $applied = true;
                    continue;
                }
                if ($instance->enrol === 'apply') {
                    // The plugin labels its button with self-enrolment's "Enrol me"; this only
                    // sends an application, so say that.
                    $html = str_replace(
                        'value="' . s(get_string('enrolme', 'enrol_self')) . '"',
                        'value="' . s(get_string('enrol_sendapplication', 'theme_hikmah')) . '"',
                        (string) $html
                    );
                    $forms[] = ['html' => $html];
                } else if ($instance->enrol === 'self' && trim((string) $instance->password) !== '') {
                    $forms[] = ['html' => (string) $html];
                }
            }
        }

        if (!$forms && $applied) {
            redirect(
                $detailurl,
                get_string('enrol_applicationsent', 'theme_hikmah'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        // Nothing here that the course page does not already offer: go there. Only for plain page
        // views; any form submission was handled by the enrolment plugins before this point.
        if (!$forms && !isguestuser() && $_SERVER['REQUEST_METHOD'] === 'GET') {
            redirect($detailurl);
        }
        if (!$forms) {
            return parent::enrolment_options($course, $widgets, $returnurl);
        }

        $detail = course_detail::export($course);
        $teachers = array_map(static fn($t) => $t['name'], $detail['teacherlist'] ?? []);
        $context = [
            'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'summary' => shorten_text(html_to_text((string) ($detail['description'] ?? ''), 0, false), 220),
            'teachers' => implode(', ', $teachers),
            'hasteachers' => !empty($teachers),
            'mode' => $detail['mode'] ?? '',
            'image' => $detail['image'] ?? '',
            'detailurl' => $detailurl->out(false),
            'forms' => $forms,
        ];
        return $this->render_from_template('theme_hikmah/enrol_apply', $context);
    }
}
