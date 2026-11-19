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

namespace local_hikmahaccess;

use core_message\api as message_api;

/**
 * The classmates rule: whether students may message each other.
 *
 * Moodle lets each person choose who may message them ("my contacts only" or "my contacts and
 * anyone in my courses"); teachers can message their students whatever the student chose
 * (moodle/site:messageanyuser in the course). So "contacts only" for every student blocks
 * student-to-student messages while students can still message teachers and teachers students.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peers {
    /** @var string preference Moodle reads for "who can message me" */
    const PREFERENCE = 'message_blocknoncontacts';

    /**
     * Whether students may see and message classmates (the switch; off by default).
     *
     * @return bool
     */
    public static function allowed(): bool {
        return (bool) get_config('local_hikmahaccess', 'allowpeers');
    }

    /**
     * The messaging preference students should have under the current switch.
     *
     * @return int
     */
    public static function wanted_preference(): int {
        return self::allowed() ? message_api::MESSAGE_PRIVACY_COURSEMEMBER : message_api::MESSAGE_PRIVACY_ONLYCONTACTS;
    }

    /**
     * Ids of every user who holds the student role anywhere.
     *
     * @return int[]
     */
    public static function student_ids(): array {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$roleid) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_sql(
            'SELECT DISTINCT ra.userid FROM {role_assignments} ra JOIN {user} u ON u.id = ra.userid
              WHERE ra.roleid = :roleid AND u.deleted = 0',
            ['roleid' => $roleid]
        ));
    }

    /**
     * Give one user the students' messaging preference, if they hold the student role and do not
     * have it already.
     *
     * @param int $userid
     * @return bool whether the preference was changed
     */
    public static function apply_to_user(int $userid): bool {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$roleid || !$DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $userid])) {
            return false;
        }
        $wanted = self::wanted_preference();
        $current = get_user_preferences(self::PREFERENCE, null, $userid);
        if ($current !== null && (int) $current === $wanted) {
            return false;
        }
        set_user_preference(self::PREFERENCE, $wanted, $userid);
        return true;
    }

    /**
     * Bring every student in line with the switch.
     *
     * @return int number of students whose preference changed
     */
    public static function sync_all(): int {
        $changed = 0;
        foreach (self::student_ids() as $userid) {
            if (self::apply_to_user($userid)) {
                $changed++;
            }
        }
        return $changed;
    }
}
