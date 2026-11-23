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

namespace local_hikmahattendance;

use local_hikmahattendance\local\calculator;
use local_hikmahattendance\local\parser;
use local_hikmahattendance\local\unknown_schema_exception;
use stdClass;

/**
 * Reconciles BigBlueButton participation with the attendance register (PRD ATT-02 to ATT-07).
 *
 * Evidence comes only from what Moodle's BBB module stored in bigbluebuttonbn_logs: the
 * authenticated post-meeting analytics callback (rows "Callback" and per-user "Summary") and the
 * "Join" logs. Nothing a learner can submit is used. Processing produces preview decisions;
 * marks reach the register only when an instructor applies them (or the course owner has
 * switched autowrite on), and a mark someone entered by hand is never overwritten.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Link table. */
    public const TABLE_LINK = 'local_hikmahattendance_link';
    /** @var string Decision table. */
    public const TABLE_DECISION = 'local_hikmahattendance_decision';
    /** @var string Audit table. */
    public const TABLE_AUDIT = 'local_hikmahattendance_audit';
    /** @var string Course switches table. */
    public const TABLE_COURSE = 'local_hikmahattendance_course';

    /** @var string Class window not finished, or callback not expected yet. */
    public const LINK_WAITING = 'waiting';
    /** @var string Window ended long enough ago and no callback arrived. */
    public const LINK_MISSING = 'missing';
    /** @var string Callback arrived in a shape the parser does not know. */
    public const LINK_UNKNOWN = 'unknown';
    /** @var string Decisions computed and waiting for review. */
    public const LINK_READY = 'ready';
    /** @var string All current decisions are applied. */
    public const LINK_APPLIED = 'applied';
    /** @var string Session cancelled: skipped. */
    public const LINK_CANCELLED = 'cancelled';

    /** @var string Decision computed, not reviewed. */
    public const DECISION_PREVIEW = 'preview';
    /** @var string Instructor changed the status, with a reason. */
    public const DECISION_OVERRIDDEN = 'overridden';
    /** @var string Approved but not written (for example a manual mark exists). */
    public const DECISION_APPROVED = 'approved';
    /** @var string Written to the register. */
    public const DECISION_APPLIED = 'applied';
    /** @var string Replaced by a decision from newer evidence. */
    public const DECISION_SUPERSEDED = 'superseded';

    /** @var int How long after the window end evidence is still collected (late callbacks). */
    public const EVIDENCE_GRACE = 172800;
    /** @var int Join logs this long before the window start still count as "joined". */
    public const JOIN_LEAD = 1800;
    /** @var string Evidence note: no BBB report, marked from the join log only. */
    public const NOTE_JOINSONLY = 'joinlogonly';

    // Configuration.

    /**
     * Site default threshold (percent needed for Present).
     *
     * @return int
     */
    public static function default_threshold(): int {
        $value = get_config('local_hikmahattendance', 'threshold');
        return ($value === false || $value === '') ? 75 : max(1, min(100, (int) $value));
    }

    /**
     * Threshold used for a link.
     *
     * @param stdClass $link
     * @return int
     */
    public static function threshold_for(stdClass $link): int {
        return $link->threshold !== null && $link->threshold !== '' ? (int) $link->threshold : self::default_threshold();
    }

    /**
     * Acronym of the attendance status that a computed status maps to.
     *
     * @param string $status present, partial or absent
     * @return string acronym, empty when the status is never written (pending)
     */
    public static function acronym_for(string $status): string {
        $defaults = [
            calculator::STATUS_PRESENT => 'P',
            calculator::STATUS_PARTIAL => 'L',
            calculator::STATUS_ABSENT => 'A',
        ];
        if (!isset($defaults[$status])) {
            return '';
        }
        $configured = trim((string) get_config('local_hikmahattendance', 'acronym_' . $status));
        return $configured !== '' ? $configured : $defaults[$status];
    }

    /**
     * Whether automatic writing is on for a course. Ships off everywhere.
     *
     * @param int $courseid
     * @return bool
     */
    public static function autowrite_enabled(int $courseid): bool {
        global $DB;
        return (bool) $DB->get_field(self::TABLE_COURSE, 'autowrite', ['courseid' => $courseid]);
    }

    /**
     * Switch automatic writing for a course.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param int $actorid
     */
    public static function set_autowrite(int $courseid, bool $enabled, int $actorid): void {
        global $DB;
        $record = $DB->get_record(self::TABLE_COURSE, ['courseid' => $courseid]);
        $old = $record ? (int) $record->autowrite : 0;
        if ($record) {
            $record->autowrite = (int) $enabled;
            $record->usermodified = $actorid;
            $record->timemodified = time();
            $DB->update_record(self::TABLE_COURSE, $record);
        } else {
            $DB->insert_record(self::TABLE_COURSE, (object) [
                'courseid' => $courseid,
                'autowrite' => (int) $enabled,
                'usermodified' => $actorid,
                'timemodified' => time(),
            ]);
        }
        if ($old !== (int) $enabled) {
            self::audit(0, 0, null, $actorid, 'autowrite', (string) $old, (string) (int) $enabled, 'course ' . $courseid);
            event\autowrite_changed::create([
                'context' => \context_course::instance($courseid),
                'other' => ['enabled' => (int) $enabled],
            ])->trigger();
        }
    }

    // Links.

    /**
     * Fetch a link.
     *
     * @param int $id
     * @param int $strictness
     * @return stdClass|false
     */
    public static function get_link(int $id, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record(self::TABLE_LINK, ['id' => $id], '*', $strictness);
    }

    /**
     * Create or update the link between a BBB activity and an attendance session.
     *
     * @param stdClass $data courseid, bbbid, attendanceid, sessionid, windowstart, windowend,
     *                       threshold (nullable), cancelled, optionally id
     * @param int $actorid
     * @return stdClass the saved link
     */
    public static function save_link(stdClass $data, int $actorid): stdClass {
        global $DB;

        if ((int) $data->windowend <= (int) $data->windowstart) {
            throw new \moodle_exception('error:window', 'local_hikmahattendance');
        }
        $session = $DB->get_record('attendance_sessions', ['id' => $data->sessionid], '*', MUST_EXIST);
        if ((int) $session->attendanceid !== (int) $data->attendanceid) {
            throw new \moodle_exception('error:session', 'local_hikmahattendance');
        }
        $attendance = $DB->get_record('attendance', ['id' => $data->attendanceid], 'id, course', MUST_EXIST);
        $bbb = $DB->get_record('bigbluebuttonbn', ['id' => $data->bbbid], 'id, course', MUST_EXIST);
        if ((int) $attendance->course !== (int) $data->courseid || (int) $bbb->course !== (int) $data->courseid) {
            throw new \moodle_exception('error:othercourse', 'local_hikmahattendance');
        }

        $now = time();
        $threshold = isset($data->threshold) && $data->threshold !== '' && $data->threshold !== null
            ? max(1, min(100, (int) $data->threshold)) : null;
        $record = (object) [
            'courseid' => (int) $data->courseid,
            'bbbid' => (int) $data->bbbid,
            'attendanceid' => (int) $data->attendanceid,
            'sessionid' => (int) $data->sessionid,
            'windowstart' => (int) $data->windowstart,
            'windowend' => (int) $data->windowend,
            'threshold' => $threshold,
            'cancelled' => empty($data->cancelled) ? 0 : 1,
            'usermodified' => $actorid,
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $existing = self::get_link((int) $data->id);
            $record->id = $existing->id;
            $record->state = $record->cancelled ? self::LINK_CANCELLED : self::LINK_WAITING;
            $DB->update_record(self::TABLE_LINK, $record);
            self::audit((int) $record->id, $record->sessionid, null, $actorid, 'link_updated', '', '', '');
        } else {
            if ($DB->record_exists(self::TABLE_LINK, ['sessionid' => $record->sessionid])) {
                throw new \moodle_exception('error:alreadylinked', 'local_hikmahattendance');
            }
            $record->state = $record->cancelled ? self::LINK_CANCELLED : self::LINK_WAITING;
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE_LINK, $record);
            self::audit((int) $record->id, $record->sessionid, null, $actorid, 'link_created', '', '', '');
        }
        return self::get_link((int) $record->id);
    }

    /**
     * Remove a link and its unapplied decisions. Marks already in the register stay there.
     *
     * @param int $linkid
     * @param int $actorid
     */
    public static function delete_link(int $linkid, int $actorid): void {
        global $DB;
        $link = self::get_link($linkid);
        $DB->delete_records(self::TABLE_DECISION, ['linkid' => $linkid]);
        $DB->delete_records(self::TABLE_LINK, ['id' => $linkid]);
        self::audit($linkid, (int) $link->sessionid, null, $actorid, 'link_deleted', '', '', '');
    }

    /**
     * Add a session to an attendance activity through mod_attendance. No calendar event: the live
     * class already puts the class in the calendar.
     *
     * @param stdClass $attendance attendance record
     * @param int $start
     * @param int $duration seconds
     * @param string $description
     * @return int session id
     */
    public static function create_session(stdClass $attendance, int $start, int $duration, string $description): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        $course = get_course($attendance->course);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
        $structure = new \mod_attendance_structure($attendance, $cm, $course, \context_module::instance($cm->id));
        return (int) $structure->add_session((object) [
            'sessdate' => $start,
            'duration' => $duration,
            'groupid' => 0,
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'descriptionitemid' => 0,
            'timemodified' => time(),
            'statusset' => 0,
            'calendarevent' => 0,
            'absenteereport' => 1,
        ]);
    }

    /**
     * The standard set-up for a scheduled live class (a room with an opening and closing time):
     * an attendance session for that time, linked to the room, in a course that marks attendance
     * automatically. Creates what is missing and moves a not-yet-held class when the room's times
     * change. Rooms without both times, or linked to several sessions by hand, are left alone.
     *
     * @param int $bbbid
     * @param int $actorid
     * @return string|null 'created', 'moved' or null when nothing changed
     */
    public static function ensure_for_room(int $bbbid, int $actorid): ?string {
        global $CFG, $DB;
        $room = $DB->get_record('bigbluebuttonbn', ['id' => $bbbid], 'id, course, openingtime, closingtime');
        if (!$room || (int) $room->openingtime <= 0 || (int) $room->closingtime <= (int) $room->openingtime) {
            return null;
        }
        $start = (int) $room->openingtime;
        $end = (int) $room->closingtime;
        $courseid = (int) $room->course;
        $result = null;

        $links = $DB->get_records(self::TABLE_LINK, ['bbbid' => $room->id]);
        if (count($links) > 1) {
            return null;
        }
        if ($link = reset($links)) {
            $moved = (int) $link->windowstart !== $start || (int) $link->windowend !== $end;
            if ($moved && $link->state === self::LINK_WAITING) {
                // Shortcut: direct update, fine because these sessions have no calendar event of their own.
                $DB->update_record(
                    'attendance_sessions',
                    (object) ['id' => $link->sessionid, 'sessdate' => $start, 'duration' => $end - $start, 'timemodified' => time()]
                );
                $link->windowstart = $start;
                $link->windowend = $end;
                self::save_link($link, $actorid);
                $result = 'moved';
            }
        } else {
            $attendance = $DB->get_records('attendance', ['course' => $courseid], 'id', '*', 0, 1);
            $attendance = reset($attendance);
            if (!$attendance) {
                require_once($CFG->dirroot . '/course/lib.php');
                $info = create_module((object) [
                    'modulename' => 'attendance',
                    'course' => $courseid,
                    'section' => 0,
                    'visible' => 1,
                    'cmidnumber' => '',
                    'name' => get_string('modulename', 'attendance'),
                    'grade' => 100,
                    'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ]);
                $attendance = $DB->get_record('attendance', ['id' => $info->instance], '*', MUST_EXIST);
            }
            // A register session the teacher already made for this start time is used, not duplicated.
            $sessionid = (int) $DB->get_field_sql('SELECT s.id FROM {attendance_sessions} s
                    LEFT JOIN {' . self::TABLE_LINK . '} l ON l.sessionid = s.id
                    WHERE s.attendanceid = ? AND s.sessdate = ? AND l.id IS NULL', [$attendance->id, $start], IGNORE_MULTIPLE);
            if (!$sessionid) {
                // From the database: the course cache may not know the new room yet inside its creation event.
                $cm = get_coursemodule_from_instance('bigbluebuttonbn', $room->id, $courseid, false, MUST_EXIST);
                $sectionnum = (int) $DB->get_field('course_sections', 'section', ['id' => $cm->section]);
                $sessionid = self::create_session(
                    $attendance,
                    $start,
                    $end - $start,
                    get_string('sessiondescription', 'local_hikmahattendance', get_section_name($courseid, $sectionnum))
                );
            }
            self::save_link((object) [
                'courseid' => $courseid,
                'bbbid' => (int) $room->id,
                'attendanceid' => (int) $attendance->id,
                'sessionid' => $sessionid,
                'windowstart' => $start,
                'windowend' => $end,
                'threshold' => null,
                'cancelled' => 0,
            ], $actorid);
            $result = 'created';
        }

        // Scheduled classes mark attendance on their own, unless someone has decided otherwise for this course.
        if (!$DB->record_exists(self::TABLE_COURSE, ['courseid' => $courseid])) {
            self::set_autowrite($courseid, true, $actorid);
            $result = $result ?? 'autowrite';
        }
        return $result;
    }

    // Evidence.

    /**
     * Read the stored BBB evidence for a link.
     *
     * @param stdClass $link
     * @return stdClass {callbackcount, summaries: userid => attendee[], joined: userid => true}
     */
    public static function read_evidence(stdClass $link): stdClass {
        global $DB;

        $from = (int) $link->windowstart;
        $to = (int) $link->windowend + self::EVIDENCE_GRACE;

        $callbackcount = $DB->count_records_select(
            'bigbluebuttonbn_logs',
            'bigbluebuttonbnid = :bbbid AND log = :log AND timecreated >= :tfrom AND timecreated <= :tto AND ' .
                $DB->sql_like('meta', ':meta'),
            ['bbbid' => $link->bbbid, 'log' => 'Callback', 'tfrom' => $from, 'tto' => $to, 'meta' => '%meeting_events%']
        );

        $summaries = [];
        $rows = $DB->get_records_select(
            'bigbluebuttonbn_logs',
            'bigbluebuttonbnid = :bbbid AND log = :log AND timecreated >= :tfrom AND timecreated <= :tto',
            ['bbbid' => $link->bbbid, 'log' => 'Summary', 'tfrom' => $from, 'tto' => $to],
            'id ASC',
            'id, userid, meta'
        );
        foreach ($rows as $row) {
            $meta = json_decode((string) $row->meta);
            // A summary row without attendee data is itself an unknown shape.
            $summaries[(int) $row->userid][] = isset($meta->data) ? $meta->data : new stdClass();
        }

        $joined = [];
        $joins = $DB->get_records_select(
            'bigbluebuttonbn_logs',
            'bigbluebuttonbnid = :bbbid AND log = :log AND timecreated >= :tfrom AND timecreated <= :tto',
            ['bbbid' => $link->bbbid, 'log' => 'Join', 'tfrom' => $from - self::JOIN_LEAD, 'tto' => (int) $link->windowend],
            '',
            'DISTINCT userid'
        );
        foreach ($joins as $join) {
            $joined[(int) $join->userid] = true;
        }

        return (object) ['callbackcount' => $callbackcount, 'summaries' => $summaries, 'joined' => $joined];
    }

    /**
     * Learners who can be marked for a link, and course staff who are excluded.
     *
     * @param stdClass $link
     * @return array [int[] learner ids, int[] staff ids]
     */
    public static function roster(stdClass $link): array {
        $context = \context_course::instance($link->courseid);
        $listed = get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id', null, 0, 0, true);
        $staff = get_enrolled_users($context, 'mod/attendance:takeattendances', 0, 'u.id', null, 0, 0, false);
        $staffids = array_map('intval', array_keys($staff));
        $learnerids = array_values(array_diff(array_map('intval', array_keys($listed)), $staffids));
        return [$learnerids, $staffids];
    }

    // Processing.

    /**
     * Compute decisions for a link from the stored evidence. Safe to run any number of times:
     * identical evidence changes nothing, new evidence creates a new preview.
     *
     * @param int $linkid
     * @param int|null $now override the clock (tests)
     * @return stdClass the link after processing
     */
    public static function process_link(int $linkid, ?int $now = null): stdClass {
        global $DB;

        $now = $now ?? time();
        $link = self::get_link($linkid);

        $setstate = static function (string $state, ?string $note = null) use ($link, $now, $DB): stdClass {
            $link->state = $state;
            $link->note = $note;
            $link->timeprocessed = $now;
            $link->timemodified = $now;
            $DB->update_record(self::TABLE_LINK, $link);
            return $link;
        };

        if ($link->cancelled) {
            return $setstate(self::LINK_CANCELLED);
        }
        if ($now < (int) $link->windowend) {
            return $setstate(self::LINK_WAITING);
        }

        $evidence = self::read_evidence($link);
        $link->callbackcount = $evidence->callbackcount;
        $joinsonly = false;
        if ($evidence->callbackcount === 0) {
            $hours = (float) (get_config('local_hikmahattendance', 'missinghours') ?: 2);
            $late = $now > (int) $link->windowend + (int) ($hours * HOURSECS);
            if (!$late || !self::autowrite_enabled((int) $link->courseid)) {
                return $setstate($late ? self::LINK_MISSING : self::LINK_WAITING);
            }
            // Automatic course and BBB never reported: mark from Moodle's join log (joined = present).
            // A report arriving later still replaces these marks with measured ones.
            $joinsonly = true;
        }

        [$learnerids, $staffids] = self::roster($link);
        $threshold = self::threshold_for($link);
        $denominator = (int) $link->windowend - (int) $link->windowstart;

        // Parse everything first: one unknown shape sends the whole session to the exceptions queue.
        $parsed = [];
        $schemas = [];
        if ($joinsonly) {
            $schemas['joinlog'] = true;
            foreach (array_keys($evidence->joined) as $userid) {
                $parsed[$userid][] = ['intervals' => [], 'total' => $denominator, 'conflict' => false,
                    'notes' => [self::NOTE_JOINSONLY], 'moderator' => false, 'schema' => 'joinlog'];
            }
        }
        try {
            foreach ($evidence->summaries as $userid => $attendees) {
                foreach ($attendees as $attendee) {
                    $entry = parser::parse_attendee($attendee);
                    $parsed[$userid][] = $entry;
                    $schemas[$entry['schema']] = true;
                }
            }
        } catch (unknown_schema_exception $e) {
            return $setstate(self::LINK_UNKNOWN, $e->getMessage());
        }

        $computed = [];
        foreach ($learnerids as $userid) {
            $computed[$userid] = self::compute_decision(
                $link,
                $userid,
                $parsed[$userid] ?? [],
                isset($evidence->joined[$userid]),
                $threshold,
                $denominator,
                ''
            );
        }
        // Participants with a Moodle id who are neither learners nor staff: shown for review, never written.
        $unmapped = 0;
        foreach ($parsed as $userid => $entries) {
            if (isset($computed[$userid]) || in_array($userid, $staffids, true)) {
                continue;
            }
            $ismoderator = (bool) array_filter($entries, static fn(array $entry): bool => $entry['moderator']);
            if ($ismoderator) {
                continue;
            }
            $unmapped++;
            if ($DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
                $computed[$userid] = self::compute_decision(
                    $link,
                    $userid,
                    $entries,
                    isset($evidence->joined[$userid]),
                    $threshold,
                    $denominator,
                    'unmapped'
                );
            }
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($computed as $userid => $decision) {
            self::upsert_decision($link, $userid, $decision, $now);
        }
        // Learners who left the roster no longer have a current decision.
        $current = $DB->get_records_select(
            self::TABLE_DECISION,
            'linkid = :linkid AND state <> :superseded',
            ['linkid' => $link->id, 'superseded' => self::DECISION_SUPERSEDED],
            '',
            'id, userid, state'
        );
        foreach ($current as $row) {
            if (!isset($computed[(int) $row->userid]) && $row->state !== self::DECISION_APPLIED) {
                $DB->set_field(self::TABLE_DECISION, 'state', self::DECISION_SUPERSEDED, ['id' => $row->id]);
            }
        }
        $transaction->allow_commit();

        $link->schemaversion = implode(',', array_keys($schemas)) ?: null;
        $link->bbbversion = self::bbb_version();
        $open = $DB->count_records_select(
            self::TABLE_DECISION,
            'linkid = :linkid AND state IN (:s1, :s2)',
            ['linkid' => $link->id, 's1' => self::DECISION_PREVIEW, 's2' => self::DECISION_OVERRIDDEN]
        );
        $link = $setstate(
            $open ? self::LINK_READY : self::LINK_APPLIED,
            $unmapped ? get_string('linknote_unmapped', 'local_hikmahattendance', $unmapped) : null
        );

        if ($open && self::autowrite_enabled((int) $link->courseid)) {
            self::apply($link->id, (int) get_admin()->id, true);
            $link = self::get_link((int) $link->id);
        }
        return $link;
    }

    /**
     * Work out one learner's decision from parsed attendee entries.
     *
     * @param stdClass $link
     * @param int $userid
     * @param array $entries parsed attendee entries (possibly from several meetings in the window)
     * @param bool $joined whether Moodle logged a join for this learner
     * @param int $threshold
     * @param int $denominator
     * @param string $note
     * @return stdClass
     */
    private static function compute_decision(
        stdClass $link,
        int $userid,
        array $entries,
        bool $joined,
        int $threshold,
        int $denominator,
        string $note
    ): stdClass {
        $intervals = [];
        $totals = 0;
        $conflict = $note === 'unmapped';
        $notes = [];
        $source = 'none';
        foreach ($entries as $entry) {
            $conflict = $conflict || $entry['conflict'];
            $notes = array_merge($notes, $entry['notes']);
            if ($entry['intervals']) {
                $intervals = array_merge($intervals, $entry['intervals']);
                $source = 'intervals';
            } else if ($entry['total'] !== null && $entry['total'] > 0) {
                $totals += $entry['total'];
                if ($source === 'none') {
                    $source = 'total';
                }
            }
        }

        $seconds = calculator::attended_seconds($intervals, (int) $link->windowstart, (int) $link->windowend);
        if (!$intervals && $totals > 0) {
            // Only totals exist: they cannot be clipped to the window, so cap them at its length.
            $seconds = min($totals, $denominator);
        }
        if ($seconds > 0 && !$joined) {
            // BBB reports attendance but Moodle has no join for this person: evidence conflicts.
            $conflict = true;
            $notes[] = 'nojoinlog';
        }

        $percent = calculator::percent($seconds, $denominator);
        $status = calculator::status($percent, $seconds, $joined, $threshold, $conflict);

        return (object) [
            'seconds' => $seconds,
            'denominator' => $denominator,
            'percent' => $percent,
            'threshold' => $threshold,
            'computedstatus' => $status,
            'evidencehash' => calculator::evidence_hash(
                (int) $link->sessionid,
                $userid,
                $seconds,
                $denominator,
                $threshold,
                $status
            ),
            'evidence' => json_encode([
                'intervals' => $intervals,
                'joined' => $joined,
                'source' => $source,
                'notes' => array_values(array_unique($notes)),
            ]),
            'note' => $note !== '' ? $note : null,
        ];
    }

    /**
     * Store a computed decision. Identical evidence is a no-op; different evidence supersedes the
     * current decision and creates (or revives) a preview, so nothing changes silently.
     *
     * @param stdClass $link
     * @param int $userid
     * @param stdClass $decision
     * @param int $now
     */
    private static function upsert_decision(stdClass $link, int $userid, stdClass $decision, int $now): void {
        global $DB;

        $current = $DB->get_records_select(
            self::TABLE_DECISION,
            'sessionid = :sessionid AND userid = :userid AND state <> :superseded',
            ['sessionid' => $link->sessionid, 'userid' => $userid, 'superseded' => self::DECISION_SUPERSEDED],
            'id DESC'
        );
        $current = $current ? reset($current) : null;
        if ($current && $current->evidencehash === $decision->evidencehash) {
            return;
        }
        if ($current) {
            $DB->set_field(self::TABLE_DECISION, 'state', self::DECISION_SUPERSEDED, ['id' => $current->id]);
        }

        $same = $DB->get_record(self::TABLE_DECISION, [
            'sessionid' => $link->sessionid,
            'userid' => $userid,
            'evidencehash' => $decision->evidencehash,
        ]);
        if ($same) {
            $same->state = self::DECISION_PREVIEW;
            $same->finalstatus = null;
            $same->reason = null;
            $same->evidence = $decision->evidence;
            $same->note = $decision->note;
            $same->timemodified = $now;
            $DB->update_record(self::TABLE_DECISION, $same);
            return;
        }

        $decision->linkid = $link->id;
        $decision->sessionid = $link->sessionid;
        $decision->userid = $userid;
        $decision->state = self::DECISION_PREVIEW;
        $decision->timecreated = $now;
        $decision->timemodified = $now;
        $DB->insert_record(self::TABLE_DECISION, $decision);
    }

    /**
     * BBB server version, when Moodle already knows it. Never blocks processing.
     *
     * @return string|null
     */
    private static function bbb_version(): ?string {
        if (PHPUNIT_TEST || (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING)) {
            return null;
        }
        try {
            $cache = \cache::make('mod_bigbluebuttonbn', 'serverinfo');
            $version = $cache->get('serverversion');
            return $version ? (string) $version : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Review.

    /**
     * Current (not superseded) decisions of a link, keyed by user id.
     *
     * @param int $linkid
     * @return stdClass[]
     */
    public static function current_decisions(int $linkid): array {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE_DECISION,
            'linkid = :linkid AND state <> :superseded',
            ['linkid' => $linkid, 'superseded' => self::DECISION_SUPERSEDED],
            'id ASC'
        );
        $byuser = [];
        foreach ($records as $record) {
            $byuser[(int) $record->userid] = $record;
        }
        return $byuser;
    }

    /**
     * The status a decision will be written as: the override when there is one, else the computed one.
     *
     * @param stdClass $decision
     * @return string
     */
    public static function effective_status(stdClass $decision): string {
        return $decision->finalstatus ?: $decision->computedstatus;
    }

    /**
     * Instructor override of one decision. A reason is required and the change is audited.
     *
     * @param int $decisionid
     * @param string $status present, partial, absent or pending
     * @param string $reason
     * @param int $actorid
     * @return bool false when nothing changed
     */
    public static function override(int $decisionid, string $status, string $reason, int $actorid): bool {
        global $DB;

        $valid = [calculator::STATUS_PRESENT, calculator::STATUS_PARTIAL, calculator::STATUS_ABSENT,
            calculator::STATUS_PENDING];
        if (!in_array($status, $valid, true)) {
            throw new \moodle_exception('error:status', 'local_hikmahattendance');
        }
        $decision = $DB->get_record(self::TABLE_DECISION, ['id' => $decisionid], '*', MUST_EXIST);
        if ($decision->state === self::DECISION_SUPERSEDED) {
            throw new \moodle_exception('error:superseded', 'local_hikmahattendance');
        }
        $old = self::effective_status($decision);
        if ($old === $status) {
            return false;
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \moodle_exception('error:reasonrequired', 'local_hikmahattendance');
        }

        $decision->finalstatus = $status === $decision->computedstatus ? null : $status;
        $decision->reason = $reason;
        $decision->state = self::DECISION_OVERRIDDEN;
        $decision->timemodified = time();
        $DB->update_record(self::TABLE_DECISION, $decision);
        $DB->set_field(self::TABLE_LINK, 'state', self::LINK_READY, ['id' => $decision->linkid]);

        self::audit(
            (int) $decision->linkid,
            (int) $decision->sessionid,
            (int) $decision->userid,
            $actorid,
            'override',
            $old,
            $status,
            $reason
        );
        $link = self::get_link((int) $decision->linkid);
        event\decision_overridden::create([
            'context' => \context_course::instance($link->courseid),
            'objectid' => $decision->id,
            'relateduserid' => $decision->userid,
            'other' => ['from' => $old, 'to' => $status, 'sessionid' => (int) $decision->sessionid],
        ])->trigger();
        return true;
    }

    /**
     * Approve the current decisions of a link and write them to the attendance register through
     * mod_attendance. Rules:
     *  - Pending decisions are never written; they stay for a person to resolve.
     *  - A learner who already has a mark that this plugin did not write keeps it (manual mark).
     *  - Participants who are not on the course roster are never written.
     *  - Running it again with nothing new writes nothing.
     *
     * @param int $linkid
     * @param int $actorid
     * @param bool $auto true when called by autowrite
     * @return array {written, skippedmanual, skippedpending, skippedother}
     */
    public static function apply(int $linkid, int $actorid, bool $auto = false): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');

        $link = self::get_link($linkid);
        $result = ['written' => 0, 'skippedmanual' => 0, 'skippedpending' => 0, 'skippedother' => 0];
        if ($link->cancelled) {
            return $result;
        }

        $session = $DB->get_record('attendance_sessions', ['id' => $link->sessionid], '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', ['id' => $link->attendanceid], '*', MUST_EXIST);
        $course = get_course($link->courseid);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $statuses = [];
        foreach (attendance_get_statuses($attendance->id, true, $session->statusset) as $status) {
            $statuses[$status->acronym] = $status;
        }
        $statusset = implode(',', array_map(static fn(stdClass $s): int => (int) $s->id, $statuses));

        $existing = $DB->get_records('attendance_log', ['sessionid' => $session->id], '', 'studentid, id, statusid, timetaken');
        [$learnerids] = self::roster($link);
        $now = time();
        $logs = [];
        $towrite = [];

        foreach (self::current_decisions($linkid) as $userid => $decision) {
            if (
                !in_array(
                    $decision->state,
                    [self::DECISION_PREVIEW, self::DECISION_OVERRIDDEN, self::DECISION_APPROVED],
                    true
                )
            ) {
                continue;
            }
            $status = self::effective_status($decision);
            if ($status === calculator::STATUS_PENDING) {
                $result['skippedpending']++;
                continue;
            }
            if ($decision->note === 'unmapped' || !in_array($userid, $learnerids, true)) {
                $result['skippedother']++;
                continue;
            }
            $acronym = self::acronym_for($status);
            if (!isset($statuses[$acronym])) {
                $decision->note = 'nostatus';
                $decision->state = self::DECISION_APPROVED;
                $decision->timemodified = $now;
                $DB->update_record(self::TABLE_DECISION, $decision);
                $result['skippedother']++;
                continue;
            }
            if (isset($existing[$userid]) && !self::is_own_mark($link, $userid, $existing[$userid])) {
                // Someone marked this learner by hand: never overwritten.
                $decision->note = 'manualmark';
                $decision->state = self::DECISION_APPROVED;
                $decision->timemodified = $now;
                $DB->update_record(self::TABLE_DECISION, $decision);
                self::audit($linkid, (int) $link->sessionid, $userid, $actorid, 'skip_manual', $status, '', '');
                $result['skippedmanual']++;
                continue;
            }

            $logs[$userid] = (object) [
                'studentid' => $userid,
                'statusid' => $statuses[$acronym]->id,
                'statusset' => $statusset,
                'remarks' => str_contains((string) $decision->evidence, self::NOTE_JOINSONLY)
                    ? get_string('logremark_joins', 'local_hikmahattendance')
                    : get_string('logremark', 'local_hikmahattendance', (object) [
                    'percent' => $decision->percent,
                    'minutes' => (int) round($decision->seconds / 60),
                ]),
                'sessionid' => $session->id,
                'timetaken' => $now,
                'takenby' => $actorid,
            ];
            $towrite[$userid] = [$decision, $status, (int) $statuses[$acronym]->id];
        }

        if ($logs) {
            $pageparams = new \mod_attendance_take_page_params();
            $pageparams->sessionid = $session->id;
            $pageparams->grouptype = 0;
            $structure = new \mod_attendance_structure($attendance, $cm, $course, $context, $pageparams);
            $structure->save_log($logs);

            foreach ($towrite as $userid => [$decision, $status, $statusid]) {
                $decision->state = self::DECISION_APPLIED;
                $decision->note = null;
                $decision->appliedstatusid = $statusid;
                $decision->appliedtime = $now;
                $decision->timemodified = $now;
                $DB->update_record(self::TABLE_DECISION, $decision);
                self::audit(
                    $linkid,
                    (int) $link->sessionid,
                    $userid,
                    $actorid,
                    $auto ? 'autoapply' : 'apply',
                    $decision->computedstatus,
                    $status,
                    (string) $decision->reason
                );
                $result['written']++;
            }
        }

        self::audit(
            $linkid,
            (int) $link->sessionid,
            null,
            $actorid,
            $auto ? 'autoapprove' : 'approve',
            '',
            json_encode($result),
            ''
        );
        $open = $DB->count_records_select(
            self::TABLE_DECISION,
            'linkid = :linkid AND state IN (:s1, :s2)',
            ['linkid' => $linkid, 's1' => self::DECISION_PREVIEW, 's2' => self::DECISION_OVERRIDDEN]
        );
        $DB->set_field(self::TABLE_LINK, 'state', $open ? self::LINK_READY : self::LINK_APPLIED, ['id' => $linkid]);

        event\decisions_applied::create([
            'context' => \context_course::instance($link->courseid),
            'objectid' => $linkid,
            'other' => $result + ['sessionid' => (int) $link->sessionid, 'auto' => (int) $auto],
        ])->trigger();
        return $result;
    }

    /**
     * Whether an existing register mark is one this plugin wrote and nobody has changed since.
     *
     * @param stdClass $link
     * @param int $userid
     * @param stdClass $log attendance_log row (statusid, timetaken)
     * @return bool
     */
    private static function is_own_mark(stdClass $link, int $userid, stdClass $log): bool {
        global $DB;
        $applied = $DB->get_records_select(
            self::TABLE_DECISION,
            'sessionid = :sessionid AND userid = :userid AND appliedstatusid IS NOT NULL',
            ['sessionid' => $link->sessionid, 'userid' => $userid],
            'appliedtime DESC, id DESC',
            'id, appliedstatusid, appliedtime',
            0,
            1
        );
        if (!$applied) {
            return false;
        }
        $last = reset($applied);
        return (int) $last->appliedstatusid === (int) $log->statusid && (int) $last->appliedtime === (int) $log->timetaken;
    }

    /**
     * Write an audit row.
     *
     * @param int $linkid
     * @param int $sessionid
     * @param int|null $userid learner concerned
     * @param int $actorid
     * @param string $action
     * @param string $from
     * @param string $to
     * @param string $reason
     */
    public static function audit(
        int $linkid,
        int $sessionid,
        ?int $userid,
        int $actorid,
        string $action,
        string $from,
        string $to,
        string $reason
    ): void {
        global $DB;
        $DB->insert_record(self::TABLE_AUDIT, (object) [
            'linkid' => $linkid,
            'sessionid' => $sessionid,
            'userid' => $userid,
            'actorid' => $actorid,
            'action' => $action,
            'fromvalue' => \core_text::substr($from, 0, 100),
            'tovalue' => \core_text::substr($to, 0, 100),
            'reason' => $reason,
            'timecreated' => time(),
        ]);
    }

    /**
     * Display information for a computed status.
     *
     * @param string $status
     * @return array {key, label, class}
     */
    public static function status_display(string $status): array {
        $classes = [
            calculator::STATUS_PRESENT => 'hk-chip--success',
            calculator::STATUS_PARTIAL => 'hk-chip--warning',
            calculator::STATUS_ABSENT => 'hk-chip--danger',
            calculator::STATUS_PENDING => 'hk-chip--sky',
        ];
        return [
            'key' => $status,
            'label' => get_string('status_' . $status, 'local_hikmahattendance'),
            'class' => $classes[$status] ?? '',
        ];
    }

    /**
     * Display information for a link state.
     *
     * @param string $state
     * @return array {key, label, class}
     */
    public static function link_state_display(string $state): array {
        $classes = [
            self::LINK_WAITING => '',
            self::LINK_MISSING => 'hk-chip--danger',
            self::LINK_UNKNOWN => 'hk-chip--danger',
            self::LINK_READY => 'hk-chip--warning',
            self::LINK_APPLIED => 'hk-chip--success',
            self::LINK_CANCELLED => '',
        ];
        return [
            'key' => $state,
            'label' => get_string('linkstate_' . $state, 'local_hikmahattendance'),
            'class' => $classes[$state] ?? '',
        ];
    }
}
