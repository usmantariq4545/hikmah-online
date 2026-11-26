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
 * Serves a teacher photo for the home page v2 draft. Photos live in moodledata/hikmah-media/teachers
 * (kept out of the public code repository) and, like the draft page, only site administrators can see them.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$name = required_param('name', PARAM_ALPHA);
// Only the known teacher keys map to a file, so the name can never reach outside the photo folder.
$path = \theme_hikmah\local\home2::photo_path($name);
if ($path === '') {
    send_header_404();
    die();
}
$mimetype = str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg';
send_file($path, basename($path), DAYSECS, 0, false, false, $mimetype);
