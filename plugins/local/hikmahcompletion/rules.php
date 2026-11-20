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
 * A course's certificate rules: minimum grade and attendance, what counts and what must be completed.
 *
 * @package    local_hikmahcompletion
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_hikmahcompletion\rules;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hikmahcompletion:configure', $context);

$url = new moodle_url('/local/hikmahcompletion/rules.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('rules_title', 'local_hikmahcompletion'));
$PAGE->set_heading(format_string($course->fullname));

$current = rules::get($course->id);
$defaults = ['grade' => (int) (get_config('local_hikmahcompletion', 'defaultgrade') ?: 50),
    'attendance' => (int) (get_config('local_hikmahcompletion', 'defaultattendance') ?: 75)];
$form = new \local_hikmahcompletion\form\rules_form($url, ['defaults' => $defaults]);
$form->set_data(['courseid' => $course->id, 'grade' => $current['gradeset'] ? $current['grade'] : '',
    'attendance' => $current['attendanceset'] ? $current['attendance'] : '']);
if ($data = $form->get_data()) {
    $value = static fn(string $v): ?int => $v === '' ? null : (int) $v;
    rules::save($course->id, $value((string) $data->grade), $value((string) $data->attendance));
    rules::sync($course->id);
    redirect($url, get_string('applied', 'local_hikmahcompletion'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('rules_title', 'local_hikmahcompletion'));
echo html_writer::tag('p', get_string('lead', 'local_hikmahcompletion'), ['class' => 'lead']);
$modinfo = get_fast_modinfo($course);
if (!$modinfo->get_instances_of('coursecertificate')) {
    echo $OUTPUT->notification(get_string('nocertificate', 'local_hikmahcompletion'), 'info');
}
$form->display();

// What counts towards the course grade, with each item's share (Moodle's Natural weights).
echo html_writer::tag('h3', get_string('weights_title', 'local_hikmahcompletion'), ['class' => 'mt-4']);
$coursecategory = grade_category::fetch_course_category($course->id);
$items = grade_item::fetch_all(['courseid' => $course->id]) ?: [];
$top = array_filter($items, static fn($item) => !$item->is_course_item() && !$item->is_category_item()
    && (int) $item->categoryid === (int) $coursecategory->id);
// Natural keeps each share in aggregationcoef2; Weighted mean keeps a weight in aggregationcoef.
$weightsum = array_sum(array_map(static fn($item) => (float) $item->aggregationcoef, $top));
$rows = [];
foreach ($top as $item) {
    $share = match ((int) $coursecategory->aggregation) {
        GRADE_AGGREGATE_SUM => $item->aggregationcoef2 * 100,
        GRADE_AGGREGATE_WEIGHTED_MEAN => $weightsum > 0 ? $item->aggregationcoef / $weightsum * 100 : null,
        default => null,
    };
    $rows[] = [format_string($item->get_name()), $share === null ? '—' : format_float($share, 1) . '%'];
}
if ($rows) {
    $table = new html_table();
    $table->head = [get_string('col_item', 'local_hikmahcompletion'), get_string('col_weight', 'local_hikmahcompletion')];
    $table->data = $rows;
    $table->attributes['class'] = 'generaltable';
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo html_writer::tag('p', get_string('noitems', 'local_hikmahcompletion'));
}
if (has_capability('moodle/grade:manage', $context)) {
    echo html_writer::link(new moodle_url('/grade/edit/tree/index.php', ['id' => $course->id]),
        get_string('editweights', 'local_hikmahcompletion'), ['class' => 'btn btn-secondary mb-4']);
}

// What must be completed.
echo html_writer::tag('h3', get_string('required_title', 'local_hikmahcompletion'), ['class' => 'mt-4']);
$list = array_map(static fn($cm) => html_writer::link($cm->url ?? new moodle_url('/course/view.php', ['id' => $course->id]),
    $cm->get_formatted_name()), rules::required($modinfo));
echo $list ? html_writer::alist($list) : html_writer::tag('p', '—');

echo $OUTPUT->footer();
