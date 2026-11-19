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
 * Callbacks for local_hikmahaccess.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Preferences page: hide links nobody but the administrator needs (owner's decision, 2026-11-19).
 * The pages themselves stay reachable; they are only no longer listed.
 *
 * @param navigation_node $usersetting
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_hikmahaccess_extend_navigation_user_settings(navigation_node $usersetting, $user, $usercontext,
        $course, $coursecontext): void {
    global $USER;
    if (is_siteadmin() || !($account = $usersetting->find('useraccount', null))) {
        return;
    }
    \local_hikmahaccess\preferences::trim($account, (int) $USER->id);
}
