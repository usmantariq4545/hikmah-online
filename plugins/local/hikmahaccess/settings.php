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
 * Settings. The switch is managed by config/settings/050-roles.php from HIKMAH_STUDENT_PEERS; this
 * page shows its current value to administrators.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_hikmahaccess', new lang_string('pluginname', 'local_hikmahaccess'));
    $settings->add(new admin_setting_configcheckbox(
        'local_hikmahaccess/allowpeers',
        new lang_string('allowpeers', 'local_hikmahaccess'),
        new lang_string('allowpeers_desc', 'local_hikmahaccess'),
        0
    ));
    $ADMIN->add('localplugins', $settings);
}

// Managers without site settings still reach the newsletter list (the page checks its own capability).
$ADMIN->add('accounts', new admin_externalpage(
    'local_hikmahaccess_news',
    new lang_string('news_admin', 'local_hikmahaccess'),
    new moodle_url('/local/hikmahaccess/news.php'),
    'local/hikmahaccess:managenews'
));
