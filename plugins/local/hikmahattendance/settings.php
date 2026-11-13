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
 * Site settings. Autowrite is not a site setting: it is switched per course and ships off.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_hikmahattendance', get_string('pluginname', 'local_hikmahattendance'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_hikmahattendance/threshold',
            get_string('setting_threshold', 'local_hikmahattendance'),
            get_string('setting_threshold_desc', 'local_hikmahattendance'),
            75,
            PARAM_INT
        ));
        foreach (['present' => 'P', 'partial' => 'L', 'absent' => 'A'] as $status => $default) {
            $settings->add(new admin_setting_configtext(
                'local_hikmahattendance/acronym_' . $status,
                get_string('setting_acronym_' . $status, 'local_hikmahattendance'),
                get_string('setting_acronym_desc', 'local_hikmahattendance'),
                $default,
                PARAM_ALPHANUM,
                4
            ));
        }
        $settings->add(new admin_setting_configtext(
            'local_hikmahattendance/missinghours',
            get_string('setting_missinghours', 'local_hikmahattendance'),
            get_string('setting_missinghours_desc', 'local_hikmahattendance'),
            2,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_hikmahattendance/retentiondays',
            get_string('setting_retentiondays', 'local_hikmahattendance'),
            get_string('setting_retentiondays_desc', 'local_hikmahattendance'),
            365,
            PARAM_INT
        ));
    }
}
