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


namespace theme_hikmah;

/**
 * Tests for reading the BigBlueButton classroom app address.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_hikmah\task\bbb_bundle
 */
final class bbb_bundle_test extends \basic_testcase {
    public function test_parse(): void {
        $html = "var bundleHash = 'b1641cac298f9d68a970';\n"
            . "script.src = 'https://biggerbluebutton.com/storage/cdn/bundle.' + bundleHash + '.safari.js';\n"
            . "script.src = 'https://biggerbluebutton.com/storage/cdn/bundle.' + bundleHash + '.js';";
        $this->assertSame(
            'https://biggerbluebutton.com/storage/cdn/bundle.b1641cac298f9d68a970.js',
            task\bbb_bundle::parse($html)
        );
        $this->assertNull(task\bbb_bundle::parse('<html>maintenance</html>'));
    }
}
