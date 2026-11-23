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

namespace local_hikmahaccess;

/**
 * Hook callbacks.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Sign-up opens straight on the form: agreement to the site policies is one required checkbox on it
     * (lib.php), so the policy tool's separate reading and consent pages are skipped. The tool still
     * records a versioned acceptance of every compulsory policy when the account is created.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $SCRIPT;
        // Date of birth left on "Day / Month / Year" (theme_hikmah blankdates): drop it, so the profile form's
        // own "Required" check rejects it instead of saving a made-up date.
        $dob = $_POST['profile_field_hkdob'] ?? null;
        if (
            is_array($dob) && in_array($SCRIPT ?? '', ['/user/edit.php', '/user/editadvanced.php'], true)
                && in_array('', [$dob['day'] ?? '', $dob['month'] ?? '', $dob['year'] ?? ''], true)
        ) {
            unset($_POST['profile_field_hkdob']);
        }
        if (
            during_initial_install() || CLI_SCRIPT || ($SCRIPT ?? '') !== '/login/signup.php' || isloggedin()
                || ($CFG->sitepolicyhandler ?? '') !== 'tool_policy'
        ) {
            return;
        }
        try {
            \cache::make('core', 'presignup')->set('tool_policy_userpolicyagreed', 1);
        } catch (\Throwable $e) {
            // Never break a page over this: the policy tool then simply shows its own pages first.
            debugging('Hikmah sign-up: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
