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
 * Public course catalog with search and filters (PRD CRS-02). Server-rendered; the motion
 * module only swaps the results region so filtering feels instant.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The catalog is public unless forcelogin is on.
require_once(__DIR__ . '/../../config.php');

use theme_hikmah\local\catalog;

$filters = [
    'q' => trim(optional_param('q', '', PARAM_TEXT)),
    'mode' => optional_param('mode', '', PARAM_ALPHA),
    'language' => trim(optional_param('language', '', PARAM_TEXT)),
    'price' => optional_param('price', '', PARAM_ALPHA),
    'category' => optional_param('category', 0, PARAM_INT),
];

if (!empty($CFG->forcelogin)) {
    require_login();
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/hikmah/catalog.php', array_filter($filters)));
$PAGE->set_pagelayout('hikmah');
$PAGE->set_title(get_string('catalog_title', 'theme_hikmah') . ' | ' . format_string($SITE->shortname));
$PAGE->set_heading(get_string('catalog_title', 'theme_hikmah'));
$PAGE->add_body_class('hk-catalogpage');

$options = catalog::get_filter_options();
$cards = catalog::get_cards($filters);

$select = static function (array $values, $current): array {
    $out = [];
    foreach ($values as $value => $label) {
        $out[] = ['value' => $value, 'label' => $label, 'selected' => (string) $value === (string) $current];
    }
    return $out;
};

$count = count($cards);
$context = [
    'pagemotif' => ['motifclass' => 'hk-pagehead__motif hk-motif--animate'],
    'action' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
    'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
    'q' => $filters['q'],
    'modes' => $select($options['modes'], $filters['mode']),
    'languages' => $select($options['languages'], $filters['language']),
    'categories' => $select($options['categories'], $filters['category'] ?: ''),
    'prices' => $select([
        'free' => get_string('filter_free', 'theme_hikmah'),
        'paid' => get_string('filter_paid', 'theme_hikmah'),
    ], $filters['price']),
    'courses' => $cards,
    'hascourses' => $count > 0,
    'resultcount' => get_string($count === 1 ? 'catalog_count_one' : 'catalog_count', 'theme_hikmah', $count),
    'filtered' => !empty(array_filter($filters)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_hikmah/catalog', $context);
echo $OUTPUT->footer();
