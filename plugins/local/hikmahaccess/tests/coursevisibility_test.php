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
 * Tests for Hidden / Public / Scheduled course visibility.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\coursevisibility
 */
final class coursevisibility_test extends \advanced_testcase {
    public function test_publish_due_publishes_only_due_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $due = $gen->create_course(['visible' => 0]);
        $later = $gen->create_course(['visible' => 0]);
        coursevisibility::schedule($due->id, time() - 60);
        coursevisibility::schedule($later->id, time() + HOURSECS);

        $this->assertSame(1, coursevisibility::publish_due());
        $this->assertEquals(1, $DB->get_field('course', 'visible', ['id' => $due->id]));
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $later->id]));
        $this->assertFalse($DB->record_exists(coursevisibility::TABLE, ['courseid' => $due->id]));
        $this->assertTrue($DB->record_exists(coursevisibility::TABLE, ['courseid' => $later->id]));
        $this->assertSame(0, coursevisibility::publish_due(), 'running again changes nothing');
    }

    public function test_schedule_updates_and_clears(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        coursevisibility::schedule($course->id, 1000);
        coursevisibility::schedule($course->id, 2000);
        $this->assertEquals(2000, $DB->get_field(coursevisibility::TABLE, 'timepublish', ['courseid' => $course->id]));
        coursevisibility::schedule($course->id, null);
        $this->assertFalse($DB->record_exists(coursevisibility::TABLE, ['courseid' => $course->id]));
    }

    public function test_deleting_a_course_forgets_its_schedule(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        coursevisibility::schedule($course->id, time() + HOURSECS);
        delete_course($course, false);
        $this->assertFalse($DB->record_exists(coursevisibility::TABLE, ['courseid' => $course->id]));
    }
}
