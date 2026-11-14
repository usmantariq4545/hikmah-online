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

namespace bbbext_hikmah\local;

/**
 * Builds the BigBlueButton API parameters that give a live class the Hikmah look.
 *
 * These are requests to the BigBlueButton server, which decides whether to honour them:
 *  - create: logo and darklogo (the class sidebar logo);
 *  - join:   userdata-bbb_custom_style_url or userdata-bbb_custom_style (Hikmah colours),
 *            userdata-bbb_display_branding_area (show the logo) and userdata-bbb_client_title.
 *
 * The class runs on an HTTPS page, so a logo or stylesheet address is only sent when this site is
 * on HTTPS too (a browser would block them otherwise). On plain HTTP, such as the laptop site,
 * the colours are sent inline instead and the logo is left out.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class branding {
    /** @var string stylesheet applied to the live class, relative to this plugin */
    const STYLESHEET = 'client/classroom.css';

    /**
     * Extra parameters for a BigBlueButton API action.
     *
     * @param string $action BigBlueButton API action, such as create or join
     * @return array parameter name => value; empty when branding is off or the action needs none
     */
    public static function params(string $action): array {
        if (!get_config('bbbext_hikmah', 'brandclient')) {
            return [];
        }
        $https = self::is_https();

        if ($action === 'create') {
            if (!$https) {
                return [];
            }
            return [
                'logo' => self::logo_url('logo-dark'),
                'darklogo' => self::logo_url('logo-light'),
            ];
        }

        if ($action === 'join') {
            $params = ['userdata-bbb_client_title' => self::site_name()];
            if ($https) {
                $params['userdata-bbb_custom_style_url'] = self::stylesheet_url();
                $params['userdata-bbb_display_branding_area'] = 'true';
            } else {
                $params['userdata-bbb_custom_style'] = self::inline_stylesheet();
            }
            return $params;
        }

        return [];
    }

    /**
     * Whether this site is served over HTTPS.
     *
     * @return bool
     */
    protected static function is_https(): bool {
        global $CFG;
        return str_starts_with(strtolower($CFG->wwwroot), 'https://');
    }

    /**
     * Site name shown as the class window title.
     *
     * @return string
     */
    protected static function site_name(): string {
        global $SITE;
        return format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Public address of a Hikmah logo from the theme.
     *
     * @param string $image image name in theme_hikmah's pix folder
     * @return string
     */
    protected static function logo_url(string $image): string {
        global $CFG;
        // The address must work for someone who is not logged in to Moodle: the class page asks for it.
        $rev = (int) theme_get_revision();
        return $CFG->wwwroot . '/theme/image.php/hikmah/theme_hikmah/' . max($rev, 1) . '/' . $image;
    }

    /**
     * Public address of the live class stylesheet.
     *
     * @return string
     */
    protected static function stylesheet_url(): string {
        global $CFG;
        $version = get_config('bbbext_hikmah', 'version') ?: 1;
        return $CFG->wwwroot . '/mod/bigbluebuttonbn/extension/hikmah/' . self::STYLESHEET . '?v=' . $version;
    }

    /**
     * The live class stylesheet as one compact line, for sending inside the join address.
     *
     * @return string
     */
    protected static function inline_stylesheet(): string {
        $css = (string) file_get_contents(__DIR__ . '/../../' . self::STYLESHEET);
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $css = preg_replace('~\s+~', ' ', $css);
        $css = preg_replace('~\s*([{}:;,])\s*~', '$1', $css);
        return trim(str_replace(';}', '}', $css));
    }
}
