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
    // Month and day names: Moodle's English uses Australian English, which writes "Sept"; US English is the
    // only installed English locale that writes "Sep" (owner's request, 2026-11-21). Date layouts stay
    // as Moodle's own strings set them ("21 Nov", "Sat 21 Nov, 6:00 PM").
    'langconfig' => [
        'locale' => 'en_US.UTF-8',
        'localecldr' => 'en-US',
    ],
    // The course's first section (course info, announcements, the register) in the class list.
    'format_topics' => [
        'section0name' => 'Course info',
    ],
    'mod_attendance' => [
        'enrolmentstart' => 'Before you enrolled',
        'enrolmentend' => 'After your enrolment ended',
    ],
];

/**
 * US spelling "enroll" (owner's request, 2026-11-21): enrol → enroll, enrolment → enrollment, unenrol → unenroll.
 * Placeholders, HTML tags, addresses (/enrol/, enrol.php) and code names (enrol_fee) are left alone.
 *
 * @param string $text
 * @return string
 */
function hk_us_enroll(string $text): string {
    $parts = preg_split('/(\{\$a[^}]*\}|<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 0; $i < count($parts); $i += 2) {
        // The whole word must not be a name in code or CSV (enrol_fee, enrolment_1, enrol.php, /enrol/).
        $parts[$i] = preg_replace_callback('/(?<![A-Za-z\/_$])((?:un|Un|UN)?)(enrol|Enrol|ENROL)(?![lL])([A-Za-z]*+)(?![_\/=\d]|\.php)/',
            fn($m) => $m[1] . $m[2] . ($m[2] === 'ENROL' ? 'L' : 'l') . $m[3], $parts[$i]);
    }
    return implode('', $parts);
}
assert(hk_us_enroll('Enrol me; unenrol {$a->enrolperiod} <a href="/enrol/x">self-enrolment</a> enrolled')
    === 'Enroll me; unenroll {$a->enrolperiod} <a href="/enrol/x">self-enrollment</a> enrolled');
assert(hk_us_enroll('enrolment_1, enrol_fee, enrol.php, ENROLMENTS') === 'enrolment_1, enrol_fee, enrol.php, ENROLLMENTS');

// Every installed component's English strings that say "enrol", plus the wording above, all spelled "enroll".
$components = ['core' => 'moodle'];
foreach (core_component::get_core_subsystems() as $name => $unused) {
    $components['core_' . $name] = $name;
}
foreach (core_component::get_plugin_types() as $type => $unused) {
    foreach (core_component::get_plugin_list($type) as $plugin => $unused2) {
        // Activity modules' language files drop the "mod_" prefix (mod_attendance → attendance.php).
        $components[$type . '_' . $plugin] = $type === 'mod' ? $plugin : $type . '_' . $plugin;
    }
}
foreach (array_keys($overrides) as $component) {
    $components[$component] ??= $component; // Not a plugin or subsystem: langconfig.
}
$sm = get_string_manager();
$files = [];
foreach ($components as $component => $filename) {
    $strings = [];
    foreach ($sm->load_component_strings($component, 'en', true, true) as $id => $text) {
        if (is_string($text) && ($fixed = hk_us_enroll($text)) !== $text) {
            $strings[$id] = $fixed;
        }
    }
    foreach ($overrides[$component] ?? [] as $id => $text) {
        $strings[$id] = hk_us_enroll($text);
    }
    if ($strings) {
        ksort($strings);
        $files[$filename] = [$component, $strings];
    }
}

$dir = ($CFG->langlocalroot ?? $CFG->dataroot . '/lang') . '/en_local';
foreach ($files as $filename => [$component, $strings]) {
    $php = "<?php\n// Written by config/settings/135-strings.php; edit that file, not this one.\n";
    foreach ($strings as $id => $text) {
        $php .= '$string[' . var_export($id, true) . '] = ' . var_export($text, true) . ";\n";
    }
    $file = "$dir/$filename.php";
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
