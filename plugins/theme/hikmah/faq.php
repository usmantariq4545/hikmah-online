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
 * Public FAQ page.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The FAQ is public.
require_once(__DIR__ . '/../../config.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/hikmah/faq.php'));
$PAGE->set_pagelayout('hikmah');
$PAGE->set_title(get_string('faq_title', 'theme_hikmah') . ' | ' . format_string($SITE->shortname));
$PAGE->set_heading(get_string('faq_title', 'theme_hikmah'));
$PAGE->add_body_class('hk-faqpage');

$data = [
    'pagemotif' => ['motifclass' => 'hk-pagehead__motif hk-motif--animate'],
    'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
    'catalogurl' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
    'faqs' => \theme_hikmah\local\faq::items(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/faq', $data);
echo $OUTPUT->footer();
