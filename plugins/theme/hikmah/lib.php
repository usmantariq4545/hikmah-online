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
