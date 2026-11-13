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
    'preferences,moodle|/user/preferences.php',
]));

if ($GLOBALS['hkchanges'] > $before) {
    theme_reset_all_caches();
}

hk_done(__FILE__);
