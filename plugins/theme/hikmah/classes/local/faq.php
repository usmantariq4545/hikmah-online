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
 * Frequently asked questions. The text lives in language strings so it can be translated and
 * customised through Moodle's language customisation tool without code changes.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq {
    /** @var int Number of question/answer pairs defined in the language file. */
    private const COUNT = 8;

    /**
     * All FAQ items.
     *
     * @return array list of {question, answer}
     */
    public static function items(): array {
        $items = [];
        for ($i = 1; $i <= self::COUNT; $i++) {
            $items[] = [
                'question' => get_string("faq_q$i", 'theme_hikmah'),
                'answer' => get_string("faq_a$i", 'theme_hikmah'),
            ];
        }
        return $items;
    }
}
