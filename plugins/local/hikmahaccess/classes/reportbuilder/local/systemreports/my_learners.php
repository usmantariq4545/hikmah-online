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

use core_course\reportbuilder\local\entities\enrolment;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use lang_string;
use local_hikmahaccess\userlist;

/**
 * A teacher's learners: one row per student per course the viewer teaches. View only, no download.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_learners extends system_report {
    /** @var string[] courses the viewer teaches, id => name */
    private array $courses = [];

    /**
     * Enrolments in the viewer's courses, with the learner and the course.
     */
    protected function initialise(): void {
        global $DB;
        $this->courses = userlist::taught_courses();

        $enrolment = new enrolment();
        $ue = $enrolment->get_table_alias('user_enrolments');
        $enrol = $enrolment->get_table_alias('enrol');
        $this->set_main_table('user_enrolments', $ue);
        $this->add_entity($enrolment->add_join("JOIN {enrol} {$enrol} ON {$enrol}.id = {$ue}.enrolid"));

        $courseentity = new course();
        $c = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_joins($enrolment->get_joins())
            ->add_join("JOIN {course} {$c} ON {$c}.id = {$enrol}.courseid"));

        $userentity = new user();
        $u = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_joins($enrolment->get_joins())
            ->add_join("JOIN {user} {$u} ON {$u}.id = {$ue}.userid AND {$u}.deleted = 0"));

        // Only students (not co-teachers) of the viewer's own courses; a learner enrolled two ways shows once (first enrollment).
        [$insql, $params] = $DB->get_in_or_equal(
            array_keys($this->courses) ?: [0],
            SQL_PARAMS_NAMED,
            database::generate_param_name() . '_'
        );
        $student = database::generate_param_name();
        $params[$student] = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->add_base_condition_sql(
            "{$enrol}.courseid {$insql} AND EXISTS (
            SELECT 1 FROM {role_assignments} hkra
              JOIN {context} hkctx ON hkctx.id = hkra.contextid AND hkctx.contextlevel = " . CONTEXT_COURSE . "
             WHERE hkctx.instanceid = {$enrol}.courseid AND hkra.userid = {$ue}.userid AND hkra.roleid = :{$student})
            AND NOT EXISTS (
            SELECT 1 FROM {user_enrolments} hkue2
              JOIN {enrol} hke2 ON hke2.id = hkue2.enrolid
             WHERE hke2.courseid = {$enrol}.courseid AND hkue2.userid = {$ue}.userid AND hkue2.id < {$ue}.id)",
            $params
        );
        $this->add_base_fields("{$u}.id");

        $this->add_learner_columns($u, $userentity->get_entity_name());
        $this->add_learner_filters($enrol, $u, $userentity->get_entity_name());
        $this->set_downloadable(false);
    }

    /**
     * Anyone who teaches at least one course.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return (bool) userlist::taught_courses();
    }

    /**
     * Name, email, phone, course, enrolled on, last access.
     *
     * @param string $u alias of the user table
     * @param string $userentityname
     */
    private function add_learner_columns(string $u, string $userentityname): void {
        $this->add_columns_from_entities(['user:fullnamewithpicturelink', 'user:email']);
        $this->add_column(userlist::phone_column($u, $userentityname));
        $this->add_columns_from_entities(['course:coursefullnamewithlink', 'enrolment:timestarted', 'user:lastaccess']);
        if ($column = $this->get_column('enrolment:timestarted')) {
            $column->set_title(new lang_string('enrolledon', 'local_hikmahaccess'));
        }
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
        $this->set_default_no_results_notice(new lang_string('nolearners', 'local_hikmahaccess'));
    }

    /**
     * One filter per column: name, email, phone, the viewer's own courses, enrolled on, last access.
     *
     * @param string $enrol alias of the enrol table
     * @param string $u alias of the user table
     * @param string $userentityname
     */
    private function add_learner_filters(string $enrol, string $u, string $userentityname): void {
        $this->add_filters_from_entities(['user:fullname', 'user:email']);
        $this->add_filter(userlist::phone_filter($u, $userentityname));
        $this->add_filter((new filter(select::class, 'hkcourse', new lang_string('course'), 'course'))
            ->set_field_sql("{$enrol}.courseid")
            ->set_options($this->courses));
        $this->add_filter_from_entity('enrolment:timestarted')->set_header(new lang_string('enrolledon', 'local_hikmahaccess'));
        $this->add_filters_from_entities(['user:lastaccess']);
    }
}
