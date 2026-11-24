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

/**
 * Event observers.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Someone was given a role: if it is the student role, apply the classmates rule at once, so a
     * new student is covered before the daily sync runs.
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event): void {
        peers::apply_to_user((int) $event->relateduserid);
    }

    /**
     * One enrollment per learner per course: when staff enrol by hand someone who already has active
     * access another way (paid, self, approval), the new, redundant manual enrollment is removed.
     * Only manual: cohort sync would re-add its own, and paid ones are refused before payment.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        global $DB;
        if (($event->other['enrol'] ?? '') !== 'manual') {
            return;
        }
        $ue = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
        if (!$ue) {
            return;
        }
        $others = $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid AND ue.id <> :ueid
                AND ue.status = :active AND e.status = :enabled",
            [
                'courseid' => $event->courseid,
                'userid' => $ue->userid,
                'ueid' => $ue->id,
                'active' => ENROL_USER_ACTIVE,
                'enabled' => ENROL_INSTANCE_ENABLED,
            ]
        );
        if ($others) {
            $instance = $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST);
            enrol_get_plugin('manual')->unenrol_user($instance, (int) $ue->userid);
        }
    }
}
