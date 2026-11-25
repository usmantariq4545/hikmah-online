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
        self::send('submitted', $learner, 'msg_submitted_subject', 'msg_submitted_body', $a, $statusurl, $request);

        if (get_config('paygw_hikmahtransfer', 'reviewerdigest')) {
            return; // Reviewers get one daily digest instead of a message per receipt.
        }
        $reviewurl = new moodle_url('/payment/gateway/hikmahtransfer/review.php', ['id' => $request->id]);
        foreach (self::reviewers() as $reviewer) {
            self::send('newsubmission', $reviewer, 'msg_new_subject', 'msg_new_body', $a, $reviewurl, $request);
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
        self::send('decision', $learner, $key . '_subject', $key . '_body', $a, $url, $request);
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
            // Written like "PKR 1,500" (design guide): no decimals when the amount is whole.
            'amount' => $request->currency . ' ' . number_format(
                (float) $request->amount,
                floor((float) $request->amount) == (float) $request->amount ? 0 : 2
            ),
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
     * @param stdClass|null $request payment request, for the summary table and course card
     */
    private static function send(
        string $provider,
        stdClass $to,
        string $subjectkey,
        string $bodykey,
        stdClass $a,
        moodle_url $url,
        ?stdClass $request = null
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
            $link = $strings->get_string('msg_link', 'paygw_hikmahtransfer', null, $lang);
            $rows = $request ? self::summary($request, $to->id != $request->userid, $lang) : [];
            $text = $body;
            foreach (array_filter($rows, fn($v) => $v !== '') as $label => $value) {
                $plain = str_starts_with($label, '!') ? html_to_text($value, 0, false) : $value;
                $text .= "\n" . ltrim($label, '!') . ': ' . $plain;
            }
            $message->fullmessage = $text . "\n\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = self::html($body, $rows, $a, $url, $link, $request, $lang);
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $link;
            message_send($message);
        } catch (\Throwable $e) {
            debugging('paygw_hikmahtransfer: notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Rows of the payment summary table. A label starting with '!' has an HTML value (the status badge).
     *
     * @param stdClass $request
     * @param bool $forreviewer include the learner's name
     * @param string|null $lang
     * @return array [label => value]
     */
    public static function summary(stdClass $request, bool $forreviewer, ?string $lang): array {
        $sm = get_string_manager();
        $str = fn(string $id) => $sm->get_string($id, 'paygw_hikmahtransfer', null, $lang);
        $tones = ['pending' => 'pending', 'approved' => 'success', 'rejected' => 'danger', 'resubmit' => 'warning'];
        $key = 'status_' . $request->status;
        $status = $sm->string_exists($key, 'paygw_hikmahtransfer') ? $str($key) : '';
        $badge = $status === '' ? '' : (class_exists(\theme_hikmah\local\email::class)
            ? \theme_hikmah\local\email::badge($status, $tones[$request->status] ?? 'pending') : s($status));
        $learner = $forreviewer ? \core_user::get_user($request->userid) : null;
        return [
            $str('msg_learner') => $learner ? fullname($learner) : '',
            $str('msg_course') => request::describe($request),
            $str('msg_reference') => (string) $request->reference,
            $str('msg_method') => !empty($request->method)
                && $sm->string_exists('method_' . $request->method, 'paygw_hikmahtransfer')
                ? $str('method_' . $request->method) : '',
            $str('msg_txnid') => (string) ($request->txnid ?? ''),
            $str('msg_paiddate') => !empty($request->paiddate)
                ? userdate($request->paiddate, get_string('strftimedate', 'langconfig')) : '',
            '!' . $str('msg_status') => $badge,
        ];
    }

    /**
     * HTML body: message, reviewer's reason, summary table with total, button, course card and sign-off. Uses the
     * theme's email building blocks when theme_hikmah is installed, otherwise plain paragraphs. Public for previews.
     *
     * @param string $body message text (plain)
     * @param array $rows summary rows
     * @param stdClass $a placeholders (amount, remarks)
     * @param moodle_url $url button target
     * @param string $link button label
     * @param stdClass|null $request
     * @param string|null $lang
     * @return string
     */
    public static function html(
        string $body,
        array $rows,
        stdClass $a,
        moodle_url $url,
        string $link,
        ?stdClass $request,
        ?string $lang
    ): string {
        $sm = get_string_manager();
        $str = fn(string $id) => $sm->get_string($id, 'paygw_hikmahtransfer', null, $lang);
        $html = '<p>' . nl2br(s($body)) . '</p>';
        if (!class_exists(\theme_hikmah\local\email::class)) {
            return $html . '<p><a href="' . $url->out(true) . '">' . s($link) . '</a></p>';
        }
        $email = \theme_hikmah\local\email::class;
        if ($request && in_array($request->status, ['rejected', 'resubmit']) && $a->remarks !== '') {
            $html .= $email::note($str('msg_reason'), $a->remarks, $request->status === 'rejected' ? 'danger' : 'warning');
        }
        if ($rows) {
            $html .= $email::table($str('msg_summary'), $rows, [$str('msg_total'), $a->amount]);
        }
        $html .= $email::button($url->out(false), $link);
        if ($request && !empty($request->courseid)) {
            $html .= $email::course_card((int) $request->courseid);
        }
        return $html . '<p style="margin-top:24px;">' . s($str('msg_signoff')) . '</p>';
    }
}
