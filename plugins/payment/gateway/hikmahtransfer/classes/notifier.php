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

use core\message\message;
use moodle_url;
use stdClass;

/**
 * Notifications for learners and reviewers (PRD OPS-01). A failed notification never blocks or
 * reverses a payment decision.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Tell the learner the receipt arrived, and tell reviewers there is work waiting.
     *
     * @param stdClass $request
     */
    public static function submitted(stdClass $request): void {
        $learner = \core_user::get_user($request->userid);
        if (!$learner) {
            return;
        }
        $a = self::placeholders($request, $learner);
        $statusurl = new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $request->id]);
        self::send('submitted', $learner, 'msg_submitted_subject', 'msg_submitted_body', $a, $statusurl);

        if (get_config('paygw_hikmahtransfer', 'reviewerdigest')) {
            return; // Reviewers get one daily digest instead of a message per receipt.
        }
        $reviewurl = new moodle_url('/payment/gateway/hikmahtransfer/review.php', ['id' => $request->id]);
        foreach (self::reviewers() as $reviewer) {
            self::send('newsubmission', $reviewer, 'msg_new_subject', 'msg_new_body', $a, $reviewurl);
        }
    }

    /**
     * Tell the learner about a decision (approved, rejected or resubmission requested).
     *
     * @param stdClass $request
     * @param string $remarks
     */
    public static function decision(stdClass $request, string $remarks): void {
        $learner = \core_user::get_user($request->userid);
        if (!$learner) {
            return;
        }
        $a = self::placeholders($request, $learner);
        $a->remarks = $remarks !== '' ? $remarks : get_string('noremarks', 'paygw_hikmahtransfer');
        $url = new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $request->id]);
        $key = 'msg_' . $request->status;
        self::send('decision', $learner, $key . '_subject', $key . '_body', $a, $url);
    }

    /**
     * Daily digest to reviewers.
     *
     * @param int $pending number of requests waiting
     */
    public static function digest(int $pending): void {
        $a = (object) ['count' => $pending];
        $url = new moodle_url('/payment/gateway/hikmahtransfer/review.php');
        foreach (self::reviewers() as $reviewer) {
            self::send('newsubmission', $reviewer, 'msg_digest_subject', 'msg_digest_body', $a, $url);
        }
    }

    /**
     * Users who can review payments.
     *
     * @return stdClass[]
     */
    public static function reviewers(): array {
        return get_users_by_capability(
            \context_system::instance(),
            'paygw/hikmahtransfer:review',
            'u.*',
            'u.id',
            '',
            '',
            '',
            '',
            false,
            true
        );
    }

    /**
     * Values available to message templates.
     *
     * @param stdClass $request
     * @param stdClass $learner
     * @return stdClass
     */
    private static function placeholders(stdClass $request, stdClass $learner): stdClass {
        return (object) [
            'fullname' => fullname($learner),
            'item' => request::describe($request),
            'reference' => $request->reference,
            'amount' => \core_payment\helper::get_cost_as_string((float) $request->amount, $request->currency),
            'remarks' => '',
        ];
    }

    /**
     * Send one notification.
     *
     * @param string $provider message provider name
     * @param stdClass $to recipient
     * @param string $subjectkey language string for the subject
     * @param string $bodykey language string for the body
     * @param stdClass $a placeholders
     * @param moodle_url $url link included with the message
     */
    private static function send(
        string $provider,
        stdClass $to,
        string $subjectkey,
        string $bodykey,
        stdClass $a,
        moodle_url $url
    ): void {
        try {
            $strings = get_string_manager();
            $lang = $to->lang ?? null;
            $subject = $strings->get_string($subjectkey, 'paygw_hikmahtransfer', $a, $lang);
            $body = $strings->get_string($bodykey, 'paygw_hikmahtransfer', $a, $lang);

            $message = new message();
            $message->component = 'paygw_hikmahtransfer';
            $message->name = $provider;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = $subject;
            $message->fullmessage = $body . "\n\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p><p><a href="' . $url->out(true) . '">' .
                s($strings->get_string('msg_link', 'paygw_hikmahtransfer', null, $lang)) . '</a></p>';
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $strings->get_string('msg_link', 'paygw_hikmahtransfer', null, $lang);
            message_send($message);
        } catch (\Throwable $e) {
            debugging('paygw_hikmahtransfer: notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
