<?php
// 120 — Theme: Hikmah as the site theme. Colours, logo and fonts live in the theme itself; the
// staging banner is forced from config.php (SITE_ENV).
require(__DIR__ . '/lib.php');

global $CFG;

$before = $GLOBALS['hkchanges'];
hk_set('theme', 'hikmah');
hk_set('allowthemechangeonurl', 0);
hk_set('allowuserthemes', 0);
hk_set('allowcoursethemes', 0);
hk_set('allowcategorythemes', 0);
hk_set('themedesignermode', 0);
hk_set('navshowfullcoursenames', 1);
hk_set('navshowcategories', 0);
hk_set('customusermenuitems', implode("\n", [
    'profile,moodle|/user/profile.php',
    'grades,grades|/grade/report/mygrades.php',
    'My payments|/payment/gateway/hikmahtransfer/status.php',
]));  // Moodle adds Preferences to the user menu itself.

// Live classes and recordings open inside a Hikmah page (theme/hikmah/classroom.php) instead of a
// separate window. Set HIKMAH_EMBED_CLASSROOM=0 if the BigBlueButton host refuses to be framed.
hk_set('embedclassroom', hk_env('HIKMAH_EMBED_CLASSROOM', '1') === '0' ? 0 : 1, 'theme_hikmah');

// Dashboard: the Hikmah summary already lists the learner's courses, so drop core's "Course
// overview" there (default page and every user's copy). It stays on My courses.
global $DB;
$dashboards = $DB->get_fieldset_select('my_pages', 'id', "name = '__default'");
if ($dashboards) {
    [$insql, $params] = $DB->get_in_or_equal(array_map('strval', $dashboards), SQL_PARAMS_NAMED);
    $params['blockname'] = 'myoverview';
    $overviews = $DB->get_records_select('block_instances',
        "blockname = :blockname AND pagetypepattern = 'my-index' AND subpagepattern $insql", $params);
    foreach ($overviews as $instance) {
        blocks_delete_instance($instance);
        hk_changed('dashboard: removed course overview block ' . $instance->id);
    }
}

if ($GLOBALS['hkchanges'] > $before) {
    theme_reset_all_caches();
}

hk_done(__FILE__);
