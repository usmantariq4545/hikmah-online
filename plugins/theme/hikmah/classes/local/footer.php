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

use moodle_url;

/**
 * Template context for the site footer (theme_hikmah/footer_site), shown on every page.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class footer {
    /** @var string WhatsApp chat link for Hikmah Online (+92 331 2755374). */
    public const WHATSAPP = 'https://wa.me/923312755374';

    /** @var string Instagram page. */
    public const INSTAGRAM = 'https://www.instagram.com/online.hikmah/';

    /** @var string Facebook page. */
    public const FACEBOOK = 'https://www.facebook.com/online.hikmah/';

    /**
     * Export the footer context.
     *
     * @return array
     */
    public static function export(): array {
        global $OUTPUT, $PAGE, $SITE;
        $catalog = new moodle_url('/theme/hikmah/catalog.php');
        $home = new moodle_url('/', ['redirect' => 0]);
        return [
            'logo' => $OUTPUT->image_url('logo-light', 'theme_hikmah')->out(false),
            'sitename' => format_string($SITE->fullname, true, ['context' => \context_system::instance()]),
            'year' => userdate(time(), '%Y'),
            'newsurl' => (new moodle_url('/local/hikmahaccess/news_subscribe.php'))->out(false),
            'sesskey' => sesskey(),
            'returnurl' => $PAGE->has_set_url() ? $PAGE->url->out_as_local_url(false) : '/',
            'catalogurl' => $catalog->out(false),
            'freeurl' => (new moodle_url($catalog, ['price' => 'free']))->out(false),
            'liveurl' => (new moodle_url($catalog, ['mode' => 'live']))->out(false),
            'topicsurl' => $home->out(false) . '#hk2-topics',
            'teachersurl' => $home->out(false) . '#hk2-teachers',
            'abouturl' => (new moodle_url('/theme/hikmah/about.php'))->out(false),
            'contacturl' => (new moodle_url('/theme/hikmah/contact.php'))->out(false),
            'faqurl' => (new moodle_url('/theme/hikmah/faq.php'))->out(false),
            'verifyurl' => (new moodle_url('/theme/hikmah/verify.php'))->out(false),
            'privacyurl' => policypage::url('privacy')->out(false),
            'termsurl' => policypage::url('terms')->out(false),
            'refundurl' => policypage::url('refund')->out(false),
            'cookiesettings' => (string) get_config('theme_hikmah', 'posthogkey') !== '',
            'whatsappurl' => self::WHATSAPP,
            'instagramurl' => self::INSTAGRAM,
            'facebookurl' => self::FACEBOOK,
        ];
    }
}
