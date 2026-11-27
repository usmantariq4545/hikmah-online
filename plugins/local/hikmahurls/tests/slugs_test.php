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

namespace local_hikmahurls;

/**
 * Tests for course link names.
 *
 * @package    local_hikmahurls
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahurls\slugs
 */
final class slugs_test extends \advanced_testcase {
    /**
     * Titles become lower-case hyphenated names.
     */
    public function test_slugify(): void {
        $this->assertSame('foundations-of-fiqh', slugs::slugify('Foundations of Fiqh'));
        $this->assertSame('the-world-behind-an-islamic-ruling', slugs::slugify('  The World Behind an Islamic Ruling! '));
        $this->assertSame('seerah-101-makkahs-years', slugs::slugify("Seerah 101 — Makkah's years"));
        $this->assertSame('aqeedah-belief', slugs::slugify('Aqeedah &amp; Belief'));
        $this->assertSame('', slugs::slugify('★ ★ ★'));
        $this->assertLessThanOrEqual(80, strlen(slugs::slugify(str_repeat('long title ', 30))));
    }

    /**
     * New courses get a name from their title; a clash gets "-2"; a title with no Latin letters gets course-<id>.
     */
    public function test_new_courses(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_course(['fullname' => 'Foundations of Fiqh']);
        $b = $gen->create_course(['fullname' => 'Foundations of Fiqh']);
        $c = $gen->create_course(['fullname' => '★ ★']);
        $this->assertSame('foundations-of-fiqh', slugs::for_course($a->id));
        $this->assertSame('foundations-of-fiqh-2', slugs::for_course($b->id));
        $this->assertSame('course-' . $c->id, slugs::for_course($c->id));
        $this->assertSame('', slugs::for_course(SITEID));
    }

    /**
     * Names of real scripts under /course/ are never used.
     */
    public function test_reserved(): void {
        $this->resetAfterTest();
        $this->assertTrue(slugs::is_reserved('edit'));
        $this->assertTrue(slugs::is_reserved('format'));
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Edit']);
        $this->assertSame('edit-2', slugs::for_course($course->id));
    }

    /**
     * Renaming a course gives a new name and keeps the old one pointing at it; renaming back reuses it.
     */
    public function test_rename(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Seerah']);
        update_course((object)['id' => $course->id, 'fullname' => 'Seerah of the Prophet']);
        $this->assertSame('seerah-of-the-prophet', slugs::for_course($course->id));
        $old = slugs::find('seerah');
        $this->assertSame((int)$course->id, $old->courseid);
        $this->assertFalse($old->iscurrent);
        $this->assertSame('seerah-of-the-prophet', $old->slug);

        update_course((object)['id' => $course->id, 'fullname' => 'Seerah']);
        $this->assertSame('seerah', slugs::for_course($course->id));
        $this->assertTrue(slugs::find('seerah')->iscurrent);
        // Editing something else keeps the name.
        update_course((object)['id' => $course->id, 'summary' => 'New summary']);
        $this->assertSame('seerah', slugs::for_course($course->id));
    }

    /**
     * Deleting a course frees its names.
     */
    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Tafseer']);
        delete_course($course, false);
        $this->assertFalse($DB->record_exists('local_hikmahurls_slug', ['courseid' => $course->id]));
        $this->assertNull(slugs::find('tafseer'));
    }
}
