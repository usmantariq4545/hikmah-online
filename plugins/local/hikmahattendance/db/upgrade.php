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
 * Upgrade steps for local_hikmahattendance.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin's database tables.
 *
 * @param int $oldversion The version installed before this upgrade.
 * @return bool
 */
function xmldb_local_hikmahattendance_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026112300) {
        // Tables take the full plugin name as their prefix (local_hikmahatt_* before); rows are kept.
        foreach (['link', 'decision', 'audit', 'course'] as $name) {
            $old = new xmldb_table('local_hikmahatt_' . $name);
            if ($dbman->table_exists($old) && !$dbman->table_exists('local_hikmahattendance_' . $name)) {
                $dbman->rename_table($old, 'local_hikmahattendance_' . $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026112300, 'local', 'hikmahattendance');
    }

    return true;
}
