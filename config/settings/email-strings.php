<?php
// Wording of the emails Hikmah Online sends (owner review 2026-11-25). Used by 135-strings.php (same folder), which writes
// them into Moodle's local language pack, and by scripts/dev/email-preview.php, which mails a sample of each.
// Keep every {$a->...} placeholder of the original string. Buttons carry their style inline ($btn), because mail
// programs drop most CSS; the outer layout is theme_hikmah/templates/core/email_html.mustache.
// Payment texts are plain text: the payment plugin adds the summary table, button, course card and sign-off itself.

$signoff = "\n\nThe Hikmah Online team";
// Same look as theme_hikmah\local\email::BUTTON_STYLE.
$btn = 'class="hk-btn" style="display:inline-block; background:#ffe000; color:#1a1a1a; text-decoration:none; ' .
    'font-weight:600; font-size:14px; line-height:18px; padding:9px 20px; border-radius:999px;"';
$button = fn(string $href, string $label) => '<p style="margin:24px 0;"><a ' . $btn . ' href="' . $href . '">' . $label .
    '</a></p>';

return [
    'core' => [
        'emailconfirmationsubject' => 'Confirm your {$a} account',
        'emailconfirmation' => "Assalamu alaikum {\$a->firstname},\n\n" .
            "Welcome to {\$a->sitename}! Please confirm your email address to finish creating your account:" .
            $button('{$a->link}', 'Confirm my account') .
            "If you did not sign up, you can ignore this email." . $signoff,

        'emailresetconfirmationsubject' => 'Reset your {$a} password',
        'emailresetconfirmation' => "Assalamu alaikum {\$a->firstname},\n\n" .
            "We received a request to reset the password for your account ({\$a->username})." .
            $button('{$a->link}', 'Set a new password') .
            "This link works for {\$a->resetminutes} minutes. If you did not ask for this, you can ignore this email: " .
            "your password stays the same." . $signoff,

        'emailpasswordchangeinfosubject' => 'Change your {$a} password',
        'emailpasswordchangeinfo' => "Assalamu alaikum {\$a->firstname},\n\n" .
            "We received a request to change the password for your account ({\$a->username})." .
            $button('{$a->link}', 'Change my password') .
            "If you did not ask for this, you can ignore this email." . $signoff,

        'newusernewpasswordtext' => "Assalamu alaikum {\$a->firstname},\n\n" .
            "An account has been created for you at {\$a->sitename}.\n\n" .
            "Username: {\$a->username}\nTemporary password: {\$a->newpassword}\n\n" .
            "You will be asked to choose your own password when you first sign in." .
            $button('{$a->link}', 'Sign in') . "{\$a->signoff}",

        'newloginnotificationtitle' => 'New sign-in to your {$a} account',
        'newloginnotificationbodyfull' => '<p>Assalamu alaikum {$a->userfullname},</p>' .
            '<p>Your {$a->sitename} account was just used to sign in on a new device.</p>' .
            '<ul><li>Account: {$a->username} ({$a->useremail})</li><li>When: {$a->logintime}</li>' .
            '<li>Device: {$a->logindevice}</li><li>IP address: {$a->loginip}</li></ul>' .
            '<p>If this was you, there is nothing to do. If it was not you, change your password now:</p>' .
            $button('{$a->changepasswordlink}', 'Change my password') .
            '<p>The Hikmah Online team</p>',
    ],

    'paygw_hikmahtransfer' => [
        'msg_link' => 'View my payment',
        'msg_submitted_subject' => 'We received your receipt: {$a->item}',
        'msg_submitted_body' => "Assalamu alaikum {\$a->fullname},\n\n" .
            "Thank you! We have received your transfer receipt. Our team will check it, usually within one working day, " .
            "and email you as soon as it is verified.",
        'msg_approved_subject' => 'Payment approved: you can now start {$a->item}',
        'msg_approved_body' => "Assalamu alaikum {\$a->fullname},\n\n" .
            "Your payment has been verified and the course is now open for you. " .
            "We wish you a blessed and beneficial journey of learning.",
        'msg_rejected_subject' => 'We could not verify your payment: {$a->item}',
        'msg_rejected_body' => "Assalamu alaikum {\$a->fullname},\n\n" .
            "We could not verify your payment. If you think this is a mistake, please contact us and include your " .
            "reference number.",
        'msg_resubmit_subject' => 'Please send your receipt again: {$a->item}',
        'msg_resubmit_body' => "Assalamu alaikum {\$a->fullname},\n\n" .
            "We need a new receipt for your payment. Please open your payment and upload the receipt again.",
        'msg_new_subject' => 'New receipt to review: {$a->reference}',
        'msg_new_body' => "A new transfer receipt is waiting for review.",
        'msg_digest_subject' => '{$a->count} receipt(s) waiting for review',
        'msg_digest_body' => "{\$a->count} transfer receipt(s) are waiting for review in the payment queue.",
    ],

    'mod_assign' => [
        'feedbackavailablehtml' => '<p>Assalamu alaikum,</p><p>{$a->username} has given feedback on your assignment ' .
            '"{$a->assignment}".</p>' . $button('{$a->url}', 'See the feedback'),
        'feedbackavailabletext' => "Assalamu alaikum,\n\n{\$a->username} has given feedback on your assignment " .
            "\"{\$a->assignment}\".\n\nSee the feedback: {\$a->url}",
        'feedbackavailablesmall' => '{$a->username} gave feedback on "{$a->assignment}"',
    ],

    'tool_certificate' => [
        'notificationsubjectcertificateissued' => 'Your certificate is ready',
        'notificationmsgcertificateissued' => '<p>Assalamu alaikum {$a->fullname},</p>' .
            '<p>Congratulations, and may Allah bless your learning! Your certificate is ready to view and download.</p>' .
            $button('{$a->url}', 'View my certificates') . '<p>The Hikmah Online team</p>',
    ],

    // enrol_apply (approval enrolment) keeps its texts in plugin settings, not language strings.
    // Placeholders: {firstname} {lastname} {username} {content} (course name) {timeend}.
    '_enrol_apply_settings' => [
        'confirmmailsubject' => 'You are enrolled: {content}',
        'confirmmailcontent' => '<p>Assalamu alaikum {firstname},</p><p>Your request to join "{content}" has been ' .
            'approved. The course is now open for you in Hikmah Online.</p><p>The Hikmah Online team</p>',
        'cancelmailsubject' => 'Your enrollment request: {content}',
        'cancelmailcontent' => '<p>Assalamu alaikum {firstname},</p><p>We are sorry: your request to join "{content}" ' .
            'was not approved this time. Please contact us if you have any questions.</p><p>The Hikmah Online team</p>',
    ],
];
