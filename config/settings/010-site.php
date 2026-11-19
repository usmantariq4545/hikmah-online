<?php
// 010 — Site identity, timezone, languages, public pages, navigation.
require(__DIR__ . '/lib.php');

global $CFG, $DB, $SITE;

// Site name.
$fullname = hk_env('HIKMAH_SITE_NAME', 'Hikmah Online');
$site = $DB->get_record('course', ['id' => SITEID], '*', MUST_EXIST);
if ($site->fullname !== $fullname || $site->shortname !== 'Hikmah') {
    $site->fullname = $fullname;
    $site->shortname = 'Hikmah';
    $DB->update_record('course', $site);
    hk_changed('site name');
}

// Times are stored in UTC and shown in the user's timezone; the default is Asia/Karachi.
hk_set('timezone', 'Asia/Karachi');
hk_set('forcetimezone', '99'); // 99 = users may choose their own timezone.
hk_set('country', 'PK');

// Language: English only (owner decision 2026-11-19; Urdu/Arabic interfaces dropped). Arabic text
// inside lessons still displays with the theme's Arabic font.
hk_set('lang', 'en');
hk_set('langmenu', 0);
$installed = get_string_manager()->get_list_of_translations(true);
$unwanted = array_intersect(['ur', 'ar'], array_keys($installed));
if ($unwanted) {
    $controller = new \tool_langimport\controller();
    foreach ($unwanted as $lang) {
        $controller->uninstall_language($lang);
    }
    hk_changed('language packs removed: ' . implode(', ', $unwanted));
}
[$insql, $params] = $DB->get_in_or_equal(['ur', 'ar', 'ur_utf8', 'ar_utf8'], SQL_PARAMS_NAMED);
if ($DB->record_exists_select('user', "lang $insql", $params)) {
    $DB->set_field_select('user', 'lang', 'en', "lang $insql", $params);
    hk_changed('users on Urdu/Arabic moved to English');
}
if ($DB->record_exists_select('course', "lang $insql", $params)) {
    $DB->set_field_select('course', 'lang', '', "lang $insql", $params);
    hk_changed('courses forced to Urdu/Arabic now follow the site language');
}

// Maths rendering is not needed and MathJax loads from an outside CDN (build guide: no CDNs).
require_once($CFG->libdir . '/filterlib.php');
$filterstates = filter_get_global_states();
if (isset($filterstates['mathjaxloader']) && (int) $filterstates['mathjaxloader']->active !== TEXTFILTER_DISABLED) {
    filter_set_global_state('mathjaxloader', TEXTFILTER_DISABLED);
    hk_changed('filter mathjaxloader disabled');
}

// Public: home, catalog, course detail, FAQ, certificate verification. Course content always needs
// a login, and guest access is off.
hk_set('forcelogin', 0);
hk_set('guestloginbutton', 0);
hk_set('autologinguests', 0);
hk_set('forceloginforprofiles', 1);
hk_set('opentowebcrawlers', 1);

// The Hikmah home page replaces Moodle's default front page lists.
hk_set('enablemyhome', 1); // Keeps the site home page (/) available; without it Moodle 5.3 sends visitors to the login page.
hk_set('enablemycourses', 1);
hk_set('frontpage', '');
hk_set('frontpageloggedin', '');
hk_set('defaulthomepage', 1); // Signed-in users land on the dashboard.
hk_set('custommenuitems', implode("\n", [
    'Courses|/theme/hikmah/catalog.php',
    'Verify certificate|/theme/hikmah/verify.php',
    'FAQ|/theme/hikmah/faq.php',
]));

// Completion and access restrictions are the basis of certificates and eligibility.
hk_set('enablecompletion', 1);
hk_set('enableavailability', 1);
hk_set('enablebadges', 0);
hk_set('enabled', 0, 'core_competency'); // Competencies and learning plans are not used.
hk_set('usetags', 0);
hk_set('enableblogs', 0);
hk_set('messaging', 0);
hk_set('enablemobilewebservice', 1); // MOB-01: free Moodle App.
hk_set('enablewebservices', 1);

hk_set('supportname', $fullname . ' support');
if ($email = hk_env('HIKMAH_SUPPORT_EMAIL')) {
    hk_set('supportemail', $email);
}

hk_done(__FILE__);
