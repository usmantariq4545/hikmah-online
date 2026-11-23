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

namespace local_hikmahcompletion\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The two certificate minimums of a course.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules_form extends \moodleform {
    /**
     * Form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        foreach (['grade', 'attendance'] as $key) {
            $mform->addElement('text', $key, get_string('field_' . $key, 'local_hikmahcompletion'), ['size' => 4]);
            $mform->setType($key, PARAM_RAW_TRIMMED);
            $mform->addElement(
                'static',
                $key . 'help',
                '',
                get_string('field_' . $key . '_help', 'local_hikmahcompletion', $this->_customdata['defaults'][$key])
            );
        }
        $this->add_action_buttons(false, get_string('save', 'local_hikmahcompletion'));
    }

    /**
     * Whole percentages, or empty.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = [];
        foreach (['grade', 'attendance'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '' && (!ctype_digit($value) || (int) $value > 100)) {
                $errors[$key] = get_string('error:percent', 'local_hikmahcompletion');
            }
        }
        return $errors;
    }
}
