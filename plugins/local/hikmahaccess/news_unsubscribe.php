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
 * Unsubscribe link (in the confirmation email and in every newsletter). Opening it shows a button, so email
 * scanners that open links do not unsubscribe anyone; pressing it removes the address from the list.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The link is opened from an email, often without an account.
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$url = \local_hikmahaccess\news::url('unsubscribe', $token);
$home = new moodle_url('/', ['redirect' => 0]);

if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $done = \local_hikmahaccess\news::unsubscribe($token);
    redirect($home, get_string($done ? 'news_unsubscribed' : 'news_unknownlink', 'local_hikmahaccess'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('news_unsubscribe', 'local_hikmahaccess'));
$PAGE->set_heading(get_string('news_unsubscribe', 'local_hikmahaccess'));

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('news_unsubscribe_confirm', 'local_hikmahaccess'),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $home
);
echo $OUTPUT->footer();
