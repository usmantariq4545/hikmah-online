<?php
// 110 — Outbound email and notification defaults (OPS-01). The SMTP host, credentials and the
// no-reply address come from the environment through config.php; nothing secret is stored here.
require(__DIR__ . '/lib.php');

global $CFG, $DB;

hk_set('emailfromvia', 1);
hk_set('allowedemaildomains', '');
hk_set('emailsubjectprefix', '');
hk_set('mailnewline', 'LF');
hk_set('messagingallowemailoverride', 0);

// Contact us page (/contact): Moodle's contact form is open to visitors too, behind reCAPTCHA, and goes to
// supportemail. 2 = CONTACT_SUPPORT_ANYONE.
hk_set('supportavailability', 2);

// Email and web notifications are on; each provider's defaults come from its db/messages.php.
foreach (['email', 'popup'] as $processor) {
    $record = $DB->get_record('message_processors', ['name' => $processor]);
    if ($record && !$record->enabled) {
        \core_message\api::update_processor_status($record, 1);
        hk_changed("message output $processor enabled");
    }
}

// Enrolment decisions, grading and certificate notifications are sent by email by default.
$defaults = [
    'message_provider_enrol_apply_application_enabled' => 'email,popup',
    'message_provider_enrol_apply_confirmation_enabled' => 'email,popup',
    'message_provider_enrol_apply_cancelation_enabled' => 'email,popup',
    'message_provider_enrol_apply_waitinglist_enabled' => 'email,popup',
    'message_provider_mod_assign_assign_notification_enabled' => 'email,popup',
    'message_provider_tool_certificate_certificateissued_enabled' => 'email,popup',
];
foreach ($defaults as $name => $value) {
    hk_set($name, $value, 'message');
}

if (empty($CFG->smtphosts) && empty($CFG->noemailever)) {
    cli_writeln('  note: SMTP_HOST is not set; Moodle will try the local PHP mailer, which does not exist in this image');
}

hk_done(__FILE__);
