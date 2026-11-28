<?php
// 150 — Portal tour on first sign-in (owner's decision, 2026-11-28: Moodle user tours, not PostHog). The tour lives in
// tours/welcome.json and is shown once to each person on their dashboard. It is imported again only when that file
// changes (its hash is kept in theme_hikmah | welcometourhash); a re-import shows the new tour to everyone once more.
require(__DIR__ . '/lib.php');

global $DB;

$json = file_get_contents(__DIR__ . '/tours/welcome.json');
$name = json_decode($json)->name;
$hash = sha1($json);
$tour = $DB->get_record('tool_usertours_tours', ['name' => $name]);

if (!$tour || get_config('theme_hikmah', 'welcometourhash') !== $hash) {
    if ($tour) {
        \tool_usertours\tour::instance($tour->id)->remove();
    }
    \tool_usertours\manager::import_tour_from_json($json);
    set_config('welcometourhash', $hash, 'theme_hikmah');
    hk_changed("user tour \"$name\" " . ($tour ? 'updated' : 'created'));
}

hk_done(__FILE__);
