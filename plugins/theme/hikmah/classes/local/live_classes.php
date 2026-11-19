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
use stdClass;

/**
 * The call to action under each scheduled live class on a course page: "Join class" before it,
 * "Join now" while it runs, "Watch recording" after it. Only live classes with an opening and
 * closing time count as scheduled; an open-ended room keeps Moodle's own link.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class live_classes {
    /** @var int[] Recording statuses that can be played (processed, notified). */
    private const PLAYABLE = [2, 3];

    /**
     * Button HTML per course module id, for the live classes the user can see.
     *
     * @param stdClass $course
     * @param int|null $now for tests
     * @return array<int, array{state: string, html: string}>
     */
    public static function for_course(stdClass $course, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $cms = array_filter(get_fast_modinfo($course)->get_instances_of('bigbluebuttonbn'), fn($cm) => $cm->uservisible);
        if (!$cms) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($cms));
        $rooms = $DB->get_records_select('bigbluebuttonbn', "id $insql AND openingtime > 0 AND closingtime > 0", $params,
            '', 'id, openingtime, closingtime');
        if (!$rooms) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($rooms));
        $recordings = [];
        foreach ($DB->get_records_select('bigbluebuttonbn_recordings', "bigbluebuttonbnid $insql", $params,
                'timecreated DESC', 'id, bigbluebuttonbnid, status') as $recording) {
            $recordings[$recording->bigbluebuttonbnid][] = $recording;
        }
        $embed = (bool) get_config('theme_hikmah', 'embedclassroom');

        $out = [];
        foreach ($rooms as $room) {
            $cm = $cms[$room->id];
            $joinurl = $embed ? new moodle_url('/theme/hikmah/classroom.php', ['id' => $cm->id])
                : new moodle_url('/mod/bigbluebuttonbn/view.php', ['id' => $cm->id]);
            if ($now < $room->openingtime) {
                $out[$cm->id] = ['state' => 'upcoming',
                    'html' => self::chip('upcoming', get_string('live_upcoming', 'theme_hikmah'))
                        . self::button($joinurl, get_string('live_join', 'theme_hikmah'), 'hk-btn--ghost')];
                continue;
            }
            if ($now <= $room->closingtime) {
                $out[$cm->id] = ['state' => 'live',
                    'html' => self::chip('live', get_string('dash_livenow', 'theme_hikmah'))
                        . self::button($joinurl, get_string('live_joinnow', 'theme_hikmah'), 'hk-btn--sun')];
                continue;
            }
            $playable = array_values(array_filter($recordings[$room->id] ?? [],
                fn($r) => in_array((int) $r->status, self::PLAYABLE, true)));
            if ($playable) {
                $playurl = $embed
                    ? new moodle_url('/theme/hikmah/classroom.php', ['id' => $cm->id, 'rid' => $playable[0]->id, 'rtype' => 'presentation'])
                    : new moodle_url('/mod/bigbluebuttonbn/view.php', ['id' => $cm->id]);
                $html = self::button($playurl, get_string('live_watch', 'theme_hikmah'), '');
            } else {
                $html = '<span class="hk-livecta__note">' . get_string(empty($recordings[$room->id])
                    ? 'live_norecording' : 'live_processing', 'theme_hikmah') . '</span>';
            }
            $out[$cm->id] = ['state' => 'done', 'html' => self::chip('done', get_string('live_held', 'theme_hikmah')) . $html];
        }
        return $out;
    }

    /**
     * A status chip.
     *
     * @param string $state
     * @param string $label
     * @return string
     */
    private static function chip(string $state, string $label): string {
        return '<span class="hk-livecta__chip hk-livecta__chip--' . $state . '">' . s($label) . '</span>';
    }

    /**
     * A small Hikmah button.
     *
     * @param moodle_url $url
     * @param string $label
     * @param string $modifier
     * @return string
     */
    private static function button(moodle_url $url, string $label, string $modifier): string {
        return '<a class="hk-btn hk-btn--sm ' . $modifier . '" href="' . $url->out() . '">' . s($label) . '</a>';
    }
}
