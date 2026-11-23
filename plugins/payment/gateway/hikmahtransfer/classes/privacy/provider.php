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

namespace paygw_hikmahtransfer\privacy;

use core_payment\privacy\paygw_provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use paygw_hikmahtransfer\receipt;
use paygw_hikmahtransfer\request;

/**
 * Privacy API: transfer requests, their history and receipt files can be exported and deleted.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    paygw_provider {
    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(request::TABLE, [
            'userid' => 'privacy:metadata:req:userid',
            'amount' => 'privacy:metadata:req:amount',
            'paidamount' => 'privacy:metadata:req:paidamount',
            'method' => 'privacy:metadata:req:method',
            'reference' => 'privacy:metadata:req:reference',
            'txnid' => 'privacy:metadata:req:txnid',
            'paiddate' => 'privacy:metadata:req:paiddate',
            'status' => 'privacy:metadata:req:status',
            'reviewerid' => 'privacy:metadata:req:reviewerid',
            'timecreated' => 'privacy:metadata:req:timecreated',
        ], 'privacy:metadata:req');
        $collection->add_database_table(request::TABLE_HISTORY, [
            'userid' => 'privacy:metadata:hist:userid',
            'tostatus' => 'privacy:metadata:hist:tostatus',
            'remarks' => 'privacy:metadata:hist:remarks',
            'timecreated' => 'privacy:metadata:hist:timecreated',
        ], 'privacy:metadata:hist');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }

    /**
     * Contexts holding data for a user: always the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists(request::TABLE, ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . request::TABLE . '}', []);
    }

    /**
     * Export a user's requests, history and receipts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            foreach ($DB->get_records(request::TABLE, ['userid' => $userid]) as $record) {
                $subcontext = [get_string('pluginname', 'paygw_hikmahtransfer'), $record->reference];
                $history = [];
                foreach (request::history((int) $record->id) as $row) {
                    $history[] = [
                        'from' => $row->fromstatus,
                        'to' => $row->tostatus,
                        'remarks' => $row->remarks,
                        'time' => \core_privacy\local\request\transform::datetime($row->timecreated),
                    ];
                }
                writer::with_context($context)->export_data($subcontext, (object) [
                    'reference' => $record->reference,
                    'item' => request::describe($record),
                    'amount' => $record->amount . ' ' . $record->currency,
                    'paidamount' => $record->paidamount,
                    'method' => $record->method,
                    'txnid' => $record->txnid,
                    'paiddate' => $record->paiddate ? \core_privacy\local\request\transform::date($record->paiddate) : null,
                    'status' => $record->status,
                    'created' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'history' => $history,
                ]);
                writer::with_context($context)->export_area_files(
                    $subcontext,
                    'paygw_hikmahtransfer',
                    request::FILEAREA,
                    $record->id
                );
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        self::delete_requests($DB->get_fieldset_select(request::TABLE, 'id', '1 = 1'));
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::delete_requests($DB->get_fieldset_select(
                    request::TABLE,
                    'id',
                    'userid = ?',
                    [$contextlist->get_user()->id]
                ));
            }
        }
    }

    /**
     * Delete data for several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids());
        self::delete_requests($DB->get_fieldset_select(request::TABLE, 'id', "userid $insql", $params));
    }

    /**
     * Remove requests, their history and receipt files.
     *
     * @param int[] $ids
     */
    private static function delete_requests(array $ids): void {
        global $DB;
        foreach ($ids as $id) {
            receipt::delete((int) $id);
            $DB->delete_records(request::TABLE_HISTORY, ['requestid' => $id]);
            $DB->delete_records(request::TABLE, ['id' => $id]);
        }
    }

    /**
     * Export the gateway-specific data of a core payment.
     *
     * @param \context $context
     * @param array $subcontext
     * @param \stdClass $payment
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment) {
        global $DB;
        $record = $DB->get_record(request::TABLE, ['paymentid' => $payment->id]);
        if (!$record) {
            return;
        }
        $subcontext[] = get_string('gatewayname', 'paygw_hikmahtransfer');
        writer::with_context($context)->export_data($subcontext, (object) [
            'reference' => $record->reference,
            'method' => $record->method,
            'txnid' => $record->txnid,
        ]);
    }

    /**
     * Delete gateway data linked to payments that are being deleted.
     *
     * @param string $paymentsql
     * @param array $paymentparams
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams) {
        global $DB;
        self::delete_requests($DB->get_fieldset_select(
            request::TABLE,
            'id',
            "paymentid IN ({$paymentsql})",
            $paymentparams
        ));
    }
}
