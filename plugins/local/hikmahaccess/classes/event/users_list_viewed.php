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

namespace local_hikmahaccess\event;

/**
 * Someone opened the "Users" page (the list of all users, or a teacher's learners).
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_list_viewed extends \core\event\base {
    /**
     * Read, not teaching.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventuserslistviewed', 'local_hikmahaccess');
    }

    /**
     * Description for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' viewed the '{$this->other['list']}' users list.";
    }

    /**
     * The page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/hikmahaccess/users.php');
    }

    /**
     * The list name is required.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['list'])) {
            throw new \coding_exception('The \'list\' value must be set in other.');
        }
    }
}
