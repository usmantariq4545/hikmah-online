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
     * @return array<int, array{state: string, dates: string, html: string, title?: string}>
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
            $dates = self::dates($room, $now);
            if ($now < $room->openingtime) {
                $out[$cm->id] = ['state' => 'upcoming', 'dates' => $dates,
                    'html' => self::chip('upcoming', get_string('live_upcoming', 'theme_hikmah'))
                        . self::button($joinurl, get_string('live_join', 'theme_hikmah'), 'hk-btn--ghost')];
                continue;
            }
            if ($now <= $room->closingtime) {
                $out[$cm->id] = ['state' => 'live', 'dates' => $dates,
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
            $out[$cm->id] = ['state' => 'done', 'dates' => $dates,
                'title' => get_string('live_recordingtitle', 'theme_hikmah'), 'html' => self::chip('done', get_string('live_held', 'theme_hikmah')) . $html];
        }
        return $out;
    }

    /**
     * "Starts: Sat 21 Nov, 6:00 PM · Duration: 1 hour" (or "Started: …") in place of Moodle's Opens/Closes.
     *
     * @param stdClass $room with openingtime and closingtime
     * @param int|null $now for tests
     * @return string HTML
     */
    public static function dates(stdClass $room, ?int $now = null): string {
        $now = $now ?? time();
        return '<span>' . get_string($now > $room->closingtime ? 'live_started' : 'live_starts', 'theme_hikmah',
                userdate($room->openingtime, get_string('live_dateformat', 'theme_hikmah'))) . '</span> · <span>'
            . get_string('live_duration', 'theme_hikmah', format_time($room->closingtime - $room->openingtime)) . '</span>';
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

    /**
     * "Class recording" on a held live class's own page (editors keep the real name), else null.
     *
     * @param \moodle_page $page
     * @return string|null
     */
    public static function held_title(\moodle_page $page): ?string {
        global $DB;
        if ($page->pagetype !== 'mod-bigbluebuttonbn-view' || !$page->cm || $page->user_is_editing()) {
            return null;
        }
        $room = $DB->get_record('bigbluebuttonbn', ['id' => $page->cm->instance], 'openingtime, closingtime');
        $held = $room && $room->openingtime > 0 && $room->closingtime > 0 && time() > $room->closingtime;
        return $held ? get_string('live_recordingtitle', 'theme_hikmah') : null;
    }
}
