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
 * Newsletter audience for admins: confirmed addresses from the footer form and accounts that ticked the news box
 * at sign-up, with a CSV download (each list address carries its unsubscribe link for the mailing tool).
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_hikmahaccess_news');

$people = \local_hikmahaccess\news::audience();
$source = fn($p) => get_string('news_source_' . $p->source, 'local_hikmahaccess');

if (optional_param('download', 0, PARAM_BOOL)) {
    require_sesskey();
    \core\dataformat::download_data(
        'hikmah-newsletter-' . date('Y-m-d'),
        'csv',
        ['email' => get_string('email'), 'source' => get_string('news_source', 'local_hikmahaccess'),
            'since' => get_string('news_since', 'local_hikmahaccess'),
            'unsubscribe' => get_string('news_unsubscribelink', 'local_hikmahaccess')],
        new ArrayIterator($people),
        fn($p) => ['email' => $p->email, 'source' => $source($p), 'since' => userdate($p->time, '%Y-%m-%d'),
            'unsubscribe' => $p->unsubscribeurl]
    );
    die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('news_admin', 'local_hikmahaccess'));
echo html_writer::tag('p', get_string('news_admin_intro', 'local_hikmahaccess', count($people)));
if ($people) {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['download' => 1, 'sesskey' => sesskey()]),
        get_string('news_download', 'local_hikmahaccess'),
        'get'
    );
    $table = new html_table();
    $table->head = [get_string('email'), get_string('news_source', 'local_hikmahaccess'),
        get_string('news_since', 'local_hikmahaccess')];
    foreach ($people as $p) {
        $table->data[] = [s($p->email), $source($p), userdate($p->time, get_string('strftimedate', 'langconfig'))];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
