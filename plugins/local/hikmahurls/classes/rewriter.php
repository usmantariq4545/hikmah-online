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

namespace local_hikmahurls;

use moodle_url;

/**
 * Prints every Moodle link without ".php" ($CFG->urlrewriteclass), and courses by name.
 *
 *   /course/view.php?id=5           -> /course/foundations-of-fiqh
 *   /theme/hikmah/course.php?id=5   -> /courses/foundations-of-fiqh
 *   /theme/hikmah/catalog.php       -> /courses        (also /about, /contact, /faq, /verify, /classroom, /privacy-policy, ...)
 *   /mod/quiz/view.php?id=9         -> /mod/quiz/view?id=9
 *   /my/index.php                   -> /my/
 *
 * Nginx (docker/nginx/default.conf.template) maps these back to the real scripts, and every ".php" link
 * keeps working, so old links, emails and certificate QR codes are unaffected. Links that other systems
 * call or that carry file paths keep their ".php" (see KEEP).
 *
 * @package    local_hikmahurls
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rewriter implements \core\output\url_rewriter {
    /** @var string[] Theme pages with short names: script => path. */
    public const ALIASES = [
        '/theme/hikmah/catalog.php' => '/courses',
        '/theme/hikmah/about.php' => '/about',
        '/theme/hikmah/contact.php' => '/contact',
        '/theme/hikmah/faq.php' => '/faq',
        '/theme/hikmah/verify.php' => '/verify',
        '/theme/hikmah/classroom.php' => '/classroom',
        '/theme/hikmah/privacy.php' => '/privacy-policy',
        '/theme/hikmah/terms.php' => '/terms-of-use',
        '/theme/hikmah/refund.php' => '/refund-policy',
    ];

    /**
     * @var string[] Scripts that keep ".php": registered with Google, BigBlueButton or the Moodle App, called by
     * JavaScript or other servers, or serving files and theme assets; MFA and policy pages, which compare the
     * address with their own ".php" one and otherwise redirect forever. A trailing "/" means everything under it.
     */
    private const KEEP = [
        '/admin/oauth2callback.php',
        '/admin/tool/mfa/',
        '/admin/tool/mobile/',
        '/admin/tool/policy/',
        '/admin/cron.php',
        '/auth/oauth2/',
        '/draftfile.php',
        '/lib/',
        '/login/token.php',
        '/mod/bigbluebuttonbn/bbb_broker.php',
        '/mod/bigbluebuttonbn/bbb_view.php',
        '/pluginfile.php',
        '/r.php',
        '/theme/font.php',
        '/theme/image.php',
        '/theme/jquery.php',
        '/theme/javascript.php',
        '/theme/hikmah/home2_photo.php',
        '/theme/styles.php',
        '/theme/styles_debug.php',
        '/theme/yui_combo.php',
        '/tokenpluginfile.php',
        '/webservice/',
        '/local/hikmahurls/',
    ];

    /**
     * Rewrites one link.
     *
     * @param moodle_url $url
     * @return moodle_url the clean link, or the same one
     */
    public static function url_rewrite(\moodle_url $url) {
        global $CFG;
        $raw = $url->raw_out(false);
        if (!str_starts_with($raw, $CFG->wwwroot . '/')) {
            return $url;
        }
        $parts = parse_url(substr($raw, strlen($CFG->wwwroot)));
        $path = $parts['path'] ?? '';
        if (!str_ends_with($path, '.php') || self::keep($path)) {
            return $url;
        }
        parse_str($parts['query'] ?? '', $params);
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $clean = self::clean($path, $params);
        if ($clean === null) {
            return $url;
        }
        $query = $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
        return new moodle_url($CFG->wwwroot . $clean . $query . $fragment);
    }

    /**
     * Whether a script keeps its ".php" link.
     *
     * @param string $path
     * @return bool
     */
    private static function keep(string $path): bool {
        if (preg_match('~(ajax|service|callback|webhook|ipn|token)[^/]*\.php$~', $path)) {
            return true;
        }
        foreach (self::KEEP as $keep) {
            if ($path === $keep || (str_ends_with($keep, '/') && str_starts_with($path, $keep))) {
                return true;
            }
        }
        return false;
    }

    /**
     * The clean path for a script (params may lose the course id it replaces).
     *
     * @param string $path script path, ending in .php
     * @param array $params query parameters
     * @return string|null
     */
    private static function clean(string $path, array &$params): ?string {
        $courseid = (int)($params['id'] ?? 0);
        if (($path === '/course/view.php' || $path === '/theme/hikmah/course.php') && $courseid) {
            $slug = self::slug($courseid);
            if ($slug !== '') {
                unset($params['id']);
                return ($path === '/course/view.php' ? '/course/' : '/courses/') . $slug;
            }
        }
        if (isset(self::ALIASES[$path])) {
            return self::ALIASES[$path];
        }
        if (str_ends_with($path, '/index.php')) {
            return substr($path, 0, -strlen('index.php'));
        }
        return substr($path, 0, -strlen('.php'));
    }

    /**
     * A course's slug, or '' when there is none or the table is not there yet (during install).
     *
     * @param int $courseid
     * @return string
     */
    private static function slug(int $courseid): string {
        try {
            return slugs::for_course($courseid);
        } catch (\dml_exception $e) {
            return '';
        }
    }

    /**
     * Nothing to add to the page head.
     *
     * @return string
     */
    public static function html_head_setup() {
        return '';
    }
}
