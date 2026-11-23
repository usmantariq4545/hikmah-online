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

namespace paygw_hikmahtransfer\form;

use paygw_hikmahtransfer\receipt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Learner form: transfer method, transaction ID, amount, date and the receipt file (PRD ENR-02).
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata;

        foreach (['component' => PARAM_COMPONENT, 'paymentarea' => PARAM_AREA, 'itemid' => PARAM_INT] as $name => $type) {
            $mform->addElement('hidden', $name, $custom[$name]);
            $mform->setType($name, $type);
        }

        $methods = [];
        foreach ($custom['methods'] as $method) {
            $methods[$method] = get_string('method_' . $method, 'paygw_hikmahtransfer');
        }
        if (count($methods) === 1) {
            // Only one way to pay: nothing to choose, so no question to answer.
            $mform->addElement('hidden', 'method', array_key_first($methods));
            $mform->setType('method', PARAM_ALPHANUMEXT);
        } else {
            $mform->addElement('select', 'method', get_string('field_method', 'paygw_hikmahtransfer'), $methods);
            $mform->addRule('method', null, 'required', null, 'client');
        }

        $mform->addElement(
            'text',
            'txnid',
            get_string('field_txnid', 'paygw_hikmahtransfer'),
            ['size' => 40, 'maxlength' => 100, 'autocomplete' => 'off']
        );
        $mform->setType('txnid', PARAM_TEXT);
        $mform->addRule('txnid', null, 'required', null, 'client');
        $mform->addRule('txnid', null, 'maxlength', 100, 'client');
        $mform->addHelpButton('txnid', 'field_txnid', 'paygw_hikmahtransfer');

        $mform->addElement(
            'float',
            'paidamount',
            get_string('field_paidamount', 'paygw_hikmahtransfer', $custom['currency']),
            ['size' => 12]
        );
        $mform->addRule('paidamount', null, 'required', null, 'client');
        $mform->setDefault('paidamount', $custom['amount']);

        $mform->addElement(
            'date_selector',
            'paiddate',
            get_string('field_paiddate', 'paygw_hikmahtransfer'),
            ['startyear' => (int) date('Y') - 1, 'stopyear' => (int) date('Y')]
        );
        // No default: the theme gives the day, month and year lists an empty "Day / Month / Year" choice.

        $mform->addElement(
            'filemanager',
            'receipt',
            get_string('field_receipt', 'paygw_hikmahtransfer'),
            null,
            receipt::filemanager_options()
        );
        $mform->addRule('receipt', null, 'required', null, 'client');
        $mform->addElement('static', 'receipthint', '', get_string(
            'field_receipt_hint',
            'paygw_hikmahtransfer',
            display_size(receipt::maxbytes())
        ));

        $this->add_action_buttons(true, get_string('submitreceipt', 'paygw_hikmahtransfer'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        if (trim((string) $data['txnid']) === '') {
            $errors['txnid'] = get_string('required');
        }
        if ((float) $data['paidamount'] <= 0) {
            $errors['paidamount'] = get_string('error:amount', 'paygw_hikmahtransfer');
        }
        $raw = (array) $this->_form->getSubmitValue('paiddate');
        if (in_array('', [$raw['day'] ?? '', $raw['month'] ?? '', $raw['year'] ?? ''], true)) {
            $errors['paiddate'] = get_string('required');
        } else if ((int) $data['paiddate'] > time() + DAYSECS) {
            $errors['paiddate'] = get_string('error:futuredate', 'paygw_hikmahtransfer');
        }
        if (!in_array($data['method'], $this->_customdata['methods'], true)) {
            $errors['method'] = get_string('error:method', 'paygw_hikmahtransfer');
        }
        if (count(receipt::draft_files((int) $data['receipt'], (int) $USER->id)) !== 1) {
            $errors['receipt'] = get_string('error:receiptrequired', 'paygw_hikmahtransfer');
        }
        return $errors;
    }
}
