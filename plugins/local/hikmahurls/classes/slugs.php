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

namespace local_hikmahurls;

/**
 * Course link names ("slugs") made from course titles: /course/<slug> and /courses/<slug>.
 *
 * A course keeps every name it has had, so links shared before a title change still lead to it;
 * exactly one row per course is current.
 *
 * @package    local_hikmahurls
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slugs {
    /** @var string Table name. */
    private const TABLE = 'local_hikmahurls_slug';

    /** @var int Longest slug. */
    private const MAXLEN = 80;

    /** @var array|null courseid => current slug, loaded once per request. */
    private static $current = null;

    /**
     * Turns a title into a link name: lower-case ASCII letters, digits and single hyphens.
     *
     * @param string $title
     * @return string may be '' for a title with no Latin letters or digits
     */
    public static function slugify(string $title): string {
        $text = \core_text::specialtoascii(strip_tags($title));
        $text = \core_text::strtolower($text);
        $text = preg_replace('/&[a-z]+;|[\'’]/', '', $text);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        if (strlen($text) > self::MAXLEN) {
            $text = rtrim(substr($text, 0, self::MAXLEN), '-');
        }
        return $text;
    }

    /**
     * Whether a slug would clash with a real Moodle script or folder under /course/ (for example "edit"),
     * which nginx serves before it looks for a course.
     *
     * @param string $slug
     * @return bool
     */
    public static function is_reserved(string $slug): bool {
        global $CFG;
        return file_exists("{$CFG->dirroot}/course/{$slug}.php") || file_exists("{$CFG->dirroot}/course/{$slug}");
    }

    /**
     * Gives a course its current slug from its full name, keeping earlier names as old ones.
     *
     * @param \stdClass $course with id and fullname
     * @return string the current slug ('' for the site course)
     */
    public static function ensure(\stdClass $course): string {
        global $DB;
        if ((int)$course->id === SITEID) {
            return '';
        }
        $base = self::slugify($course->fullname) ?: 'course-' . $course->id;
        $rows = $DB->get_records(self::TABLE, ['courseid' => $course->id]);
        $current = null;
        foreach ($rows as $row) {
            if ($row->iscurrent) {
                $current = $row;
            }
        }
        // Still matches the title (also when the title gave "-2" because the name was taken): nothing to do.
        if ($current && preg_match('/^' . preg_quote($base, '/') . '(-\d+)?$/', $current->slug)) {
            return $current->slug;
        }

        $slug = $base;
        for ($n = 2; self::taken($slug, (int)$course->id) || self::is_reserved($slug); $n++) {
            $slug = $base . '-' . $n;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->set_field(self::TABLE, 'iscurrent', 0, ['courseid' => $course->id]);
        $own = null;
        foreach ($rows as $row) {
            if ($row->slug === $slug) {
                $own = $row;
            }
        }
        if ($own) {
            $DB->set_field(self::TABLE, 'iscurrent', 1, ['id' => $own->id]);
        } else {
            $DB->insert_record(self::TABLE, [
                'courseid' => $course->id, 'slug' => $slug, 'iscurrent' => 1, 'timecreated' => time(),
            ]);
        }
        $transaction->allow_commit();
        self::$current = null;
        return $slug;
    }

    /**
     * Whether another course already uses (or used) this slug.
     *
     * @param string $slug
     * @param int $courseid the course asking
     * @return bool
     */
    private static function taken(string $slug, int $courseid): bool {
        global $DB;
        $owner = $DB->get_field(self::TABLE, 'courseid', ['slug' => $slug]);
        return $owner !== false && (int)$owner !== $courseid;
    }

    /**
     * Current slug of a course, or '' when it has none.
     *
     * @param int $courseid
     * @return string
     */
    public static function for_course(int $courseid): string {
        global $DB;
        if (self::$current === null) {
            self::$current = $DB->get_records_menu(self::TABLE, ['iscurrent' => 1], '', 'courseid, slug');
        }
        return self::$current[$courseid] ?? '';
    }

    /**
     * Looks a slug up.
     *
     * @param string $slug
     * @return \stdClass|null {courseid, slug (the current one), iscurrent (whether the asked one is current)}
     */
    public static function find(string $slug): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['slug' => $slug]);
        if (!$row) {
            return null;
        }
        $current = $row->iscurrent ? $row->slug : self::for_course((int)$row->courseid);
        return (object)['courseid' => (int)$row->courseid, 'slug' => $current, 'iscurrent' => (bool)$row->iscurrent];
    }

    /**
     * Gives every course without a slug one (install and upgrade).
     */
    public static function backfill(): void {
        global $DB;
        $courses = $DB->get_records_select('course', 'id <> ?', [SITEID], 'id', 'id, fullname');
        foreach ($courses as $course) {
            self::ensure($course);
        }
    }

    /**
     * Observer: a course was created, renamed or restored.
     *
     * @param \core\event\base $event
     */
    public static function course_changed(\core\event\base $event): void {
        global $DB;
        $course = $DB->get_record('course', ['id' => $event->courseid], 'id, fullname');
        if ($course) {
            self::ensure($course);
        }
    }

    /**
     * Observer: a course was deleted, so its names are free again.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $event->objectid]);
        self::$current = null;
    }

    /**
     * Forget the per-request cache (tests).
     */
    public static function reset_cache(): void {
        self::$current = null;
    }
}
