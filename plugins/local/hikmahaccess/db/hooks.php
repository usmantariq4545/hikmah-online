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
 * Hook callbacks for local_hikmahaccess.
 *
 * @package    local_hikmahaccess
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_hikmahaccess\hook_callbacks::class, 'after_config'],
    ],
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => [\local_hikmahaccess\coursevisibility::class, 'form_definition'],
    ],
    [
        'hook' => \core_course\hook\after_form_definition_after_data::class,
        'callback' => [\local_hikmahaccess\coursevisibility::class, 'form_definition_after_data'],
    ],
    [
        'hook' => \core_course\hook\after_form_validation::class,
        'callback' => [\local_hikmahaccess\coursevisibility::class, 'form_validation'],
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => [\local_hikmahaccess\coursevisibility::class, 'form_submission'],
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_hikmahaccess\coursevisibility::class, 'before_footer'],
    ],
];
