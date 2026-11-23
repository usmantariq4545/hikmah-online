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

namespace local_hikmahattendance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Link a BigBlueButton activity to an attendance session and set the class window (PRD ATT-02).
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'courseid', $custom['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id', $custom['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'bbbid', get_string('form_bbb', 'local_hikmahattendance'), $custom['bbbs']);
        $mform->addRule('bbbid', null, 'required', null, 'client');

        $mform->addElement('select', 'target', get_string('form_session', 'local_hikmahattendance'), $custom['targets']);
        $mform->addRule('target', null, 'required', null, 'client');
        $mform->addHelpButton('target', 'form_session', 'local_hikmahattendance');

        $mform->addElement('date_time_selector', 'windowstart', get_string('form_windowstart', 'local_hikmahattendance'));
        $mform->addElement('date_time_selector', 'windowend', get_string('form_windowend', 'local_hikmahattendance'));
        $mform->addHelpButton('windowstart', 'form_windowstart', 'local_hikmahattendance');

        $mform->addElement(
            'text',
            'threshold',
            get_string('form_threshold', 'local_hikmahattendance', $custom['default']),
            ['size' => 4]
        );
        $mform->setType('threshold', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('threshold', 'form_threshold', 'local_hikmahattendance');

        $mform->addElement(
            'advcheckbox',
            'cancelled',
            get_string('form_cancelled', 'local_hikmahattendance'),
            get_string('form_cancelled_label', 'local_hikmahattendance')
        );

        $this->add_action_buttons(true, get_string('form_save', 'local_hikmahattendance'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int) $data['windowend'] <= (int) $data['windowstart']) {
            $errors['windowend'] = get_string('error:window', 'local_hikmahattendance');
        }
        if ($data['windowend'] - $data['windowstart'] > DAYSECS) {
            $errors['windowend'] = get_string('error:windowlong', 'local_hikmahattendance');
        }
        if (
            $data['threshold'] !== '' && (!ctype_digit((string) $data['threshold']) || $data['threshold'] < 1
                || $data['threshold'] > 100)
        ) {
            $errors['threshold'] = get_string('error:threshold', 'local_hikmahattendance');
        }
        if (empty($data['bbbid']) || !isset($this->_customdata['bbbs'][$data['bbbid']])) {
            $errors['bbbid'] = get_string('required');
        }
        if (empty($data['target']) || !isset($this->_customdata['targets'][$data['target']])) {
            $errors['target'] = get_string('required');
        }
        return $errors;
    }
}
