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

namespace local_hikmahaccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist as request_userlist;
use core_privacy\local\request\writer;
use local_hikmahaccess\news;
use local_hikmahaccess\userlist;

/**
 * Privacy API: one user preference (the columns chosen on the Users page), and the newsletter list, whose rows
 * belong to an account when the address is the account's email address. The messaging preference this plugin sets
 * for students is core's own and exported by core.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * The stored preference and the newsletter table.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(userlist::PREFERENCE, 'privacy:metadata:preference:usercolumns');
        $collection->add_user_preference(\local_hikmahaccess\analytics::PREFERENCE, 'privacy:metadata:preference:analytics');
        $collection->add_user_preference(\local_hikmahaccess\analytics::PROFILEDONE, 'privacy:metadata:preference:profiledone');
        $collection->add_external_location_link('posthog', [
            'userid' => 'privacy:metadata:posthog:userid',
            'role' => 'privacy:metadata:posthog:role',
            'country' => 'privacy:metadata:posthog:country',
            'events' => 'privacy:metadata:posthog:events',
        ], 'privacy:metadata:posthog');
        $collection->add_database_table(news::TABLE, [
            'email' => 'privacy:metadata:news:email',
            'timecreated' => 'privacy:metadata:news:timecreated',
            'timeconfirmed' => 'privacy:metadata:news:timeconfirmed',
        ], 'privacy:metadata:news');
        return $collection;
    }

    /**
     * Exports the user's chosen columns.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $preferences = [
            userlist::PREFERENCE => 'usercolumns',
            \local_hikmahaccess\analytics::PREFERENCE => 'analytics',
            \local_hikmahaccess\analytics::PROFILEDONE => 'profiledone',
        ];
        foreach ($preferences as $name => $string) {
            $value = get_user_preferences($name, null, $userid);
            if ($value !== null) {
                writer::export_user_preference(
                    'local_hikmahaccess',
                    $name,
                    $value,
                    get_string('privacy:metadata:preference:' . $string, 'local_hikmahaccess')
                );
            }
        }
    }

    /**
     * The user's newsletter row, found by their account email address.
     *
     * @param int $userid
     * @return \stdClass|false
     */
    private static function news_row(int $userid) {
        global $DB;
        $email = $DB->get_field('user', 'email', ['id' => $userid]);
        return $email ? $DB->get_record(news::TABLE, ['email' => \core_text::strtolower($email)]) : false;
    }

    /**
     * The system context, when the user's address is on the newsletter list.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if (self::news_row($userid)) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Accounts whose address is on the newsletter list.
     *
     * @param request_userlist $userlist
     */
    public static function get_users_in_context(request_userlist $userlist) {
        if ($userlist->get_context() instanceof \context_system) {
            $sql = 'SELECT u.id FROM {user} u JOIN {' . news::TABLE . '} n ON n.email = LOWER(u.email)';
            $userlist->add_from_sql('id', $sql, []);
        }
    }

    /**
     * Exports the newsletter row.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $row = self::news_row($contextlist->get_user()->id);
        if ($row && in_array(SYSCONTEXTID, $contextlist->get_contextids())) {
            writer::with_context(\context_system::instance())->export_data(
                [get_string('news_admin', 'local_hikmahaccess')],
                (object) [
                    'email' => $row->email,
                    'confirmed' => transform::yesno($row->confirmed),
                    'timecreated' => transform::datetime($row->timecreated),
                    'timeconfirmed' => $row->timeconfirmed ? transform::datetime($row->timeconfirmed) : '-',
                ]
            );
        }
    }

    /**
     * Deletes every newsletter row that belongs to an account.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records_select(news::TABLE, 'email IN (SELECT LOWER(email) FROM {user})');
        }
    }

    /**
     * Deletes the user's newsletter row.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $row = self::news_row($contextlist->get_user()->id);
        if ($row && in_array(SYSCONTEXTID, $contextlist->get_contextids())) {
            $DB->delete_records(news::TABLE, ['id' => $row->id]);
        }
    }

    /**
     * Deletes the newsletter rows of the given users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        foreach ($userlist->get_userids() as $userid) {
            if ($row = self::news_row($userid)) {
                $DB->delete_records(news::TABLE, ['id' => $row->id]);
            }
        }
    }
}
