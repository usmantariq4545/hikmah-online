<?php
// 060 — Course custom fields used by the catalog and course detail pages (CRS-01, CRS-02),
// and course defaults.
require(__DIR__ . '/lib.php');

global $DB;

$handler = \core_course\customfield\course_handler::create();

/**
 * Id of the course custom field category with this name, created if missing.
 *
 * @param \core_course\customfield\course_handler $handler
 * @param string $name
 * @return int
 */
function hk_field_category(\core_course\customfield\course_handler $handler, string $name): int {
    foreach ($handler->get_categories_with_fields() as $category) {
        if ($category->get('name') === $name) {
            return (int) $category->get('id');
        }
    }
    hk_changed("course custom field category '$name' created");
    return (int) $handler->create_category($name);
}
// Each category is its own step on the course form (theme_hikmah courseform.js).
$categoryid = hk_field_category($handler, 'Hikmah course details');
$certcategoryid = hk_field_category($handler, 'Certificate rules');

$base = ['required' => 0, 'uniquevalues' => 0, 'locked' => 0, 'visibility' => 2];
$text = static fn(int $max = 255): array => $base + ['defaultvalue' => '', 'displaysize' => 60, 'maxlength' => $max,
    'ispassword' => 0, 'link' => ''];
$select = static fn(string $options): array => $base + ['options' => $options, 'defaultvalue' => ''];

// Shortname => [name, type, configdata(, category id)]. Shortnames are read by theme_hikmah\local\catalog.
$fields = [
    'mode' => ['Mode', 'select', $select("Live\nSelf-paced\nHybrid")],
    'language' => ['Language', 'select', $select("English\nUrdu\nArabic\nEnglish and Urdu")],
    'length' => ['Length', 'text', $text(100)],
    'schedule' => ['Schedule', 'text', $text(500)],
    'price' => ['Price (shown before enrollment opens)', 'text', $text(20)],
    'currency' => ['Currency', 'select', $select("PKR\nUSD\nGBP\nEUR")],
    'accesspolicy' => ['Access policy', 'text', $text(200)],
    'eligibility' => ['Eligibility', 'text', $text(500)],
    'prerequisites' => ['Prerequisites', 'text', $text(500)],
    'outcomes' => ['Learning outcomes (one per line)', 'textarea', $base + ['defaultvalue' => '',
        'defaultvalueformat' => FORMAT_PLAIN]],
    // Certificate rules, read by local_hikmahcompletion (empty = site default). Teachers set them on the
    // course's "Certificate rules" page.
    'certgrade' => ['Certificate: minimum course grade (%)', 'text', $text(3), $certcategoryid],
    'certattendance' => ['Certificate: minimum attendance (%)', 'text', $text(3), $certcategoryid],
];

$existing = [];
foreach ($handler->get_fields() as $field) {
    $existing[$field->get('shortname')] = $field;
}
foreach ($fields as $shortname => $spec) {
    [$name, $type, $configdata] = $spec;
    $fieldcategoryid = $spec[3] ?? $categoryid;
    if (isset($existing[$shortname])) {
        if ($existing[$shortname]->get('name') !== $name) {
            $existing[$shortname]->set('name', $name);
            $existing[$shortname]->save();
            hk_changed("course field $shortname renamed");
        }
        if ((int) $existing[$shortname]->get('categoryid') !== $fieldcategoryid) {
            // Moving keeps every course's value: data rows belong to the field, not the category.
            $existing[$shortname]->set('categoryid', $fieldcategoryid);
            $existing[$shortname]->save();
            hk_changed("course field $shortname moved to another category");
        }
        continue;
    }
    $field = \core_customfield\field_controller::create(0, (object) ['type' => $type, 'categoryid' => $fieldcategoryid],
        \core_customfield\category_controller::create($fieldcategoryid));
    $handler->save_field_configuration($field, (object) [
        'name' => $name,
        'shortname' => $shortname,
        'configdata' => $configdata,
    ]);
    hk_changed("course field $shortname created");
}

// Course defaults: completion tracking on, topics format, dates shown.
hk_set('enablecompletion', 1, 'moodlecourse');
hk_set('format', 'topics', 'moodlecourse');
hk_set('showgrades', 1, 'moodlecourse');
hk_set('showactivitydates', 1, 'moodlecourse');
hk_set('visible', 0, 'moodlecourse'); // New courses stay hidden until an administrator publishes them.
hk_set('coursecontact', implode(',', $DB->get_fieldset_select('role', 'id', "shortname IN ('editingteacher', 'teacher')")));

// Moodle's install-time "Category 1" goes once real categories exist, but only while it is empty
// (no courses, sub-categories or anything else in its context: owner's decision, 2026-11-20).
$default = $DB->get_record('course_categories', ['name' => 'Category 1', 'parent' => 0]);
if ($default && $DB->count_records('course_categories') > 1 && !$DB->record_exists('course', ['category' => $default->id])
        && !$DB->record_exists('course_categories', ['parent' => $default->id])) {
    $context = context_coursecat::instance($default->id);
    if (!$DB->record_exists_select('context', 'path LIKE ?', [$context->path . '/%'])) {
        $next = (int) $DB->get_field_sql('SELECT id FROM {course_categories} WHERE id <> ? AND parent = 0 ORDER BY sortorder',
            [$default->id], IGNORE_MULTIPLE);
        if ((int) get_config('core', 'defaultrequestcategory') === (int) $default->id) {
            hk_set('defaultrequestcategory', $next);
        }
        core_course_category::get($default->id, MUST_EXIST, true)->delete_full(false);
        hk_changed('empty default "Category 1" removed');
    }
}

hk_done(__FILE__);
