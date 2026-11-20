<?php
// 050 — Roles (PRD section 3): Finance reviewer and Support, and the limits on instructors.
// Each role is also exported to config/roles/<shortname>.xml for review.
require(__DIR__ . '/lib.php');

global $CFG, $DB;

/**
 * Create a role if it does not exist and return its id.
 *
 * @param string $shortname
 * @param string $name
 * @param string $description
 * @param int[] $contextlevels where the role may be assigned
 * @return int
 */
function hk_role(string $shortname, string $name, string $description, array $contextlevels): int {
    global $DB;
    $role = $DB->get_record('role', ['shortname' => $shortname]);
    if ($role) {
        $roleid = (int) $role->id;
    } else {
        $roleid = create_role($name, $shortname, $description);
        hk_changed("role $shortname created");
    }
    $current = get_role_contextlevels($roleid);
    sort($current);
    sort($contextlevels);
    if (array_values($current) != $contextlevels) {
        set_role_contextlevels($roleid, $contextlevels);
        hk_changed("role $shortname context levels");
    }
    return $roleid;
}

// Finance reviewer: payment evidence and decisions only. No coursework, no other learner data.
$finance = hk_role('financereviewer', 'Finance reviewer',
    'Reviews transfer receipts and approves or rejects payments. No access to coursework.',
    [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);
foreach ([
    'paygw/hikmahtransfer:review',
    'paygw/hikmahtransfer:viewreceipt',
    'moodle/payment:viewpayments',
] as $capability) {
    hk_role_cap($finance, $capability, CAP_ALLOW);
}

// Support: account and course access issues with limited data visibility.
$support = hk_role('support', 'Support',
    'Resolves account and course access issues. Limited data visibility; every action is logged.',
    [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);
foreach ([
    'moodle/user:viewdetails',
    'moodle/site:viewparticipants',
    'moodle/course:view',
    'moodle/course:viewparticipants',
    'moodle/course:enrolreview',
    'enrol/manual:enrol',
    'enrol/manual:unenrol',
    'moodle/site:viewuseridentity',
] as $capability) {
    hk_role_cap($support, $capability, CAP_ALLOW);
}

// Instructors run their assigned teaching activities only: they cannot change enrolment, course
// settings, roles or completion. Only authorised staff (managers) can.
foreach (['editingteacher', 'teacher'] as $shortname) {
    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    foreach ([
        'moodle/course:update',
        'moodle/course:enrolconfig',
        'moodle/course:visibility',
        'moodle/course:overridecompletion',
        'moodle/role:assign',
        'moodle/restore:restorecourse',
        'enrol/manual:enrol',
        'enrol/manual:unenrol',
        'enrol/manual:manage',
        'enrol/manual:config',
        'enrol/self:config',
        'enrol/self:manage',
        'enrol/fee:config',
        'enrol/fee:manage',
        'enrol/apply:config',
        'enrol/apply:manageapplications',
        'enrol/cohort:config',
        'moodle/course:reset',
        // Site-wide tools teachers do not use: kept out of their course and activity menus.
        'moodle/filter:manage',
        'moodle/contentbank:access',
        'moodle/role:review',
        'mod/lti:addpreconfiguredinstance',
        'report/aiusage:view',
        // Attendance statuses are set site-wide and classes are created from live classes.
        'mod/attendance:changepreferences',
        'mod/attendance:managetemporaryusers',
        'mod/attendance:import',
    ] as $capability) {
        hk_role_cap($roleid, $capability, CAP_PREVENT);
    }
}

// Managers may assign the two new roles.
$manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

// Managers handle payments, so they see the payments report too; only the super administrator
// may log in as another user (owner's decision, 2026-11-18).
hk_role_cap($manager, 'moodle/payment:viewpayments', CAP_ALLOW);
hk_role_cap($manager, 'moodle/user:loginas', CAP_PREVENT);

// Dashboards are laid out centrally: nobody but the super administrator gets "Edit mode" there.
$authenticated = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
hk_role_cap($authenticated, 'moodle/my:manageblocks', CAP_PREVENT);
hk_role_cap($manager, 'moodle/my:manageblocks', CAP_PREVENT);
// Profile pages too: no "Edit mode" switch or "Reset page to default" on a learner's own profile.
hk_role_cap($authenticated, 'moodle/user:manageownblocks', CAP_PREVENT);
hk_role_cap($manager, 'moodle/user:manageownblocks', CAP_PREVENT);

// Personal access tokens (outside programs acting as the user): super administrator only. The
// Moodle app signs in with moodle/webservice:createmobiletoken, which stays allowed.
hk_role_cap($authenticated, 'moodle/api:createtoken', CAP_PREVENT);
hk_role_cap($manager, 'moodle/api:createtoken', CAP_PREVENT);

// Classmates switch (HIKMAH_STUDENT_PEERS, off by default): whether students may see classmate
// lists and profiles and message each other. Teachers stay reachable either way. The messaging
// half lives in local_hikmahaccess (it sets each student's "who can message me" preference).
$allowpeers = hk_env('HIKMAH_STUDENT_PEERS', '0') === '1' ? 1 : 0;
hk_set('allowpeers', $allowpeers, 'local_hikmahaccess');
$student = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$studentdefaults = get_default_capabilities('student');
foreach (['moodle/course:viewparticipants', 'moodle/user:viewdetails'] as $capability) {
    hk_role_cap($student, $capability, $allowpeers ? (int) ($studentdefaults[$capability] ?? CAP_INHERIT) : CAP_PREVENT);
}
if (class_exists('\local_hikmahaccess\peers')) {
    $changedpeers = \local_hikmahaccess\peers::sync_all();
    if ($changedpeers) {
        hk_changed("classmates messaging rule applied to $changedpeers student(s)");
    }
}
// Support sees who is a student or teacher on a course's participants page (else every row says "No roles").
foreach (['student', 'teacher', 'editingteacher'] as $shortname) {
    $target = (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    if (!$DB->record_exists('role_allow_view', ['roleid' => $support, 'allowview' => $target])) {
        core_role_set_view_allowed($support, $target);
        hk_changed("support may view role $shortname");
    }
}
foreach ([$finance, $support] as $target) {
    if (!$DB->record_exists('role_allow_assign', ['roleid' => $manager, 'allowassign' => $target])) {
        core_role_set_assign_allowed($manager, $target);
        hk_changed("manager may assign role $target");
    }
}

// Export for review (dev mounts config/roles writable; on servers the directory is read-only).
$exportdir = '/opt/hikmah/roles';
if (is_dir($exportdir) && is_writable($exportdir)) {
    foreach (['financereviewer', 'support', 'editingteacher', 'teacher'] as $shortname) {
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
        $xml = core_role_preset::get_export_xml($roleid);
        $file = "$exportdir/$shortname.xml";
        if (!file_exists($file) || file_get_contents($file) !== $xml) {
            file_put_contents($file, $xml);
            cli_writeln("  exported $shortname.xml");
        }
    }
}

accesslib_clear_all_caches(true);
hk_done(__FILE__);
