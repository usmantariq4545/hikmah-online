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

/**
 * Public, branded pages for the Privacy Policy, Terms of Use and Refund Policy. The text is the current
 * version in tool_policy (published by config/settings/130-privacy.php), so what people read here is what
 * they accept at sign-up.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policypage {
    /** @var array kind => [tool_policy type, page script] */
    public const PAGES = [
        'privacy' => [1, '/theme/hikmah/privacy.php'],
        'terms' => [0, '/theme/hikmah/terms.php'],
        'refund' => [99, '/theme/hikmah/refund.php'],
    ];

    /**
     * Link to one of the pages.
     *
     * @param string $kind privacy, terms or refund
     * @return moodle_url
     */
    public static function url(string $kind): moodle_url {
        return new moodle_url(self::PAGES[$kind][1]);
    }

    /**
     * Prints the whole page.
     *
     * @param string $kind privacy, terms or refund
     */
    public static function output(string $kind): void {
        global $DB, $OUTPUT, $PAGE;
        $context = \context_system::instance();
        $title = get_string('policy_' . $kind, 'theme_hikmah');
        $PAGE->set_context($context);
        $PAGE->set_url(self::url($kind));
        $PAGE->set_pagelayout('hikmah');
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->add_body_class('hk-policypage');

        $version = $DB->get_record_sql(
            'SELECT v.*
               FROM {tool_policy} p
               JOIN {tool_policy_versions} v ON v.id = p.currentversionid
              WHERE v.type = ?
           ORDER BY p.id',
            [self::PAGES[$kind][0]],
            IGNORE_MULTIPLE
        );

        $others = [];
        foreach (array_keys(self::PAGES) as $other) {
            if ($other !== $kind) {
                $others[] = ['url' => self::url($other)->out(false),
                    'name' => get_string('policy_' . $other, 'theme_hikmah')];
            }
        }
        $data = [
            'pagemotif' => ['motifclass' => 'hk-pagehead__motif hk-motif--animate'],
            'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
            'title' => $title,
            'lead' => $version ? format_string(strip_tags($version->summary), true, ['context' => $context]) : '',
            'updated' => $version ? get_string('policy_updated', 'theme_hikmah',
                userdate($version->timecreated, get_string('strftimedate', 'langconfig'))) : '',
            'content' => $version ? format_text($version->content, $version->contentformat,
                ['context' => $context]) : '',
            'others' => $others,
        ];

        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template('theme_hikmah/policy', $data);
        echo $OUTPUT->footer();
    }
}
