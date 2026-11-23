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

namespace local_hikmahattendance\event;

/**
 * An instructor changed a computed attendance status.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decision_overridden extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_hikmahattendance_decision';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_overridden', 'local_hikmahattendance');
    }

    /**
     * Event description for the log.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' changed the attendance status of the user with id " .
            "'$this->relateduserid' from '{$this->other['from']}' to '{$this->other['to']}' for attendance session " .
            "'{$this->other['sessionid']}'.";
    }

    /**
     * Link to the reconciliation pages of the course.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/hikmahattendance/index.php', ['courseid' => $this->courseid]);
    }

    /**
     * Object id mapping for backup/restore: not part of course backups.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_hikmahattendance_decision', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other-field mapping for backup/restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
