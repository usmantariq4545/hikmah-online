<?php
// Role access check (read-only): what each demo account can do versus the agreed table in
// docs/PRD.md section 3. Prints PASS/FAIL per row; exit 1 if anything differs.
// Needs the demo data (demo.* accounts, course D-TAJWEED). Run inside the moodle container:
//   docker cp scripts/server/role-audit.php <moodle>:/tmp/ && docker exec -u www-data <moodle> php /tmp/role-audit.php

define('CLI_SCRIPT', true);
require '/var/www/moodle/config.php';

use core_message\api as message_api;

$sys = context_system::instance();
$course = $DB->get_record('course', ['shortname' => 'D-TAJWEED'], '*', MUST_EXIST);
$cctx = context_course::instance($course->id);
$peers = (bool) get_config('local_hikmahaccess', 'allowpeers');

$u = [];
foreach (['admin' => 'admin', 'manager' => 'demo.manager', 'finance' => 'demo.finance', 'support' => 'demo.support',
        'teacher' => 'demo.teacher', 'assistant' => 'demo.assistant', 'student' => 'demo.student1'] as $key => $name) {
    $u[$key] = $DB->get_record('user', ['username' => $name], '*', MUST_EXIST);
}

// [label, context, capability, roles that must have it — all others must not].
$staff = ['admin', 'manager'];
$expect = [
    ['Site settings', $sys, 'moodle/site:config', ['admin']],
    ['Log in as another user', $sys, 'moodle/user:loginas', ['admin']],
    ['Create courses', $sys, 'moodle/course:create', $staff],
    ['Create users', $sys, 'moodle/user:create', $staff],
    ['Assign roles', $sys, 'moodle/role:assign', $staff],
    ['Payment review queue', $sys, 'paygw/hikmahtransfer:review', [...$staff, 'finance']],
    ['Open receipts', $sys, 'paygw/hikmahtransfer:viewreceipt', [...$staff, 'finance']],
    ['Payments report', $sys, 'moodle/payment:viewpayments', [...$staff, 'finance']],
    ['Dashboard edit mode', $sys, 'moodle/my:manageblocks', ['admin']],
    ['Enter course unenrolled', $cctx, 'moodle/course:view', [...$staff, 'support']],
    ['Course settings', $cctx, 'moodle/course:update', $staff],
    ['Edit course content', $cctx, 'moodle/course:manageactivities', [...$staff, 'teacher']],
    ['Grade', $cctx, 'moodle/grade:edit', [...$staff, 'teacher']],
    ['Enrol manually', $cctx, 'enrol/manual:enrol', [...$staff, 'support']],
    ['Attendance review', $cctx, 'local/hikmahattendance:review', [...$staff, 'teacher', 'assistant']],
    ['Attendance reports', $cctx, 'local/hikmahattendance:viewreports', $staff],
    ['Join live class', $cctx, 'mod/bigbluebuttonbn:join', [...$staff, 'teacher', 'assistant', 'student']],
    ['Personal access tokens', $sys, 'moodle/api:createtoken', ['admin']],
    ['Moodle app sign-in', $sys, 'moodle/webservice:createmobiletoken', array_keys($u)],
    ['See participant list', $cctx, 'moodle/course:viewparticipants',
        [...$staff, 'support', 'teacher', 'assistant', ...($peers ? ['student'] : [])]],
];

$fail = 0;
$report = static function (bool $ok, string $label, string $detail = '') use (&$fail): void {
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . str_pad($label, 28) . $detail . PHP_EOL;
};

echo 'Classmates switch: ' . ($peers ? 'on' : 'off') . PHP_EOL;
foreach ($expect as [$label, $ctx, $cap, $allowed]) {
    $wrong = [];
    foreach ($u as $key => $user) {
        if (has_capability($cap, $ctx, $user) !== in_array($key, $allowed, true)) {
            $wrong[] = $key;
        }
    }
    $report(!$wrong, $label, $wrong ? 'wrong for: ' . implode(', ', $wrong) : '');
}

$classmate = $DB->get_record('user', ['username' => 'demo.student4'], '*', MUST_EXIST);
$report(message_api::can_send_message($classmate->id, $u['student']->id) === $peers, 'Student -> classmate',
    $peers ? '(should be allowed)' : '(should be refused)');
$report(message_api::can_send_message($u['teacher']->id, $u['student']->id), 'Student -> own teacher');
$report(message_api::can_send_message($u['student']->id, $u['teacher']->id), 'Teacher -> own student');

echo PHP_EOL . ($fail ? "$fail FAILED" : 'All passed') . PHP_EOL;
exit($fail ? 1 : 0);
