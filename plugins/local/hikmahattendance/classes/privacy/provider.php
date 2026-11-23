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

namespace local_hikmahattendance\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_hikmahattendance\manager;

/**
 * Privacy API: attendance decisions and audit rows about a learner can be exported and deleted.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(manager::TABLE_DECISION, [
            'userid' => 'privacy:metadata:decision:userid',
            'seconds' => 'privacy:metadata:decision:seconds',
            'percent' => 'privacy:metadata:decision:percent',
            'computedstatus' => 'privacy:metadata:decision:computedstatus',
            'finalstatus' => 'privacy:metadata:decision:finalstatus',
            'evidence' => 'privacy:metadata:decision:evidence',
            'reason' => 'privacy:metadata:decision:reason',
            'timecreated' => 'privacy:metadata:decision:timecreated',
        ], 'privacy:metadata:decision');
        $collection->add_database_table(manager::TABLE_AUDIT, [
            'userid' => 'privacy:metadata:audit:userid',
            'actorid' => 'privacy:metadata:audit:actorid',
            'action' => 'privacy:metadata:audit:action',
            'reason' => 'privacy:metadata:audit:reason',
            'timecreated' => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');
        return $collection;
    }

    /**
     * Course contexts holding decisions about a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {' . manager::TABLE_DECISION . '} d
               JOIN {' . manager::TABLE_LINK . '} l ON l.id = d.linkid
               JOIN {context} ctx ON ctx.instanceid = l.courseid AND ctx.contextlevel = :level
              WHERE d.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with decisions in a course context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT d.userid
               FROM {' . manager::TABLE_DECISION . '} d
               JOIN {' . manager::TABLE_LINK . '} l ON l.id = d.linkid
              WHERE l.courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export a user's decisions and the audit rows about them.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $decisions = $DB->get_records_sql(
                'SELECT d.*, l.windowstart, l.windowend
                   FROM {' . manager::TABLE_DECISION . '} d
                   JOIN {' . manager::TABLE_LINK . '} l ON l.id = d.linkid
                  WHERE l.courseid = :courseid AND d.userid = :userid
               ORDER BY l.windowstart, d.id',
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );
            if (!$decisions) {
                continue;
            }
            $export = [];
            foreach ($decisions as $decision) {
                $export[] = [
                    'classstart' => transform::datetime($decision->windowstart),
                    'classend' => transform::datetime($decision->windowend),
                    'attendedseconds' => $decision->seconds,
                    'percent' => $decision->percent,
                    'threshold' => $decision->threshold,
                    'computedstatus' => $decision->computedstatus,
                    'finalstatus' => $decision->finalstatus,
                    'state' => $decision->state,
                    'reason' => $decision->reason,
                    'evidence' => $decision->evidence,
                ];
            }
            $audit = $DB->get_records_sql(
                'SELECT a.*
                   FROM {' . manager::TABLE_AUDIT . '} a
                   JOIN {' . manager::TABLE_LINK . '} l ON l.id = a.linkid
                  WHERE l.courseid = :courseid AND a.userid = :userid
               ORDER BY a.id',
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );
            $auditexport = [];
            foreach ($audit as $row) {
                $auditexport[] = [
                    'action' => $row->action,
                    'from' => $row->fromvalue,
                    'to' => $row->tovalue,
                    'reason' => $row->reason,
                    'time' => transform::datetime($row->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_hikmahattendance')],
                (object) ['decisions' => $export, 'audit' => $auditexport]
            );
        }
    }

    /**
     * Delete everything in a course context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $linkids = $DB->get_fieldset_select(manager::TABLE_LINK, 'id', 'courseid = ?', [$context->instanceid]);
        if ($linkids) {
            [$insql, $params] = $DB->get_in_or_equal($linkids);
            $DB->delete_records_select(manager::TABLE_DECISION, "linkid $insql", $params);
            $DB->delete_records_select(manager::TABLE_AUDIT, "linkid $insql", $params);
        }
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                self::delete_users((int) $context->instanceid, [(int) $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete data for several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_COURSE && $userlist->get_userids()) {
            self::delete_users((int) $context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Remove decisions and audit rows about some users in one course.
     *
     * @param int $courseid
     * @param int[] $userids
     */
    private static function delete_users(int $courseid, array $userids): void {
        global $DB;
        $linkids = $DB->get_fieldset_select(manager::TABLE_LINK, 'id', 'courseid = ?', [$courseid]);
        if (!$linkids) {
            return;
        }
        [$linksql, $linkparams] = $DB->get_in_or_equal($linkids, SQL_PARAMS_NAMED, 'l');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $DB->delete_records_select(manager::TABLE_DECISION, "linkid $linksql AND userid $usersql", $linkparams + $userparams);
        $DB->delete_records_select(manager::TABLE_AUDIT, "linkid $linksql AND userid $usersql", $linkparams + $userparams);
    }
}
