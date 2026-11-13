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

use core_payment\helper as payment_helper;
use stdClass;

/**
 * Transfer requests and their state machine (PRD ENR-02 to ENR-05).
 *
 *   draft -> pending -> approved | rejected | resubmit,   resubmit -> pending
 *
 * Every transition is serialised with a lock, runs in a database transaction, writes a history
 * row and fires an event. Approval delivers the order exactly once: a second approval of the
 * same request finds it no longer pending and does nothing.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    /** @var string Request table. */
    public const TABLE = 'paygw_hikmahtransfer_req';
    /** @var string History table. */
    public const TABLE_HISTORY = 'paygw_hikmahtransfer_hist';
    /** @var string Gateway short name. */
    public const GATEWAY = 'hikmahtransfer';
    /** @var string File area for receipts (system context, itemid = request id). */
    public const FILEAREA = 'receipt';

    /** @var string Created, nothing submitted yet. */
    public const STATUS_DRAFT = 'draft';
    /** @var string Receipt submitted, waiting for a reviewer. */
    public const STATUS_PENDING = 'pending';
    /** @var string Verified; access delivered. */
    public const STATUS_APPROVED = 'approved';
    /** @var string Refused by a reviewer. */
    public const STATUS_REJECTED = 'rejected';
    /** @var string Returned to the learner for a better receipt or details. */
    public const STATUS_RESUBMIT = 'resubmit';

    /** @var string[] States in which a request still blocks a new one for the same item. */
    public const OPEN_STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_RESUBMIT];

    /** @var array Allowed transitions: from => [to, ...]. */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING],
        self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_RESUBMIT],
        self::STATUS_RESUBMIT => [self::STATUS_PENDING],
        self::STATUS_APPROVED => [],
        self::STATUS_REJECTED => [],
    ];

    /** @var string[] Transfer methods. */
    public const METHODS = ['bank', 'jazzcash', 'easypaisa', 'raast'];

    /**
     * Whether a transition is allowed by the state machine.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function can_transition(string $from, string $to): bool {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Fetch one request.
     *
     * @param int $id
     * @param int $strictness
     * @return stdClass|false
     */
    public static function get(int $id, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id], '*', $strictness);
    }

    /**
     * The newest request a learner has for an item, whatever its state.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return stdClass|null
     */
    public static function latest_for_user(string $component, string $paymentarea, int $itemid, int $userid): ?stdClass {
        global $DB;
        $records = $DB->get_records(self::TABLE, [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $userid,
        ], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * The open (draft, pending or resubmit) request a learner has for an item, if any.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return stdClass|null
     */
    public static function open_for_user(string $component, string $paymentarea, int $itemid, int $userid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['openkey' => self::open_key($component, $paymentarea, $itemid, $userid)]);
        return $record ?: null;
    }

    /**
     * Whether a learner already has an approved request for an item.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    public static function has_approved(string $component, string $paymentarea, int $itemid, int $userid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $userid,
            'status' => self::STATUS_APPROVED,
        ]);
    }

    /**
     * Value of the unique "open" key for an item and learner.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return string
     */
    public static function open_key(string $component, string $paymentarea, int $itemid, int $userid): string {
        return "open/$component/$paymentarea/$itemid/$userid";
    }

    /**
     * Return the learner's open request for an item, creating a draft if there is none.
     * The unique open key makes a duplicate open request impossible, even under a race.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return stdClass
     */
    public static function get_or_create_draft(string $component, string $paymentarea, int $itemid, int $userid): stdClass {
        global $DB;

        if ($existing = self::open_for_user($component, $paymentarea, $itemid, $userid)) {
            return $existing;
        }

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = payment_helper::get_gateway_surcharge(self::GATEWAY);
        $courseid = self::resolve_courseid($component, $paymentarea, $itemid);
        $now = time();

        $record = new stdClass();
        $record->component = $component;
        $record->paymentarea = $paymentarea;
        $record->itemid = $itemid;
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->accountid = $payable->get_account_id();
        $record->amount = payment_helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $record->currency = $payable->get_currency();
        $record->reference = self::make_reference($courseid ?: $itemid, $userid);
        $record->status = self::STATUS_DRAFT;
        $record->openkey = self::open_key($component, $paymentarea, $itemid, $userid);
        $record->timecreated = $now;
        $record->timemodified = $now;

        try {
            $record->id = $DB->insert_record(self::TABLE, $record);
        } catch (\dml_write_exception $e) {
            // Another request created the open draft a moment ago.
            if ($existing = self::open_for_user($component, $paymentarea, $itemid, $userid)) {
                return $existing;
            }
            throw $e;
        }
        self::add_history($record->id, null, self::STATUS_DRAFT, $userid, '');
        return self::get($record->id);
    }

    /**
     * Build a unique, human-readable payment reference: HKM-<course>-<user>-<hash>.
     *
     * @param int $courseoritem
     * @param int $userid
     * @return string
     */
    public static function make_reference(int $courseoritem, int $userid): string {
        global $DB;
        do {
            $hash = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $reference = "HKM-$courseoritem-$userid-$hash";
        } while ($DB->record_exists(self::TABLE, ['reference' => $reference]));
        return $reference;
    }

    /**
     * The course a payable item belongs to, when the owning component is a course enrolment.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return int course id or 0
     */
    public static function resolve_courseid(string $component, string $paymentarea, int $itemid): int {
        global $DB;
        if (strpos($component, 'enrol_') === 0) {
            return (int) $DB->get_field('enrol', 'courseid', ['id' => $itemid]);
        }
        return 0;
    }

    /**
     * Short description of what a request pays for.
     *
     * @param stdClass $request
     * @return string
     */
    public static function describe(stdClass $request): string {
        global $DB;
        if ($request->courseid && ($course = $DB->get_record('course', ['id' => $request->courseid]))) {
            return format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
        }
        return $request->component . ' / ' . $request->paymentarea . ' #' . $request->itemid;
    }

    /**
     * Learner submits (or resubmits) transfer details and a receipt: draft|resubmit -> pending.
     *
     * The receipt is taken from a draft file area. Every file is checked for type and size and
     * passed through the antivirus manager before it is stored; an infected or invalid file
     * aborts the submission and nothing changes.
     *
     * @param int $id request id
     * @param stdClass $data method, txnid, paidamount, paiddate
     * @param int $draftitemid draft area holding the receipt
     * @param int $userid acting user (must own the request)
     * @return bool false when the request was not in a submittable state
     */
    public static function submit(int $id, stdClass $data, int $draftitemid, int $userid): bool {
        global $DB;

        $request = self::get($id);
        if ((int) $request->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('submitreceipt', 'paygw_hikmahtransfer'));
        }
        if (!in_array($data->method ?? '', self::METHODS, true)) {
            throw new \moodle_exception('error:method', 'paygw_hikmahtransfer');
        }

        $scanstatus = receipt::validate_draft($draftitemid, $userid);

        $changed = self::change_status(
            $id,
            [self::STATUS_DRAFT, self::STATUS_RESUBMIT],
            self::STATUS_PENDING,
            $userid,
            '',
            static function (stdClass $request) use ($data, $draftitemid, $scanstatus): void {
                $request->method = $data->method;
                $request->txnid = trim((string) $data->txnid);
                $request->paidamount = (float) $data->paidamount;
                $request->paiddate = (int) $data->paiddate;
                $request->scanstatus = $scanstatus;
                $request->receiptdeleted = 0;
                $request->timesubmitted = time();
                receipt::store_from_draft($draftitemid, (int) $request->id);
            }
        );

        if ($changed) {
            $request = self::get($id);
            event\request_submitted::create_from_request($request)->trigger();
            notifier::submitted($request);
        }
        return $changed;
    }

    /**
     * Reviewer approves: pending -> approved, then the paid item is delivered exactly once
     * through the standard Payment API path (save_payment + deliver_order).
     *
     * @param int $id
     * @param int $reviewerid
     * @param string $remarks
     * @return bool false when the request was not pending (already decided): nothing happens
     */
    public static function approve(int $id, int $reviewerid, string $remarks = ''): bool {
        $changed = self::change_status(
            $id,
            [self::STATUS_PENDING],
            self::STATUS_APPROVED,
            $reviewerid,
            $remarks,
            static function (stdClass $request) use ($reviewerid): void {
                $request->reviewerid = $reviewerid;
                $request->paymentid = payment_helper::save_payment(
                    (int) $request->accountid,
                    $request->component,
                    $request->paymentarea,
                    (int) $request->itemid,
                    (int) $request->userid,
                    (float) $request->amount,
                    $request->currency,
                    self::GATEWAY
                );
                $delivered = payment_helper::deliver_order(
                    $request->component,
                    $request->paymentarea,
                    (int) $request->itemid,
                    (int) $request->paymentid,
                    (int) $request->userid
                );
                if (!$delivered) {
                    // Rolls the whole approval back: no payment row, request stays pending.
                    throw new \moodle_exception('error:delivery', 'paygw_hikmahtransfer');
                }
            }
        );

        if ($changed) {
            $request = self::get($id);
            event\request_approved::create_from_request($request)->trigger();
            notifier::decision($request, $remarks);
        }
        return $changed;
    }

    /**
     * Reviewer rejects: pending -> rejected. A reason is required.
     *
     * @param int $id
     * @param int $reviewerid
     * @param string $remarks
     * @return bool
     */
    public static function reject(int $id, int $reviewerid, string $remarks): bool {
        return self::decide($id, self::STATUS_REJECTED, $reviewerid, $remarks);
    }

    /**
     * Reviewer asks the learner to resubmit: pending -> resubmit. A reason is required.
     *
     * @param int $id
     * @param int $reviewerid
     * @param string $remarks
     * @return bool
     */
    public static function request_resubmission(int $id, int $reviewerid, string $remarks): bool {
        return self::decide($id, self::STATUS_RESUBMIT, $reviewerid, $remarks);
    }

    /**
     * Shared code for the two negative decisions.
     *
     * @param int $id
     * @param string $to
     * @param int $reviewerid
     * @param string $remarks
     * @return bool
     */
    private static function decide(int $id, string $to, int $reviewerid, string $remarks): bool {
        $remarks = trim($remarks);
        if ($remarks === '') {
            throw new \moodle_exception('error:reasonrequired', 'paygw_hikmahtransfer');
        }
        $changed = self::change_status(
            $id,
            [self::STATUS_PENDING],
            $to,
            $reviewerid,
            $remarks,
            static function (stdClass $request) use ($reviewerid): void {
                $request->reviewerid = $reviewerid;
            }
        );
        if ($changed) {
            $request = self::get($id);
            if ($to === self::STATUS_REJECTED) {
                event\request_rejected::create_from_request($request)->trigger();
            } else {
                event\request_resubmit_requested::create_from_request($request)->trigger();
            }
            notifier::decision($request, $remarks);
        }
        return $changed;
    }

    /**
     * Move a request from one of the allowed states to a new state, atomically.
     *
     * @param int $id request id
     * @param string[] $from states the request must currently be in
     * @param string $to target state
     * @param int $actorid who is doing this
     * @param string $remarks stored in the history row
     * @param callable|null $inside extra work inside the transaction; receives the request record
     * @return bool true when the transition happened, false when the request was in another state
     */
    private static function change_status(
        int $id,
        array $from,
        string $to,
        int $actorid,
        string $remarks,
        ?callable $inside = null
    ): bool {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('paygw_hikmahtransfer');
        $lock = $factory->get_lock('request_' . $id, 20);
        if (!$lock) {
            throw new \moodle_exception('error:busy', 'paygw_hikmahtransfer');
        }

        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();
            $request = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
            if (!in_array($request->status, $from, true) || !self::can_transition($request->status, $to)) {
                $transaction->allow_commit();
                return false;
            }
            $old = $request->status;
            $request->status = $to;
            $request->timemodified = time();
            $request->openkey = in_array($to, self::OPEN_STATUSES, true)
                ? self::open_key($request->component, $request->paymentarea, (int) $request->itemid, (int) $request->userid)
                : 'closed-' . $request->id;
            if ($inside) {
                $inside($request);
            }
            $DB->update_record(self::TABLE, $request);
            self::add_history((int) $request->id, $old, $to, $actorid, $remarks);
            $transaction->allow_commit();
            return true;
        } catch (\Throwable $e) {
            if ($transaction && !$transaction->is_disposed()) {
                try {
                    $transaction->rollback($e);
                } catch (\Throwable $rethrown) {
                    throw $rethrown;
                }
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Append a history row.
     *
     * @param int $requestid
     * @param string|null $from
     * @param string $to
     * @param int $userid
     * @param string $remarks
     */
    private static function add_history(int $requestid, ?string $from, string $to, int $userid, string $remarks): void {
        global $DB;
        $DB->insert_record(self::TABLE_HISTORY, (object) [
            'requestid' => $requestid,
            'fromstatus' => $from,
            'tostatus' => $to,
            'userid' => $userid,
            'remarks' => $remarks,
            'timecreated' => time(),
        ]);
    }

    /**
     * History of a request, oldest first.
     *
     * @param int $requestid
     * @return stdClass[]
     */
    public static function history(int $requestid): array {
        global $DB;
        return $DB->get_records(self::TABLE_HISTORY, ['requestid' => $requestid], 'timecreated ASC, id ASC');
    }

    /**
     * The reviewer's latest remarks on a request (shown to the learner).
     *
     * @param int $requestid
     * @return string
     */
    public static function latest_remarks(int $requestid): string {
        global $DB;
        $rows = $DB->get_records_select(
            self::TABLE_HISTORY,
            "requestid = :id AND tostatus IN ('rejected', 'resubmit', 'approved')",
            ['id' => $requestid],
            'id DESC',
            'id, remarks',
            0,
            1
        );
        return $rows ? (string) reset($rows)->remarks : '';
    }

    /**
     * Number of requests waiting for review.
     *
     * @return int
     */
    public static function count_pending(): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['status' => self::STATUS_PENDING]);
    }

    /**
     * Display information for a status: label and chip class.
     *
     * @param string $status
     * @return array {label, class, key}
     */
    public static function status_display(string $status): array {
        $classes = [
            self::STATUS_DRAFT => '',
            self::STATUS_PENDING => 'hk-chip--warning',
            self::STATUS_APPROVED => 'hk-chip--success',
            self::STATUS_REJECTED => 'hk-chip--danger',
            self::STATUS_RESUBMIT => 'hk-chip--sky',
        ];
        return [
            'key' => $status,
            'label' => get_string('status_' . $status, 'paygw_hikmahtransfer'),
            'class' => $classes[$status] ?? '',
        ];
    }
}
