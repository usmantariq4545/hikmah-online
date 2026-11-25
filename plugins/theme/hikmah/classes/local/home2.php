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
 * Template context for the draft home page v2 (/theme/hikmah/home2.php).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home2 {
    /** @var string[] Topic keys; each has home2_topic_<key> and home2_topic_<key>_text strings. */
    private const TOPICS = ['seerah', 'faith', 'sharia', 'self', 'politics', 'psychology', 'history', 'science', 'family'];

    /**
     * Export the page context. Courses and teachers come from the live catalog; the rest is copy.
     *
     * @return array
     */
    public static function export(): array {
        global $OUTPUT;

        $data = home::export();
        $cards = $data['courses'];
        $catalog = new moodle_url('/theme/hikmah/catalog.php');
        $data['loginurl'] = (new moodle_url('/login/index.php'))->out(false);
        $data['policiesurl'] = (new moodle_url('/admin/tool/policy/viewall.php'))->out(false);
        $data['logourl'] = $OUTPUT->hk_logo_light();
        $data['year'] = userdate(time(), '%Y');

        if ($data['cansignup']) {
            $data['primaryurl'] = $data['signupurl'];
            $data['primarylabel'] = get_string('home2_cta_signup', 'theme_hikmah');
        } else if ($data['loggedin']) {
            $data['primaryurl'] = $data['dashboardurl'];
            $data['primarylabel'] = get_string('hero_cta_dashboard', 'theme_hikmah');
        } else {
            $data['primaryurl'] = $data['catalogurl'];
            $data['primarylabel'] = get_string('hero_cta_courses', 'theme_hikmah');
        }

        $data['promises'] = self::numbered('promise', 4);
        $data['steps'] = self::numbered('step', 4);
        $data['quotes'] = self::numbered('quote', 4);
        $data['faqs'] = self::numbered('faq', 10);

        // A topic links to its course category when one has the same name, otherwise to the whole catalog.
        $categories = [];
        foreach ($cards as $card) {
            $categories[\core_text::strtolower($card['category'])] = $card['categoryid'];
        }
        $data['topics'] = [];
        foreach (self::TOPICS as $key) {
            $name = get_string("home2_topic_$key", 'theme_hikmah');
            $categoryid = $categories[\core_text::strtolower($name)] ?? null;
            $data['topics'][] = [
                'name' => $name,
                'text' => get_string("home2_topic_{$key}_text", 'theme_hikmah'),
                'url' => ($categoryid ? new moodle_url($catalog, ['category' => $categoryid]) : $catalog)->out(false),
            ];
        }

        $data['teachers'] = self::teachers();
        $data['sessions'] = self::numbered('session', 3);
        $data['times'] = self::numbered('time', 4);
        $data['freeurl'] = (new moodle_url($catalog, ['price' => 'free']))->out(false);
        return $data;
    }

    /**
     * Title/text pairs from numbered language strings (home2_<prefix>_<n> and home2_<prefix>_<n>_text).
     *
     * @param string $prefix
     * @param int $count
     * @return array list of {title, text}
     */
    private static function numbered(string $prefix, int $count): array {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'title' => get_string("home2_{$prefix}_{$i}", 'theme_hikmah'),
                'text' => get_string("home2_{$prefix}_{$i}_text", 'theme_hikmah'),
            ];
        }
        return $items;
    }

    /**
     * The teachers named in the home page copy (to be confirmed for the new platform).
     *
     * @return array list of {name, initials, role, courses}
     */
    private static function teachers(): array {
        $teachers = [];
        for ($i = 1; $i <= 4; $i++) {
            $name = get_string("home2_teacher_{$i}", 'theme_hikmah');
            // Initials from the last two words, so titles such as Maulana are skipped.
            $initials = '';
            foreach (array_slice(preg_split('/\s+/', $name), -2) as $part) {
                $initials .= \core_text::strtoupper(\core_text::substr($part, 0, 1));
            }
            $teachers[] = [
                'name' => $name,
                'initials' => $initials,
                'role' => get_string("home2_teacher_{$i}_role", 'theme_hikmah'),
                'courses' => get_string("home2_teacher_{$i}_text", 'theme_hikmah'),
            ];
        }
        return $teachers;
    }
}
