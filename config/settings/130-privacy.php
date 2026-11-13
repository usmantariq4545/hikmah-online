<?php
// 130 — Site policies and data retention (PRD section 11).
//
// The policy texts created here are DRAFTS so that sign-up consent works from day one. They are
// clearly marked as drafts and must be replaced by wording approved by Hikmah (legal and privacy
// review is an open decision in the PRD). Retention periods are the PRD defaults.
require(__DIR__ . '/lib.php');

global $DB;

hk_set('sitepolicyhandler', 'tool_policy');
hk_set('contactdataprotectionofficer', 1, 'tool_dataprivacy');
hk_set('automaticdeletionrequests', 0, 'tool_dataprivacy');
hk_set('showdataretentionsummary', 1, 'tool_dataprivacy');

// ---- Policies -------------------------------------------------------------------------------------
$policies = [
    'Privacy notice (draft)' => [
        \tool_policy\policy_version::TYPE_PRIVACY,
        'How Hikmah Online uses your personal data.',
        '<p><strong>Draft for review. Not yet approved by Hikmah Institute.</strong></p>' .
        '<p>Hikmah Online stores the details you give when you register (name, email address and profile fields), ' .
        'your course activity, grades and attendance, and, for paid courses, the transfer details and receipt you ' .
        'upload. This data is used to run your courses, verify payments, record attendance and issue certificates.</p>' .
        '<p>Receipts can be opened only by Hikmah finance reviewers and are deleted after the retention period. ' .
        'Live class recordings can be watched only by enrolled learners who are signed in. A certificate can be ' .
        'verified by anyone who has its code; verification shows your name, the course and the issue date only.</p>' .
        '<p>You can ask for a copy of your data or for its deletion from your profile page.</p>',
    ],
    'Terms of use (draft)' => [
        \tool_policy\policy_version::TYPE_SITE,
        'The rules for using Hikmah Online.',
        '<p><strong>Draft for review. Not yet approved by Hikmah Institute.</strong></p>' .
        '<p>Your account is personal. Keep your password private and do not share access to courses, live classes ' .
        'or recordings. Submit your own work. A place on a paid course is confirmed only after Hikmah has verified ' .
        'your payment. Hikmah may suspend accounts that break these rules.</p>',
    ],
];
$existing = $DB->get_fieldset_select('tool_policy_versions', 'name', '1 = 1');
foreach ($policies as $name => [$type, $summary, $content]) {
    if (in_array($name, $existing, true)) {
        continue;
    }
    $version = \tool_policy\api::form_policydoc_add((object) [
        'name' => $name,
        'type' => $type,
        'audience' => \tool_policy\policy_version::AUDIENCE_ALL,
        'revision' => 'draft-1',
        'summary_editor' => ['text' => $summary, 'format' => FORMAT_HTML, 'itemid' => 0],
        'content_editor' => ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0],
        'agreementstyle' => \tool_policy\policy_version::AGREEMENTSTYLE_CONSENTPAGE,
        'optional' => \tool_policy\policy_version::AGREEMENT_COMPULSORY,
    ]);
    \tool_policy\api::make_current($version->get('id'));
    hk_changed("policy \"$name\" created");
}

// ---- Retention: purposes and categories -------------------------------------------------------------
$purposes = [
    'Payment receipts' => ['P1Y', 'Transfer receipts and payment review history. Receipt files are deleted by ' .
        'paygw_hikmahtransfer after the retention period; the decision history is kept.'],
    'Live class recordings' => ['P1Y', 'Recordings are stored on the BigBlueButton host; retention is set there ' .
        '(7 days on the free tier).'],
    'Attendance evidence' => ['P1Y', 'Join and leave times used to compute attendance. Removed by ' .
        'local_hikmahattendance after the retention period; the computed result is kept.'],
    'Learning records' => ['P5Y', 'Enrolments, grades, completion and certificates.'],
];
$have = [];
foreach (\tool_dataprivacy\api::get_purposes() as $purpose) {
    $have[$purpose->get('name')] = true;
}
foreach ($purposes as $name => [$period, $description]) {
    if (isset($have[$name])) {
        continue;
    }
    \tool_dataprivacy\api::create_purpose((object) [
        'name' => $name,
        'description' => $description,
        'descriptionformat' => FORMAT_PLAIN,
        'retentionperiod' => $period,
        'protected' => 0,
        'lawfulbases' => 'gdpr_art_6_1_b',
    ]);
    hk_changed("retention purpose \"$name\" created");
}

$categories = [
    'Financial evidence' => 'Receipts and transfer details.',
    'Participation data' => 'Live class participation and attendance.',
    'Academic records' => 'Grades, completion and certificates.',
];
$have = [];
foreach (\tool_dataprivacy\api::get_categories() as $category) {
    $have[$category->get('name')] = true;
}
foreach ($categories as $name => $description) {
    if (isset($have[$name])) {
        continue;
    }
    \tool_dataprivacy\api::create_category((object) [
        'name' => $name,
        'description' => $description,
        'descriptionformat' => FORMAT_PLAIN,
    ]);
    hk_changed("data category \"$name\" created");
}

// Plugin retention periods.
hk_set('retentiondays', 365, 'paygw_hikmahtransfer');
hk_set('maxbytes', 5242880, 'paygw_hikmahtransfer');
hk_set('retentiondays', 365, 'local_hikmahattendance');

hk_done(__FILE__);
