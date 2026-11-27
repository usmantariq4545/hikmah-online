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

namespace local_hikmahurls;

use moodle_url;

/**
 * Tests for the clean link rewriter.
 *
 * @package    local_hikmahurls
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hikmahurls\rewriter
 */
final class rewriter_test extends \advanced_testcase {
    /**
     * Rewrites a link and returns its path and query (without wwwroot).
     *
     * @param moodle_url $url
     * @return string
     */
    private function clean(moodle_url $url): string {
        global $CFG;
        return substr(rewriter::url_rewrite($url)->raw_out(false), strlen($CFG->wwwroot));
    }

    /**
     * Scripts lose ".php", folders keep their slash, courses go by name, theme pages get short names.
     */
    public function test_rewrites(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Foundations of Fiqh']);
        slugs::reset_cache();

        $this->assertSame('/course/foundations-of-fiqh', $this->clean(new moodle_url('/course/view.php', ['id' => $course->id])));
        $section = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => 2]);
        $this->assertSame('/course/foundations-of-fiqh?section=2', $this->clean($section));
        $info = new moodle_url('/theme/hikmah/course.php', ['id' => $course->id]);
        $this->assertSame('/courses/foundations-of-fiqh', $this->clean($info));
        $this->assertSame('/course/view?id=999', $this->clean(new moodle_url('/course/view.php', ['id' => 999])));
        $this->assertSame('/courses?mode=live', $this->clean(new moodle_url('/theme/hikmah/catalog.php', ['mode' => 'live'])));
        $this->assertSame('/faq', $this->clean(new moodle_url('/theme/hikmah/faq.php')));
        $this->assertSame('/about', $this->clean(new moodle_url('/theme/hikmah/about.php')));
        $this->assertSame('/contact', $this->clean(new moodle_url('/theme/hikmah/contact.php')));
        $this->assertSame('/terms-of-use', $this->clean(new moodle_url('/theme/hikmah/terms.php')));
        $this->assertSame('/mod/quiz/view?id=9', $this->clean(new moodle_url('/mod/quiz/view.php', ['id' => 9])));
        $this->assertSame('/my/', $this->clean(new moodle_url('/my/index.php')));
        $this->assertSame('/', $this->clean(new moodle_url('/index.php', [])));
        $this->assertSame('/?redirect=0', $this->clean(new moodle_url('/', ['redirect' => 0])));
        $this->assertSame('/login/', $this->clean(new moodle_url('/login/index.php')));
        $this->assertSame('/user/profile?id=2#about', $this->clean(new moodle_url('/user/profile.php', ['id' => 2], 'about')));
    }

    /**
     * Links other systems call, file links and theme assets keep ".php"; other sites are left alone.
     */
    public function test_keeps(): void {
        $keep = [
            '/admin/oauth2callback.php',
            '/login/token.php',
            '/lib/ajax/service.php',
            '/mod/bigbluebuttonbn/bbb_broker.php',
            '/mod/bigbluebuttonbn/bbb_view.php?id=3',
            '/admin/tool/mobile/launch.php',
            '/webservice/rest/server.php',
            '/repository/repository_ajax.php',
            '/pluginfile.php/1/a/b.jpg',
            '/theme/image.php/hikmah/core/1/logo',
            '/r.php/api/x',
        ];
        foreach ($keep as $path) {
            $this->assertSame($path, $this->clean(new moodle_url($path)), $path);
        }
        $other = new moodle_url('https://example.org/a.php');
        $this->assertSame('https://example.org/a.php', rewriter::url_rewrite($other)->out(false));
    }
}
