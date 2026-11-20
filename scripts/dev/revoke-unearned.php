<?php
// Demo accounts only: revoke certificates that were issued before the certificate rules existed to
// demo learners who do not meet them (owner's decision, 2026-11-20). Real learners' certificates are
// never touched. Run once inside the moodle container:
//   php revoke-unearned.php           (lists what would be revoked)
//   php revoke-unearned.php --apply   (revokes)

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['apply' => false]);
\core\session\manager::set_user(get_admin());

$issues = $DB->get_records_sql("SELECT i.id, i.userid, i.courseid, i.templateid, u.username
                                  FROM {tool_certificate_issues} i
                                  JOIN {user} u ON u.id = i.userid
                                 WHERE " . $DB->sql_like('u.username', '?') . " AND i.courseid > 0", ['demo.%']);
foreach ($issues as $issue) {
    $certificates = get_fast_modinfo($issue->courseid, $issue->userid)->get_instances_of('coursecertificate');
    $earned = false;
    foreach ($certificates as $cm) {
        $info = new \core_availability\info_module($cm);
        $reason = '';
        $earned = $earned || $info->is_available($reason, false, $issue->userid);
    }
    if ($earned) {
        continue;
    }
    cli_writeln(($options['apply'] ? 'Revoked' : 'Would revoke') . " certificate {$issue->id} of {$issue->username} in course {$issue->courseid}");
    if ($options['apply']) {
        \tool_certificate\template::instance((int) $issue->templateid)->revoke_issue((int) $issue->id);
    }
}
