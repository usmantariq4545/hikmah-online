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

namespace local_hikmahattendance;

/**
 * Event observers.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A live class was added or edited: give it its attendance session and link (manager::ensure_for_room).
     *
     * @param \core\event\base $event course_module_created or course_module_updated
     */
    public static function room_saved(\core\event\base $event): void {
        if (($event->other['modulename'] ?? '') !== 'bigbluebuttonbn') {
            return;
        }
        try {
            manager::ensure_for_room((int) $event->other['instanceid'], (int) $event->userid);
        } catch (\Throwable $e) {
            // Saving the live class must not fail because of this; the link can still be made by hand.
            debugging('local_hikmahattendance: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
