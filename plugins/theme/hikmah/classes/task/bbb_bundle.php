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

namespace theme_hikmah\task;

/**
 * Finds the current address of the BigBlueButton classroom app (a 9.4 MB script on BiggerBlueButton, measured at about
 * 3.5 minutes to download from Pakistan on 2026-11-28) and stores it in theme_hikmah | bbbbundle, so that pages can
 * let browsers download it in the background before a class (hook_callbacks::bbb_prefetch). The address changes
 * whenever BiggerBlueButton updates BigBlueButton, so it is read again every hour from their public client page.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bbb_bundle extends \core\task\scheduled_task {
    /** @var string The classroom page for this site's meetings (BiggerBlueButton's Singapore server). */
    public const CLIENT = 'https://biggerbluebutton.com/cb-sg-01/html5client/';

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_bbbbundle', 'theme_hikmah');
    }

    /**
     * Reads the app address from the client page; keeps the last known one if that fails.
     */
    public function execute() {
        $curl = new \curl(['ignoresecurity' => true]);
        $html = (string) $curl->get(self::CLIENT, [], ['CURLOPT_TIMEOUT' => 20]);
        $url = self::parse($html);
        if ($url === null) {
            mtrace('BigBlueButton app address not found; keeping ' . (get_config('theme_hikmah', 'bbbbundle') ?: 'none'));
            return;
        }
        if ($url !== get_config('theme_hikmah', 'bbbbundle')) {
            set_config('bbbbundle', $url, 'theme_hikmah');
        }
        mtrace('BigBlueButton app: ' . $url);
    }

    /**
     * The app address in the client page: script.src = '<prefix>bundle.' + bundleHash + '.js', bundleHash = '<hash>'.
     *
     * @param string $html
     * @return string|null
     */
    public static function parse(string $html): ?string {
        if (
            preg_match("~var bundleHash = '([a-f0-9]{8,64})'~", $html, $hash)
                && preg_match("~'(https://[^']+/)bundle\.' \+ bundleHash \+ '\.js'~", $html, $prefix)
        ) {
            return $prefix[1] . 'bundle.' . $hash[1] . '.js';
        }
        return null;
    }
}
