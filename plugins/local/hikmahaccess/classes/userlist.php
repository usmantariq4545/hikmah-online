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

use core_reportbuilder\local\report\column;

/**
 * Who may see which list on the "Users" page.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist {
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
            ->add_field("(SELECT hkd.data FROM {user_info_data} hkd JOIN {user_info_field} hkf ON hkf.id = hkd.fieldid
                WHERE hkf.shortname = 'hkphone' AND hkd.userid = {$useralias}.id)", 'hkphone')
            ->set_is_sortable(true)
            ->add_callback(static fn($value): string => s((string) $value));
    }
}
