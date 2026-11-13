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

namespace paygw_hikmahtransfer;

/**
 * Gateway definition for the Payment API: supported currencies and the per-account settings
 * (the transfer details shown to learners).
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /** @var array Per-account fields: key => [method it enables or null, PARAM type, input size]. */
    public const FIELDS = [
        'banktitle' => ['bank', PARAM_TEXT, 50],
        'bankname' => ['bank', PARAM_TEXT, 50],
        'bankiban' => ['bank', PARAM_TEXT, 40],
        'jazzcash' => ['jazzcash', PARAM_TEXT, 30],
        'easypaisa' => ['easypaisa', PARAM_TEXT, 30],
        'raast' => ['raast', PARAM_TEXT, 40],
    ];

    /**
     * Currencies learners may be charged in for a manual transfer.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return ['PKR', 'USD', 'GBP', 'EUR', 'AED', 'SAR'];
    }

    /**
     * Account-level settings: where learners send the money.
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('static', 'hikmahtransfer_intro', '', get_string('config_intro', 'paygw_hikmahtransfer'));
        foreach (self::FIELDS as $name => [, $type, $size]) {
            $mform->addElement('text', $name, get_string('config_' . $name, 'paygw_hikmahtransfer'), ['size' => $size]);
            $mform->setType($name, $type);
        }
        $mform->addElement('textarea', 'instructions', get_string('config_instructions', 'paygw_hikmahtransfer'),
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('instructions', PARAM_TEXT);
        $mform->addHelpButton('instructions', 'config_instructions', 'paygw_hikmahtransfer');
    }

    /**
     * The gateway can only be enabled when at least one transfer method is filled in.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if ($data->enabled && !self::enabled_methods((array) $data)) {
            $errors['enabled'] = get_string('error:nomethods', 'paygw_hikmahtransfer');
        }
    }

    /**
     * Transfer methods available for an account configuration.
     *
     * @param array $config gateway configuration
     * @return string[] method keys (bank, jazzcash, easypaisa, raast)
     */
    public static function enabled_methods(array $config): array {
        $methods = [];
        if (trim((string) ($config['bankiban'] ?? '')) !== '') {
            $methods[] = 'bank';
        }
        foreach (['jazzcash', 'easypaisa', 'raast'] as $method) {
            if (trim((string) ($config[$method] ?? '')) !== '') {
                $methods[] = $method;
            }
        }
        return $methods;
    }
}
