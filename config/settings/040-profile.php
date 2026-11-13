<?php
// 040 — Custom profile fields in the category "Hikmah profile" (AUTH-03). Required fields are
// asked at sign-up and Moodle blocks users with an incomplete profile until they fill them in.
// Final field list is an open decision in the PRD; change it here, not in the admin UI.
require(__DIR__ . '/lib.php');

global $CFG, $DB;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

$categoryname = 'Hikmah profile';
$category = $DB->get_record('user_info_category', ['name' => $categoryname]);
if (!$category) {
    $sortorder = (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {user_info_category}');
    $categoryid = $DB->insert_record('user_info_category', (object) ['name' => $categoryname, 'sortorder' => $sortorder]);
    hk_changed('profile category created');
} else {
    $categoryid = $category->id;
}

// Shortname => [name, datatype, required, on sign-up form, extra].
$fields = [
    'hkgender' => ['Gender', 'menu', 1, 1, ['param1' => "Male\nFemale", 'defaultdata' => '']],
    'hkdob' => ['Date of birth', 'datetime', 1, 1, ['param1' => 1930, 'param2' => (int) date('Y'), 'param3' => 0]],
    'hkphone' => ['Mobile / WhatsApp number', 'text', 1, 1, ['param1' => 30, 'param2' => 30, 'param3' => 0]],
    'hkguardian' => ['Guardian contact (for learners under 18)', 'text', 0, 1, ['param1' => 40, 'param2' => 100,
        'param3' => 0]],
    'hkconsentcomms' => ['I agree to receive course announcements by email', 'checkbox', 0, 1, ['defaultdata' => 0]],
];

$sort = 0;
foreach ($fields as $shortname => [$name, $datatype, $required, $signup, $extra]) {
    $sort++;
    if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
        continue;
    }
    require_once($CFG->dirroot . '/user/profile/field/' . $datatype . '/define.class.php');
    $class = 'profile_define_' . $datatype;
    $define = new $class();
    $data = (object) array_merge([
        'id' => 0,
        'shortname' => $shortname,
        'name' => $name,
        'datatype' => $datatype,
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'categoryid' => $categoryid,
        'sortorder' => $sort,
        'required' => $required,
        'locked' => 0,
        'visible' => 1, // Visible to the user and to staff with the right capability; not public.
        'forceunique' => 0,
        'signup' => $signup,
        'defaultdata' => '',
        'defaultdataformat' => FORMAT_HTML,
        'param1' => null,
        'param2' => null,
        'param3' => null,
        'param4' => null,
        'param5' => null,
    ], $extra);
    $define->define_save($data);
    hk_changed("profile field $shortname created");
}

// City and country are core fields; make them part of every profile.
hk_set('defaultcity', '');
hk_set('showuseridentity', 'email');

hk_done(__FILE__);
