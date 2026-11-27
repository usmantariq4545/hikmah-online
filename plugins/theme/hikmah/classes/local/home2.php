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
 * Template context for the home page (v2, from the "Hikmah Online homepage v2" design canvas), used by
 * the front page layout and the admin preview at /theme/hikmah/home2.php.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home2 {
    /** @var string[] Topic keys: strings home2_topic_<key>[_text|_tag1|_tag2|_alt] and pix/home2/topics/<key>.jpg. */
    private const TOPICS = ['seerah', 'faith', 'sharia', 'self', 'politics', 'psychology', 'history', 'science', 'family'];

    /**
     * @var array Teachers in reading order (phones show them in this order): key => [photo file or '' for the outline,
     * credential lines, big card, lift px]. On wider screens the SCSS reorders the arc so the two co-founders sit in
     * the middle: Faheem, Faaiz, Khizer, Shahzaib, Jawwad, Maaz.
     */
    private const TEACHERS = [
        'khizer' => ['khizer-khalid.webp', 2, true, 0],
        'faaiz' => ['faaiz-hassan.jpg', 1, false, 12],
        'shahzaib' => ['shahzaib-aijaz.webp', 2, true, 0],
        'jawwad' => ['jawwad-mehmood.webp', 2, false, 12],
        'faheem' => ['faheem-vohra.webp', 1, false, 36],
        'maaz' => ['', 1, false, 36],
    ];

    /** @var array Line icons as SVG path data on a 24px grid. */
    private const ICONS = [
        'book' => ['M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z', 'M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z'],
        'bulb' => [
            'M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5',
            'M9 18h6',
            'M10 22h4',
        ],
        'globe' => ['M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20z', 'M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20', 'M2 12h20'],
        'award' => [
            'M12 2a6 6 0 1 0 0 12 6 6 0 1 0 0-12z',
            'm15.48 12.89 1.51 8.53a.5.5 0 0 1-.81.47l-3.58-2.69a1 1 0 0 0-1.2 0l-3.58 2.69a.5.5 0 0 1-.81-.47l1.51-8.53',
        ],
        'video' => ['M6 6h6a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z', 'm15 10 6-3v10l-6-3'],
        'clock' => ['M12 3a9 9 0 1 0 0 18 9 9 0 1 0 0-18z', 'M12 7v5l3 2'],
        'layers' => ['m12 3 9 5-9 5-9-5 9-5z', 'm3 13 9 5 9-5'],
    ];

    /**
     * Export the page context. Courses come from the live catalog; everything else is copy.
     *
     * @return array
     */
    public static function export(): array {
        $data = home::export();
        $catalog = $data['catalogurl'];
        $pix = (new moodle_url('/theme/hikmah/pix/home2/'))->out(false);

        $data['pix'] = $pix;
        $data['primaryurl'] = $data['cansignup'] ? $data['signupurl'] : $catalog;
        $data['khizerphoto'] = self::photo_url('khizer');
        // Until the guide video is ready, its card leads back to the home page.
        $data['homeurl'] = (new moodle_url('/', ['redirect' => 0]))->out(false);
        $data['whatsappurl'] = footer::WHATSAPP;

        $data['promises'] = self::with_icons(self::numbered('promise', 4), ['book', 'bulb', 'globe', 'award']);
        $data['sessions'] = self::numbered('session', 3);
        $data['times'] = self::numbered('time', 4);
        $data['steps'] = self::numbered('step', 4);
        $data['ways'] = self::with_icons(self::numbered('way', 3), ['video', 'clock', 'layers']);
        $data['points'] = self::numbered('point', 3);
        $data['faqs'] = self::numbered('faq', 5);
        $data['faqs'][0]['open'] = true;

        $data['topics'] = [];
        foreach (self::TOPICS as $key) {
            $data['topics'][] = [
                'name' => get_string("home2_topic_{$key}", 'theme_hikmah'),
                'text' => get_string("home2_topic_{$key}_text", 'theme_hikmah'),
                'tag1' => get_string("home2_topic_{$key}_tag1", 'theme_hikmah'),
                'tag2' => get_string("home2_topic_{$key}_tag2", 'theme_hikmah'),
                'alt' => get_string("home2_topic_{$key}_alt", 'theme_hikmah'),
                'image' => $pix . "topics/{$key}.jpg",
                'url' => $catalog,
            ];
        }

        $data['teachers'] = [];
        foreach (self::TEACHERS as $key => [, $creds, $big, $lift]) {
            $teacher = [
                'name' => get_string("home2_teacher_{$key}", 'theme_hikmah'),
                'photo' => self::photo_url($key),
                'big' => $big,
                'lift' => $lift,
                'creds' => [],
            ];
            for ($i = 1; $i <= $creds; $i++) {
                $teacher['creds'][] = ['text' => get_string("home2_teacher_{$key}_cred{$i}", 'theme_hikmah')];
            }
            $data['teachers'][] = $teacher;
        }
        return $data;
    }

    /**
     * Where a teacher's photo lives on the server. Photos are kept in moodledata, not in the public code
     * repository; a teacher without one (or whose file is missing) gets the outline instead.
     *
     * @param string $key teacher key
     * @return string full path, or '' when there is no photo
     */
    public static function photo_path(string $key): string {
        global $CFG;
        $file = self::TEACHERS[$key][0] ?? '';
        $path = $CFG->dataroot . '/hikmah-media/teachers/' . $file;
        return ($file !== '' && is_readable($path)) ? $path : '';
    }

    /**
     * URL of a teacher's photo, served by home2_photo.php, or '' for the outline.
     *
     * @param string $key teacher key
     * @return string
     */
    private static function photo_url(string $key): string {
        return self::photo_path($key) ? (new moodle_url('/theme/hikmah/home2_photo.php', ['name' => $key]))->out(false) : '';
    }

    /**
     * Adds a line icon (SVG path data, 24px grid) to each item, in order.
     *
     * @param array $items
     * @param string[] $names keys of ICONS
     * @return array
     */
    private static function with_icons(array $items, array $names): array {
        foreach ($items as $i => &$item) {
            $item['icon'] = self::ICONS[$names[$i]];
        }
        return $items;
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
}
