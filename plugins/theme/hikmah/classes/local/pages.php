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
 * Template context for the About us and Contact us pages. They reuse the home page v2 blocks
 * (teachers, ways to learn) so a change there shows on every page.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages {
    /** @var string Public contact address shown on the Contact us page. */
    public const EMAIL = 'info@hikmahonline.com';

    /**
     * About us page.
     *
     * @return array
     */
    public static function about(): array {
        $values = [];
        for ($i = 1; $i <= 4; $i++) {
            $values[] = [
                'title' => get_string("about_value_{$i}", 'theme_hikmah'),
                'text' => get_string("about_value_{$i}_text", 'theme_hikmah'),
            ];
        }
        return [
            'pix' => (new moodle_url('/theme/hikmah/pix/home2/'))->out(false),
            'catalogurl' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
            'contacturl' => (new moodle_url('/theme/hikmah/contact.php'))->out(false),
            'values' => home2::with_icons($values, ['book', 'bulb', 'globe', 'heart']),
            'teachers' => home2::teachers(),
            'ways' => home2::with_icons(home2::numbered('way', 3), ['video', 'clock', 'layers']),
        ];
    }

    /**
     * Contact us page.
     *
     * @param string $form rendered message form, or '' when the form is not offered
     * @param bool $sent true right after a message was sent
     * @param bool $failed true when sending failed
     * @return array
     */
    public static function contact(string $form, bool $sent, bool $failed): array {
        $faqs = [];
        for ($i = 1; $i <= 5; $i++) {
            $faqs[] = [
                'title' => get_string("contact_faq_{$i}", 'theme_hikmah'),
                'text' => get_string("contact_faq_{$i}_text", 'theme_hikmah'),
                'open' => $i === 1,
            ];
        }
        return [
            'pix' => (new moodle_url('/theme/hikmah/pix/home2/'))->out(false),
            'whatsappurl' => footer::WHATSAPP,
            'instagramurl' => footer::INSTAGRAM,
            'facebookurl' => footer::FACEBOOK,
            'email' => self::EMAIL,
            'faqurl' => (new moodle_url('/theme/hikmah/faq.php'))->out(false),
            'policiesurl' => policypage::url('privacy')->out(false),
            'contacturl' => (new moodle_url('/theme/hikmah/contact.php'))->out(false),
            'form' => $form,
            'hasform' => $form !== '',
            'sent' => $sent,
            'failed' => $failed,
            'loginurl' => get_login_url(),
            'faqs' => $faqs,
        ];
    }
}
