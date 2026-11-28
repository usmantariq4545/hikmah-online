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
 * Tests for the PostHog key-event queue.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\analytics
 */
final class analytics_test extends \advanced_testcase {
    public function test_events_are_queued_for_the_right_person(): void {
        $this->resetAfterTest();
        set_config('posthogkey', 'phc_test', 'theme_hikmah');
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Foundations of Tajweed']);
        $learner = $this->getDataGenerator()->create_user();

        // The learner's own action waits in their session.
        $this->setUser($learner);
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        $items = analytics::take();
        $this->assertSame('course viewed', $items[0]['event']);
        $this->assertSame('Foundations of Tajweed', $items[0]['properties']['course_name']);
        $this->assertSame([], analytics::take());

        // An admin enrolling the learner: the event waits for the learner's next page.
        $this->setAdminUser();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->assertSame([], analytics::take());
        $this->setUser($learner);
        $this->assertSame(['enrolled'], array_column(analytics::take(), 'event'));

        // Without a PostHog key nothing is queued.
        set_config('posthogkey', '', 'theme_hikmah');
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        $this->assertSame([], analytics::take());
    }
}
