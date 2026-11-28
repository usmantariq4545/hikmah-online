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
 * PostHog key events and person details (owner, 2026-11-28). Nothing is sent from the server: Moodle events are
 * queued for the person they are about and printed into their next page, where theme_hikmah's PostHog snippet sends
 * them only if that person pressed Accept in the cookie banner. Events about the person who is browsing wait in their
 * session; events caused by someone else (a reviewer approving a payment, cron issuing a certificate) wait in a user
 * preference until the person's next page. No names, emails or phone numbers are ever included.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics {
    /** @var array Moodle event => PostHog event name. */
    public const EVENTS = [
        '\core\event\user_created' => 'signed up',
        '\core\event\user_updated' => 'profile completed',
        '\core\event\course_viewed' => 'course viewed',
        '\core\event\user_enrolment_created' => 'enrolled',
        '\paygw_hikmahtransfer\event\request_submitted' => 'payment submitted',
        '\paygw_hikmahtransfer\event\request_approved' => 'payment approved',
        '\mod_bigbluebuttonbn\event\meeting_joined' => 'live class joined',
        '\tool_certificate\event\certificate_issued' => 'certificate issued',
    ];

    /** @var string User preference holding events caused by someone else. */
    public const PREFERENCE = 'local_hikmahaccess_analytics';

    /** @var string User preference set once the profile-completed event has been queued. */
    public const PROFILEDONE = 'local_hikmahaccess_profiledone';

    /** @var int Most events kept waiting for one person. */
    private const MAXQUEUE = 20;

    /**
     * Event observer (db/events.php): queues the PostHog event for the person it is about.
     *
     * @param \core\event\base $event
     */
    public static function observe(\core\event\base $event): void {
        global $USER;
        $name = self::EVENTS['\\' . ltrim($event->eventname, '\\')] ?? null;
        if ($name === null || (string) get_config('theme_hikmah', 'posthogkey') === '') {
            return;
        }
        $userid = (int) ($event->relateduserid ?: $event->userid);
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }
        if ($name === 'profile completed' && !self::profile_just_completed($userid)) {
            return;
        }
        if ($name === 'course viewed' && (int) $event->courseid === SITEID) {
            return;
        }
        $properties = [];
        if (!empty($event->courseid) && (int) $event->courseid !== SITEID) {
            $properties['course_id'] = (int) $event->courseid;
            $course = get_course($event->courseid);
            $properties['course_name'] = \core_text::substr(format_string($course->fullname, true,
                ['context' => \context_course::instance($course->id)]), 0, 60);
        }
        $item = ['event' => $name, 'properties' => $properties];

        if (isloggedin() && (int) $USER->id === $userid) {
            self::push_session($item);
        } else {
            self::push_preference($userid, $item);
        }
    }

    /**
     * The queued events for the person browsing, removed from the queue (each is printed once).
     *
     * @return array of ['event' => name, 'properties' => array]
     */
    public static function take(): array {
        global $SESSION, $USER;
        $items = $SESSION->hkanalytics ?? [];
        unset($SESSION->hkanalytics);
        if (isloggedin() && !isguestuser()) {
            $waiting = get_user_preferences(self::PREFERENCE, null, $USER->id);
            if ($waiting !== null) {
                unset_user_preference(self::PREFERENCE, $USER->id);
                $items = array_merge(json_decode($waiting, true) ?: [], $items);
            }
        }
        return array_slice($items, -self::MAXQUEUE);
    }

    /**
     * Person details sent with posthog.identify: role, country and number of courses. Worked out once per session.
     *
     * @return array
     */
    public static function person(): array {
        global $DB, $SESSION, $USER;
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        if (!isset($SESSION->hkanalyticsperson)) {
            if (is_siteadmin()) {
                $role = 'admin';
            } else if ($DB->record_exists('role_assignments', ['userid' => $USER->id, 'contextid' => SYSCONTEXTID])) {
                $role = 'staff';
            } else if (
                $DB->record_exists_sql(
                    "SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
                      WHERE ra.userid = ? AND r.archetype IN ('editingteacher', 'teacher')",
                    [$USER->id]
                )
            ) {
                $role = 'teacher';
            } else {
                $role = 'student';
            }
            $SESSION->hkanalyticsperson = [
                'role' => $role,
                'country' => (string) $USER->country,
                'courses' => count(enrol_get_my_courses('id')),
            ];
        }
        return $SESSION->hkanalyticsperson;
    }

    /**
     * True the first time the user's profile is fully set up (the event fires on every profile save).
     *
     * @param int $userid
     * @return bool
     */
    private static function profile_just_completed(int $userid): bool {
        global $DB;
        if (get_user_preferences(self::PROFILEDONE, null, $userid) !== null) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || user_not_fully_set_up($user, true)) {
            return false;
        }
        set_user_preference(self::PROFILEDONE, time(), $userid);
        return true;
    }

    /**
     * Queue in this session.
     *
     * @param array $item
     */
    private static function push_session(array $item): void {
        global $SESSION;
        $SESSION->hkanalytics = array_slice(array_merge($SESSION->hkanalytics ?? [], [$item]), -self::MAXQUEUE);
    }

    /**
     * Queue in the person's preferences, for their next page.
     *
     * @param int $userid
     * @param array $item
     */
    private static function push_preference(int $userid, array $item): void {
        $items = json_decode((string) get_user_preferences(self::PREFERENCE, '[]', $userid), true) ?: [];
        $items[] = $item;
        // A preference holds 1333 characters: drop the oldest events until the list fits.
        do {
            $json = json_encode(array_slice($items, -self::MAXQUEUE));
            array_shift($items);
        } while (\core_text::strlen($json) > 1300 && $items);
        set_user_preference(self::PREFERENCE, $json, $userid);
    }
}
