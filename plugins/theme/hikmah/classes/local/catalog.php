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

use core_course_category;
use core_course_list_element;
use moodle_url;
use stdClass;

/**
 * Reads visible courses, their Hikmah custom fields and enrolment options for the catalog,
 * the course detail page and the home page (PRD CRS-01, CRS-02).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string[] Enrolment plugins the catalog understands, in order of precedence. */
    private const ENROL_TYPES = ['fee', 'apply', 'self'];

    /** @var string[] Course custom field shortnames created by config/settings/060-courses.php. */
    public const FIELDS = [
        'mode', 'language', 'price', 'currency', 'accesspolicy', 'eligibility',
        'outcomes', 'schedule', 'length', 'prerequisites',
    ];

    /**
     * Cards for every course the current user may see in the catalog.
     *
     * @param array $filters keys: q, mode, language, price (free|paid), category (int)
     * @return array list of template-ready course cards
     */
    public static function get_cards(array $filters = []): array {
        $courses = self::get_visible_courses();
        if (!$courses) {
            return [];
        }
        $ids = array_keys($courses);
        $fields = self::get_custom_fields($ids);
        $enrol = self::get_enrol_info($ids);

        $cards = [];
        foreach ($courses as $id => $course) {
            $card = self::build_card($course, $fields[$id] ?? [], $enrol[$id] ?? null);
            if (self::matches($card, $filters)) {
                $cards[] = $card;
            }
        }
        return $cards;
    }

    /**
     * Options for the catalog filter selects, limited to values that are actually in use.
     *
     * @return array with keys modes, languages, categories (each a list of [value, label])
     */
    public static function get_filter_options(): array {
        $cards = self::get_cards();
        $modes = $languages = $categories = [];
        foreach ($cards as $card) {
            if ($card['mode']) {
                $modes[$card['modekey']] = $card['mode'];
            }
            if ($card['language']) {
                $languages[$card['language']] = $card['language'];
            }
            $categories[$card['categoryid']] = $card['category'];
        }
        asort($modes);
        asort($languages);
        asort($categories);
        return ['modes' => $modes, 'languages' => $languages, 'categories' => $categories];
    }

    /**
     * Whether a card passes the catalog filters.
     *
     * @param array $card
     * @param array $filters
     * @return bool
     */
    private static function matches(array $card, array $filters): bool {
        $q = \core_text::strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = \core_text::strtolower($card['fullname'] . ' ' . $card['summary'] . ' ' .
                $card['category'] . ' ' . $card['teachers']);
            foreach (preg_split('/\s+/', $q) as $word) {
                if ($word !== '' && !str_contains($haystack, $word)) {
                    return false;
                }
            }
        }
        if (!empty($filters['mode']) && $card['modekey'] !== $filters['mode']) {
            return false;
        }
        if (!empty($filters['language']) && $card['language'] !== $filters['language']) {
            return false;
        }
        if (!empty($filters['price'])) {
            if ($filters['price'] === 'free' && !$card['isfree']) {
                return false;
            }
            if ($filters['price'] === 'paid' && $card['isfree']) {
                return false;
            }
        }
        if (!empty($filters['category']) && (int) $card['categoryid'] !== (int) $filters['category']) {
            return false;
        }
        return true;
    }

    /**
     * Visible courses (never the site course), keyed by id, in catalog order.
     *
     * @return core_course_list_element[]
     */
    public static function get_visible_courses(): array {
        $top = core_course_category::top();
        if (!$top) {
            return [];
        }
        $courses = $top->get_courses([
            'recursive' => true,
            'summary' => true,
            'coursecontacts' => true,
            'sort' => ['sortorder' => 1],
        ]);
        unset($courses[SITEID]);
        return $courses;
    }

    /**
     * Hikmah custom field values for a set of courses.
     *
     * @param int[] $courseids
     * @return array courseid => [shortname => exported value]
     */
    public static function get_custom_fields(array $courseids): array {
        $handler = \core_course\customfield\course_handler::create();
        $result = [];
        foreach ($handler->get_instances_data($courseids, true) as $courseid => $datas) {
            foreach ($datas as $data) {
                $shortname = $data->get_field()->get('shortname');
                if (in_array($shortname, self::FIELDS, true)) {
                    $value = $data->export_value();
                    $result[$courseid][$shortname] = is_string($value) ? trim($value) : $value;
                }
            }
        }
        return $result;
    }

    /**
     * The enrolment route for each course: paid transfer, approval, instant, or closed.
     *
     * @param int[] $courseids
     * @return array courseid => stdClass {type, instance, cost, currency, state, opens, closes}
     */
    public static function get_enrol_info(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        [$typesql, $typeparams] = $DB->get_in_or_equal(self::ENROL_TYPES, SQL_PARAMS_NAMED, 't');
        $enabled = array_flip(explode(',', (string) get_config('core', 'enrol_plugins_enabled')));
        $instances = $DB->get_records_select(
            'enrol',
            "courseid $insql AND enrol $typesql AND status = :status",
            $params + $typeparams + ['status' => ENROL_INSTANCE_ENABLED],
            'sortorder ASC'
        );

        $bycourse = [];
        foreach ($instances as $instance) {
            if (!isset($enabled[$instance->enrol])) {
                continue;
            }
            $bycourse[$instance->courseid][$instance->enrol] ??= $instance;
        }

        $now = time();
        $result = [];
        foreach ($courseids as $courseid) {
            $info = new stdClass();
            $info->type = 'closed';
            $info->instance = null;
            $info->cost = 0.0;
            $info->currency = '';
            $info->state = 'closed';
            $info->opens = 0;
            $info->closes = 0;

            foreach (self::ENROL_TYPES as $type) {
                $instance = $bycourse[$courseid][$type] ?? null;
                if (!$instance) {
                    continue;
                }
                if ($type === 'fee' && (float) $instance->cost <= 0) {
                    continue;
                }
                $info->instance = $instance;
                $info->type = ['fee' => 'paid', 'apply' => 'approval', 'self' => 'free'][$type];
                if ($type === 'fee') {
                    $info->cost = (float) $instance->cost;
                    $info->currency = (string) $instance->currency;
                }
                $info->opens = (int) $instance->enrolstartdate;
                $info->closes = (int) $instance->enrolenddate;
                if ($info->opens && $info->opens > $now) {
                    $info->state = 'notyet';
                } else if ($info->closes && $info->closes < $now) {
                    $info->state = 'ended';
                } else if ($type === 'self' && (int) $instance->customint3 > 0 &&
                        $DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= (int) $instance->customint3) {
                    $info->state = 'full';
                } else if ($type === 'self' && empty($instance->customint6)) {
                    // Self enrolment instance exists but new enrolments are switched off.
                    $info->state = 'closed';
                } else {
                    $info->state = 'open';
                }
                break;
            }
            $result[$courseid] = $info;
        }
        return $result;
    }

    /**
     * Build one course card.
     *
     * @param core_course_list_element $course
     * @param array $fields custom field values
     * @param stdClass|null $enrol enrolment info
     * @return array template context
     */
    public static function build_card(core_course_list_element $course, array $fields, ?stdClass $enrol): array {
        $context = \context_course::instance($course->id);
        $category = core_course_category::get($course->category, IGNORE_MISSING, true);

        $summary = '';
        if ($course->has_summary()) {
            $summary = html_to_text(format_text($course->summary, $course->summaryformat, ['context' => $context]), 0, false);
            $summary = trim(preg_replace('/\s+/', ' ', $summary));
            $summary = shorten_text($summary, 170, true, '…');
        }

        $teachers = [];
        foreach ($course->get_course_contacts() as $contact) {
            $teachers[] = $contact['username'];
        }

        $mode = (string) ($fields['mode'] ?? '');
        $price = self::price_label($fields, $enrol);

        return [
            'id' => $course->id,
            'url' => (new moodle_url('/theme/hikmah/course.php', ['id' => $course->id]))->out(false),
            'fullname' => $course->get_formatted_fullname(),
            'summary' => $summary,
            'category' => $category ? $category->get_formatted_name() : '',
            'categoryid' => (int) $course->category,
            'image' => self::course_image($course),
            'hue' => ($course->id * 67) % 360,
            'mode' => $mode,
            'modekey' => self::mode_key($mode),
            'islive' => self::mode_key($mode) === 'live',
            'language' => (string) ($fields['language'] ?? ''),
            'length' => (string) ($fields['length'] ?? ''),
            'teachers' => implode(', ', array_slice($teachers, 0, 2)) . (count($teachers) > 2 ? ' +' . (count($teachers) - 2) : ''),
            'hasteachers' => !empty($teachers),
            'startdate' => $course->startdate ? userdate($course->startdate, get_string('strftimedatefullshort', 'langconfig')) : '',
            'startts' => (int) $course->startdate,
            'isfree' => $price['isfree'],
            'pricelabel' => $price['label'],
            'pricecurrency' => $price['currency'],
            'priceamount' => $price['amount'],
            'status' => self::status_chip($enrol),
        ];
    }

    /**
     * A stable key for a mode label (live, selfpaced, hybrid) for filtering and styling.
     *
     * @param string $mode
     * @return string
     */
    public static function mode_key(string $mode): string {
        $key = strtolower(preg_replace('/[^a-z]/i', '', $mode));
        return $key ?: '';
    }

    /**
     * Price shown to learners. The fee enrolment instance is the source of truth for paid courses;
     * the "price" custom field is only a fallback for courses that are not open yet.
     *
     * @param array $fields
     * @param stdClass|null $enrol
     * @return array {isfree, label, currency, amount}
     */
    public static function price_label(array $fields, ?stdClass $enrol): array {
        $amount = 0.0;
        $currency = '';
        if ($enrol && $enrol->type === 'paid') {
            $amount = $enrol->cost;
            $currency = $enrol->currency;
        } else if ((!$enrol || $enrol->type === 'closed') && !empty($fields['price']) && (float) $fields['price'] > 0) {
            $amount = (float) $fields['price'];
            $currency = (string) ($fields['currency'] ?? 'PKR');
        }
        if ($amount <= 0) {
            return ['isfree' => true, 'label' => get_string('free', 'theme_hikmah'), 'currency' => '', 'amount' => ''];
        }
        $formatted = number_format($amount, fmod($amount, 1.0) > 0 ? 2 : 0);
        return [
            'isfree' => false,
            'label' => trim($currency . ' ' . $formatted),
            'currency' => $currency,
            'amount' => $formatted,
        ];
    }

    /**
     * The enrolment status chip for a card.
     *
     * @param stdClass|null $enrol
     * @return array {label, class}
     */
    public static function status_chip(?stdClass $enrol): array {
        if (!$enrol || $enrol->type === 'closed' || $enrol->state === 'closed') {
            return ['label' => get_string('status_closed', 'theme_hikmah'), 'class' => ''];
        }
        switch ($enrol->state) {
            case 'notyet':
                return [
                    'label' => get_string('status_opens', 'theme_hikmah',
                        userdate($enrol->opens, get_string('strftimedateshort', 'langconfig'))),
                    'class' => 'hk-chip--warning',
                ];
            case 'ended':
                return ['label' => get_string('status_closed', 'theme_hikmah'), 'class' => ''];
            case 'full':
                return ['label' => get_string('status_full', 'theme_hikmah'), 'class' => 'hk-chip--danger'];
        }
        if ($enrol->type === 'approval') {
            return ['label' => get_string('status_approval', 'theme_hikmah'), 'class' => 'hk-chip--sky'];
        }
        return ['label' => get_string('status_open', 'theme_hikmah'), 'class' => 'hk-chip--success'];
    }

    /**
     * First usable overview image of a course, or null.
     *
     * @param core_course_list_element $course
     * @return string|null
     */
    public static function course_image(core_course_list_element $course): ?string {
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }
        return null;
    }

    /**
     * Headline numbers for the home page.
     *
     * @return array
     */
    public static function get_stats(): array {
        global $DB;

        $cache = \cache::make('theme_hikmah', 'stats');
        $stats = $cache->get('home');
        if ($stats !== false) {
            return $stats;
        }

        $courses = $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]);
        $learners = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE ue.status = :active",
            ['active' => ENROL_USER_ACTIVE]
        );
        $certificates = 0;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('tool_certificate_issues')) {
            $certificates = $DB->count_records('tool_certificate_issues');
        }
        $live = 0;
        if ($dbman->table_exists('bigbluebuttonbn')) {
            $live = $DB->count_records('bigbluebuttonbn');
        }

        $stats = [
            'courses' => $courses,
            'learners' => $learners,
            'certificates' => $certificates,
            'live' => $live,
        ];
        $cache->set('home', $stats);
        return $stats;
    }
}
