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
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_hikmahaccess.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_hikmahaccess_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026112302) {
        $table = new xmldb_table('local_hikmahaccess_publish');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timepublish', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $table->add_index('timepublish', XMLDB_INDEX_NOTUNIQUE, ['timepublish']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112302, 'local', 'hikmahaccess');
    }

    if ($oldversion < 2026112800) {
        $table = new xmldb_table('local_hikmahaccess_news');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '254', null, XMLDB_NOTNULL, null, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('confirmed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeconfirmed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('email', XMLDB_INDEX_UNIQUE, ['email']);
        $table->add_index('token', XMLDB_INDEX_UNIQUE, ['token']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026112800, 'local', 'hikmahaccess');
    }

    return true;
}
