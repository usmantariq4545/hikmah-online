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

/**
 * Site defaults for certificate rules.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_hikmahcompletion', get_string('pluginname', 'local_hikmahcompletion'));
    $settings->add(new admin_setting_configtext('local_hikmahcompletion/defaultgrade',
        get_string('defaultgrade', 'local_hikmahcompletion'), get_string('defaultgrade_desc', 'local_hikmahcompletion'),
        50, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_hikmahcompletion/defaultattendance',
        get_string('defaultattendance', 'local_hikmahcompletion'),
        get_string('defaultattendance_desc', 'local_hikmahcompletion'), 75, PARAM_INT));
    $ADMIN->add('localplugins', $settings);
}
