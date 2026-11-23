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

namespace local_hikmahcompletion;

use stdClass;

/**
 * A course's certificate rules and their translation into Moodle's own settings.
 *
 * Each course sets a minimum course grade and a minimum attendance, in percent (course custom fields
 * certgrade and certattendance; empty means the site default). How much each quiz, assignment or
 * the register counts towards the course grade is set in Moodle's Gradebook setup. sync() then makes
 * Moodle enforce the rules:
 *  - the attendance register's grade to pass is the attendance minimum, and passing it completes it;
 *  - course completion needs every activity with completion tracking plus the minimum course grade;
 *  - the certificate opens only when those activities are complete and both minimums are met.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {
    /** Course custom field shortnames. */
    public const FIELD_GRADE = 'certgrade';
    /** Course custom field shortnames. */
    public const FIELD_ATTENDANCE = 'certattendance';

    /**
     * The course's rules, with the site defaults where the course sets none.
     *
     * @param int $courseid
     * @return array{grade: int, attendance: int, gradeset: bool, attendanceset: bool}
     */
    public static function get(int $courseid): array {
        $values = [];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $data) {
            $values[$data->get_field()->get('shortname')] = trim((string) $data->get_value());
        }
        $out = [];
        foreach (['grade' => self::FIELD_GRADE, 'attendance' => self::FIELD_ATTENDANCE] as $key => $shortname) {
            $value = $values[$shortname] ?? '';
            $out[$key . 'set'] = $value !== '' && is_numeric($value);
            $out[$key] = $out[$key . 'set'] ? max(0, min(100, (int) $value))
                : (int) (get_config('local_hikmahcompletion', 'default' . $key) ?: ($key === 'grade' ? 50 : 75));
        }
        return $out;
    }

    /**
     * Save the course's minimums (null = use the site default). Teachers cannot open the course settings
     * form, so this writes the custom field values directly.
     *
     * @param int $courseid
     * @param int|null $grade
     * @param int|null $attendance
     */
    public static function save(int $courseid, ?int $grade, ?int $attendance): void {
        $context = \context_course::instance($courseid);
        $new = [self::FIELD_GRADE => $grade, self::FIELD_ATTENDANCE => $attendance];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $data) {
            $shortname = $data->get_field()->get('shortname');
            if (!array_key_exists($shortname, $new)) {
                continue;
            }
            $value = $new[$shortname] === null ? '' : (string) $new[$shortname];
            $data->set('contextid', $context->id);
            $data->set($data->datafield(), $value);
            $data->set('value', $value);
            $data->save();
        }
    }

    /**
     * Make Moodle enforce the course's rules. Idempotent: writes only what differs.
     *
     * @param int $courseid
     * @return string[] what changed, for logs
     */
    public static function sync(int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $certificates = $modinfo->get_instances_of('coursecertificate');
        if (!$certificates) {
            return []; // Nothing to protect; leave the course as its teachers set it.
        }
        $rules = self::get($courseid);
        $changes = [];

        if (!$course->enablecompletion) {
            $DB->set_field('course', 'enablecompletion', 1, ['id' => $courseid]);
            $changes[] = 'completion tracking on';
        }

        // The register: grade to pass = attendance minimum, completed by passing.
        $attendanceitem = null;
        if ($attendancecm = self::first_cm($modinfo, 'attendance')) {
            $attendanceitem = \grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'mod', 'itemmodule' => 'attendance',
                'iteminstance' => $attendancecm->instance, 'itemnumber' => 0]);
            if ($attendanceitem && $attendanceitem->grademax > 0) {
                $pass = round($attendanceitem->grademax * $rules['attendance'] / 100, 5);
                if (abs((float) $attendanceitem->gradepass - $pass) > 0.00001) {
                    $attendanceitem->gradepass = $pass;
                    $attendanceitem->update('local_hikmahcompletion');
                    $changes[] = "attendance pass grade $pass";
                }
                $cmrow = $DB->get_record(
                    'course_modules',
                    ['id' => $attendancecm->id],
                    'id, completion, completionpassgrade, completiongradeitemnumber'
                );
                if (
                    (int) $cmrow->completion !== COMPLETION_TRACKING_AUTOMATIC || !$cmrow->completionpassgrade
                        || $cmrow->completiongradeitemnumber === null
                ) {
                    $DB->update_record('course_modules', (object) ['id' => $cmrow->id,
                        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionpassgrade' => 1,
                        'completiongradeitemnumber' => 0]);
                    rebuild_course_cache($courseid, true);
                    $modinfo = get_fast_modinfo($courseid);
                    (new \completion_info($course))->reset_all_state($modinfo->get_cm($cmrow->id));
                    $changes[] = 'attendance completes on passing';
                }
            } else {
                $attendanceitem = null;
            }
        }

        // Course completion: every tracked activity (the certificate aside) and the minimum course grade.
        $required = array_map(static fn($cm) => $cm->modname, self::required($modinfo));
        $courseitem = \grade_item::fetch_course_item($courseid);
        $gradepass = $courseitem && $courseitem->grademax > 0 ? round($courseitem->grademax * $rules['grade'] / 100, 5) : null;
        $changes = array_merge($changes, self::completion_criteria($course, $required, $gradepass));

        // The certificate opens only when the rules are met.
        $conditions = [];
        foreach (array_keys($required) as $cmid) {
            $conditions[] = ['type' => 'completion', 'cm' => $cmid, 'e' => COMPLETION_COMPLETE];
        }
        if ($gradepass !== null) {
            $conditions[] = ['type' => 'grade', 'id' => (int) $courseitem->id, 'min' => (float) $rules['grade']];
        }
        if ($attendanceitem) {
            $conditions[] = ['type' => 'grade', 'id' => (int) $attendanceitem->id, 'min' => (float) $rules['attendance']];
        }
        // Every condition is shown, so learners see the certificate locked with what is left (a hidden
        // condition that fails would hide the certificate itself; Moodle folds long lists behind "Show more").
        $availability = json_encode(['op' => '&', 'c' => $conditions, 'showc' => array_fill(0, count($conditions), true)]);
        foreach ($certificates as $cm) {
            if ((string) $DB->get_field('course_modules', 'availability', ['id' => $cm->id]) !== $availability) {
                $DB->set_field('course_modules', 'availability', $availability, ['id' => $cm->id]);
                $changes[] = "certificate {$cm->id} restricted";
            }
        }
        if ($changes) {
            rebuild_course_cache($courseid, true);
        }
        return $changes;
    }

    /**
     * Write the course completion criteria if they differ from what the rules need.
     *
     * @param stdClass $course
     * @param array $required cm id => module name
     * @param float|null $gradepass course grade needed, in points
     * @return string[] changes
     */
    private static function completion_criteria(stdClass $course, array $required, ?float $gradepass): array {
        global $DB;
        $existing = $DB->get_records_select(
            'course_completion_criteria',
            'course = ? AND criteriatype IN (?, ?)',
            [$course->id, COMPLETION_CRITERIA_TYPE_ACTIVITY, COMPLETION_CRITERIA_TYPE_GRADE]
        );
        $haveactivities = [];
        $havegrade = null;
        foreach ($existing as $criterion) {
            if ((int) $criterion->criteriatype === COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                $haveactivities[(int) $criterion->moduleinstance] = true;
            } else {
                $havegrade = (float) $criterion->gradepass;
            }
        }
        ksort($haveactivities);
        $want = $required;
        ksort($want);
        $samegrade = ($gradepass === null && $havegrade === null)
            || ($gradepass !== null && $havegrade !== null && abs($havegrade - $gradepass) < 0.00001);
        $aggregation = $DB->get_field('course_completion_aggr_methd', 'method', ['course' => $course->id, 'criteriatype' => null]);
        if (array_keys($haveactivities) === array_keys($want) && $samegrade && (int) $aggregation === COMPLETION_AGGREGATION_ALL) {
            return [];
        }

        // The rules changed: earlier course completions were judged on other rules, so Moodle works them
        // out again from the new ones (its completion task re-checks every learner).
        $completion = new \completion_info($course);
        $completion->delete_course_completion_data();
        $DB->delete_records_select(
            'course_completion_criteria',
            'course = ? AND criteriatype IN (?, ?)',
            [$course->id, COMPLETION_CRITERIA_TYPE_ACTIVITY, COMPLETION_CRITERIA_TYPE_GRADE]
        );
        foreach ($want as $cmid => $modname) {
            $DB->insert_record('course_completion_criteria', (object) ['course' => $course->id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY, 'module' => $modname, 'moduleinstance' => $cmid]);
        }
        if ($gradepass !== null) {
            $DB->insert_record('course_completion_criteria', (object) ['course' => $course->id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_GRADE, 'gradepass' => $gradepass]);
        }
        foreach ([null, COMPLETION_CRITERIA_TYPE_ACTIVITY] as $type) {
            $aggr = new \completion_aggregation(['course' => $course->id, 'criteriatype' => $type]);
            $aggr->setMethod(COMPLETION_AGGREGATION_ALL);
            $aggr->save();
        }
        \cache::make('core', 'coursecompletion')->purge();
        return ['course completion: ' . count($want) . ' activities' . ($gradepass !== null ? " and grade $gradepass" : '')];
    }

    /**
     * Activities a learner must complete: every visible one with completion tracking, the certificate aside.
     *
     * @param \course_modinfo $modinfo
     * @return \cm_info[] by cm id
     */
    public static function required(\course_modinfo $modinfo): array {
        return array_filter($modinfo->get_cms(), static fn($cm) => $cm->completion != COMPLETION_TRACKING_NONE
            && $cm->visible && !$cm->deletioninprogress && $cm->modname !== 'coursecertificate');
    }

    /**
     * The course's first activity of a type (the oldest register, as elsewhere in Hikmah).
     *
     * @param \course_modinfo $modinfo
     * @param string $modname
     * @return \cm_info|null
     */
    private static function first_cm(\course_modinfo $modinfo, string $modname): ?\cm_info {
        $cms = array_filter($modinfo->get_instances_of($modname), static fn($cm) => !$cm->deletioninprogress);
        ksort($cms);
        return $cms ? reset($cms) : null;
    }
}
