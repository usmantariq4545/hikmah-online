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

use core_reportbuilder\system_report_factory;
use local_hikmahaccess\reportbuilder\local\systemreports\all_users;
use local_hikmahaccess\reportbuilder\local\systemreports\my_learners;

/**
 * Tests for the "Users" page: who gets which list, and what a teacher's list holds.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\userlist
 * @covers     \local_hikmahaccess\reportbuilder\local\systemreports\my_learners
 * @covers     \local_hikmahaccess\reportbuilder\local\systemreports\all_users
 */
final class userlist_test extends \advanced_testcase {
    /**
     * A teacher sees only the students of their own courses: not other courses' students, not a co-teacher.
     */
    public function test_teacher_sees_own_learners_only(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course();
        $other = $gen->create_course();
        // Fixed names: random ones can repeat and make the "not shown" checks fail by chance.
        $teacher = $gen->create_user(['firstname' => 'Tariq', 'lastname' => 'Teacher']);
        $coteacher = $gen->create_user(['firstname' => 'Cora', 'lastname' => 'Coteacher']);
        $learner = $gen->create_user(['firstname' => 'Layla', 'lastname' => 'Learner']);
        $stranger = $gen->create_user(['firstname' => 'Sami', 'lastname' => 'Stranger']);
        $gen->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $gen->enrol_user($coteacher->id, $mine->id, 'teacher');
        $gen->enrol_user($learner->id, $mine->id, 'student');
        $gen->enrol_user($stranger->id, $other->id, 'student');

        $this->setUser($teacher);
        $this->assertSame(my_learners::class, userlist::report_class());
        $this->assertSame([(int) $mine->id], array_keys(userlist::taught_courses()));

        $report = system_report_factory::create(my_learners::class, \context_system::instance());
        $html = $report->output();
        $this->assertStringContainsString(fullname($learner), $html);
        $this->assertStringNotContainsString(fullname($stranger), $html);
        $this->assertStringNotContainsString(fullname($coteacher), $html);
        $this->assertFalse($report->is_downloadable());
    }

    /**
     * Managers and support see everyone; only managers (and administrators) may download.
     */
    public function test_staff_lists_and_download(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $system = \context_system::instance();
        $manager = $gen->create_user();
        $support = $gen->create_user();
        $gen->role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $manager->id, $system->id);
        $supportrole = create_role('Support', 'hksupporttest', '');
        assign_capability('local/hikmahaccess:viewallusers', CAP_ALLOW, $supportrole, $system->id);
        $gen->role_assign($supportrole, $support->id, $system->id);

        $this->setUser($manager);
        $this->assertSame(all_users::class, userlist::report_class());
        $this->assertTrue(system_report_factory::create(all_users::class, $system)->is_downloadable());

        $this->setUser($support);
        $this->assertSame(all_users::class, userlist::report_class());
        $this->assertFalse(system_report_factory::create(all_users::class, $system)->is_downloadable());

        $this->setAdminUser();
        $this->assertTrue(system_report_factory::create(all_users::class, $system)->is_downloadable());
    }

    /**
     * Managers choose their columns (kept in display order, unknown ones dropped); support always gets the default ones.
     */
    public function test_choose_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $system = \context_system::instance();
        $manager = $gen->create_user();
        $gen->role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $manager->id, $system->id);
        $support = $gen->create_user();
        $supportrole = create_role('Support', 'hksupportcols', '');
        assign_capability('local/hikmahaccess:viewallusers', CAP_ALLOW, $supportrole, $system->id);
        $gen->role_assign($supportrole, $support->id, $system->id);

        $this->setUser($manager);
        $this->assertTrue(userlist::can_choose_columns());
        $this->assertSame(userlist::DEFAULT_COLUMNS, userlist::chosen_columns());
        userlist::set_columns(['lastip', 'nosuchcolumn', 'email', 'roles']);
        $this->assertSame(['email', 'roles', 'lastip'], userlist::chosen_columns());
        $html = system_report_factory::create(all_users::class, $system)->output();
        $this->assertStringContainsString(get_string('roles', 'local_hikmahaccess'), $html);
        userlist::set_columns([]);
        $this->assertSame(userlist::DEFAULT_COLUMNS, userlist::chosen_columns());

        $this->setUser($support);
        $this->assertFalse(userlist::can_choose_columns());
        set_user_preference(userlist::PREFERENCE, 'lastip,city');
        $this->assertSame(userlist::DEFAULT_COLUMNS, userlist::chosen_columns());
    }

    /**
     * Students and finance reviewers get no list.
     */
    public function test_others_get_nothing(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $finance = $gen->create_user();
        $financerole = create_role('Finance', 'hkfinancetest', '');
        $gen->role_assign($financerole, $finance->id, \context_system::instance()->id);

        $this->setUser($student);
        $this->assertNull(userlist::report_class());
        $this->setUser($finance);
        $this->assertNull(userlist::report_class());
    }
}
