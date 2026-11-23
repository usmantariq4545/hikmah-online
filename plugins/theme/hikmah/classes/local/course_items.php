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

use stdClass;

/**
 * Course pages in the style of the owner's reference (2026-11-20): each item says what it is and, where
 * Moodle knows it, how long it takes ("Quiz • 20 min", "Assignment • Due Sat 21 Nov"), each class opens
 * with a one-line summary ("1 live class · 2 readings · 1 quiz"), and the course home opens on the
 * learner's current class.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_items {
    /** Activity type => item kind shown to learners. */
    private const KINDS = [
        'bigbluebuttonbn' => 'live', 'quiz' => 'quiz', 'assign' => 'assign', 'page' => 'reading', 'book' => 'reading',
        'resource' => 'file', 'folder' => 'file', 'url' => 'link', 'forum' => 'forum', 'attendance' => 'attendance',
        'coursecertificate' => 'certificate',
    ];

    /** Kinds counted in a class's summary line, in this order. */
    private const SUMMARY = ['live', 'reading', 'file', 'quiz', 'assign', 'forum'];

    /**
     * Label per visible item and summary per section.
     *
     * Items the user has already submitted, attempted or completed are listed in done: their due and
     * closing dates no longer matter to them, so the page drops those lines.
     *
     * @param stdClass $course
     * @param int|null $userid defaults to the current user
     * @return array{items: array<int, string>, sections: array<int, string>, done: int[]} cm id => label,
     *     section number => summary, cm ids done
     */
    public static function export(stdClass $course, ?int $userid = null): array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/completionlib.php');
        $userid = $userid ?? (int) $USER->id;
        $modinfo = get_fast_modinfo($course, $userid);
        $cms = array_filter($modinfo->get_cms(), static fn($cm) => $cm->is_visible_on_course_page()
            && $cm->modname !== 'label');
        $quizzes = self::instances('quiz', $cms, 'id, timelimit');
        $assigns = self::instances('assign', $cms, 'id, duedate');
        $submitted = $assigns ? $DB->get_records_select_menu(
            'assign_submission',
            'userid = ? AND latest = 1 AND status = ? AND assignment IN (' . implode(',', array_keys($assigns)) . ')',
            [$userid, 'submitted'],
            '',
            'assignment, id'
        ) : [];
        $attempted = $quizzes ? $DB->get_records_select_menu(
            'quiz_attempts',
            'userid = ? AND state = ? AND quiz IN (' . implode(',', array_keys($quizzes)) . ')',
            [$userid, 'finished'],
            '',
            'DISTINCT quiz, quiz AS q'
        ) : [];
        $completion = new \completion_info($course);

        $items = [];
        $done = [];
        $counts = [];
        foreach ($cms as $cm) {
            $kind = self::KINDS[$cm->modname] ?? null;
            $isdone = ($cm->modname === 'assign' && isset($submitted[$cm->instance]))
                || ($cm->modname === 'quiz' && isset($attempted[$cm->instance]))
                || ($completion->is_enabled($cm) && $completion->get_data($cm, true, $userid)->completionstate > 0);
            if ($isdone && $cm->modname !== 'bigbluebuttonbn') {
                $done[] = $cm->id;
            }
            // The type, unless the name already says it ("Quiz 1: …", "Your certificate") or, for a live
            // class, its "Starts … · Duration …" line does.
            $type = $kind ? get_string('item_' . $kind, 'theme_hikmah') : get_string('modulename', $cm->modname);
            $parts = $cm->modname === 'bigbluebuttonbn' || \core_text::strpos(
                \core_text::strtolower($cm->name),
                \core_text::strtolower($type)
            ) !== false ? [] : [$type];
            if ($cm->modname === 'assign' && isset($submitted[$cm->instance])) {
                $parts[] = get_string('item_submitted', 'theme_hikmah');
            } else if ($cm->modname === 'assign' && !$isdone && !empty($assigns[$cm->instance]->duedate)) {
                $parts[] = get_string(
                    'item_due',
                    'theme_hikmah',
                    userdate($assigns[$cm->instance]->duedate, get_string('item_dateformat', 'theme_hikmah'))
                );
            } else if ($cm->modname === 'quiz' && isset($attempted[$cm->instance])) {
                $parts[] = get_string('item_attempted', 'theme_hikmah');
            } else if ($cm->modname === 'quiz' && !empty($quizzes[$cm->instance]->timelimit)) {
                $parts[] = get_string('item_minutes', 'theme_hikmah', (int) ceil($quizzes[$cm->instance]->timelimit / 60));
            }
            if ($parts) {
                $items[$cm->id] = implode(' • ', $parts);
            }
            if ($kind && in_array($kind, self::SUMMARY, true)) {
                $counts[$cm->sectionnum][$kind] = ($counts[$cm->sectionnum][$kind] ?? 0) + 1;
            }
        }

        $sections = [];
        foreach ($counts as $sectionnum => $kinds) {
            $parts = [];
            foreach (self::SUMMARY as $kind) {
                if (!empty($kinds[$kind])) {
                    $parts[] = get_string('sum_' . $kind . ($kinds[$kind] > 1 ? 's' : ''), 'theme_hikmah', $kinds[$kind]);
                }
            }
            $sections[$sectionnum] = implode(' · ', $parts);
        }
        return ['items' => $items, 'sections' => $sections, 'done' => $done];
    }

    /**
     * The learner's current class: the class of the next or ongoing live class, else the first class with
     * something still to complete, else the first class. Null when the course has no visible class.
     *
     * @param stdClass $course
     * @param int|null $now for tests
     * @return \section_info|null
     */
    public static function current_section(stdClass $course, ?int $now = null): ?\section_info {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $now = $now ?? time();
        $modinfo = get_fast_modinfo($course);
        $classes = array_filter(
            $modinfo->get_section_info_all(),
            static fn($section) => $section->section > 0 && $section->uservisible && !$section->is_delegated()
        );
        if (!$classes) {
            return null;
        }

        $rooms = array_filter($modinfo->get_instances_of('bigbluebuttonbn'), static fn($cm) => $cm->uservisible);
        if ($rooms) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($rooms));
            $next = $DB->get_records_select(
                'bigbluebuttonbn',
                "id $insql AND openingtime > 0 AND closingtime >= ?",
                array_merge($params, [$now]),
                'openingtime ASC',
                'id',
                0,
                1
            );
            if ($next && ($section = $classes[$rooms[reset($next)->id]->sectionnum] ?? null)) {
                return $section;
            }
        }

        $completion = new \completion_info($course);
        if ($completion->is_enabled()) {
            foreach ($classes as $section) {
                foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    if (
                        $cm->uservisible && $completion->is_enabled($cm)
                            && $completion->get_data($cm)->completionstate == COMPLETION_INCOMPLETE
                    ) {
                        return $section;
                    }
                }
            }
        }
        return reset($classes);
    }

    /**
     * Instance records of one activity type among the given course modules.
     *
     * @param string $modname
     * @param \cm_info[] $cms
     * @param string $fields
     * @return stdClass[] by instance id
     */
    private static function instances(string $modname, array $cms, string $fields): array {
        global $DB;
        $ids = array_map(static fn($cm) => $cm->instance, array_filter($cms, static fn($cm) => $cm->modname === $modname));
        return $ids ? $DB->get_records_list($modname, 'id', $ids, '', $fields) : [];
    }
}
