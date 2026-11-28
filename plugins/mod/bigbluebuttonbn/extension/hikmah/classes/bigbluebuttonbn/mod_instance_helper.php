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
namespace bbbext_hikmah\bigbluebuttonbn;

use bbbext_hikmah\local\schedule;
use stdClass;

/**
 * Opens a live class a few minutes before its start (owner, 2026-11-28): after Moodle saves the form, the stored
 * opening time moves back by schedule::early(). Moodle's calendar entry keeps the class start.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_instance_helper extends \mod_bigbluebuttonbn\local\extension\mod_instance_helper {
    /**
     * New activity saved.
     *
     * @param stdClass $bigbluebuttonbn form data, with its id
     */
    public function add_instance(stdClass $bigbluebuttonbn) {
        schedule::store_opening((int) $bigbluebuttonbn->id, (int) ($bigbluebuttonbn->openingtime ?? 0));
    }

    /**
     * Activity saved again.
     *
     * @param stdClass $bigbluebuttonbn form data, with its id
     */
    public function update_instance(stdClass $bigbluebuttonbn): void {
        schedule::store_opening((int) $bigbluebuttonbn->id, (int) ($bigbluebuttonbn->openingtime ?? 0));
    }
}
