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

use core_course_list_element;

/**
 * Building blocks for branded emails: button, status badge, summary table and course card. Everything is a table
 * with inline styles, because mail programs drop most CSS. The outer layout is templates/core/email_html.mustache.
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email {
    /** Inline style of the yellow pill button; also used in the email wording (config/settings/email-strings.php). */
    const BUTTON_STYLE = 'display:inline-block; background:#ffe000; color:#1a1a1a; text-decoration:none; font-weight:600;'
        . ' font-size:14px; line-height:18px; padding:9px 20px; border-radius:999px; mso-padding-alt:0;';

    /** Font stack for text. */
    const FONT = "Inter, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    /** Badge colours per tone: [text, background]. */
    const TONES = [
        'pending' => ['#1a1a1a', '#fff7b8'],
        'success' => ['#1f7a4d', '#e3f1ea'],
        'danger' => ['#b3261e', '#fbe9e7'],
        'warning' => ['#9a6700', '#fff1cc'],
    ];

    /**
     * A yellow pill button on its own line.
     *
     * @param string $url
     * @param string $label plain text
     * @return string HTML
     */
    public static function button(string $url, string $label): string {
        return '<p style="margin:24px 0;"><a class="hk-btn" href="' . s($url) . '" style="' . self::BUTTON_STYLE . '">'
            . s($label) . '</a></p>';
    }

    /**
     * Small coloured status label.
     *
     * @param string $label plain text
     * @param string $tone key of TONES
     * @return string HTML
     */
    public static function badge(string $label, string $tone): string {
        [$fg, $bg] = self::TONES[$tone] ?? self::TONES['pending'];
        return '<span style="display:inline-block; background:' . $bg . '; color:' . $fg . '; font-size:13px; font-weight:600;'
            . ' line-height:18px; padding:3px 10px; border-radius:999px;">' . s($label) . '</span>';
    }

    /**
     * Note box, e.g. the reviewer's reason.
     *
     * @param string $title plain text
     * @param string $text plain text
     * @param string $tone key of TONES
     * @return string HTML
     */
    public static function note(string $title, string $text, string $tone): string {
        [$fg, $bg] = self::TONES[$tone] ?? self::TONES['pending'];
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">'
            . '<tr><td style="background:' . $bg . '; border-left:4px solid ' . $fg . '; border-radius:8px; padding:12px 16px;'
            . ' font-family:' . self::FONT . '; font-size:15px; line-height:1.5; color:#1a1a1a;">'
            . '<strong style="color:' . $fg . ';">' . s($title) . '</strong><br>' . nl2br(s($text)) . '</td></tr></table>';
    }

    /**
     * Order-style summary table: a small caps heading, label/value rows with hairlines and an optional total row.
     *
     * @param string $heading plain text
     * @param array $rows [label => value]; a value is plain text unless the label starts with '!' (then HTML)
     * @param array|null $total [label, value] plain text
     * @return string HTML
     */
    public static function table(string $heading, array $rows, ?array $total = null): string {
        $cell = 'font-family:' . self::FONT . '; font-size:14px; line-height:20px; padding:10px 0;'
            . ' border-bottom:1px solid rgba(26,26,26,.1);';
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 8px;'
            . ' border-collapse:collapse;">'
            . '<tr><td colspan="2" style="font-family:' . self::FONT . '; font-size:12px; font-weight:600; letter-spacing:1.6px;'
            . ' text-transform:uppercase; color:#0a6a9c; padding:0 0 6px; border-bottom:2px solid #1a1a1a;">' . s($heading)
            . '</td></tr>';
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ishtml = str_starts_with($label, '!');
            $label = ltrim($label, '!');
            $html .= '<tr><td style="' . $cell . ' color:#5c5b55; width:42%; vertical-align:top;">' . s($label) . '</td>'
                . '<td align="right" style="' . $cell . ' color:#1a1a1a; font-weight:600; vertical-align:top;">'
                . ($ishtml ? $value : s($value)) . '</td></tr>';
        }
        if ($total) {
            $html .= '<tr><td style="font-family:' . self::FONT . '; font-size:15px; font-weight:600; padding:14px 0 0;">'
                . s($total[0]) . '</td><td align="right" style="font-family:Jost, ' . self::FONT . '; font-size:22px;'
                . ' font-weight:500; letter-spacing:-0.5px; padding:14px 0 0; color:#1a1a1a;">' . s($total[1]) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * Course card like the catalog's: image (when the course has one), mode and language, title, summary, key facts
     * and a link to the course page.
     *
     * @param int $courseid
     * @return string HTML, or '' when the course is gone
     */
    public static function course_card(int $courseid): string {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || $courseid == SITEID) {
            return '';
        }
        $fields = catalog::get_custom_fields([$courseid])[$courseid] ?? [];
        $enrol = catalog::get_enrol_info([$courseid])[$courseid] ?? null;
        $card = catalog::build_card(new core_course_list_element($course), $fields, $enrol);
        return self::render_card($card + [
            'schedule' => trim(html_to_text((string) ($fields['schedule'] ?? ''), 0, false)),
            'starts' => $course->startdate ? userdate($course->startdate, get_string('strftimedate', 'langconfig')) : '',
        ]);
    }

    /**
     * HTML for a course card from catalog-style data (see catalog::build_card()).
     *
     * @param array $card fullname, url, image, mode, language, summary, teachers, starts, schedule, length, pricelabel
     * @return string HTML
     */
    public static function render_card(array $card): string {
        $font = 'font-family:' . self::FONT . ';';
        $eyebrow = implode(' · ', array_filter([$card['mode'] ?? '', $card['language'] ?? '']));
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;'
            . ' background:#fbfaf6; border:1px solid rgba(26,26,26,.1); border-radius:18px; border-collapse:separate;">';
        if (!empty($card['image'])) {
            $html .= '<tr><td style="padding:0;"><img src="' . s($card['image']) . '" width="100%" alt="" style="display:block;'
                . ' width:100%; height:auto; border:0; border-radius:18px 18px 0 0;"></td></tr>';
        }
        $html .= '<tr><td style="padding:20px 22px;">';
        if ($eyebrow !== '') {
            $html .= '<div style="' . $font . ' font-size:12px; font-weight:600; letter-spacing:1.6px; text-transform:uppercase;'
                . ' color:#0a6a9c; margin:0 0 6px;"><span style="display:inline-block; width:24px; height:3px; background:#ffe000;'
                . ' vertical-align:middle; margin-right:8px;"></span>' . s($eyebrow) . '</div>';
        }
        $html .= '<div style="font-family:Jost, ' . self::FONT . '; font-size:22px; font-weight:500; line-height:1.25;'
            . ' letter-spacing:-0.4px; color:#1a1a1a; margin:0 0 8px;">' . s($card['fullname']) . '</div>';
        if (!empty($card['summary'])) {
            $html .= '<div style="' . $font . ' font-size:14px; line-height:1.55; color:#5c5b55; margin:0 0 12px;">'
                . s($card['summary']) . '</div>';
        }
        $facts = [
            get_string('email_teacher', 'theme_hikmah') => $card['teachers'] ?? '',
            get_string('fact_starts', 'theme_hikmah') => $card['starts'] ?? '',
            get_string('email_schedule', 'theme_hikmah') => $card['schedule'] ?? '',
            get_string('fact_length', 'theme_hikmah') => $card['length'] ?? '',
            get_string('email_price', 'theme_hikmah') => $card['pricelabel'] ?? '',
        ];
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
        foreach ($facts as $label => $value) {
            if ($value !== '') {
                $html .= '<tr><td style="' . $font . ' font-size:13px; line-height:18px; color:#5c5b55; padding:4px 12px 4px 0;'
                    . ' width:30%; vertical-align:top;">' . s($label) . '</td><td style="' . $font . ' font-size:13px;'
                    . ' line-height:18px; color:#1a1a1a; font-weight:600; padding:4px 0; vertical-align:top;">' . s($value)
                    . '</td></tr>';
            }
        }
        $html .= '</table>';
        $html .= '<div style="' . $font . ' font-size:14px; margin:14px 0 0;"><a href="' . s($card['url']) . '"'
            . ' style="color:#0a6a9c; font-weight:600; text-decoration:none;">'
            . s(get_string('email_viewcourse', 'theme_hikmah')) . ' &rarr;</a></div>';
        return $html . '</td></tr></table>';
    }
}
