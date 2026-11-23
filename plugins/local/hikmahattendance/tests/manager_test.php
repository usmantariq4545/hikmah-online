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

/**
 * End-to-end tests: stored BBB evidence -> preview decisions -> override -> apply to the register.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahattendance\manager
 */
final class manager_test extends \advanced_testcase {
    /** @var int Window start: 2026-11-10 13:00:00 UTC. */
    private const START = 1794315600;
    /** @var int Window end. */
    private const END = 1794319200;

    /** @var \stdClass */
    private $course;
    /** @var \stdClass BBB instance. */
    private $bbb;
    /** @var \stdClass Attendance instance. */
    private $attendance;
    /** @var \stdClass Link. */
    private $link;
    /** @var \stdClass[] Users by fixture case. */
    private $users = [];
    /** @var \stdClass */
    private $teacher;

    /**
     * Course with a BBB activity, a register with one session, learners and a teacher.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->bbb = $generator->create_module('bigbluebuttonbn', ['course' => $this->course->id]);
        $this->attendance = $generator->create_module('attendance', ['course' => $this->course->id]);

        foreach (['fullstay', 'latejoin', 'rejoin', 'overlap', 'earlyleave', 'noshow', 'joinonly'] as $case) {
            $this->users[$case] = $generator->create_and_enrol($this->course, 'student');
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->users['outsider'] = $generator->create_user();

        $sessionid = $DB->insert_record('attendance_sessions', (object) [
            'attendanceid' => $this->attendance->id,
            'groupid' => 0,
            'sessdate' => self::START,
            'duration' => 3600,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'statusset' => 0,
            'timemodified' => self::START,
        ]);

        $this->setUser($this->teacher);
        $this->link = manager::save_link((object) [
            'courseid' => $this->course->id,
            'bbbid' => $this->bbb->id,
            'attendanceid' => $this->attendance->id,
            'sessionid' => $sessionid,
            'windowstart' => self::START,
            'windowend' => self::END,
            'threshold' => null,
            'cancelled' => 0,
        ], (int) $this->teacher->id);
    }

    /**
     * Insert a BBB log row the way mod_bigbluebuttonbn does.
     *
     * @param string $log
     * @param int|null $userid
     * @param array $meta
     * @param int $time
     */
    private function log(string $log, ?int $userid, array $meta, int $time): void {
        global $DB;
        $DB->insert_record('bigbluebuttonbn_logs', (object) [
            'courseid' => $this->course->id,
            'bigbluebuttonbnid' => $this->bbb->id,
            'userid' => $userid,
            'timecreated' => $time,
            'meetingid' => 'meeting-' . $this->bbb->id,
            'log' => $log,
            'meta' => json_encode($meta),
        ]);
    }

    /**
     * Store the fixture callback: one Callback row plus a Summary row per attendee, and Join logs.
     *
     * @param string[] $skipjoin fixture cases that get no Moodle join log
     */
    private function store_fixture(array $skipjoin = []): void {
        $payload = json_decode(file_get_contents(__DIR__ . '/fixtures/bbb-3.0-analytics-callback.json'));
        $map = ['FULLSTAY' => 'fullstay', 'LATEJOIN' => 'latejoin', 'REJOIN' => 'rejoin', 'OVERLAP' => 'overlap',
            'EARLYLEAVE' => 'earlyleave', 'OUTSIDER' => 'outsider'];
        $this->log(
            'Callback',
            0,
            ['internalmeetingid' => $payload->internal_meeting_id, 'callback' => 'meeting_events'],
            self::END + 120
        );
        foreach ($payload->data->attendees as $attendee) {
            $userid = $attendee->ext_user_id === 'TEACHER'
                ? $this->teacher->id : $this->users[$map[$attendee->ext_user_id]]->id;
            $attendee->ext_user_id = (string) $userid;
            $this->log(
                'Summary',
                (int) $userid,
                ['recordid' => $payload->internal_meeting_id, 'data' => $attendee],
                self::END + 120
            );
            if (!in_array($attendee->_case, $skipjoin, true)) {
                $this->log('Join', (int) $userid, [], self::START - 60);
            }
        }
        // Someone who opened the room from Moodle but has no BBB attendance record.
        $this->log('Join', (int) $this->users['joinonly']->id, [], self::START + 30);
    }

    /**
     * Decisions computed from the fixture.
     */
    public function test_preview_from_fixture(): void {
        global $DB;
        $this->store_fixture();
        $link = manager::process_link((int) $this->link->id, self::END + 600);
        $this->assertSame(manager::LINK_READY, $link->state);
        $this->assertSame(1, (int) $link->callbackcount);

        $decisions = manager::current_decisions((int) $link->id);
        $expect = [
            'fullstay' => [3600, 100, calculator::STATUS_PRESENT],
            'latejoin' => [2400, 66, calculator::STATUS_PARTIAL],
            'rejoin' => [3000, 83, calculator::STATUS_PRESENT],
            'overlap' => [3000, 83, calculator::STATUS_PRESENT],
            'earlyleave' => [600, 16, calculator::STATUS_PARTIAL],
            'noshow' => [0, 0, calculator::STATUS_ABSENT],
            'joinonly' => [0, 0, calculator::STATUS_PENDING],
        ];
        foreach ($expect as $case => [$seconds, $percent, $status]) {
            $decision = $decisions[$this->users[$case]->id];
            $this->assertEquals($seconds, $decision->seconds, $case);
            $this->assertEquals($percent, $decision->percent, $case);
            $this->assertSame($status, $decision->computedstatus, $case);
            $this->assertSame(manager::DECISION_PREVIEW, $decision->state, $case);
        }
        // The instructor is excluded; the outsider is listed for review only.
        $this->assertArrayNotHasKey($this->teacher->id, $decisions);
        $this->assertSame(calculator::STATUS_PENDING, $decisions[$this->users['outsider']->id]->computedstatus);
        $this->assertSame('unmapped', $decisions[$this->users['outsider']->id]->note);

        // Preview only: nothing in the register.
        $this->assertSame(0, $DB->count_records('attendance_log'));
    }

    /**
     * A repeated callback or a second run changes nothing.
     */
    public function test_replay_safe(): void {
        global $DB;
        $this->store_fixture();
        manager::process_link((int) $this->link->id, self::END + 600);
        $before = $DB->get_records(manager::TABLE_DECISION, null, 'id');

        // Same callback delivered again, and the task running twice more.
        $this->log('Callback', 0, ['internalmeetingid' => 'again', 'callback' => 'meeting_events'], self::END + 300);
        manager::process_link((int) $this->link->id, self::END + 900);
        manager::process_link((int) $this->link->id, self::END + 1200);

        $after = $DB->get_records(manager::TABLE_DECISION, null, 'id');
        $this->assertEquals(array_keys($before), array_keys($after));
        foreach ($before as $id => $row) {
            $this->assertSame($row->evidencehash, $after[$id]->evidencehash);
            $this->assertSame($row->state, $after[$id]->state);
        }
    }

    /**
     * No callback: waiting at first, then the exceptions queue; a late callback is picked up.
     */
    public function test_missing_callback_and_retry(): void {
        $link = manager::process_link((int) $this->link->id, self::END + 600);
        $this->assertSame(manager::LINK_WAITING, $link->state);

        $link = manager::process_link((int) $this->link->id, self::END + 3 * HOURSECS);
        $this->assertSame(manager::LINK_MISSING, $link->state);
        $this->assertEmpty(manager::current_decisions((int) $link->id));

        $this->store_fixture();
        $link = manager::process_link((int) $this->link->id, self::END + 4 * HOURSECS);
        $this->assertSame(manager::LINK_READY, $link->state);
    }

    /**
     * Before the class ends nothing is computed; a cancelled class is skipped.
     */
    public function test_waiting_and_cancelled(): void {
        global $DB;
        $this->store_fixture();
        $this->assertSame(manager::LINK_WAITING, manager::process_link((int) $this->link->id, self::END - 60)->state);

        $DB->set_field(manager::TABLE_LINK, 'cancelled', 1, ['id' => $this->link->id]);
        $this->assertSame(manager::LINK_CANCELLED, manager::process_link((int) $this->link->id, self::END + 600)->state);
        $this->assertEmpty(manager::current_decisions((int) $this->link->id));
    }

    /**
     * An unknown payload shape goes to the exceptions queue.
     */
    public function test_unknown_schema(): void {
        $payload = json_decode(file_get_contents(__DIR__ . '/fixtures/unknown-shape.json'));
        $this->log('Callback', 0, ['internalmeetingid' => 'x', 'callback' => 'meeting_events'], self::END + 60);
        $this->log('Summary', (int) $this->users['fullstay']->id, ['data' => $payload->data->attendees[0]], self::END + 60);
        $link = manager::process_link((int) $this->link->id, self::END + 600);
        $this->assertSame(manager::LINK_UNKNOWN, $link->state);
        $this->assertEmpty(manager::current_decisions((int) $link->id));
    }

    /**
     * BBB time without a Moodle join log is conflicting evidence: pending, never Present.
     */
    public function test_conflict_is_pending(): void {
        $this->store_fixture(['fullstay']);
        manager::process_link((int) $this->link->id, self::END + 600);
        $decision = manager::current_decisions((int) $this->link->id)[$this->users['fullstay']->id];
        $this->assertSame(calculator::STATUS_PENDING, $decision->computedstatus);
        $this->assertEquals(3600, $decision->seconds);
    }

    /**
     * Apply writes marks, keeps manual marks, leaves pending unmarked and is idempotent.
     */
    public function test_apply(): void {
        global $DB;
        $this->store_fixture();
        manager::process_link((int) $this->link->id, self::END + 600);

        // The instructor already marked one learner by hand as Excused.
        $statuses = [];
        foreach (attendance_get_statuses($this->attendance->id) as $status) {
            $statuses[$status->acronym] = $status;
        }
        $DB->insert_record('attendance_log', (object) [
            'sessionid' => $this->link->sessionid,
            'studentid' => $this->users['latejoin']->id,
            'statusid' => $statuses['E']->id,
            'statusset' => implode(',', array_column($statuses, 'id')),
            'timetaken' => self::END + 10,
            'takenby' => $this->teacher->id,
            'remarks' => 'Doctor appointment',
        ]);

        $result = manager::apply((int) $this->link->id, (int) $this->teacher->id);
        $this->assertSame(5, $result['written']); // Fullstay, rejoin, overlap, earlyleave, noshow.
        $this->assertSame(1, $result['skippedmanual']);
        $this->assertSame(2, $result['skippedpending']); // Joinonly and the outsider.

        $log = $DB->get_records('attendance_log', ['sessionid' => $this->link->sessionid], '', 'studentid, statusid, remarks');
        $this->assertEquals($statuses['P']->id, $log[$this->users['fullstay']->id]->statusid);
        $this->assertEquals($statuses['L']->id, $log[$this->users['earlyleave']->id]->statusid);
        $this->assertEquals($statuses['A']->id, $log[$this->users['noshow']->id]->statusid);
        // Manual mark untouched.
        $this->assertEquals($statuses['E']->id, $log[$this->users['latejoin']->id]->statusid);
        $this->assertSame('Doctor appointment', $log[$this->users['latejoin']->id]->remarks);
        // Pending and unmapped never written.
        $this->assertArrayNotHasKey($this->users['joinonly']->id, $log);
        $this->assertArrayNotHasKey($this->users['outsider']->id, $log);

        // Applying again writes nothing new.
        $again = manager::apply((int) $this->link->id, (int) $this->teacher->id);
        $this->assertSame(0, $again['written']);
        $this->assertSame(6, $DB->count_records('attendance_log', ['sessionid' => $this->link->sessionid]));

        // Reprocessing identical evidence after apply changes nothing either.
        manager::process_link((int) $this->link->id, self::END + 1800);
        $decision = manager::current_decisions((int) $this->link->id)[$this->users['fullstay']->id];
        $this->assertSame(manager::DECISION_APPLIED, $decision->state);
    }

    /**
     * Overrides need a reason, are audited, and are what gets written.
     */
    public function test_override(): void {
        global $DB;
        $this->store_fixture();
        manager::process_link((int) $this->link->id, self::END + 600);
        $decision = manager::current_decisions((int) $this->link->id)[$this->users['latejoin']->id];

        try {
            manager::override((int) $decision->id, calculator::STATUS_PRESENT, ' ', (int) $this->teacher->id);
            $this->fail('An override without a reason must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:reasonrequired', $e->errorcode);
        }

        $sink = $this->redirectEvents();
        $this->assertTrue(manager::override(
            (int) $decision->id,
            calculator::STATUS_PRESENT,
            'Audio only, was present',
            (int) $this->teacher->id
        ));
        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof event\decision_overridden);
        $sink->close();
        $this->assertCount(1, $events);

        $audit = $DB->get_records(manager::TABLE_AUDIT, ['action' => 'override']);
        $this->assertCount(1, $audit);
        $row = reset($audit);
        $this->assertSame('partial', $row->fromvalue);
        $this->assertSame('present', $row->tovalue);
        $this->assertEquals($this->teacher->id, $row->actorid);

        manager::apply((int) $this->link->id, (int) $this->teacher->id);
        $present = $DB->get_field('attendance_statuses', 'id', ['attendanceid' => $this->attendance->id, 'acronym' => 'P']);
        $this->assertEquals($present, $DB->get_field(
            'attendance_log',
            'statusid',
            ['sessionid' => $this->link->sessionid, 'studentid' => $this->users['latejoin']->id]
        ));
    }

    /**
     * New evidence after an apply creates a new preview; the register is not changed silently.
     */
    public function test_new_evidence_creates_new_preview(): void {
        global $DB;
        $this->store_fixture();
        manager::process_link((int) $this->link->id, self::END + 600);
        manager::apply((int) $this->link->id, (int) $this->teacher->id);
        $userid = $this->users['earlyleave']->id;
        $before = $DB->get_field('attendance_log', 'statusid', ['sessionid' => $this->link->sessionid, 'studentid' => $userid]);

        // A second meeting in the same window: the learner came back for the rest of the class.
        $this->log('Callback', 0, ['internalmeetingid' => 'second', 'callback' => 'meeting_events'], self::END + 900);
        $this->log('Summary', (int) $userid, ['data' => [
            'ext_user_id' => (string) $userid,
            'joins' => ['2026-11-10T13:10:00Z'],
            'leaves' => ['2026-11-10T14:00:00Z'],
            'duration' => 3000,
        ]], self::END + 900);
        $link = manager::process_link((int) $this->link->id, self::END + 1200);

        $decision = manager::current_decisions((int) $link->id)[$userid];
        $this->assertSame(manager::DECISION_PREVIEW, $decision->state);
        $this->assertSame(calculator::STATUS_PRESENT, $decision->computedstatus);
        $this->assertSame(manager::LINK_READY, $link->state);
        // Register untouched until someone applies.
        $this->assertEquals($before, $DB->get_field(
            'attendance_log',
            'statusid',
            ['sessionid' => $this->link->sessionid, 'studentid' => $userid]
        ));

        // Our own earlier mark may be updated on the next apply.
        $result = manager::apply((int) $link->id, (int) $this->teacher->id);
        $this->assertSame(1, $result['written']);
        $this->assertNotEquals($before, $DB->get_field(
            'attendance_log',
            'statusid',
            ['sessionid' => $this->link->sessionid, 'studentid' => $userid]
        ));
    }

    /**
     * Autowrite ships off, and only writes once the course owner switches it on.
     */
    public function test_autowrite(): void {
        global $DB;
        $this->assertFalse(manager::autowrite_enabled((int) $this->course->id));
        $this->store_fixture();
        manager::process_link((int) $this->link->id, self::END + 600);
        $this->assertSame(0, $DB->count_records('attendance_log'));

        manager::set_autowrite((int) $this->course->id, true, (int) $this->teacher->id);
        // New evidence triggers processing, which now applies automatically.
        $this->log('Summary', (int) $this->users['noshow']->id, ['data' => [
            'joins' => ['2026-11-10T13:00:00Z'], 'leaves' => ['2026-11-10T14:00:00Z'], 'duration' => 3600,
        ]], self::END + 900);
        $this->log('Join', (int) $this->users['noshow']->id, [], self::START);
        $this->setAdminUser();
        manager::process_link((int) $this->link->id, self::END + 1200);
        $this->assertGreaterThan(0, $DB->count_records('attendance_log'));
        // Pending cases are still never written, even with autowrite on.
        $this->assertFalse($DB->record_exists('attendance_log', ['studentid' => $this->users['joinonly']->id]));
    }

    /**
     * Capabilities: students cannot review; teachers can review and apply but not configure.
     */
    public function test_capabilities(): void {
        $context = \context_course::instance($this->course->id);
        $student = $this->users['fullstay'];
        $this->assertFalse(has_capability('local/hikmahattendance:review', $context, $student));
        $this->assertTrue(has_capability('local/hikmahattendance:review', $context, $this->teacher));
        $this->assertTrue(has_capability('local/hikmahattendance:apply', $context, $this->teacher));
        $this->assertFalse(has_capability('local/hikmahattendance:configure', $context, $this->teacher));
        $this->assertFalse(has_capability('local/hikmahattendance:viewreports', $context, $this->teacher));
    }

    /**
     * Automatic course with no BBB report: joined learners Present, the rest Absent; a late report replaces them.
     */
    public function test_missing_callback_marks_from_joins(): void {
        global $DB;
        manager::set_autowrite((int) $this->course->id, true, (int) $this->teacher->id);
        $this->log('Join', (int) $this->users['fullstay']->id, [], self::START + 60);

        $link = manager::process_link((int) $this->link->id, self::END + 3 * HOURSECS);
        $this->assertSame(manager::LINK_APPLIED, $link->state);
        $decisions = manager::current_decisions((int) $link->id);
        $this->assertSame(calculator::STATUS_PRESENT, $decisions[$this->users['fullstay']->id]->computedstatus);
        $this->assertSame(calculator::STATUS_ABSENT, $decisions[$this->users['latejoin']->id]->computedstatus);
        $remark = $DB->get_field(
            'attendance_log',
            'remarks',
            ['sessionid' => $link->sessionid, 'studentid' => $this->users['fullstay']->id]
        );
        $this->assertSame(get_string('logremark_joins', 'local_hikmahattendance'), $remark);

        $this->store_fixture();
        manager::process_link((int) $link->id, self::END + 4 * HOURSECS);
        $remark = $DB->get_field(
            'attendance_log',
            'remarks',
            ['sessionid' => $link->sessionid, 'studentid' => $this->users['latejoin']->id]
        );
        $this->assertNotSame(get_string('logremark_joins', 'local_hikmahattendance'), $remark);
    }

    /**
     * Adding a live class with times creates its session and link and turns autowrite on; moving it moves them.
     */
    public function test_room_with_times_is_set_up(): void {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $room = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id, 'section' => 1,
            'openingtime' => self::START, 'closingtime' => self::END]);

        $link = $DB->get_record(manager::TABLE_LINK, ['bbbid' => $room->id], '*', MUST_EXIST);
        $this->assertSame(self::START, (int) $link->windowstart);
        $session = $DB->get_record('attendance_sessions', ['id' => $link->sessionid], '*', MUST_EXIST);
        $this->assertSame(HOURSECS, (int) $session->duration);
        $this->assertTrue(manager::autowrite_enabled((int) $course->id));

        $DB->set_field('bigbluebuttonbn', 'openingtime', self::START + HOURSECS, ['id' => $room->id]);
        $DB->set_field('bigbluebuttonbn', 'closingtime', self::END + HOURSECS, ['id' => $room->id]);
        $this->assertSame('moved', manager::ensure_for_room((int) $room->id, (int) get_admin()->id));
        $this->assertSame(
            self::START + HOURSECS,
            (int) $DB->get_field('attendance_sessions', 'sessdate', ['id' => $link->sessionid])
        );
        $this->assertNull(manager::ensure_for_room((int) $room->id, (int) get_admin()->id));
        $this->assertSame(1, $DB->count_records('attendance', ['course' => $course->id]));
    }
}
