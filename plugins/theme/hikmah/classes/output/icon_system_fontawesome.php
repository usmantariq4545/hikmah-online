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

namespace theme_hikmah\output;

/**
 * Moodle's Font Awesome icons with one change: required form fields are marked with a red "*",
 * the convention people know, instead of a red "!" (owner's request, 2026-11-20).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icon_system_fontawesome extends \core\output\icon_system_fontawesome {
    /**
     * Core icon map with the required-field icon changed.
     *
     * @return array
     */
    public function get_core_icon_map() {
        return array_merge(parent::get_core_icon_map(), ['core:req' => 'fa-asterisk text-danger']);
    }
}
