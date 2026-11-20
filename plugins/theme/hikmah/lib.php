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
 * Theme callbacks.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Variables that must exist before Bootstrap and Boost are compiled.
 *
 * @param theme_config $theme
 * @return string SCSS
 */
function theme_hikmah_get_pre_scss($theme): string {
    $scss = file_get_contents(__DIR__ . '/scss/pre.scss');
    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\n\$behatsite: true;\n";
    }
    return $scss;
}

/**
 * Main SCSS: the Boost default preset followed by the Hikmah partials.
 *
 * @param theme_config $theme
 * @return string SCSS
 */
function theme_hikmah_get_main_scss_content($theme): string {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    foreach (['base', 'motion', 'moodle', 'pages', 'app'] as $partial) {
        $scss .= "\n" . file_get_contents(__DIR__ . '/scss/hikmah/_' . $partial . '.scss');
    }
    return $scss;
}

/**
 * Extra SCSS appended last (admin-supplied overrides).
 *
 * @param theme_config $theme
 * @return string SCSS
 */
function theme_hikmah_get_extra_scss($theme): string {
    return !empty($theme->settings->scss) ? $theme->settings->scss : '';
}

/**
 * Marks course pages seen by students (anyone who cannot edit the course) with the body class
 * hk-learner, so the theme can hide teacher-only clutter such as recording names or register filters.
 *
 * @param moodle_page $page
 */
function theme_hikmah_page_init(moodle_page $page): void {
    if (!isloggedin() || isguestuser() || empty($page->course->id) || $page->course->id == SITEID) {
        return;
    }
    // Teachers cannot edit course settings here (050-roles.php), so "staff" is who sees all grades.
    if (!has_capability('moodle/grade:viewall', context_course::instance($page->course->id))) {
        $page->add_body_class('hk-learner');
    }
    // A held live class is headed "Class recording", as on the course page (editors keep the real name).
    if ($page->pagetype === 'mod-bigbluebuttonbn-view' && $page->cm && !$page->user_is_editing()) {
        global $DB;
        $room = $DB->get_record('bigbluebuttonbn', ['id' => $page->cm->instance], 'openingtime, closingtime');
        if ($room && $room->openingtime > 0 && $room->closingtime > 0 && time() > $room->closingtime) {
            $title = get_string('live_recordingtitle', 'theme_hikmah');
            $page->activityheader->set_title($title);
            if ($page->heading === format_string($page->cm->name)) {
                $page->set_heading($title);
            }
        }
    }
}
