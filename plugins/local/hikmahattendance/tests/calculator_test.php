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

namespace local_hikmahattendance;

use local_hikmahattendance\local\calculator;
use local_hikmahattendance\local\parser;
use local_hikmahattendance\local\unknown_schema_exception;

/**
 * Tests for the attendance arithmetic and the versioned payload parser.
 *
 * @package    local_hikmahattendance
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahattendance\local\calculator
 * @covers     \local_hikmahattendance\local\parser
 */
final class calculator_test extends \basic_testcase {
    /** @var int Window start: 2026-11-10 13:00:00 UTC. */
    private const START = 1794315600;
    /** @var int Window end: one hour later. */
    private const END = 1794319200;

    /**
     * Interval cases.
     *
     * @return array
     */
    public static function interval_provider(): array {
        $s = self::START;
        $e = self::END;
        return [
            'full stay' => [[[$s, $e]], 3600],
            'joined early and stayed late is clipped' => [[[$s - 600, $e + 900]], 3600],
            'late join' => [[[$s + 1200, $e]], 2400],
            'leave and rejoin' => [[[$s, $s + 1200], [$s + 1800, $e]], 3000],
            'overlapping devices count once' => [[[$s, $s + 2400], [$s + 600, $s + 3000]], 3000],
            'touching intervals merge' => [[[$s, $s + 600], [$s + 600, $s + 1200]], 1200],
            'open interval closes at window end' => [[[$s + 1800, 0]], 1800],
            'entirely before the window' => [[[$s - 900, $s - 60]], 0],
            'entirely after the window' => [[[$e + 60, $e + 900]], 0],
            'unsorted input' => [[[$s + 1800, $e], [$s, $s + 600]], 2400],
            'no evidence' => [[], 0],
            'invalid start ignored' => [[[0, $e]], 0],
        ];
    }

    /**
     * Intervals are clipped to the window, merged and summed.
     *
     * @dataProvider interval_provider
     * @param array $intervals
     * @param int $expected
     */
    public function test_attended_seconds(array $intervals, int $expected): void {
        $this->assertSame($expected, calculator::attended_seconds($intervals, self::START, self::END));
    }

    /**
     * Percent rounds down and never exceeds 100.
     */
    public function test_percent(): void {
        $this->assertSame(100, calculator::percent(3600, 3600));
        $this->assertSame(74, calculator::percent(2699, 3600)); // 74.97% rounds down, not up to 75.
        $this->assertSame(75, calculator::percent(2700, 3600));
        $this->assertSame(55, calculator::percent(55, 100));
        $this->assertSame(0, calculator::percent(0, 3600));
        $this->assertSame(0, calculator::percent(100, 0));
        $this->assertSame(100, calculator::percent(5000, 3600));
    }

    /**
     * Status rules, including "a join alone is not proof" and "conflict is never Present".
     */
    public function test_status(): void {
        $this->assertSame(calculator::STATUS_PRESENT, calculator::status(100, 3600, true, 75));
        $this->assertSame(calculator::STATUS_PRESENT, calculator::status(75, 2700, true, 75));
        $this->assertSame(calculator::STATUS_PARTIAL, calculator::status(74, 2699, true, 75));
        $this->assertSame(calculator::STATUS_PARTIAL, calculator::status(55, 1980, true, 75));
        $this->assertSame(calculator::STATUS_ABSENT, calculator::status(0, 0, false, 75));
        $this->assertSame(calculator::STATUS_PENDING, calculator::status(0, 0, true, 75));
        $this->assertSame(calculator::STATUS_PENDING, calculator::status(100, 3600, true, 75, true));
    }

    /**
     * The same evidence always hashes the same; any change gives a new hash.
     */
    public function test_evidence_hash(): void {
        $hash = calculator::evidence_hash(5, 9, 3000, 3600, 75, 'present');
        $this->assertSame($hash, calculator::evidence_hash(5, 9, 3000, 3600, 75, 'present'));
        $this->assertNotSame($hash, calculator::evidence_hash(5, 9, 3001, 3600, 75, 'present'));
        $this->assertNotSame($hash, calculator::evidence_hash(5, 10, 3000, 3600, 75, 'present'));
        $this->assertNotSame($hash, calculator::evidence_hash(5, 9, 3000, 3600, 80, 'present'));
    }

    /**
     * Regression test: every attendee in the pinned BBB fixture parses to the expected seconds.
     * Run this after every BigBlueButton upgrade with a fresh fixture from the new version.
     */
    public function test_fixture_bbb30(): void {
        $payload = json_decode(file_get_contents(__DIR__ . '/fixtures/bbb-3.0-analytics-callback.json'));
        $expected = [
            'fullstay' => 3600,
            'latejoin' => 2400,
            'rejoin' => 3000,
            'overlap' => 3000,
            'earlyleave' => 600,
            'instructor' => 3600,
            'unmapped' => 3600,
        ];
        foreach ($payload->data->attendees as $attendee) {
            $parsed = parser::parse_attendee($attendee);
            $this->assertSame(parser::SCHEMA_INTERVALS, $parsed['schema'], $attendee->_case);
            $this->assertFalse($parsed['conflict'], $attendee->_case);
            $this->assertSame(
                $expected[$attendee->_case],
                calculator::attended_seconds($parsed['intervals'], self::START, self::END),
                $attendee->_case
            );
            $this->assertSame($attendee->_case === 'instructor', $parsed['moderator'], $attendee->_case);
        }
    }

    /**
     * An unknown payload shape is refused, not guessed at.
     */
    public function test_unknown_shape(): void {
        $payload = json_decode(file_get_contents(__DIR__ . '/fixtures/unknown-shape.json'));
        $this->expectException(unknown_schema_exception::class);
        parser::parse_attendee($payload->data->attendees[0]);
    }

    /**
     * Totals-only entries, odd timestamps and inconsistent join/leave lists.
     */
    public function test_parser_edge_cases(): void {
        $total = parser::parse_attendee((object) ['ext_user_id' => '5', 'duration' => 1500]);
        $this->assertSame(parser::SCHEMA_TOTAL, $total['schema']);
        $this->assertSame(1500, $total['total']);
        $this->assertSame([], $total['intervals']);

        // Epoch seconds and milliseconds are both understood.
        $epoch = parser::parse_attendee((object) ['joins' => [self::START, (self::START + 1800) * 1000],
            'leaves' => [self::START + 600]]);
        $this->assertSame([[self::START, self::START + 600], [self::START + 1800, 0]], $epoch['intervals']);

        // A leave with no join is a conflict.
        $odd = parser::parse_attendee((object) ['joins' => [], 'leaves' => ['2026-11-10T13:10:00Z'], 'duration' => 0]);
        $this->assertTrue($odd['conflict']);

        // Unreadable timestamps are a conflict, not a zero.
        $bad = parser::parse_attendee((object) ['joins' => ['not a date'], 'leaves' => []]);
        $this->assertTrue($bad['conflict']);
        $this->assertSame([], $bad['intervals']);

        $this->assertSame(self::START, parser::parse_time('2026-11-10T13:00:00Z'));
        $this->assertSame(self::START, parser::parse_time('2026-11-10 13:00:00 UTC'));
        $this->assertNull(parser::parse_time(''));
        $this->assertNull(parser::parse_time(12));
    }
}
