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

namespace paygw_hikmahtransfer;

/**
 * Tests for the transfer request state machine, idempotent approval and receipt validation.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \paygw_hikmahtransfer\request
 * @covers     \paygw_hikmahtransfer\receipt
 */
final class request_test extends \advanced_testcase {
    /** @var string A valid one-pixel PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var \stdClass Course with a fee enrolment instance. */
    private $course;
    /** @var int Fee enrolment instance id. */
    private $instanceid;
    /** @var \stdClass Learner. */
    private $learner;
    /** @var \stdClass Reviewer. */
    private $reviewer;

    /**
     * Course with a paid enrolment method on an account that uses this gateway.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $account = \core_payment\helper::save_payment_account((object) [
            'name' => 'Hikmah',
            'idnumber' => '',
            'contextid' => \context_system::instance()->id,
        ]);
        \core_payment\helper::save_payment_gateway((object) [
            'accountid' => $account->get('id'),
            'gateway' => 'hikmahtransfer',
            'enabled' => 1,
            'config' => json_encode(['banktitle' => 'Hikmah', 'bankiban' => 'PK00TEST', 'jazzcash' => '0300']),
        ]);

        $this->course = $this->getDataGenerator()->create_course();
        $enabled = explode(',', get_config('core', 'enrol_plugins_enabled'));
        $enabled[] = 'fee';
        set_config('enrol_plugins_enabled', implode(',', array_unique($enabled)));
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->instanceid = enrol_get_plugin('fee')->add_instance($this->course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 4500,
            'currency' => 'PKR',
            'customint1' => $account->get('id'),
            'roleid' => $studentrole->id,
        ]);

        $this->learner = $this->getDataGenerator()->create_user();
        $this->reviewer = $this->getDataGenerator()->create_user();
    }

    /**
     * Put a file into the learner's draft area and return the draft item id.
     *
     * @param string $filename
     * @param ?string $content
     * @return int
     */
    private function draft(string $filename = 'receipt.png', ?string $content = null): int {
        $this->setUser($this->learner);
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($this->learner->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content ?? base64_decode(self::PNG));
        return $draftitemid;
    }

    /**
     * Submission data.
     *
     * @return \stdClass
     */
    private function data(): \stdClass {
        return (object) ['method' => 'bank', 'txnid' => 'TX-1', 'paidamount' => 4500, 'paiddate' => time()];
    }

    /**
     * A draft request for the learner, submitted and waiting.
     *
     * @return \stdClass
     */
    private function pending_request(): \stdClass {
        $request = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        $this->assertTrue(request::submit((int) $request->id, $this->data(), $this->draft(), (int) $this->learner->id));
        return request::get($request->id);
    }

    /**
     * The transition table matches the PRD state machine.
     */
    public function test_transitions(): void {
        $this->assertTrue(request::can_transition('draft', 'pending'));
        $this->assertTrue(request::can_transition('pending', 'approved'));
        $this->assertTrue(request::can_transition('pending', 'rejected'));
        $this->assertTrue(request::can_transition('pending', 'resubmit'));
        $this->assertTrue(request::can_transition('resubmit', 'pending'));
        $this->assertFalse(request::can_transition('draft', 'approved'));
        $this->assertFalse(request::can_transition('approved', 'pending'));
        $this->assertFalse(request::can_transition('rejected', 'approved'));
        $this->assertFalse(request::can_transition('resubmit', 'approved'));
    }

    /**
     * Opening the pay page twice gives the same draft: no duplicate open request.
     */
    public function test_single_open_request(): void {
        global $DB;
        $first = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        $second = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, $DB->count_records(request::TABLE));
        $this->assertEquals('4500.00', $first->amount);
        $this->assertEquals('PKR', $first->currency);
        $this->assertMatchesRegularExpression(
            '/^HKM-' . $this->course->id . '-' . $this->learner->id . '-[0-9A-F]{6}$/',
            $first->reference
        );
    }

    /**
     * Submitting stores the receipt privately and gives no access.
     */
    public function test_submit_gives_no_access(): void {
        $request = $this->pending_request();
        $this->assertEquals(request::STATUS_PENDING, $request->status);
        $this->assertNotNull(receipt::get_file((int) $request->id));
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $this->learner));
    }

    /**
     * Approval enrols the learner once; approving again changes nothing.
     */
    public function test_approve_is_idempotent(): void {
        global $DB;
        $request = $this->pending_request();

        $sink = $this->redirectEvents();
        $this->assertTrue(request::approve((int) $request->id, (int) $this->reviewer->id, 'ok'));
        $this->assertFalse(request::approve((int) $request->id, (int) $this->reviewer->id, 'again'));
        $approvals = array_filter($sink->get_events(), static fn($e) => $e instanceof event\request_approved);
        $sink->close();

        $this->assertCount(1, $approvals);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $this->learner));
        $this->assertEquals(1, $DB->count_records('payments', ['gateway' => 'hikmahtransfer']));
        $this->assertEquals(1, $DB->count_records('user_enrolments', ['userid' => $this->learner->id]));

        $request = request::get($request->id);
        $this->assertEquals(request::STATUS_APPROVED, $request->status);
        $this->assertEquals($this->reviewer->id, $request->reviewerid);
        $this->assertNotEmpty($request->paymentid);
        $this->assertTrue(request::has_approved('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id));
    }

    /**
     * A reason is required to reject, and rejection gives no access.
     */
    public function test_reject_requires_reason(): void {
        $request = $this->pending_request();
        try {
            request::reject((int) $request->id, (int) $this->reviewer->id, '  ');
            $this->fail('A rejection without a reason must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:reasonrequired', $e->errorcode);
        }
        $this->assertTrue(request::reject((int) $request->id, (int) $this->reviewer->id, 'No such transfer'));
        $this->assertEquals(request::STATUS_REJECTED, request::get($request->id)->status);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $this->learner));
        // A decided request cannot be approved afterwards.
        $this->assertFalse(request::approve((int) $request->id, (int) $this->reviewer->id));
        $this->assertEquals('No such transfer', request::latest_remarks((int) $request->id));
    }

    /**
     * Resubmission loop: pending -> resubmit -> pending -> approved, with full history.
     */
    public function test_resubmit_then_approve(): void {
        $request = $this->pending_request();
        $this->assertTrue(request::request_resubmission((int) $request->id, (int) $this->reviewer->id, 'Blurry'));
        $this->assertEquals(request::STATUS_RESUBMIT, request::get($request->id)->status);

        // Still the same open request for the learner.
        $again = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        $this->assertEquals($request->id, $again->id);

        $this->assertTrue(request::submit(
            (int) $request->id,
            $this->data(),
            $this->draft('second.png'),
            (int) $this->learner->id
        ));
        $this->assertEquals('second.png', receipt::get_file((int) $request->id)->get_filename());
        $this->assertTrue(request::approve((int) $request->id, (int) $this->reviewer->id));

        $states = array_column(request::history((int) $request->id), 'tostatus');
        $this->assertEquals(['draft', 'pending', 'resubmit', 'pending', 'approved'], array_values($states));
    }

    /**
     * Only the owner can submit a request.
     */
    public function test_submit_by_other_user_denied(): void {
        $request = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        $draft = $this->draft();
        $this->expectException(\moodle_exception::class);
        request::submit((int) $request->id, $this->data(), $draft, (int) $this->reviewer->id);
    }

    /**
     * A file that is not really an image or PDF is refused, whatever its name says.
     */
    public function test_invalid_receipt_rejected(): void {
        $request = request::get_or_create_draft('enrol_fee', 'fee', $this->instanceid, (int) $this->learner->id);
        try {
            request::submit(
                (int) $request->id,
                $this->data(),
                $this->draft('fake.png', '<?php echo 1;'),
                (int) $this->learner->id
            );
            $this->fail('A disguised file must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:receipttype', $e->errorcode);
        }
        try {
            request::submit(
                (int) $request->id,
                $this->data(),
                $this->draft('notes.txt', 'hello'),
                (int) $this->learner->id
            );
            $this->fail('A text file must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:receipttype', $e->errorcode);
        }
        $this->assertEquals(request::STATUS_DRAFT, request::get($request->id)->status);
        $this->assertNull(receipt::get_file((int) $request->id));
    }

    /**
     * Review capability is not given to ordinary users or teachers.
     */
    public function test_review_capability(): void {
        $system = \context_system::instance();
        $this->assertFalse(has_capability('paygw/hikmahtransfer:review', $system, $this->learner));
        $this->assertFalse(has_capability('paygw/hikmahtransfer:viewreceipt', $system, $this->learner));
        $this->assertTrue(has_capability('paygw/hikmahtransfer:submit', $system, $this->learner));
        $this->assertTrue(has_capability('paygw/hikmahtransfer:review', $system, get_admin()));
    }

    /**
     * A course teacher is refused the review queue: review.php calls require_capability() in the system context.
     *
     * Behat cannot check this journey, because it fails any step that lands on an error page.
     */
    public function test_teacher_denied_review_queue(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $system = \context_system::instance();
        $this->assertFalse(has_capability('paygw/hikmahtransfer:review', $system, $teacher));
        $this->assertFalse(has_capability('paygw/hikmahtransfer:viewreceipt', $system, $teacher));

        $this->setUser($teacher);
        $this->expectException(\core\exception\required_capability_exception::class);
        require_capability('paygw/hikmahtransfer:review', $system);
    }

    /**
     * The retention task removes old receipts but keeps the request and its history.
     */
    public function test_retention(): void {
        global $DB;
        $request = $this->pending_request();
        request::approve((int) $request->id, (int) $this->reviewer->id);
        set_config('retentiondays', 30, 'paygw_hikmahtransfer');
        $DB->set_field(request::TABLE, 'timemodified', time() - 31 * DAYSECS, ['id' => $request->id]);

        ob_start();
        (new task\cleanup_receipts())->execute();
        ob_end_clean();

        $this->assertNull(receipt::get_file((int) $request->id));
        $this->assertEquals(1, request::get($request->id)->receiptdeleted);
        $this->assertNotEmpty(request::history((int) $request->id));
    }
}
