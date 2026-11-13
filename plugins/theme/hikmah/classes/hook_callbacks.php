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

namespace theme_hikmah;

use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Hook callbacks for the Hikmah theme.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * True when the Hikmah theme is rendering the current page.
     *
     * @return bool
     */
    private static function is_active(): bool {
        global $PAGE;
        // The theme is a magic property of the page, so isset() cannot be used to test it.
        try {
            $theme = $PAGE->theme;
        } catch (\Throwable $e) {
            return false;
        }
        return $theme->name === 'hikmah' || in_array('hikmah', $theme->parents ?? [], true);
    }

    /**
     * Marks the document as script-capable before first paint, so scroll reveals never flash.
     * If the motion module fails to start within three seconds, the mark is removed again and
     * every element shows without animation.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_head(before_standard_head_html_generation $hook): void {
        if (!self::is_active()) {
            return;
        }
        $hook->add_html(
            "<script>(function(d){d.classList.add('hk-js');setTimeout(function(){" .
            "if(!window.hkMotionReady){d.classList.remove('hk-js');}},3000);})(document.documentElement);</script>\n" .
            '<meta name="theme-color" content="#1a1a1a">'
        );
    }

    /**
     * Staging banner and the motion module.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_top_of_body(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (!self::is_active()) {
            return;
        }
        if (get_config('theme_hikmah', 'siteenv') === 'staging') {
            $hook->add_html('<div class="hk-staging-banner" role="note">' .
                get_string('stagingbanner', 'theme_hikmah') . '</div>');
        }
        $PAGE->requires->js_call_amd('theme_hikmah/motion', 'init');

        // On BigBlueButton activity pages, "Join session" and recordings open in the in-portal
        // classroom instead of a separate window.
        if (get_config('theme_hikmah', 'embedclassroom') && str_starts_with($PAGE->pagetype, 'mod-bigbluebuttonbn-')) {
            $PAGE->requires->js_call_amd('theme_hikmah/classroom', 'intercept',
                [(new \moodle_url('/theme/hikmah/classroom.php'))->out(false)]);
        }
    }
}
