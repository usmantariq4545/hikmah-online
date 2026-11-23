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

use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Who may see which list on the "Users" page.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist {
    /** @var string user preference holding the chosen columns of the all-users list */
    const PREFERENCE = 'local_hikmahaccess_usercolumns';

    /** @var string[] columns shown by default (and always, for those who cannot choose) */
    const DEFAULT_COLUMNS = ['email', 'phone', 'courses', 'lastaccess'];

    /** @var string[] columns one may add to the all-users list, in display order (report builder user columns, or ours) */
    const COLUMNS = ['email', 'phone', 'profilefield_hkgender', 'profilefield_hkdob', 'profilefield_hkguardian',
        'profilefield_hkconsentcomms', 'city', 'country', 'username', 'auth', 'roles', 'courses', 'confirmed', 'suspended',
        'timecreated', 'firstaccess', 'lastaccess', 'lastip'];

    /**
     * Whether the current user may choose the all-users list's columns (administrators and managers).
     *
     * @return bool
     */
    public static function can_choose_columns(): bool {
        return has_capability('local/hikmahaccess:choosecolumns', \context_system::instance());
    }

    /**
     * Columns one may choose from, with their names, in display order.
     *
     * @return string[] key => name
     */
    public static function column_options(): array {
        $entity = new \core_reportbuilder\local\entities\user();
        $entity->initialise();
        $options = [];
        foreach (self::COLUMNS as $key) {
            if (in_array($key, ['phone', 'roles', 'courses'], true)) {
                $options[$key] = get_string($key, 'local_hikmahaccess');
            } else if (array_key_exists($key, $columns = $entity->get_columns())) {
                $options[$key] = $columns[$key]->get_title();
            }
        }
        return $options;
    }

    /**
     * The all-users list's columns for the current user, in display order.
     *
     * @return string[]
     */
    public static function chosen_columns(): array {
        $chosen = self::can_choose_columns() ? get_user_preferences(self::PREFERENCE) : null;
        $chosen = $chosen ? explode(',', $chosen) : self::DEFAULT_COLUMNS;
        return array_values(array_intersect(self::COLUMNS, $chosen));
    }

    /**
     * Saves the current user's choice of columns (unknown keys are dropped).
     *
     * @param string[] $keys
     */
    public static function set_columns(array $keys): void {
        $keys = array_values(array_intersect(self::COLUMNS, $keys));
        set_user_preference(self::PREFERENCE, $keys ? implode(',', $keys) : null);
    }
    /**
     * Courses the current user teaches (holds local/hikmahaccess:viewlearners in).
     *
     * @return string[] course id => course name
     */
    public static function taught_courses(): array {
        $courses = [];
        foreach (enrol_get_my_courses() as $course) {
            $context = \context_course::instance($course->id);
            if (has_capability('local/hikmahaccess:viewlearners', $context)) {
                $courses[$course->id] = format_string($course->fullname, true, ['context' => $context]);
            }
        }
        return $courses;
    }

    /**
     * The report the current user gets on the "Users" page, or null when there is none.
     *
     * @return string|null system report class
     */
    public static function report_class(): ?string {
        if (has_capability('local/hikmahaccess:viewallusers', \context_system::instance())) {
            return reportbuilder\local\systemreports\all_users::class;
        }
        return self::taught_courses() ? reportbuilder\local\systemreports\my_learners::class : null;
    }

    /**
     * The "Mobile / WhatsApp number" profile field as a column. Read straight from the table: report builder's own
     * profile-field columns show only to holders of moodle/user:viewalldetails, and the page's capability is the gate.
     *
     * @param string $useralias alias of the user table
     * @param string $entityname entity the column belongs to
     * @return column
     */
    public static function phone_column(string $useralias, string $entityname): column {
        return (new column('hkphone', new \lang_string('phone', 'local_hikmahaccess'), $entityname))
            ->set_type(column::TYPE_TEXT)
            ->add_field(self::phone_sql($useralias), 'hkphone')
            ->set_is_sortable(true)
            ->add_callback(static fn($value): string => s((string) $value));
    }

    /**
     * Filter on the phone number (same source as the column).
     *
     * @param string $useralias alias of the user table
     * @param string $entityname
     * @return filter
     */
    public static function phone_filter(string $useralias, string $entityname): filter {
        return (new filter(text::class, 'hkphone', new \lang_string('phone', 'local_hikmahaccess'), $entityname))
            ->set_field_sql(self::phone_sql($useralias));
    }

    /**
     * The phone number of the user in the row.
     *
     * @param string $useralias
     * @return string SQL
     */
    private static function phone_sql(string $useralias): string {
        return "(SELECT hkd.data FROM {user_info_data} hkd JOIN {user_info_field} hkf ON hkf.id = hkd.fieldid
                WHERE hkf.shortname = 'hkphone' AND hkd.userid = {$useralias}.id)";
    }
}
