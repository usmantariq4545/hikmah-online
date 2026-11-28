<?php
// 130 — Site policies and data retention (PRD section 11).
//
// Publishes the Privacy Policy, Terms of Use and Refund Policy approved by the owner on 2026-11-27
// (texts in policies/). Retention periods are the PRD defaults.
require(__DIR__ . '/lib.php');

global $DB;

hk_set('sitepolicyhandler', 'tool_policy');
hk_set('contactdataprotectionofficer', 1, 'tool_dataprivacy');
hk_set('automaticdeletionrequests', 0, 'tool_dataprivacy');
hk_set('showdataretentionsummary', 1, 'tool_dataprivacy');

// ---- Policies -------------------------------------------------------------------------------------
// The approved texts (owner, 2026-11-27) live in policies/*.html. To publish a change, edit the file AND raise
// its revision: a new revision becomes the current version and everyone accepts it again at their next sign-in.
// One policy per type; the Refund Policy is the only "other" one. The first run replaces the drafts of
// 2026-11-20 ("Privacy notice (draft)", "Terms of use (draft)") with new versions of the same policies.
$policies = [
    'Privacy Policy' => [\tool_policy\policy_version::TYPE_PRIVACY, '1.2', 'privacy.html',
        'What personal data Hikmah Online collects, who can see it and how long it is kept.'],
    'Terms of Use' => [\tool_policy\policy_version::TYPE_SITE, '1.0', 'terms.html',
        'The rules for using Hikmah Online, its courses and live classes.'],
    'Refund Policy' => [\tool_policy\policy_version::TYPE_OTHER, '1.0', 'refund.html',
        'When and how you can get a refund for a paid course.'],
];
foreach ($policies as $name => [$type, $revision, $file, $summary]) {
    $current = $DB->get_record_sql(
        'SELECT p.id AS policyid, v.name, v.revision
           FROM {tool_policy} p
           JOIN {tool_policy_versions} v ON v.id = p.currentversionid
          WHERE v.type = ?
       ORDER BY p.id',
        [$type],
        IGNORE_MULTIPLE
    );
    if ($current && $current->name === $name && $current->revision === $revision) {
        continue;
    }
    $form = (object) [
        'name' => $name,
        'type' => $type,
        'audience' => \tool_policy\policy_version::AUDIENCE_ALL,
        'revision' => $revision,
        'summary_editor' => ['text' => "<p>$summary</p>", 'format' => FORMAT_HTML, 'itemid' => 0],
        'content_editor' => ['text' => file_get_contents(__DIR__ . "/policies/$file"), 'format' => FORMAT_HTML,
            'itemid' => 0],
        'agreementstyle' => \tool_policy\policy_version::AGREEMENTSTYLE_CONSENTPAGE,
        'optional' => \tool_policy\policy_version::AGREEMENT_COMPULSORY,
    ];
    if ($current) {
        $form->policyid = $current->policyid;
        $version = \tool_policy\api::form_policydoc_update_new($form);
    } else {
        $version = \tool_policy\api::form_policydoc_add($form);
    }
    \tool_policy\api::make_current($version->get('id'));
    hk_changed("policy \"$name\" revision $revision published");
}

// Activity logs are kept 2 years (Privacy Policy, section 3).
hk_set('loglifetime', 730, 'logstore_standard');

// ---- Retention: purposes and categories -------------------------------------------------------------
$purposes = [
    'Payment receipts' => ['P1Y', 'Transfer receipts and payment review history. Receipt files are deleted by ' .
        'paygw_hikmahtransfer after the retention period; the decision history is kept.'],
    'Live class recordings' => ['P1Y', 'Recordings are stored on the BigBlueButton host; retention is set there ' .
        '(7 days on the free tier).'],
    'Attendance evidence' => ['P1Y', 'Join and leave times used to compute attendance. Removed by ' .
        'local_hikmahattendance after the retention period; the computed result is kept.'],
    'Learning records' => ['P5Y', 'Enrollments, grades, completion and certificates.'],
];
$have = [];
foreach (\tool_dataprivacy\api::get_purposes() as $purpose) {
    $have[$purpose->get('name')] = $purpose;
}
foreach ($purposes as $name => [$period, $description]) {
    if (isset($have[$name])) {
        if ($have[$name]->get('description') !== $description) {
            $have[$name]->set('description', $description);
            $have[$name]->update();
            hk_changed("retention purpose \"$name\" description");
        }
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
