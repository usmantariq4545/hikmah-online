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

namespace theme_hikmah\local;

use moodle_page;

/**
 * The dashboard's banner on a learner's own pages: profile, grades, payments and preferences
 * (owner's request, 2026-11-20). Each gets the same dark card with the rosette, a title and, where
 * it helps, a few numbers.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pagehero {
    /** Page types that get the banner => eyebrow string id. */
    public const PAGES = [
        'user-profile' => 'hero_profile',
        'grade-report-overview-index' => 'hero_grades',
        'payment-gateway-hikmahtransfer-status' => 'hero_payments',
        'user-preferences' => 'hero_preferences',
    ];

    /**
     * Template context for the page's banner, or null when the page has none.
     *
     * @param moodle_page $page
     * @return array|null
     */
    public static function export(moodle_page $page): ?array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');
        if (!isset(self::PAGES[$page->pagetype]) || !isloggedin() || isguestuser()) {
            return null;
        }
        // Only the profile page's id is a user (on the grades page it is the course).
        $profileid = $page->pagetype === 'user-profile' ? $page->url->get_param('id') : null;
        $userid = (int) ($profileid ?: $USER->id);
        $own = $userid === (int) $USER->id;
        $stats = [];
        $title = get_string(self::PAGES[$page->pagetype] . '_title', 'theme_hikmah');
        $lead = get_string(self::PAGES[$page->pagetype] . '_lead', 'theme_hikmah');

        switch ($page->pagetype) {
            case 'user-profile':
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
                if (!$user) {
                    return null;
                }
                $title = fullname($user);
                $lead = implode(' · ', array_filter([$user->city, $user->country
                    ? get_string_manager()->get_list_of_countries()[$user->country] ?? '' : '']));
                if ($own) {
                    $courses = enrol_get_my_courses('id, enablecompletion');
                    $completed = $DB->count_records_select('course_completions', 'userid = ? AND timecompleted > 0', [$userid]);
                    $certificates = $DB->get_manager()->table_exists('tool_certificate_issues')
                        ? $DB->count_records('tool_certificate_issues', ['userid' => $userid]) : 0;
                    $stats = [
                        self::stat(count($courses), 'dash_courses'),
                        self::stat($completed, 'dash_completed'),
                        self::stat($certificates, 'dash_certificates'),
                    ];
                }
                break;

            case 'grade-report-overview-index':
                $percents = [];
                foreach (enrol_get_users_courses($userid, true, 'id') as $course) {
                    $grade = grade_get_course_grade($userid, $course->id);
                    if ($grade && $grade->grade !== null && $grade->item->grademax > $grade->item->grademin) {
                        $range = $grade->item->grademax - $grade->item->grademin;
                        $percents[] = ($grade->grade - $grade->item->grademin) / $range * 100;
                    }
                }
                $stats = [self::stat(count($percents), 'hero_gradedcourses')];
                if ($percents) {
                    $stats[] = self::stat((int) round(array_sum($percents) / count($percents)), 'hero_average', '%');
                }
                break;

            case 'payment-gateway-hikmahtransfer-status':
                if ($DB->get_manager()->table_exists('paygw_hikmahtransfer_req')) {
                    $count = static fn(string $status): int => $DB->count_records(
                        'paygw_hikmahtransfer_req',
                        ['userid' => $USER->id, 'status' => $status]
                    );
                    $stats = [
                        self::stat($count('pending'), 'dash_paywaiting'),
                        self::stat($count('resubmit'), 'hero_needsaction'),
                        self::stat($count('approved'), 'hero_approved'),
                    ];
                }
                break;
        }

        return [
            'motif' => ['motifclass' => 'hk-dash__motif hk-motif--animate'],
            'eyebrow' => get_string(self::PAGES[$page->pagetype], 'theme_hikmah'),
            'title' => $title,
            'lead' => $lead,
            'stats' => $stats,
            'hasstats' => !empty($stats),
        ];
    }

    /**
     * One number in the banner.
     *
     * @param int $value
     * @param string $label string id in theme_hikmah
     * @param string $suffix
     * @return array
     */
    private static function stat(int $value, string $label, string $suffix = ''): array {
        return ['value' => $value, 'suffix' => $suffix, 'label' => get_string($label, 'theme_hikmah')];
    }
}
