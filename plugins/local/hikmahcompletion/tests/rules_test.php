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

namespace local_hikmahcompletion;

/**
 * Tests for the certificate rules.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahcompletion\rules
 */
final class rules_test extends \advanced_testcase {
    /**
     * Without course values the site defaults apply.
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        set_config('defaultgrade', 60, 'local_hikmahcompletion');
        set_config('defaultattendance', 80, 'local_hikmahcompletion');
        $course = $this->getDataGenerator()->create_course();
        $rules = rules::get($course->id);
        $this->assertSame(60, $rules['grade']);
        $this->assertSame(80, $rules['attendance']);
        $this->assertFalse($rules['gradeset']);
    }

    /**
     * A course without a certificate is left alone.
     */
    public function test_no_certificate_no_changes(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'completion' => 1]);
        $this->assertSame([], rules::sync($course->id));
        $this->assertSame(0, (int) get_course($course->id)->enablecompletion);
    }

    /**
     * With a certificate: completion criteria and the restriction are written once; a second run changes nothing.
     */
    public function test_sync_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('mod_coursecertificate')) {
            $this->markTestSkipped('mod_coursecertificate is not installed.');
        }
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'completion' => 1, 'grade' => 10]);
        $template = $generator->get_plugin_generator('tool_certificate')->create_template((object) ['name' => 'T']);
        $certificate = $generator->create_module('coursecertificate', ['course' => $course->id,
            'template' => $template->get_id()]);

        $this->assertNotEmpty(rules::sync($course->id));
        $this->assertSame([], rules::sync($course->id));
        $this->assertTrue($DB->record_exists('course_completion_criteria', ['course' => $course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY, 'moduleinstance' => $quiz->cmid]));
        $availability = json_decode($DB->get_field('course_modules', 'availability', ['id' => $certificate->cmid]));
        $this->assertSame('&', $availability->op);
        $this->assertContains($quiz->cmid, array_column($availability->c, 'cm'));
    }
}
