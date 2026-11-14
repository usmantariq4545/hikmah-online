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

use bbbext_hikmah\local\branding;

/**
 * Adds Hikmah branding parameters to the "create" and "join" calls sent to the BigBlueButton server.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_url_addons extends \mod_bigbluebuttonbn\local\extension\action_url_addons {
    /**
     * Returns the extra parameters for an action. Only ever adds; never changes what Moodle sends.
     *
     * @param string $action BigBlueButton API action
     * @param array $data parameters Moodle is about to send
     * @param array $metadata metadata Moodle is about to send
     * @param int|null $instanceid BigBlueButton activity instance id
     * @return array extra parameters under 'data' and extra metadata under 'metadata'
     */
    public function execute(
        string $action = '',
        array $data = [],
        array $metadata = [],
        ?int $instanceid = null
    ): array {
        // Never replace a value another extension or Moodle itself has already set.
        return [
            'data' => array_diff_key(branding::params($action), $data),
            'metadata' => [],
        ];
    }
}
