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

use navigation_node;

/**
 * Tests for the trimmed Preferences page.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahaccess\preferences
 */
final class preferences_test extends \advanced_testcase {
    /**
     * Keys left on a Preferences node shaped like Moodle's, after trimming for the user.
     *
     * @param int $userid
     * @return string[]
     */
    private function trimmed_for(int $userid): array {
        $account = new navigation_node(['text' => 'Preferences', 'key' => 'useraccount']);
        $account->add('Edit profile', new \core\url('/user/edit.php'), navigation_node::TYPE_SETTING, null, 'editprofile');
        $account->add('Preferred language', new \core\url('/user/language.php'), navigation_node::TYPE_SETTING, null,
            'preferredlanguage');
        $account->add('Editor preferences', new \core\url('/user/editor.php'), navigation_node::TYPE_SETTING, null, 'editor');
        $account->add('Content bank', new \core\url('/user/contentbank.php'), navigation_node::TYPE_SETTING, null,
            'contentbankpreferences');
        preferences::trim($account, $userid);
        return array_values(array_map(fn($node) => $node->key, iterator_to_array($account->children)));
    }

    public function test_student_and_teacher(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertSame(['editprofile'], $this->trimmed_for($student->id));
        $this->assertSame(['editprofile', 'contentbankpreferences'], $this->trimmed_for($teacher->id));
    }
}
