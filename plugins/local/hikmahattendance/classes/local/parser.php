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
 * Versioned parser for one attendee entry of BigBlueButton's post-meeting analytics callback.
 *
 * The payload is not formally documented and may change between BBB versions (PRD ATT-07), so
 * the parser only accepts shapes it knows. Anything else raises unknown_schema_exception and the
 * session goes to the exceptions queue instead of being guessed at. Fixtures for every known
 * shape live in tests/fixtures and are replayed by the regression test.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {
    /** @var string Attendee with joins[] and leaves[] timestamps (BBB 2.x and 3.0 analytics callback). */
    public const SCHEMA_INTERVALS = 'bbb-analytics-intervals-1';
    /** @var string Attendee with only a total duration in seconds. */
    public const SCHEMA_TOTAL = 'bbb-analytics-total-1';

    /**
     * Parse one attendee.
     *
     * @param \stdClass|array $attendee decoded attendee entry
     * @return array {schema, intervals: [[start, end|0], ...], total: int|null, moderator: bool,
     *               conflict: bool, notes: string[]}
     * @throws unknown_schema_exception when the entry has no shape this parser knows
     */
    public static function parse_attendee($attendee): array {
        $attendee = (array) $attendee;

        $hasjoins = array_key_exists('joins', $attendee) && is_array($attendee['joins']);
        $hasleaves = array_key_exists('leaves', $attendee) && is_array($attendee['leaves']);
        $hastotal = array_key_exists('duration', $attendee) && is_numeric($attendee['duration']);

        if (!$hasjoins && !$hastotal) {
            throw new unknown_schema_exception('Attendee entry has neither joins[] nor duration: ' .
                implode(',', array_keys($attendee)));
        }
        if ($hasjoins && array_key_exists('leaves', $attendee) && !$hasleaves) {
            throw new unknown_schema_exception('Attendee entry has joins[] but leaves is not a list.');
        }

        $result = [
            'schema' => $hasjoins ? self::SCHEMA_INTERVALS : self::SCHEMA_TOTAL,
            'intervals' => [],
            'total' => $hastotal ? max(0, (int) $attendee['duration']) : null,
            'moderator' => !empty($attendee['moderator']) && $attendee['moderator'] !== 'false',
            'conflict' => false,
            'notes' => [],
        ];

        if (!$hasjoins) {
            return $result;
        }

        $joins = [];
        foreach ($attendee['joins'] as $value) {
            $time = self::parse_time($value);
            if ($time === null) {
                $result['conflict'] = true;
                $result['notes'][] = 'unreadablejoin';
                continue;
            }
            $joins[] = $time;
        }
        $leaves = [];
        foreach ($hasleaves ? $attendee['leaves'] : [] as $value) {
            $time = self::parse_time($value);
            if ($time === null) {
                $result['conflict'] = true;
                $result['notes'][] = 'unreadableleave';
                continue;
            }
            $leaves[] = $time;
        }
        sort($joins);
        sort($leaves);

        if (count($leaves) > count($joins)) {
            $result['conflict'] = true;
            $result['notes'][] = 'moreleavesthanjoins';
        }

        // Pair each join with the first unused leave at or after it. A join with no leave stays
        // open and is closed at the end of the class window by the calculator.
        foreach ($joins as $join) {
            $end = 0;
            foreach ($leaves as $index => $leave) {
                if ($leave >= $join) {
                    $end = $leave;
                    unset($leaves[$index]);
                    break;
                }
            }
            $result['intervals'][] = [$join, $end];
        }

        if (!$joins && $hastotal && $result['total'] > 0) {
            // Duration without any join time: use the total and say so.
            $result['schema'] = self::SCHEMA_TOTAL;
            $result['notes'][] = 'totalonly';
        }

        return $result;
    }

    /**
     * Read a timestamp: ISO 8601 text, "Y-m-d H:i:s UTC" text, epoch seconds or epoch milliseconds.
     *
     * @param mixed $value
     * @return int|null unix time in seconds, or null when unreadable
     */
    public static function parse_time($value): ?int {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $number = (float) $value;
            if ($number > 1e11) {
                $number /= 1000; // Milliseconds.
            }
            return $number > 946684800 ? (int) $number : null; // Nothing before 2000 is real.
        }
        if (is_string($value) && trim($value) !== '') {
            try {
                $date = new \DateTimeImmutable(trim($value), new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                return null;
            }
            $time = $date->getTimestamp();
            return $time > 946684800 ? $time : null;
        }
        return null;
    }
}
