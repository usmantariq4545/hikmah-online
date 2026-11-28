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
 * Footer newsletter form target: adds the address and sends the confirmation email, then goes back.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Visitors without an account can join the list.
require_once(__DIR__ . '/../../config.php');

require_sesskey();
$email = required_param('email', PARAM_RAW_TRIMMED);
$return = new moodle_url(optional_param('returnurl', '/', PARAM_LOCALURL));

// A few sign-ups per browser session at most, so the form cannot be used to send mail to many addresses.
$SESSION->hknewscount = ($SESSION->hknewscount ?? 0) + 1;
if ($SESSION->hknewscount > 3) {
    redirect($return, get_string('news_toomany', 'local_hikmahaccess'), null, \core\output\notification::NOTIFY_WARNING);
}
if (!\local_hikmahaccess\news::subscribe($email)) {
    redirect($return, get_string('news_invalid', 'local_hikmahaccess'), null, \core\output\notification::NOTIFY_ERROR);
}
redirect($return, get_string('news_checkinbox', 'local_hikmahaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
