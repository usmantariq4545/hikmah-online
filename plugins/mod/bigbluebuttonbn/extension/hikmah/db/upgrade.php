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
/**
 * Upgrade steps.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_bbbext_hikmah_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026112800) {
        // Live classes open 5 minutes early: every class that has an opening time (its start until now) opens
        // that much earlier. Runs once; the setting records that it ran.
        if (!get_config('bbbext_hikmah', 'earlyopen')) {
            $early = \bbbext_hikmah\local\schedule::EARLY;
            $DB->execute('UPDATE {bigbluebuttonbn} SET openingtime = openingtime - ? WHERE openingtime > 0', [$early]);
            set_config('earlyopen', $early, 'bbbext_hikmah');
        }
        upgrade_plugin_savepoint(true, 2026112800, 'bbbext', 'hikmah');
    }

    return true;
}
