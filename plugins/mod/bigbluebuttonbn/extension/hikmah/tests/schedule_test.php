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

namespace bbbext_hikmah;

use bbbext_hikmah\local\schedule;

/**
 * Tests for opening live classes before they start.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \bbbext_hikmah\local\schedule
 */
final class schedule_test extends \advanced_testcase {
    public function test_room_opens_early_and_shows_start(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser(); // Creating a class with a start time adds a calendar event.
        $this->assertEquals(schedule::EARLY, schedule::early(), 'db/install.php sets the setting');

        $course = $this->getDataGenerator()->create_course();
        $start = make_timestamp(2030, 1, 5, 10, 0);
        $room = $this->getDataGenerator()->create_module('bigbluebuttonbn', [
            'course' => $course->id,
            'openingtime' => $start,
            'closingtime' => $start + HOURSECS,
        ]);

        schedule::store_opening((int) $room->id, $start);
        $opening = (int) $DB->get_field('bigbluebuttonbn', 'openingtime', ['id' => $room->id]);
        $this->assertSame($start - 5 * MINSECS, $opening);
        $this->assertSame($start, schedule::start($opening));
        $this->assertSame(0, schedule::start(0));

        // No opening time: left alone.
        schedule::store_opening((int) $room->id, 0);
        $this->assertSame($opening, (int) $DB->get_field('bigbluebuttonbn', 'openingtime', ['id' => $room->id]));
    }
}
