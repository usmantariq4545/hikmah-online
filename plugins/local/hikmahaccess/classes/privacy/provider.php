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

namespace local_hikmahaccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_hikmahaccess\userlist;

/**
 * Privacy API: one user preference (the columns chosen on the Users page). The messaging preference this plugin
 * sets for students is core's own and exported by core.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * The stored preference.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(userlist::PREFERENCE, 'privacy:metadata:preference:usercolumns');
        return $collection;
    }

    /**
     * Exports the user's chosen columns.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $value = get_user_preferences(userlist::PREFERENCE, null, $userid);
        if ($value !== null) {
            writer::export_user_preference(
                'local_hikmahaccess',
                userlist::PREFERENCE,
                $value,
                get_string('privacy:metadata:preference:usercolumns', 'local_hikmahaccess')
            );
        }
    }
}
