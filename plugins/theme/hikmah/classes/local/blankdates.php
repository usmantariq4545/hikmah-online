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

namespace theme_hikmah\local;

/**
 * Empty date fields: Moodle's date lists always show a date (today), so a date the person has not
 * given yet looks filled in. This adds an empty "Day / Month / Year" choice, selected and required.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blankdates {
    /**
     * Start the date field $name empty on this page, unless the form came back with a date picked.
     *
     * @param string $name the form element name, e.g. profile_field_hkdob
     * @param bool $hasvalue the person already has a saved date for this field
     */
    public static function add(string $name, bool $hasvalue): void {
        global $PAGE;
        $posted = optional_param_array($name, null, PARAM_RAW);
        if ($posted !== null ? ($posted['day'] ?? '') !== '' : $hasvalue) {
            return;
        }
        $labels = [get_string('day', 'form'), get_string('month', 'form'), get_string('year', 'form')];
        $PAGE->requires->js_init_code('(function(n,l){["day","month","year"].forEach(function(p,i){'
            . 'var s=document.querySelector(\'select[name="\'+n+"["+p+\']"]\');if(!s){return;}'
            . 's.insertBefore(new Option(l[i],""),s.firstChild);s.value="";s.required=true;});})('
            . json_encode($name) . ',' . json_encode($labels) . ');');
    }
}
