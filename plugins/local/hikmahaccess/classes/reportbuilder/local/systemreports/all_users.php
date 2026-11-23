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

namespace local_hikmahaccess\reportbuilder\local\systemreports;

use core\context\system;
use local_hikmahaccess\userlist;

/**
 * Every user of the site, view only: Moodle's own "Browse list of users" without its edit, suspend and delete
 * actions, plus the phone number. Download only with local/hikmahaccess:exportusers.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_users extends \core_admin\reportbuilder\local\systemreports\users {
    /**
     * Moodle's report, with download limited.
     */
    protected function initialise(): void {
        parent::initialise();
        $this->set_downloadable(has_capability('local/hikmahaccess:exportusers', system::instance()), get_string('users'));
    }

    /**
     * Managers, support and site administrators.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('local/hikmahaccess:viewallusers', system::instance());
    }

    /**
     * Moodle's columns, then the phone number.
     */
    public function add_columns(): void {
        parent::add_columns();
        $user = $this->get_entity('user');
        $this->add_column(userlist::phone_column($user->get_table_alias('user'), $user->get_entity_name()));
    }

    /**
     * View only: no row actions (editing stays in Moodle's own user pages).
     */
    protected function add_actions(): void {
    }
}
