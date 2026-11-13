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

namespace local_hikmahattendance\local;

/**
 * Pure attendance arithmetic: interval clipping/merging, percentage and status rules.
 *
 * PRD business rules: attendance % = verified attended seconds in the class window divided by
 * eligible class-window seconds, rounded down to a whole percent. A join alone is not proof of
 * duration, and conflicting evidence is never auto-marked Present.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator {
    /** @var string Computed status: met the threshold. */
    public const STATUS_PRESENT = 'present';
    /** @var string Computed status: attended, but below the threshold. */
    public const STATUS_PARTIAL = 'partial';
    /** @var string Computed status: no verified attendance and no join. */
    public const STATUS_ABSENT = 'absent';
    /** @var string Computed status: evidence is incomplete or conflicting. */
    public const STATUS_PENDING = 'pending';

    /**
     * Clip intervals to the class window, merge overlaps and reconnects, and sum the seconds.
     *
     * @param array $intervals list of [start, end] unix timestamps (end may be null/0 for "still open")
     * @param int $windowstart class window start (unix time)
     * @param int $windowend class window end (unix time)
     * @return int verified attended seconds inside the window
     */
    public static function attended_seconds(array $intervals, int $windowstart, int $windowend): int {
        if ($windowend <= $windowstart) {
            return 0;
        }
        $clipped = [];
        foreach ($intervals as $interval) {
            $start = (int) ($interval[0] ?? 0);
            $end = (int) ($interval[1] ?? 0);
            if ($start <= 0) {
                continue;
            }
            if ($end <= 0) {
                // An interval that never closed counts up to the end of the window only.
                $end = $windowend;
            }
            $start = max($start, $windowstart);
            $end = min($end, $windowend);
            if ($end > $start) {
                $clipped[] = [$start, $end];
            }
        }
        if (!$clipped) {
            return 0;
        }
        usort($clipped, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $total = 0;
        [$curstart, $curend] = $clipped[0];
        foreach (array_slice($clipped, 1) as [$start, $end]) {
            if ($start <= $curend) {
                $curend = max($curend, $end);
            } else {
                $total += $curend - $curstart;
                [$curstart, $curend] = [$start, $end];
            }
        }
        $total += $curend - $curstart;

        return min($total, $windowend - $windowstart);
    }

    /**
     * Attendance percentage, rounded down to a whole percent and capped at 100.
     *
     * @param int $seconds verified attended seconds
     * @param int $denominator eligible class-window seconds
     * @return int 0-100
     */
    public static function percent(int $seconds, int $denominator): int {
        if ($denominator <= 0 || $seconds <= 0) {
            return 0;
        }
        return (int) min(100, floor($seconds * 100 / $denominator));
    }

    /**
     * Map evidence to a computed status.
     *
     * @param int $percent attendance percentage (0-100)
     * @param int $seconds verified attended seconds
     * @param bool $joined whether a Moodle join log exists for the learner
     * @param int $threshold percentage needed for Present
     * @param bool $conflict true when the evidence is incomplete or contradictory
     * @return string one of the STATUS_* constants
     */
    public static function status(int $percent, int $seconds, bool $joined, int $threshold, bool $conflict = false): string {
        if ($conflict) {
            return self::STATUS_PENDING;
        }
        if ($seconds > 0) {
            return $percent >= $threshold ? self::STATUS_PRESENT : self::STATUS_PARTIAL;
        }
        // A join without any verified duration is not proof of attendance: a person reviews it.
        return $joined ? self::STATUS_PENDING : self::STATUS_ABSENT;
    }

    /**
     * Stable hash of the evidence behind a decision. The same evidence always produces the same
     * hash, which is what makes reprocessing a repeated callback a no-op.
     *
     * @param int $sessionid attendance session id
     * @param int $userid Moodle user id
     * @param int $seconds verified attended seconds
     * @param int $denominator class-window seconds
     * @param int $threshold threshold used
     * @param string $status computed status
     * @return string sha1 hex digest
     */
    public static function evidence_hash(
        int $sessionid,
        int $userid,
        int $seconds,
        int $denominator,
        int $threshold,
        string $status
    ): string {
        return sha1(implode('|', [$sessionid, $userid, $seconds, $denominator, $threshold, $status]));
    }
}
