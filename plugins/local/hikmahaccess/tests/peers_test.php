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

use core_message\api as message_api;

/**
 * Tests for the classmates messaging rule.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\peers
 * @covers     \local_hikmahaccess\observer
 */
final class peers_test extends \advanced_testcase {
    /**
     * A course with one teacher and two students.
     *
     * @return array [teacher, student1, student2]
     */
    private function classroom(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $s1 = $gen->create_user();
        $s2 = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($s1->id, $course->id, 'student');
        $gen->enrol_user($s2->id, $course->id, 'student');
        return [$teacher, $s1, $s2];
    }

    public function test_switch_off_blocks_classmates_but_not_teachers(): void {
        set_config('allowpeers', 0, 'local_hikmahaccess');
        [$teacher, $s1, $s2] = $this->classroom();

        // The observer has already applied the rule when the students were enrolled.
        $this->assertEquals(
            message_api::MESSAGE_PRIVACY_ONLYCONTACTS,
            get_user_preferences(peers::PREFERENCE, null, $s1->id)
        );

        $this->assertFalse(message_api::can_send_message($s2->id, $s1->id), 'student to student');
        $this->assertTrue(message_api::can_send_message($teacher->id, $s1->id), 'student to teacher');
        $this->assertTrue(message_api::can_send_message($s1->id, $teacher->id), 'teacher to student');
    }

    public function test_switch_on_restores_course_messaging(): void {
        set_config('allowpeers', 0, 'local_hikmahaccess');
        [, $s1, $s2] = $this->classroom();

        set_config('allowpeers', 1, 'local_hikmahaccess');
        $this->assertSame(2, peers::sync_all());
        $this->assertTrue(message_api::can_send_message($s2->id, $s1->id), 'student to student');

        // Running again changes nothing.
        $this->assertSame(0, peers::sync_all());
    }

    public function test_sync_puts_back_a_student_change(): void {
        set_config('allowpeers', 0, 'local_hikmahaccess');
        [, $s1] = $this->classroom();

        set_user_preference(peers::PREFERENCE, message_api::MESSAGE_PRIVACY_COURSEMEMBER, $s1->id);
        $this->assertSame(1, peers::sync_all());
        $this->assertEquals(
            message_api::MESSAGE_PRIVACY_ONLYCONTACTS,
            get_user_preferences(peers::PREFERENCE, null, $s1->id)
        );
    }

    public function test_staff_are_left_alone(): void {
        set_config('allowpeers', 0, 'local_hikmahaccess');
        [$teacher] = $this->classroom();
        $this->assertNull(get_user_preferences(peers::PREFERENCE, null, $teacher->id));
        $this->assertFalse(peers::apply_to_user((int) $teacher->id));
    }
}
