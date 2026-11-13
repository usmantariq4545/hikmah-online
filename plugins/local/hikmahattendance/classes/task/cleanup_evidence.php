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
 * Removes raw evidence (join/leave intervals) from old decisions after the retention period.
 * The decision itself, with seconds, percentage and status, stays as the audit record.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_evidence extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'local_hikmahattendance');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;

        $days = (int) get_config('local_hikmahattendance', 'retentiondays');
        if ($days <= 0) {
            $days = 365;
        }
        $cutoff = time() - $days * DAYSECS;
        $DB->execute(
            'UPDATE {' . manager::TABLE_DECISION . '} SET evidence = NULL WHERE evidence IS NOT NULL AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        mtrace("Removed raw attendance evidence older than $days days.");
    }
}
