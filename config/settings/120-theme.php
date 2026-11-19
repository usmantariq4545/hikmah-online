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

// Dashboard: the Hikmah summary already has "Continue learning" and "Coming up", so drop core
// blocks that repeat them (default page and every user's copy): Course overview (stays on My
// courses), Timeline, Recently accessed items and the month calendar.
global $DB;
$dashboards = $DB->get_fieldset_select('my_pages', 'id', "name = '__default'");
if ($dashboards) {
    [$pagesql, $params] = $DB->get_in_or_equal(array_map('strval', $dashboards), SQL_PARAMS_NAMED, 'p');
    [$blocksql, $blockparams] = $DB->get_in_or_equal(['myoverview', 'timeline', 'recentlyaccesseditems', 'calendar_month'],
        SQL_PARAMS_NAMED, 'b');
    $repeats = $DB->get_records_select('block_instances',
        "blockname $blocksql AND pagetypepattern = 'my-index' AND subpagepattern $pagesql", $params + $blockparams);
    foreach ($repeats as $instance) {
        blocks_delete_instance($instance);
        hk_changed("dashboard: removed $instance->blockname block $instance->id");
    }
}

if ($GLOBALS['hkchanges'] > $before) {
    theme_reset_all_caches();
}

hk_done(__FILE__);
