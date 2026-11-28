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

use moodle_url;

/**
 * Newsletter list from the site footer (owner's decision, 2026-11-28: a separate list, not tied to accounts).
 * Double opt-in: an address joins the list only after its owner clicks the link in the confirmation email; every
 * address has its own unsubscribe link, which deletes the row. Admins see the list, plus the accounts that ticked
 * the news box at sign-up (profile field hkconsentcomms), on news.php.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class news {
    /** @var string Table name. */
    public const TABLE = 'local_hikmahaccess_news';

    /**
     * Adds an address (unconfirmed) and sends the confirmation email. An address that is already confirmed, or was
     * sent a confirmation in the last 24 hours, gets no new email, so the form cannot be used to flood an inbox.
     *
     * @param string $email
     * @return bool false if the address is not valid
     */
    public static function subscribe(string $email): bool {
        global $DB;
        $email = \core_text::strtolower(trim($email));
        if ($email === '' || \core_text::strlen($email) > 254 || !validate_email($email)) {
            return false;
        }
        $row = $DB->get_record(self::TABLE, ['email' => $email]);
        if ($row && ($row->confirmed || $row->timecreated > time() - DAYSECS)) {
            return true;
        }
        if (!$row) {
            $row = (object) ['email' => $email, 'confirmed' => 0, 'timeconfirmed' => 0];
        }
        $row->token = random_string(32);
        $row->timecreated = time();
        if (empty($row->id)) {
            $row->id = $DB->insert_record(self::TABLE, $row);
        } else {
            $DB->update_record(self::TABLE, $row);
        }
        self::send_confirmation($row);
        return true;
    }

    /**
     * Confirms the address that owns the token.
     *
     * @param string $token
     * @return bool false if the token is unknown
     */
    public static function confirm(string $token): bool {
        global $DB;
        $row = $token === '' ? false : $DB->get_record(self::TABLE, ['token' => $token]);
        if (!$row) {
            return false;
        }
        if (!$row->confirmed) {
            $DB->update_record(self::TABLE, (object) ['id' => $row->id, 'confirmed' => 1, 'timeconfirmed' => time()]);
        }
        return true;
    }

    /**
     * Removes the address that owns the token from the list.
     *
     * @param string $token
     * @return bool false if the token is unknown (already removed)
     */
    public static function unsubscribe(string $token): bool {
        global $DB;
        return $token !== '' && $DB->record_exists(self::TABLE, ['token' => $token])
            && $DB->delete_records(self::TABLE, ['token' => $token]);
    }

    /**
     * Link to confirm or unsubscribe.
     *
     * @param string $action confirm or unsubscribe
     * @param string $token
     * @return moodle_url
     */
    public static function url(string $action, string $token): moodle_url {
        return new moodle_url('/local/hikmahaccess/news_' . $action . '.php', ['token' => $token]);
    }

    /**
     * Everyone who wants the news: confirmed list addresses, and active accounts with the sign-up news box ticked.
     *
     * @return array of objects with email, source, time and unsubscribeurl (empty for accounts)
     */
    public static function audience(): array {
        global $DB;
        $people = [];
        foreach ($DB->get_records(self::TABLE, ['confirmed' => 1], 'timeconfirmed DESC') as $row) {
            $people[$row->email] = (object) ['email' => $row->email, 'source' => 'list', 'time' => $row->timeconfirmed,
                'unsubscribeurl' => self::url('unsubscribe', $row->token)->out(false)];
        }
        $sql = "SELECT u.id, u.email, u.timecreated
                  FROM {user} u
                  JOIN {user_info_data} d ON d.userid = u.id
                  JOIN {user_info_field} f ON f.id = d.fieldid AND f.shortname = :field
                 WHERE d.data = :yes AND u.deleted = 0 AND u.suspended = 0
              ORDER BY u.timecreated DESC";
        foreach ($DB->get_records_sql($sql, ['field' => 'hkconsentcomms', 'yes' => '1']) as $user) {
            $email = \core_text::strtolower($user->email);
            if (!isset($people[$email])) {
                $people[$email] = (object) ['email' => $email, 'source' => 'account', 'time' => $user->timecreated,
                    'unsubscribeurl' => ''];
            }
        }
        return array_values($people);
    }

    /**
     * Sends the "confirm your subscription" email.
     *
     * @param \stdClass $row
     */
    private static function send_confirmation(\stdClass $row): void {
        global $SITE;
        $to = \core_user::get_noreply_user();
        $to->email = $row->email;
        $to->auth = 'manual';
        $to->emailstop = 0;
        $to->mailformat = 1;
        $a = (object) [
            'site' => format_string($SITE->fullname),
            'confirm' => self::url('confirm', $row->token)->out(false),
            'unsubscribe' => self::url('unsubscribe', $row->token)->out(false),
        ];
        email_to_user(
            $to,
            \core_user::get_noreply_user(),
            get_string('news_email_subject', 'local_hikmahaccess', $a),
            get_string('news_email_text', 'local_hikmahaccess', $a),
            text_to_html(get_string('news_email_text', 'local_hikmahaccess', $a), false, false, true)
        );
    }
}
