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
 * Template context for the public home page.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home {
    /** @var int Number of courses featured on the home page. */
    private const FEATURED = 6;

    /**
     * Export the home page context.
     *
     * @return array
     */
    public static function export(): array {
        $cards = catalog::get_cards();
        $stats = catalog::get_stats();
        $catalog = new moodle_url('/theme/hikmah/catalog.php');
        $loggedin = isloggedin() && !isguestuser();

        return [
            'heromotif' => ['motifclass' => 'hk-hero__motif hk-motif--animate'],
            'quotemotif' => ['motifclass' => 'hk-quote__motif hk-motif--breathe'],
            'loggedin' => $loggedin,
            'catalogurl' => $catalog->out(false),
            'dashboardurl' => (new moodle_url('/my/'))->out(false),
            'signupurl' => (new moodle_url('/login/signup.php'))->out(false),
            'verifyurl' => (new moodle_url('/theme/hikmah/verify.php'))->out(false),
            'faqurl' => (new moodle_url('/theme/hikmah/faq.php'))->out(false),
            'liveurl' => (new moodle_url($catalog, ['mode' => 'live']))->out(false),
            'selfpacedurl' => (new moodle_url($catalog, ['mode' => 'selfpaced']))->out(false),
            'hybridurl' => (new moodle_url($catalog, ['mode' => 'hybrid']))->out(false),
            'cansignup' => !$loggedin && !empty($GLOBALS['CFG']->registerauth),
            'courses' => array_slice($cards, 0, self::FEATURED),
            'hascourses' => !empty($cards),
            'morecourses' => count($cards) > self::FEATURED,
            'coursecount' => count($cards),
            'stats' => [
                ['value' => $stats['courses'], 'label' => get_string('stat_courses', 'theme_hikmah')],
                ['value' => $stats['learners'], 'label' => get_string('stat_learners', 'theme_hikmah')],
                ['value' => $stats['live'], 'label' => get_string('stat_live', 'theme_hikmah')],
                ['value' => $stats['certificates'], 'label' => get_string('stat_certificates', 'theme_hikmah')],
            ],
            'faqs' => array_slice(faq::items(), 0, 4),
        ];
    }
}
