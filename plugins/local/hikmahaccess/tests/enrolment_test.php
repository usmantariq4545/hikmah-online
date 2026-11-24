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
 * Tests for one enrollment per learner per course.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\observer::user_enrolment_created
 */
final class enrolment_test extends \advanced_testcase {
    /**
     * Number of enrollments a user has in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    private function count_enrolments(int $userid, int $courseid): int {
        return $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = ? AND e.courseid = ?",
            [$userid, $courseid]
        );
    }

    public function test_manual_enrolment_of_already_enrolled_learner_is_removed(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'self']);
        $gen->enrol_user($user->id, $course->id, 'student', 'self');
        $gen->enrol_user($user->id, $course->id, 'student', 'manual');

        $this->assertEquals(1, $this->count_enrolments($user->id, $course->id));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user, '', true));
    }

    public function test_first_manual_enrolment_is_kept(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student', 'manual');

        $this->assertEquals(1, $this->count_enrolments($user->id, $course->id));
    }
}
