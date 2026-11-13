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
 * Site-wide settings. Transfer account details are set per payment account.
 *
 * @package    paygw_hikmahtransfer
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $reviewurl = new moodle_url('/payment/gateway/hikmahtransfer/review.php');
    $settings->add(new admin_setting_heading(
        'paygw_hikmahtransfer_settings',
        '',
        get_string('pluginname_desc', 'paygw_hikmahtransfer') . ' ' .
            html_writer::link($reviewurl, get_string('reviewtitle', 'paygw_hikmahtransfer'))
    ));

    $settings->add(new admin_setting_configselect(
        'paygw_hikmahtransfer/maxbytes',
        get_string('setting_maxbytes', 'paygw_hikmahtransfer'),
        get_string('setting_maxbytes_desc', 'paygw_hikmahtransfer'),
        5242880,
        [1048576 => '1 MB', 2097152 => '2 MB', 5242880 => '5 MB', 10485760 => '10 MB']
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_hikmahtransfer/retentiondays',
        get_string('setting_retentiondays', 'paygw_hikmahtransfer'),
        get_string('setting_retentiondays_desc', 'paygw_hikmahtransfer'),
        365,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'paygw_hikmahtransfer/reviewerdigest',
        get_string('setting_reviewerdigest', 'paygw_hikmahtransfer'),
        get_string('setting_reviewerdigest_desc', 'paygw_hikmahtransfer'),
        0
    ));

    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_hikmahtransfer');
}
