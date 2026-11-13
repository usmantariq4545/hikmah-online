<?php
// 080 — BigBlueButton (LIVE-01 to LIVE-05, ATT-02). BBB never runs in this Compose stack.
require(__DIR__ . '/lib.php');

global $DB;

// The activity module must be enabled.
if (!$DB->get_field('modules', 'visible', ['name' => 'bigbluebuttonbn'])) {
    \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
    hk_changed('BigBlueButton activity enabled');
}

// Server: a managed or self-hosted BBB from the environment. With nothing set, Moodle's free
// tier is used (25 users, 60 minutes, recordings kept 7 days); that tier asks an administrator to
// accept its data processing agreement once in Site administration > Plugins > BigBlueButton.
// This script never accepts that agreement on anyone's behalf.
$url = hk_env('BBB_SERVER_URL');
$secret = hk_env('BBB_SHARED_SECRET');
if ($url && $secret) {
    hk_set('bigbluebuttonbn_server_url', rtrim($url, '/') . '/');
    hk_set('bigbluebuttonbn_shared_secret', $secret, null, true);
    hk_set('bigbluebuttonbn_checksum_algorithm', 'SHA256');
} else {
    cli_writeln('  note: BBB_SERVER_URL / BBB_SHARED_SECRET not set; Moodle\'s free BBB tier applies once an ' .
        'administrator accepts its data processing agreement in the BigBlueButton settings');
}

// Attendance evidence: BBB posts its post-meeting analytics to Moodle ("Register meeting events").
hk_set('bigbluebuttonbn_meetingevents_enabled', 1);

// Recordings on, shown inside Moodle only; learners cannot download or share links.
hk_set('bigbluebuttonbn_recordings_enabled', 1);
hk_set('bigbluebuttonbn_recordingready_enabled', 1);
hk_set('bigbluebuttonbn_recordings_imported_default', 0);
hk_set('bigbluebuttonbn_recordings_preview_default', 0);
hk_set('bigbluebuttonbn_recording_protect_editable', 1);
hk_set('bigbluebuttonbn_recording_default', 1);

// Learners wait for the instructor; instructors moderate.
hk_set('bigbluebuttonbn_waitformoderator_default', 1);
hk_set('bigbluebuttonbn_participant_moderator_default', '0');

hk_done(__FILE__);
