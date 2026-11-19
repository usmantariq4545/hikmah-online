<?php
// 135 — Wording: replace a few core and plugin phrases students see, through Moodle's standard
// local language pack (en_local), the same files Site administration → Language customisation writes.
require(__DIR__ . '/lib.php');

global $CFG;

$overrides = [
    'mod_bigbluebuttonbn' => [
        'modulename' => 'Live class',
        'modulenameplural' => 'Live classes',
        'pluginname' => 'Live class',
        'waitformoderator' => 'Your teacher hasn\'t started the class yet.',
        'view_message_conference_wait_for_moderator' => 'Your teacher hasn\'t started the class yet.',
    ],
    'mod_attendance' => [
        'enrolmentstart' => 'Before you enrolled',
        'enrolmentend' => 'After your enrolment ended',
    ],
];

$dir = ($CFG->langlocalroot ?? $CFG->dataroot . '/lang') . '/en_local';
foreach ($overrides as $component => $strings) {
    $php = "<?php\n// Written by config/settings/135-strings.php; edit that file, not this one.\n";
    foreach ($strings as $id => $text) {
        $php .= '$string[' . var_export($id, true) . '] = ' . var_export($text, true) . ";\n";
    }
    // Activity modules' language files drop the "mod_" prefix (mod_attendance → attendance.php).
    $file = $dir . '/' . preg_replace('/^mod_/', '', $component) . '.php';
    @unlink("$dir/$component.php"); // shortcut: cleans up the first, misnamed version; drop after one deploy.
    if (is_readable($file) && file_get_contents($file) === $php) {
        continue;
    }
    check_dir_exists($dir);
    file_put_contents($file, $php);
    hk_changed("strings: $component");
}

if ($GLOBALS['hkchanges']) {
    get_string_manager()->reset_caches();
}

hk_done(__FILE__);
