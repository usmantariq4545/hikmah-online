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

namespace local_hikmahattendance\task;

use local_hikmahattendance\manager;

/**
 * Every five minutes: compute previews for linked sessions whose class window has ended.
 *
 * Sessions without a callback are retried on every run, so a late callback is picked up as soon
 * as it arrives; after the configured wait they show in the exceptions queue as "missing
 * callback" and are still retried. Sessions that already have previews are re-read for two days
 * so late evidence produces a new preview rather than being lost.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_sessions extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process', 'local_hikmahattendance');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;

        $now = time();
        $links = $DB->get_records_select(
            manager::TABLE_LINK,
            'cancelled = 0 AND windowend <= :now AND (
                state IN (:waiting, :missing, :unknown)
                OR (state IN (:ready, :applied) AND windowend >= :recent)
             )',
            [
                'now' => $now,
                'waiting' => manager::LINK_WAITING,
                'missing' => manager::LINK_MISSING,
                'unknown' => manager::LINK_UNKNOWN,
                'ready' => manager::LINK_READY,
                'applied' => manager::LINK_APPLIED,
                'recent' => $now - manager::EVIDENCE_GRACE,
            ],
            'windowend ASC',
            'id',
            0,
            200
        );

        $counts = [];
        foreach ($links as $link) {
            try {
                $processed = manager::process_link((int) $link->id, $now);
                $counts[$processed->state] = ($counts[$processed->state] ?? 0) + 1;
            } catch (\Throwable $e) {
                mtrace('Link ' . $link->id . ' failed: ' . $e->getMessage());
            }
        }
        foreach ($counts as $state => $count) {
            mtrace("$count session(s): $state");
        }
    }
}
