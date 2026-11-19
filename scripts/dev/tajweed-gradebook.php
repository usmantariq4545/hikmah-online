<?php
// Gradebook of the demo course "Foundations of Tajweed": a weighted mean matching the course handbook
// (quizzes 40%, assignments 30%, final recitation 20%, attendance 10%). Used by tajweed-course.php;
// can also be run on its own inside the moodle container: php tajweed-gradebook.php

if (!defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
    require('/var/www/moodle/config.php');
    $runnow = true;
}
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

/**
 * Weight the course's grade items as the handbook describes.
 *
 * @param int $courseid
 */
function hk_tajweed_gradebook(int $courseid): void {
    $weights = [
        'Attendance register' => 10,
        'Quiz 1: makharij check' => 10,
        'Quiz 2: noon and meem sakinah' => 10,
        'Final quiz' => 20,
        'Assignment 1: recite the throat letters' => 15,
        'Assignment 2: noon sakinah worksheet' => 15,
        'Final recitation: Surah Al-Ikhlas' => 20,
    ];
    $category = grade_category::fetch_course_category($courseid);
    $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
    $category->aggregateonlygraded = 1; // Work not yet due does not pull the total down.
    $category->update();
    foreach (grade_item::fetch_all(['courseid' => $courseid, 'itemtype' => 'mod']) ?: [] as $item) {
        if (isset($weights[$item->itemname])) {
            $item->aggregationcoef = $weights[$item->itemname];
            $item->update();
        }
    }
    $total = grade_item::fetch_course_item($courseid);
    $total->grademax = 100; // The course total reads as a percentage.
    $total->update();
    grade_regrade_final_grades($courseid);
}

if (!empty($runnow)) {
    \core\session\manager::set_user(get_admin());
    hk_tajweed_gradebook((int) $DB->get_field('course', 'id', ['idnumber' => 'demo-tajweed'], MUST_EXIST));
    cli_writeln('Gradebook weighted.');
}
