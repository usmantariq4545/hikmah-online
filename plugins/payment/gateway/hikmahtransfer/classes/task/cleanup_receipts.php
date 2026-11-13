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

namespace paygw_hikmahtransfer\task;

use paygw_hikmahtransfer\receipt;
use paygw_hikmahtransfer\request;

/**
 * Deletes receipt files of decided requests once the retention period has passed. The request
 * and its history stay as the audit record (PRD ENR-05, business rule 9).
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_receipts extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'paygw_hikmahtransfer');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;

        $days = (int) get_config('paygw_hikmahtransfer', 'retentiondays');
        if ($days <= 0) {
            $days = 365;
        }
        $cutoff = time() - $days * DAYSECS;

        $requests = $DB->get_records_select(
            request::TABLE,
            "receiptdeleted = 0 AND timemodified < :cutoff AND status IN ('approved', 'rejected')",
            ['cutoff' => $cutoff],
            'id ASC',
            'id',
            0,
            500
        );
        foreach ($requests as $request) {
            receipt::delete((int) $request->id);
            $DB->set_field(request::TABLE, 'receiptdeleted', 1, ['id' => $request->id]);
        }
        mtrace('Removed receipts of ' . count($requests) . ' request(s) older than ' . $days . ' days.');
    }
}
