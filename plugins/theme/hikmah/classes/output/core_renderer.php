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

use moodle_url;

/**
 * Core renderer for the Hikmah theme. Adds small helpers the templates call as {{output.hk_*}} and
 * places the learner/instructor summary at the top of the dashboard.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Logo with dark lettering, for light backgrounds.
     *
     * @return string URL
     */
    public function hk_logo_dark(): string {
        return $this->image_url('logo-dark', 'theme_hikmah')->out(false);
    }

    /**
     * Logo with light lettering, for dark backgrounds.
     *
     * @return string URL
     */
    public function hk_logo_light(): string {
        return $this->image_url('logo-light', 'theme_hikmah')->out(false);
    }

    /**
     * Site home URL.
     *
     * @return string
     */
    public function hk_home_url(): string {
        return (new moodle_url('/', ['redirect' => 0]))->out(false);
    }

    /**
     * Catalog URL.
     *
     * @return string
     */
    public function hk_catalog_url(): string {
        return (new moodle_url('/theme/hikmah/catalog.php'))->out(false);
    }

    /**
     * FAQ URL.
     *
     * @return string
     */
    public function hk_faq_url(): string {
        return (new moodle_url('/theme/hikmah/faq.php'))->out(false);
    }

    /**
     * Public certificate verification URL.
     *
     * @return string
     */
    public function hk_verify_url(): string {
        return (new moodle_url('/theme/hikmah/verify.php'))->out(false);
    }

    /**
     * Dashboard URL.
     *
     * @return string
     */
    public function hk_dashboard_url(): string {
        return (new moodle_url('/my/'))->out(false);
    }

    /**
     * Login URL.
     *
     * @return string
     */
    public function hk_login_url(): string {
        return get_login_url();
    }

    /**
     * Sign-up URL.
     *
     * @return string
     */
    public function hk_signup_url(): string {
        return (new moodle_url('/login/signup.php'))->out(false);
    }

    /**
     * Site policies URL (privacy and terms), when the policy tool is the handler.
     *
     * @return string
     */
    public function hk_policies_url(): string {
        return (new moodle_url('/admin/tool/policy/viewall.php'))->out(false);
    }

    /**
     * True when nobody (or only the guest account) is signed in.
     *
     * @return bool
     */
    public function hk_is_guest(): bool {
        return !isloggedin() || isguestuser();
    }

    /**
     * True when self-registration is switched on.
     *
     * @return bool
     */
    public function hk_can_signup(): bool {
        global $CFG;
        return $this->hk_is_guest() && !empty($CFG->registerauth);
    }

    /**
     * True on the staging environment, which shows a red banner on every page.
     *
     * @return bool
     */
    public function hk_is_staging(): bool {
        return get_config('theme_hikmah', 'siteenv') === 'staging';
    }

    /**
     * Current year for the footer.
     *
     * @return string
     */
    public function hk_year(): string {
        return userdate(time(), '%Y');
    }

    /**
     * Site full name.
     *
     * @return string
     */
    public function hk_sitename(): string {
        global $SITE;
        return format_string($SITE->fullname, true, ['context' => \context_system::instance()]);
    }

    /**
     * Boost always heads an activity page with the activity's name; a held live class says "Class recording".
     *
     * @param array|null $headerinfo
     * @param int $headinglevel
     * @return string HTML
     */
    public function context_header($headerinfo = null, $headinglevel = 1): string {
        if ($this->page->pagetype === 'course-view-participants' && $this->page->course->id == SITEID
                && !isset($headerinfo['heading'])) {
            // The site's participant list is the "People" page of the top menu.
            return parent::context_header(['heading' => get_string('nav_people', 'theme_hikmah')], $headinglevel);
        }
        $title = \theme_hikmah\local\live_classes::held_title($this->page);
        if ($title && !isset($headerinfo['heading'])) {
            $name = $this->page->cm->get_formatted_name();
            return str_replace('>' . $name . '</h', '>' . s($title) . '</h', parent::context_header($headerinfo, $headinglevel));
        }
        return parent::context_header($headerinfo, $headinglevel);
    }

    /**
     * Adds the Hikmah summary above the dashboard blocks.
     *
     * @param bool $onlyifnotcalledbefore
     * @return string HTML
     */
    public function course_content_header($onlyifnotcalledbefore = false) {
        $html = parent::course_content_header($onlyifnotcalledbefore);
        static $done = false;
        if (!$done && $this->page->pagelayout === 'mydashboard' && isloggedin() && !isguestuser()
                && !$this->page->user_is_editing()) {
            $done = true;
            try {
                $html .= $this->render_from_template('theme_hikmah/dashboard', \theme_hikmah\local\dashboard::export());
            } catch (\Throwable $e) {
                debugging('Hikmah dashboard summary failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $html;
    }
}
