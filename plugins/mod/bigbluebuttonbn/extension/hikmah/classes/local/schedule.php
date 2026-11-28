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
 * Live classes open a few minutes before they start (owner, 2026-11-28). Moodle lets nobody in before an activity's
 * opening time, so the stored opening time is the class start minus early(); everything Hikmah shows or measures
 * (activity form, course and dashboard times, attendance window) uses start(). Moodle's calendar entry keeps the
 * class start. The setting bbbext_hikmah | earlyopen holds early() in seconds; it is set once by db/install.php and
 * db/upgrade.php, which also moved every existing class. Changing it later would need the same move again.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule {
    /** @var int Seconds a live class opens before it starts. */
    public const EARLY = 300;

    /**
     * Seconds the room opens before the class starts (0 when this plugin is not set up).
     *
     * @return int
     */
    public static function early(): int {
        return (int) get_config('bbbext_hikmah', 'earlyopen');
    }

    /**
     * Class start for a stored opening time (0 stays 0: no opening time).
     *
     * @param int $openingtime
     * @return int
     */
    public static function start(int $openingtime): int {
        return $openingtime > 0 ? $openingtime + self::early() : 0;
    }

    /**
     * Saves the opening time for a class that starts at $start (0 = no opening time, left alone).
     *
     * @param int $bbbid
     * @param int $start
     */
    public static function store_opening(int $bbbid, int $start): void {
        global $DB;
        if ($start > 0 && self::early() > 0) {
            $DB->set_field('bigbluebuttonbn', 'openingtime', $start - self::early(), ['id' => $bbbid]);
        }
    }
}
