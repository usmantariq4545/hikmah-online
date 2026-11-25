<?php
// Mails one sample of every Hikmah Online email, using the layout, wording and email code in this checkout (not the
// ones installed on the site), so the owner can approve them before they go live. Run through email-preview.sh, which
// prepends $hkfiles: base64 copies of the files below. Sample people and payments; the course card uses a real
// course (HK_PREVIEW_COURSE, default the first visible one). Nothing on the site changes.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
global $CFG, $SITE, $DB;

$to = getenv('HK_PREVIEW_TO') ?: '';
if (!validate_email($to)) {
    fwrite(STDERR, "Usage: email-preview.sh <email address>\n");
    exit(1);
}
$dir = make_request_directory();
$load = function(string $name) use ($hkfiles, $dir): string {
    file_put_contents("$dir/$name", base64_decode($hkfiles[$name]));
    return "$dir/$name";
};

// Strings from this checkout: plugin language files, then the email wording on top (what 135-strings.php installs).
$emails = require($load('email-strings.php'));
$hkmap = [];
foreach (['theme_hikmah' => 'theme_lang.php', 'paygw_hikmahtransfer' => 'paygw_lang.php'] as $component => $file) {
    $string = [];
    require($load($file));
    $hkmap[$component] = $string;
}
foreach ($emails as $component => $strings) {
    $hkmap[$component] = array_merge($hkmap[$component] ?? [], $strings);
}
$GLOBALS['hkmap'] = $hkmap;

/** get_string() that prefers this checkout's strings. */
function hk_str($id, $component = 'moodle', $a = null, $lang = null) {
    $text = $GLOBALS['hkmap'][$component][$id] ?? null;
    if ($text === null) {
        return get_string($id, $component, $a, $lang);
    }
    if (is_object($a) || is_array($a)) {
        return preg_replace_callback('/\{\$a->(\w+)\}/', fn($m) => (string) (((object) $a)->{$m[1]} ?? $m[0]), $text);
    }
    return $a === null ? $text : str_replace('{$a}', (string) $a, $text);
}

/** string_exists() that knows this checkout's strings. */
function hk_exists($id, $component) {
    return isset($GLOBALS['hkmap'][$component][$id]) || get_string_manager()->string_exists($id, $component);
}

// This checkout's email classes, with string lookups pointed at hk_str()/hk_exists().
foreach (['email.php', 'notifier.php'] as $file) {
    $code = base64_decode($hkfiles[$file]);
    $code = str_replace(['$sm->get_string(', '$sm->string_exists('], ['\hk_str(', '\hk_exists('], $code);
    $code = preg_replace('/(?<![>\w\\\\])get_string\(/', '\hk_str(', $code);
    file_put_contents("$dir/$file", $code);
    require("$dir/$file");
}

$recipient = clone(get_admin());
$recipient->email = $to;
$recipient->mailformat = 1;
$site = $SITE->fullname;
$url = $CFG->wwwroot;

$courseid = (int) (getenv('HK_PREVIEW_COURSE') ?: $DB->get_field_select('course', 'MIN(id)', 'id <> ? AND visible = 1', [SITEID]));
$coursename = format_string($DB->get_field('course', 'fullname', ['id' => $courseid]));

$fill = fn(string $text, $a) => is_object($a)
    ? preg_replace_callback('/\{\$a->(\w+)\}/', fn($m) => (string) ($a->{$m[1]} ?? $m[0]), $text)
    : str_replace('{$a}', (string) $a, $text);
$tpl = preg_replace('/\{\{!.*?\}\}\s*/s', '', base64_decode($hkfiles['email_html.mustache']));
$wrap = fn(string $subject, string $body): string => strtr($tpl, [
    '{{{body}}}' => $body, '{{subject}}' => s($subject), '{{sitefullname}}' => s($site), '{{sitewwwroot}}' => $url,
]);

$user = (object) ['firstname' => 'Aisha', 'username' => 'aisha.khan', 'sitename' => $site, 'link' => "$url/login/index.php",
    'resetminutes' => 30, 'newpassword' => 'Temp-Pass-123', 'signoff' => 'The Hikmah Online team',
    'userfullname' => 'Aisha Khan', 'useremail' => 'aisha@example.com', 'logintime' => userdate(time()),
    'logindevice' => 'Chrome on Windows', 'loginip' => '203.0.113.10', 'changepasswordlink' => "$url/login/change_password.php"];
$c = $emails['core'];
$text = fn(string $s) => text_to_html($s, false, false, true);

// Payment emails go through this checkout's notifier::html(), as real ones will.
$payment = function(string $status, string $key, bool $forreviewer = false) use ($courseid, $coursename, $url) {
    $request = (object) ['id' => 1, 'userid' => 0, 'courseid' => $courseid, 'component' => 'enrol_fee', 'paymentarea' => 'fee',
        'itemid' => 0, 'amount' => 5000, 'currency' => 'PKR', 'method' => 'bank', 'reference' => "HKM-$courseid-21-7f3a",
        'txnid' => 'TRX-88214670', 'paiddate' => time() - DAYSECS, 'status' => $status];
    $a = (object) ['fullname' => 'Aisha Khan', 'item' => $coursename, 'reference' => $request->reference,
        'amount' => 'PKR 5,000', 'remarks' => '', 'count' => 3];
    if ($status === 'rejected') {
        $a->remarks = 'The amount on the receipt does not match the course fee.';
    } else if ($status === 'resubmit') {
        $a->remarks = 'The receipt image is blurry; the transaction ID cannot be read.';
    }
    $rows = \paygw_hikmahtransfer\notifier::summary($request, false, null);
    if ($forreviewer) {
        $rows = [hk_str('msg_learner', 'paygw_hikmahtransfer') => 'Aisha Khan'] + $rows;
    }
    $page = $forreviewer ? 'review.php' : 'status.php';
    $body = hk_str("msg_{$key}_body", 'paygw_hikmahtransfer', $a);
    $html = \paygw_hikmahtransfer\notifier::html($body, $key === 'digest' ? [] : $rows, $a,
        new moodle_url("/payment/gateway/hikmahtransfer/$page"), hk_str('msg_link', 'paygw_hikmahtransfer'),
        $key === 'digest' ? null : $request, null);
    return [hk_str("msg_{$key}_subject", 'paygw_hikmahtransfer', $a), $html];
};

$apply = fn(string $s) => strtr($s, ['{firstname}' => 'Aisha', '{lastname}' => 'Khan', '{username}' => 'aisha.khan',
    '{content}' => $coursename, '{timeend}' => '']);
$ea = $emails['_enrol_apply_settings'];
$cert = (object) ['fullname' => 'Aisha Khan', 'url' => "$url/admin/tool/certificate/my.php"];
$assign = (object) ['username' => 'Ustadh Bilal', 'assignment' => 'Week 2: Makharij practice', 'url' => "$url/my/"];

$list = [
    ['Sign-up confirmation', $fill($c['emailconfirmationsubject'], $site), $text($fill($c['emailconfirmation'], $user))],
    ['Password reset', $fill($c['emailresetconfirmationsubject'], $site), $text($fill($c['emailresetconfirmation'], $user))],
    ['Password change link', $fill($c['emailpasswordchangeinfosubject'], $site),
        $text($fill($c['emailpasswordchangeinfo'], $user))],
    ['Account created by staff', "$site: New user account", $text($fill($c['newusernewpasswordtext'], $user))],
    ['New sign-in alert', $fill($c['newloginnotificationtitle'], $site), $fill($c['newloginnotificationbodyfull'], $user)],
    array_merge(['Payment: receipt received'], $payment('pending', 'submitted')),
    array_merge(['Payment: approved'], $payment('approved', 'approved')),
    array_merge(['Payment: not approved'], $payment('rejected', 'rejected')),
    array_merge(['Payment: send receipt again'], $payment('resubmit', 'resubmit')),
    array_merge(['Reviewer: new receipt'], $payment('pending', 'new', true)),
    array_merge(['Reviewer: daily digest'], $payment('pending', 'digest', true)),
    ['Enrollment request approved', $apply($ea['confirmmailsubject']), $apply($ea['confirmmailcontent'])],
    ['Enrollment request not approved', $apply($ea['cancelmailsubject']), $apply($ea['cancelmailcontent'])],
    ['Assignment feedback', 'Feedback: Week 2: Makharij practice', $fill($emails['mod_assign']['feedbackavailablehtml'], $assign)],
    ['Certificate ready', $emails['tool_certificate']['notificationsubjectcertificateissued'],
        $fill($emails['tool_certificate']['notificationmsgcertificateissued'], $cert)],
];

$from = core_user::get_noreply_user();
$n = count($list);
foreach ($list as $i => [$name, $subject, $body]) {
    $subject = '[Preview ' . ($i + 1) . "/$n · $name] $subject";
    $ok = email_to_user($recipient, $from, $subject, html_to_text($body), $wrap($subject, $body));
    echo ($ok ? 'sent  ' : 'FAILED') . "  $subject\n";
}
