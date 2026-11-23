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
use core_admin\reportbuilder\local\filters\courserole;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use local_hikmahaccess\userlist;

/**
 * Every user of the site, view only: Moodle's own "Browse list of users" without its edit, suspend and delete
 * actions. Shows the columns the viewer chose (userlist::chosen_columns), and filters for exactly those columns.
 * Download only with local/hikmahaccess:exportusers.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_users extends \core_admin\reportbuilder\local\systemreports\users {
    /** @var string joins course names in SQL; not expected inside a course name */
    private const SEPARATOR = '|~|';

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
     * The name, then the chosen columns.
     */
    public function add_columns(): void {
        $user = $this->get_entity('user');
        $u = $user->get_table_alias('user');
        $this->add_column_from_entity('user:fullnamewithpicturelink')
            ->add_fields("{$u}.suspended")
            ->add_callback(static fn(string $fullname, \stdClass $row): string => $row->suspended
                ? $fullname . ' ' . \html_writer::span(get_string('suspended', 'auth'), 'badge text-bg-warning ms-1')
                : $fullname);

        foreach (userlist::chosen_columns() as $key) {
            if ($key === 'phone') {
                $this->add_column(userlist::phone_column($u, $user->get_entity_name()));
            } else if ($key === 'roles') {
                $this->add_column($this->roles_column($u, $user->get_entity_name()));
            } else if ($key === 'courses') {
                // One tag per course; the hidden comma keeps "Course A, Course B" in downloads (cells become plain text).
                $this->add_column((new column(
                    'hkcourses',
                    new lang_string('courses', 'local_hikmahaccess'),
                    $user->get_entity_name()
                ))
                    ->set_type(column::TYPE_TEXT)
                    ->add_field($this->courses_sql($u), 'hkcourses')
                    ->set_is_sortable(true)
                    ->add_callback(static fn($value): string => implode(
                        \html_writer::span(', ', 'visually-hidden'),
                        array_map(
                            fn($name) => \html_writer::span(format_string(
                                $name,
                                true,
                                ['context' => system::instance()]
                            ), 'badge text-bg-light border me-1 mb-1'),
                            array_filter(explode(self::SEPARATOR, (string) $value))
                        )
                    )));
            } else if ($key === 'lastaccess' || $key === 'firstaccess') {
                // Shows "2 days" rather than a date, as in Moodle's own user list.
                $this->add_column_from_entity("user:$key")
                    ->set_callback(static fn($value): string => $value ? format_time(time() - $value) : get_string('never'));
            } else if (array_key_exists($key, $user->get_columns())) {
                $this->add_column_from_entity("user:$key");
            }
        }
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
        $this->set_default_no_results_notice(new lang_string('nousersfound', 'moodle'));
    }

    /**
     * Filters for the name and the chosen columns only.
     */
    protected function add_filters(): void {
        $user = $this->get_entity('user');
        $u = $user->get_table_alias('user');
        $this->add_filter_from_entity('user:fullname');
        foreach (userlist::chosen_columns() as $key) {
            if ($key === 'phone') {
                $this->add_filter(userlist::phone_filter($u, $user->get_entity_name()));
            } else if ($key === 'roles') {
                $this->add_filter((new filter(
                    courserole::class,
                    'courserole',
                    new lang_string('roles', 'local_hikmahaccess'),
                    $user->get_entity_name()
                ))->set_field_sql("{$u}.id"));
            } else if ($key === 'courses') {
                $this->add_filter((new filter(
                    text::class,
                    'hkcourses',
                    new lang_string('courses', 'local_hikmahaccess'),
                    $user->get_entity_name()
                ))->set_field_sql($this->courses_sql($u)));
            } else if (array_key_exists($key, $user->get_filters())) {
                $this->add_filter_from_entity("user:$key");
            }
        }
    }

    /**
     * View only: no row actions (editing stays in Moodle's own user pages).
     */
    protected function add_actions(): void {
    }

    /**
     * Every role the user holds anywhere (site or course), by name; site administrators are marked too.
     *
     * @param string $u alias of the user table
     * @param string $entityname
     * @return column
     */
    private function roles_column(string $u, string $entityname): column {
        global $DB;
        $roleids = $DB->sql_group_concat('hkr.roleid', ',', 'hkr.roleid');
        return (new column('hkroles', new lang_string('roles', 'local_hikmahaccess'), $entityname))
            ->set_type(column::TYPE_TEXT)
            ->add_field("(SELECT {$roleids} FROM (SELECT DISTINCT ra.roleid FROM {role_assignments} ra
                WHERE ra.userid = {$u}.id) hkr)", 'hkroles')
            ->add_field("{$u}.id", 'hkuserid')
            ->add_callback(static function ($value, \stdClass $row): string {
                static $names = null;
                $names ??= role_get_names(null, ROLENAME_ALIAS, true);
                $list = array_map(fn($id) => $names[$id] ?? '', array_filter(explode(',', (string) $value)));
                if (is_siteadmin($row->hkuserid)) {
                    array_unshift($list, get_string('administrator'));
                }
                return s(implode(', ', array_filter($list)));
            });
    }

    /**
     * Names of the courses the user is enrolled in, A to Z, joined by self::SEPARATOR.
     *
     * @param string $u alias of the user table
     * @return string SQL
     */
    private function courses_sql(string $u): string {
        global $DB;
        $names = $DB->sql_group_concat('hkc.fullname', self::SEPARATOR, 'hkc.fullname');
        return "(SELECT {$names} FROM (SELECT DISTINCT hkco.fullname FROM {user_enrolments} hkue
            JOIN {enrol} hke ON hke.id = hkue.enrolid JOIN {course} hkco ON hkco.id = hke.courseid
            WHERE hkue.userid = {$u}.id) hkc)";
    }
}
