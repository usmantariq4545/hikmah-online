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
use moodle_url;
use stdClass;

/**
 * Builds the public course detail page: outcomes, syllabus, teachers, schedule, access policy and
 * the right enrol action for the current user (PRD CRS-02, ENR-01, ENR-02, ENR-04).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_detail {
    /**
     * Template context for a course detail page.
     *
     * @param stdClass $course course record
     * @return array
     */
    public static function export(stdClass $course): array {
        global $USER;

        $element = new core_course_list_element($course);
        $context = \context_course::instance($course->id);
        $fields = catalog::get_custom_fields([$course->id])[$course->id] ?? [];
        $enrol = catalog::get_enrol_info([$course->id])[$course->id] ?? null;
        $card = catalog::build_card($element, $fields, $enrol);

        $description = '';
        if (!empty($course->summary)) {
            $summary = file_rewrite_pluginfile_urls($course->summary, 'pluginfile.php', $context->id, 'course', 'summary', null);
            $description = format_text($summary, $course->summaryformat, ['context' => $context, 'overflowdiv' => true]);
        }

        $outcomes = [];
        // The field is a text area: turn block and line-break tags into new lines before reading it.
        $rawoutcomes = preg_replace('#<br\s*/?>|</p>|</li>|</div>#i', "\n", (string) ($fields['outcomes'] ?? ''));
        foreach (preg_split('/\r?\n/', $rawoutcomes) as $line) {
            $line = trim(html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t-•*");
            if ($line !== '') {
                $outcomes[] = ['text' => $line];
            }
        }

        $teachers = [];
        foreach ($element->get_course_contacts() as $contact) {
            $name = $contact['username'];
            $initials = '';
            foreach (preg_split('/\s+/', trim($name)) as $part) {
                $initials .= \core_text::strtoupper(\core_text::substr($part, 0, 1));
            }
            $teachers[] = [
                'name' => $name,
                'role' => $contact['rolename'],
                'initials' => \core_text::substr($initials, 0, 2),
            ];
        }

        $facts = [];
        $addfact = static function (string $label, ?string $value) use (&$facts): void {
            if ($value !== null && $value !== '') {
                $facts[] = ['label' => get_string($label, 'theme_hikmah'), 'value' => $value];
            }
        };
        $addfact('fact_mode', $card['mode']);
        $addfact('fact_language', $card['language']);
        $addfact('fact_starts', $course->startdate ? userdate($course->startdate, get_string('strftimedate', 'langconfig')) : '');
        $addfact('fact_ends', !empty($course->enddate) ? userdate($course->enddate, get_string('strftimedate', 'langconfig')) : '');
        $addfact('fact_length', $card['length']);
        $addfact('fact_access', (string) ($fields['accesspolicy'] ?? ''));

        return $card + [
            'description' => $description,
            'hasdescription' => $description !== '',
            'outcomes' => $outcomes,
            'hasoutcomes' => !empty($outcomes),
            'syllabus' => self::syllabus($course),
            'teacherlist' => $teachers,
            'hasteacherlist' => !empty($teachers),
            'schedule' => (string) ($fields['schedule'] ?? ''),
            'timezone' => \core_date::get_user_timezone(),
            'eligibility' => (string) ($fields['eligibility'] ?? ''),
            'prerequisites' => (string) ($fields['prerequisites'] ?? ''),
            'accesspolicy' => (string) ($fields['accesspolicy'] ?? ''),
            'facts' => $facts,
            'action' => self::action($course, $enrol, (int) $USER->id),
            'catalogurl' => (new moodle_url('/theme/hikmah/catalog.php'))->out(false),
            'homeurl' => (new moodle_url('/'))->out(false),
        ];
    }

    /**
     * Section titles and visible activity names: enough for a syllabus, no protected content.
     *
     * @param stdClass $course
     * @return array
     */
    private static function syllabus(stdClass $course): array {
        $modinfo = get_fast_modinfo($course, -1);
        $format = course_get_format($course);
        $sections = [];
        $number = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible || (int) $section->section === 0 || !empty($section->component)) {
                continue;
            }
            $items = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->visible || $cm->deletioninprogress || !$cm->has_view()) {
                    continue;
                }
                $items[] = [
                    'name' => $cm->get_formatted_name(),
                    'type' => $cm->modfullname,
                    'icon' => $cm->get_icon_url()->out(false),
                ];
            }
            $number++;
            $sections[] = [
                'number' => $number,
                'name' => $format->get_section_name($section),
                'items' => $items,
                'count' => count($items),
                'hasitems' => !empty($items),
                'open' => $number === 1,
            ];
        }
        return $sections;
    }

    /**
     * Decide which enrol action the current user sees.
     *
     * @param stdClass $course
     * @param stdClass|null $enrol
     * @param int $userid
     * @return array template context for the enrol card
     */
    public static function action(stdClass $course, ?stdClass $enrol, int $userid): array {
        global $DB;

        $context = \context_course::instance($course->id);
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $detailurl = new moodle_url('/theme/hikmah/course.php', ['id' => $course->id]);
        $action = [
            'kind' => 'closed',
            'label' => get_string('cta_closed', 'theme_hikmah'),
            'url' => '',
            'disabled' => true,
            'post' => false,
            'note' => '',
            'notice' => false,
        ];

        $loggedin = isloggedin() && !isguestuser();

        if ($loggedin && is_enrolled($context, $userid, '', true)) {
            return array_merge($action, [
                'kind' => 'enrolled',
                'label' => get_string('cta_gotocourse', 'theme_hikmah'),
                'url' => $courseurl->out(false),
                'disabled' => false,
                'notice' => ['class' => 'hk-enrol__status--success', 'text' => get_string('status_enrolled', 'theme_hikmah')],
            ]);
        }
        if ($loggedin && has_capability('moodle/course:view', $context)) {
            return array_merge($action, [
                'kind' => 'staff',
                'label' => get_string('cta_opencourse', 'theme_hikmah'),
                'url' => $courseurl->out(false),
                'disabled' => false,
            ]);
        }

        if (!$enrol || $enrol->type === 'closed') {
            return $action;
        }
        switch ($enrol->state) {
            case 'notyet':
                $action['label'] = get_string(
                    'cta_notyet',
                    'theme_hikmah',
                    userdate($enrol->opens, get_string('strftimedate', 'langconfig'))
                );
                return $action;
            case 'ended':
            case 'closed':
                return $action;
            case 'full':
                $action['label'] = get_string('cta_full', 'theme_hikmah');
                return $action;
        }

        if (!$loggedin) {
            return array_merge($action, [
                'kind' => 'login',
                'label' => get_string('cta_login', 'theme_hikmah'),
                'url' => (new moodle_url('/theme/hikmah/course.php', ['id' => $course->id, 'login' => 1]))->out(false),
                'disabled' => false,
                'note' => get_string('cta_login_note', 'theme_hikmah'),
            ]);
        }

        if ($enrol->type === 'paid') {
            $payurl = new moodle_url('/payment/gateway/hikmahtransfer/pay.php', [
                'component' => 'enrol_fee',
                'paymentarea' => 'fee',
                'itemid' => $enrol->instance->id,
            ]);
            $action = array_merge($action, [
                'kind' => 'pay',
                'label' => get_string('cta_pay', 'theme_hikmah'),
                'url' => $payurl->out(false),
                'disabled' => false,
                'note' => get_string('cta_pay_note', 'theme_hikmah'),
            ]);
            if (class_exists('\paygw_hikmahtransfer\request')) {
                $request = \paygw_hikmahtransfer\request::latest_for_user('enrol_fee', 'fee', (int) $enrol->instance->id, $userid);
                if ($request) {
                    $statusurl = new moodle_url('/payment/gateway/hikmahtransfer/status.php', ['id' => $request->id]);
                    switch ($request->status) {
                        case 'pending':
                            $action['kind'] = 'pending';
                            $action['label'] = get_string('cta_viewpayment', 'theme_hikmah');
                            $action['url'] = $statusurl->out(false);
                            $action['notice'] = [
                                'class' => 'hk-enrol__status--pending',
                                'text' => get_string('status_paymentpending', 'theme_hikmah'),
                            ];
                            $action['note'] = '';
                            break;
                        case 'resubmit':
                            $action['kind'] = 'resubmit';
                            $action['label'] = get_string('cta_resubmit', 'theme_hikmah');
                            $action['notice'] = [
                                'class' => 'hk-enrol__status--pending',
                                'text' => get_string('status_paymentresubmit', 'theme_hikmah'),
                            ];
                            break;
                        case 'rejected':
                            $action['notice'] = [
                                'class' => 'hk-enrol__status--danger',
                                'text' => get_string('status_paymentrejected', 'theme_hikmah'),
                            ];
                            break;
                    }
                }
            }
            return $action;
        }

        if ($enrol->type === 'approval') {
            // Note: enrol_apply records a new application as suspended (1) and a wait-listed one as 2.
            $waiting = $DB->record_exists_select(
                'user_enrolments',
                'enrolid = :enrolid AND userid = :userid AND status IN (1, 2)',
                ['enrolid' => $enrol->instance->id, 'userid' => $userid]
            );
            if ($waiting) {
                return array_merge($action, [
                    'kind' => 'waiting',
                    'label' => get_string('cta_applied', 'theme_hikmah'),
                    'notice' => [
                        'class' => 'hk-enrol__status--pending',
                        'text' => get_string('status_applicationpending', 'theme_hikmah'),
                    ],
                ]);
            }
            return array_merge($action, [
                'kind' => 'apply',
                'label' => get_string('cta_apply', 'theme_hikmah'),
                'url' => (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
                'disabled' => false,
                'note' => get_string('cta_apply_note', 'theme_hikmah'),
            ]);
        }

        // Free, instant enrolment: a one-click POST back to the detail page.
        return array_merge($action, [
            'kind' => 'free',
            'label' => get_string('cta_enrolfree', 'theme_hikmah'),
            'url' => $detailurl->out(false),
            'disabled' => false,
            'post' => true,
            'sesskey' => sesskey(),
            'note' => get_string('cta_free_note', 'theme_hikmah'),
        ]);
    }
}
