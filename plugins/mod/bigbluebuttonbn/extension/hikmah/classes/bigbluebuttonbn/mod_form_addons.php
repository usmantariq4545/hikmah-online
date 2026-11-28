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
 * The activity form shows the class start, not the earlier time the room opens (see mod_instance_helper).
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_form_addons extends \mod_bigbluebuttonbn\local\extension\mod_form_addons {
    /**
     * No fields of its own.
     */
    public function add_fields(): void {
    }

    /**
     * Stored opening time (room opens) to the class start the teacher entered.
     *
     * @param array|null $defaultvalues
     */
    public function data_preprocessing(?array &$defaultvalues): void {
        if (!empty($defaultvalues['openingtime'])) {
            $defaultvalues['openingtime'] = schedule::start((int) $defaultvalues['openingtime']);
        }
    }

    /**
     * Nothing to check.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation(array $data, array $files): array {
        return [];
    }

    /**
     * Nothing to change.
     *
     * @param stdClass $data
     */
    public function data_postprocessing(stdClass &$data): void {
    }

    /**
     * No completion rules.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        return [];
    }
}
