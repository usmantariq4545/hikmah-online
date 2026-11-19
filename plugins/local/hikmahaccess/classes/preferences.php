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

use navigation_node;

/**
 * Trims the Preferences page for everyone but the site administrator.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {
    /**
     * Remove "Preferred language" (English-only site), "Editor preferences" (one editor) and, for
     * people who cannot use the content bank anywhere, "Content bank preferences".
     *
     * @param navigation_node $account the "useraccount" node
     * @param int $userid the viewing user
     */
    public static function trim(navigation_node $account, int $userid): void {
        $hide = ['preferredlanguage'];
        if (!get_user_capability_course('moodle/contentbank:access', $userid, true, '', '', 1)) {
            $hide[] = 'contentbankpreferences';
        }
        foreach ($account->children as $child) {
            $url = $child->action instanceof \core\url ? $child->action->get_path() : '';
            if (in_array($child->key, $hide, true) || str_ends_with($url, '/user/editor.php')) {
                $child->remove();
            }
        }
    }
}
