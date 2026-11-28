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
 * Link in the confirmation email: puts the address on the newsletter list.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The link is opened from an email, often without an account.
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$ok = \local_hikmahaccess\news::confirm($token);
redirect(
    new moodle_url('/', ['redirect' => 0]),
    get_string($ok ? 'news_confirmed' : 'news_unknownlink', 'local_hikmahaccess'),
    null,
    $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
);
