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

namespace local_hikmahcompletion;

/**
 * Event observers.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * An activity was added, changed or removed, or the course settings (and so possibly its certificate
     * rules) changed: apply the rules again so a new quiz counts without anyone remembering to.
     *
     * @param \core\event\base $event
     */
    public static function resync(\core\event\base $event): void {
        if (empty($event->courseid) || $event->courseid == SITEID) {
            return;
        }
        try {
            rules::sync((int) $event->courseid);
        } catch (\Throwable $e) {
            // Never block the teacher's edit; the rules page and the nightly settings run apply them again.
            debugging('Hikmah certificate rules: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
