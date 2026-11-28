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
 * Tests for the newsletter list (double opt-in, unsubscribe, audience).
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\news
 */
final class news_test extends \advanced_testcase {
    public function test_subscribe_confirm_unsubscribe(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEmails();

        $this->assertFalse(news::subscribe('not an email'));
        $this->assertTrue(news::subscribe(' Sana@Example.com '));
        $this->assertSame(1, $sink->count());
        $row = $DB->get_record(news::TABLE, ['email' => 'sana@example.com']);
        $this->assertEquals(0, $row->confirmed);
        $this->assertSame([], news::audience());

        // A second request within a day sends nothing new.
        $this->assertTrue(news::subscribe('sana@example.com'));
        $this->assertSame(1, $sink->count());

        $this->assertFalse(news::confirm('wrong'));
        $this->assertTrue(news::confirm($row->token));
        $people = news::audience();
        $this->assertCount(1, $people);
        $this->assertSame('list', $people[0]->source);

        $this->assertTrue(news::unsubscribe($row->token));
        $this->assertFalse(news::unsubscribe($row->token));
        $this->assertSame([], news::audience());
    }
}
