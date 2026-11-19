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

namespace local_hikmahaccess\task;

use local_hikmahaccess\peers;

/**
 * Hourly: keep every student's messaging preference in line with the classmates switch (a student
 * could change it in their own messaging settings; this puts it back).
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_peers extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_syncpeers', 'local_hikmahaccess');
    }

    /**
     * Run the sync.
     */
    public function execute(): void {
        $changed = peers::sync_all();
        mtrace("Classmates rule (allowed: " . (peers::allowed() ? 'yes' : 'no') . "): $changed student(s) updated.");
    }
}
