<?php
// 010 — Site identity, timezone, languages, public pages, navigation.
require(__DIR__ . '/lib.php');

global $DB, $SITE;

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

// Languages: English by default, Urdu and Arabic packs installed when the download site is reachable.
hk_set('lang', 'en');
hk_set('langmenu', 1);
$installed = get_string_manager()->get_list_of_translations(true);
$wanted = array_diff(['ur', 'ar'], array_keys($installed));
if ($wanted) {
    try {
        $controller = new \tool_langimport\controller();
        $controller->install_languagepacks(array_values($wanted));
        hk_changed('language packs installed: ' . implode(', ', $wanted));
    } catch (\Throwable $e) {
        cli_writeln('  note: language packs not installed (' . $e->getMessage() . '); will retry on the next run');
    }
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
