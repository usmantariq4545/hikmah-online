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

namespace bbbext_hikmah;

use bbbext_hikmah\bigbluebuttonbn\action_url_addons;
use bbbext_hikmah\local\branding;

/**
 * Tests for the branding parameters added to BigBlueButton calls.
 *
 * @package    bbbext_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \bbbext_hikmah\local\branding
 * @covers     \bbbext_hikmah\bigbluebuttonbn\action_url_addons
 */
final class branding_test extends \advanced_testcase {
    /**
     * Switches branding on and sets the site address.
     *
     * @param string $wwwroot
     */
    private function prepare(string $wwwroot): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('brandclient', 1, 'bbbext_hikmah');
        $CFG->wwwroot = $wwwroot;
    }

    public function test_https_site_sends_addresses(): void {
        $this->prepare('https://hikmah.example');

        $create = branding::params('create');
        $this->assertStringStartsWith('https://hikmah.example/theme/image.php/hikmah/theme_hikmah/', $create['logo']);
        $this->assertStringEndsWith('/logo-dark', $create['logo']);
        $this->assertStringEndsWith('/logo-light', $create['darklogo']);

        $join = branding::params('join');
        $this->assertStringStartsWith(
            'https://hikmah.example/mod/bigbluebuttonbn/extension/hikmah/client/classroom.css?v=',
            $join['userdata-bbb_custom_style_url']
        );
        $this->assertSame('true', $join['userdata-bbb_display_branding_area']);
        $this->assertArrayNotHasKey('userdata-bbb_custom_style', $join);
        $this->assertNotEmpty($join['userdata-bbb_client_title']);
    }

    public function test_http_site_sends_inline_colours_and_no_addresses(): void {
        $this->prepare('http://localhost:8080');

        $this->assertSame([], branding::params('create'));

        $join = branding::params('join');
        $this->assertArrayNotHasKey('userdata-bbb_custom_style_url', $join);
        $this->assertArrayNotHasKey('userdata-bbb_display_branding_area', $join);
        $css = $join['userdata-bbb_custom_style'];
        $this->assertStringStartsWith(':root{--color-primary:#0a6a9c;', $css);
        $this->assertStringEndsWith('}', $css);
        $this->assertStringNotContainsString('/*', $css);
        $this->assertStringNotContainsString("\n", $css);
        $this->assertStringNotContainsString('url(', $css);
        // It travels inside the join address, so it has to stay small.
        $this->assertLessThan(1500, strlen($css));
    }

    public function test_other_actions_and_disabled_setting_add_nothing(): void {
        $this->prepare('https://hikmah.example');
        $this->assertSame([], branding::params('getMeetingInfo'));
        $this->assertSame([], branding::params('end'));

        set_config('brandclient', 0, 'bbbext_hikmah');
        $this->assertSame([], branding::params('create'));
        $this->assertSame([], branding::params('join'));
    }

    public function test_addon_only_adds_and_never_replaces(): void {
        $this->prepare('https://hikmah.example');
        $addon = new action_url_addons();

        $result = $addon->execute('join', ['fullName' => 'Amina', 'userdata-bbb_client_title' => 'Set elsewhere'], [], 1);
        $this->assertSame([], $result['metadata']);
        $this->assertArrayNotHasKey('fullName', $result['data']);
        $this->assertArrayNotHasKey('userdata-bbb_client_title', $result['data']);
        $this->assertArrayHasKey('userdata-bbb_custom_style_url', $result['data']);
    }
}
