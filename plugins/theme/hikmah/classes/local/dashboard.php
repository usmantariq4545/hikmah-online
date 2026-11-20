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

use moodle_url;

/**
 * The summary at the top of the dashboard (PRD OPS-02, OPS-03): courses and progress, upcoming
 * classes and deadlines, payment status and certificates for learners; work waiting for
 * instructors and finance reviewers. Everything shown is limited to what the user may see.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {
    /** @var int Courses listed under "Continue learning". */
    private const MAX_COURSES = 4;
    /** @var int Upcoming items listed. */
    private const MAX_EVENTS = 5;

    /**
     * Export the dashboard summary for the current user.
     *
     * @return array
     */
    public static function export(): array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');

        $courses = enrol_get_my_courses('id, fullname, shortname, category, startdate, enablecompletion, visible',
            'visible DESC, sortorder ASC');
        $dbman = $DB->get_manager();

        // Staff with no enrolments (managers, support) see the whole site; finance reviewers see payments.
        $system = \context_system::instance();
        $siteview = !$courses && has_capability('moodle/course:view', $system);
        $finance = !$courses && !$siteview && has_capability('paygw/hikmahtransfer:review', $system);
        $eventcourses = $siteview
            ? $DB->get_records_select('course', 'id <> ? AND visible = 1', [SITEID], 'sortorder', 'id, fullname')
            : $courses;

        // Courses with progress.
        $rows = [];
        $progresssum = 0;
        $progresscount = 0;
        $completed = 0;
        $attendance = self::attendance(array_keys($courses), (int) $USER->id);
        foreach ($courses as $course) {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            if ($percent !== null) {
                $progresssum += $percent;
                $progresscount++;
                $completed += $percent >= 100 ? 1 : 0;
            }
            if (count($rows) < self::MAX_COURSES) {
                $rows[] = [
                    'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                    'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'hasprogress' => $percent !== null,
                    'percent' => $percent !== null ? (int) floor($percent) : 0,
                    // Ring circumference is 100 so the dash offset is simply the remainder.
                    'offset' => $percent !== null ? 100 - (int) floor($percent) : 100,
                    'hue' => ($course->id * 67) % 360,
                    'hasattendance' => isset($attendance[$course->id]),
                    'attendance' => $attendance[$course->id] ?? 0,
                ];
            }
        }

        // Upcoming classes and deadlines for the next two weeks.
        $events = [];
        $seenlive = [];
        if ($eventcourses) {
            $now = time();
            $raw = calendar_get_legacy_events($now - HOURSECS, $now + 14 * DAYSECS, true, true, array_keys($eventcourses), true, true);
            usort($raw, static fn($a, $b): int => $a->timestart <=> $b->timestart);
            foreach ($raw as $event) {
                if (count($events) >= self::MAX_EVENTS) {
                    break;
                }
                if ($event->timestart + (int) $event->timeduration < $now) {
                    continue;
                }
                $islive = $event->modulename === 'bigbluebuttonbn';
                $url = $event->modulename && $event->instance
                    ? self::module_url($event->modulename, (int) $event->instance, (int) $event->courseid)
                    : new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $event->timestart]);
                $name = format_string($event->name);
                // A register session is a scheduled class: show it as one and link to the classroom.
                if ($event->modulename === 'attendance') {
                    $name = get_string('dash_liveclass', 'theme_hikmah');
                    // The class's own live room (opening at the session time), else the course's room.
                    $rooms = array_filter(get_fast_modinfo($event->courseid)->get_instances_of('bigbluebuttonbn'),
                        static fn($cm) => $cm->uservisible);
                    $opening = $rooms ? $DB->get_records_list('bigbluebuttonbn', 'id', array_keys($rooms), '', 'id, openingtime')
                        : [];
                    uksort($rooms, static fn($a, $b) => (int) (abs(($opening[$a]->openingtime ?: PHP_INT_MAX / 2) - $event->timestart)
                        <=> abs(($opening[$b]->openingtime ?: PHP_INT_MAX / 2) - $event->timestart)));
                    if ($cm = reset($rooms)) {
                        $url = new moodle_url('/mod/bigbluebuttonbn/view.php', ['id' => $cm->id]);
                        $islive = true;
                    }
                }
                $key = $event->courseid . '-' . $event->timestart;
                if ($islive && isset($seenlive[$key])) {
                    continue; // The same class from both the classroom and the register.
                }
                $seenlive[$key] = true;
                $events[] = [
                    'name' => $name,
                    'course' => isset($eventcourses[$event->courseid]) ? format_string($eventcourses[$event->courseid]->fullname) : '',
                    'day' => userdate($event->timestart, '%d'),
                    'month' => userdate($event->timestart, '%b'),
                    'time' => userdate($event->timestart, get_string('strftimetime', 'langconfig')),
                    'islive' => $islive,
                    'isnow' => $event->timestart <= $now,
                    'url' => $url->out(false),
                ];
            }
        }

        // Certificates.
        $certificates = 0;
        if ($dbman->table_exists('tool_certificate_issues')) {
            $certificates = $DB->count_records('tool_certificate_issues', ['userid' => $USER->id]);
        }

        // The learner's own payments that still need something.
        $payments = [];
        if (class_exists('\paygw_hikmahtransfer\request') && $dbman->table_exists('paygw_hikmahtransfer_req')) {
            $open = $DB->get_records_select(
                'paygw_hikmahtransfer_req',
                "userid = :userid AND status IN ('pending', 'resubmit')",
                ['userid' => $USER->id],
                'id DESC',
                '*',
                0,
                3
            );
            foreach ($open as $request) {
                $payments[] = [
                    'item' => \paygw_hikmahtransfer\request::describe($request),
                    'status' => \paygw_hikmahtransfer\request::status_display($request->status),
                    'needsaction' => $request->status === 'resubmit',
                    'url' => (new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $request->id]))->out(false),
                ];
            }
        }

        $stats = [
            ['value' => count($courses), 'suffix' => '', 'label' => get_string('dash_courses', 'theme_hikmah')],
            [
                'value' => $progresscount ? (int) round($progresssum / $progresscount) : 0,
                'suffix' => '%',
                'label' => get_string('dash_progress', 'theme_hikmah'),
            ],
            ['value' => $completed, 'suffix' => '', 'label' => get_string('dash_completed', 'theme_hikmah')],
            ['value' => $certificates, 'suffix' => '', 'label' => get_string('dash_certificates', 'theme_hikmah')],
        ];
        if ($attendance) {
            array_splice($stats, 2, 0, [['value' => (int) round(array_sum($attendance) / count($attendance)), 'suffix' => '%',
                'label' => get_string('dash_attendance', 'theme_hikmah')]]);
        }

        // Someone who only teaches sees their teaching, not learner progress.
        $isteacher = $courses && !array_filter($courses,
            static fn($c): bool => !has_capability('moodle/grade:viewall', \context_course::instance($c->id)));
        if ($isteacher) {
            [$stats, $rows] = self::teaching($courses);
        } else if ($siteview && $eventcourses) {
            // shortcut: counts every course on each visit; fine for tens of courses, cache it past a few hundred.
            [$stats, $rows] = self::teaching($eventcourses);
        } else if ($finance) {
            $since = time() - 30 * DAYSECS;
            $count = static fn(string $where, array $params = []): int =>
                $DB->count_records_select('paygw_hikmahtransfer_req', $where, $params);
            $stats = [
                ['value' => $count("status = 'pending'"), 'suffix' => '', 'label' => get_string('dash_paywaiting', 'theme_hikmah')],
                ['value' => $count("status = 'approved' AND timemodified >= ?", [$since]), 'suffix' => '',
                    'label' => get_string('dash_payapproved', 'theme_hikmah')],
                ['value' => $count("status IN ('rejected', 'resubmit') AND timemodified >= ?", [$since]), 'suffix' => '',
                    'label' => get_string('dash_payreturned', 'theme_hikmah')],
            ];
        }
        $allcourses = $siteview ? new moodle_url(has_capability('moodle/category:manage', $system)
            ? '/course/management.php' : '/course/index.php') : new moodle_url('/my/courses.php');

        $hour = (int) userdate(time(), '%H');
        return [
            'motif' => ['motifclass' => 'hk-dash__motif hk-motif--animate'],
            'firstname' => $USER->firstname,
            'date' => userdate(time(), get_string('strftimedaydate', 'langconfig')),
            'greeting' => get_string($hour < 12 ? 'dash_morning' : ($hour < 17 ? 'dash_afternoon' : 'dash_evening'),
                'theme_hikmah'),
            'stats' => $stats,
            'coursestitle' => get_string($siteview ? 'dash_sitecourses' : ($isteacher ? 'dash_teaching' : 'dash_continue'),
                'theme_hikmah'),
            'courseslink' => get_string($siteview ? 'dash_managecourses' : 'dash_allcourses', 'theme_hikmah'),
            'showgrid' => !$finance,
            'courses' => $rows,
            'hascourses' => !empty($rows),
            'mycoursesurl' => $allcourses->out(false),
            'catalogurl' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
            'events' => $events,
            'hasevents' => !empty($events),
            'payments' => $payments,
            'haspayments' => !empty($payments),
            'paymentsurl' => (new moodle_url('/payment/gateway/hikmahtransfer/status.php'))->out(false),
            'certificatesurl' => $certificates ? (new moodle_url('/admin/tool/certificate/my.php'))->out(false) : '',
            'work' => self::staff_work(),
        ];
    }

    /**
     * Stats and course rows for someone who teaches every course they are in: courses, students,
     * live classes in the next seven days and class attendance (average of the students' register %).
     *
     * @param \stdClass[] $courses
     * @return array [stats, rows]
     */
    private static function teaching(array $courses): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        $now = time();
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses));
        $classes = $DB->count_records_select('bigbluebuttonbn', "course $insql AND openingtime >= ? AND openingtime < ?",
            array_merge($params, [$now, $now + 7 * DAYSECS]));
        $registers = [];
        foreach ($DB->get_records_select('attendance', "course $insql", $params, 'id DESC', 'id, course') as $att) {
            $registers[$att->course] = $att->id; // Oldest activity wins.
        }

        $rows = [];
        $students = 0;
        $averages = [];
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $count = count_enrolled_users($context, 'mod/assign:submit', 0, true);
            $students += $count;
            $average = null;
            if (isset($registers[$course->id])) {
                $percentages = (new \mod_attendance_summary($registers[$course->id]))->get_user_taken_sessions_percentages();
                if ($percentages) {
                    $average = (int) floor(array_sum($percentages) / count($percentages) * 100);
                    $averages[] = $average;
                }
            }
            if (count($rows) < self::MAX_COURSES) {
                $rows[] = [
                    'name' => format_string($course->fullname, true, ['context' => $context]),
                    'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'teaching' => true,
                    'meta' => $average === null ? get_string('dash_coursestudents', 'theme_hikmah', $count)
                        : get_string('dash_coursemeta', 'theme_hikmah', ['students' => $count, 'attendance' => $average]),
                ];
            }
        }
        $stats = [
            ['value' => count($courses), 'suffix' => '', 'label' => get_string('dash_courses', 'theme_hikmah')],
            ['value' => $students, 'suffix' => '', 'label' => get_string('dash_students', 'theme_hikmah')],
            ['value' => $classes, 'suffix' => '', 'label' => get_string('dash_classesweek', 'theme_hikmah')],
        ];
        if ($averages) {
            $stats[] = ['value' => (int) round(array_sum($averages) / count($averages)), 'suffix' => '%',
                'label' => get_string('dash_classattendance', 'theme_hikmah')];
        }
        return [$stats, $rows];
    }

    /**
     * The user's attendance per course, as mod_attendance's register shows it (marked classes only).
     *
     * @param int[] $courseids
     * @param int $userid
     * @return array<int, int> course id => whole percent, only courses with marked classes
     */
    private static function attendance(array $courseids, int $userid): array {
        global $CFG, $DB;
        if (!$courseids || !$DB->get_manager()->table_exists('attendance')) {
            return [];
        }
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        $out = [];
        foreach ($DB->get_records_select('attendance', "course $insql", $params, 'id DESC', 'id, course') as $att) {
            $summary = (new \mod_attendance_summary($att->id, [$userid]))->get_taken_sessions_summary_for($userid);
            if ($summary->numtakensessions > 0) {
                $out[$att->course] = (int) floor($summary->takensessionspercentage * 100); // Oldest activity wins.
            }
        }
        return $out;
    }

    /**
     * URL of an activity from its module name and instance id.
     *
     * @param string $modulename
     * @param int $instance
     * @param int $courseid
     * @return moodle_url
     */
    private static function module_url(string $modulename, int $instance, int $courseid): moodle_url {
        $cm = get_coursemodule_from_instance($modulename, $instance, $courseid);
        return $cm
            ? new moodle_url('/mod/' . $modulename . '/view.php', ['id' => $cm->id])
            : new moodle_url('/course/view.php', ['id' => $courseid]);
    }

    /**
     * Work waiting for staff: receipts to verify, attendance to review, submissions to grade.
     *
     * @return array list of {value, label, url, urgent}
     */
    private static function staff_work(): array {
        global $DB, $USER;

        $work = [];
        $dbman = $DB->get_manager();
        $system = \context_system::instance();

        if (class_exists('\paygw_hikmahtransfer\request') && $dbman->table_exists('paygw_hikmahtransfer_req')
                && has_capability('paygw/hikmahtransfer:review', $system)) {
            $pending = \paygw_hikmahtransfer\request::count_pending();
            $work[] = [
                'value' => $pending,
                'label' => get_string('work_payments', 'theme_hikmah'),
                'url' => (new moodle_url('/payment/gateway/hikmahtransfer/review.php'))->out(false),
                'urgent' => $pending > 0,
            ];
        }

        if ($dbman->table_exists('local_hikmahatt_link')) {
            $teaching = get_user_capability_course('local/hikmahattendance:review', $USER->id, true, 'fullname', 'fullname', 20);
            if ($teaching) {
                $ids = array_column($teaching, 'id');
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'c');
                $toreview = $DB->count_records_select(
                    'local_hikmahatt_link',
                    "courseid $insql AND state IN ('ready', 'missing', 'unknown')",
                    $params
                );
                // Open the course that has the work, else the first course.
                $flagged = $DB->get_field_select('local_hikmahatt_link', 'MIN(courseid)',
                    "courseid $insql AND state IN ('ready', 'missing', 'unknown')", $params);
                $work[] = [
                    'value' => $toreview,
                    'label' => get_string('work_attendance', 'theme_hikmah'),
                    'url' => (new moodle_url('/local/hikmahattendance/index.php',
                        ['courseid' => $flagged ?: reset($teaching)->id]))->out(false),
                    'urgent' => $toreview > 0,
                ];

                $tograde = $DB->get_record_sql(
                    "SELECT COUNT(s.id) AS total, MIN(a.course) AS courseid
                       FROM {assign_submission} s
                       JOIN {assign} a ON a.id = s.assignment
                  LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                            AND g.attemptnumber = s.attemptnumber
                      WHERE a.course $insql AND s.latest = 1 AND s.status = 'submitted'
                            AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)",
                    $params
                );
                $work[] = [
                    'value' => (int) $tograde->total,
                    'label' => get_string('work_grading', 'theme_hikmah'),
                    // The course's assignments, where the "Needs grading" counts are.
                    'url' => ($tograde->courseid ? new moodle_url('/mod/assign/index.php', ['id' => $tograde->courseid])
                        : new moodle_url('/my/courses.php'))->out(false),
                    'urgent' => $tograde->total > 0,
                ];
            }
        }
        return $work;
    }
}
