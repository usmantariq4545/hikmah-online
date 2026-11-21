<?php
// 020 — Passwords, lockout, cookies, sessions, antivirus, upload limits.
require(__DIR__ . '/lib.php');

global $CFG;

// Password policy.
hk_set('passwordpolicy', 1);
hk_set('minpasswordlength', 10);
hk_set('minpassworddigits', 1);
hk_set('minpasswordlower', 1);
hk_set('minpasswordupper', 1);
hk_set('minpasswordnonalphanum', 0);
hk_set('maxconsecutiveidentchars', 3);
hk_set('passwordreuselimit', 3);
hk_set('pwchangelogout', 1);

// Login rate limiting.
hk_set('lockoutthreshold', 10);
hk_set('lockoutwindow', 1800);
hk_set('lockoutduration', 1800);
hk_set('protectusernames', 1);

// Cookies and sessions.
hk_set('cookiesecure', strpos($CFG->wwwroot, 'https://') === 0 ? 1 : 0);
hk_set('cookiehttponly', 1);
hk_set('sessiontimeout', 4 * HOURSECS);
hk_set('sessiontimeoutwarning', 20 * MINSECS);

// Content safety.
hk_set('allowobjectembed', 0);
hk_set('enabletrusttext', 0);
hk_set('allowframembedding', 0);
hk_set('profilesforenrolledusersonly', 1);

// Uploads: 100 MB site limit (matches Nginx and PHP); receipts have their own 5 MB limit.
hk_set('maxbytes', 104857600);

// ClamAV over TCP. An upload is refused when the scanner cannot be reached.
hk_set('antiviruses', 'clamav');
hk_set('runningmethod', 'tcpsocket', 'antivirus_clamav');
hk_set('tcpsockethost', 'clamav', 'antivirus_clamav');
hk_set('tcpsocketport', 3310, 'antivirus_clamav');
hk_set('clamfailureonupload', 'actlikevirus', 'antivirus_clamav');
hk_set('tries', 2, 'antivirus_clamav');

// Two-step sign-in (owner, 2026-11-21 security review) for site administrators and the site-wide staff
// roles: a code from an authenticator app. Everyone else passes through the "role" factor (it gives full
// points to users without those roles). Staff get 7 days' grace and are asked to set it up at sign-in.
// Lost phone: an administrator resets that person's factor (admin/tool/mfa/reset_factor.php). The administrator
// locked out: on the server, php admin/cli/cfg.php --component=tool_mfa --name=enabled --set=0, then sign in.
global $DB;
$staffroles = ['admin'];
foreach (['manager', 'financereviewer', 'support'] as $shortname) {
    if ($roleid = $DB->get_field('role', 'id', ['shortname' => $shortname])) {
        $staffroles[] = $roleid;
    }
}
hk_set('enabled', 1, 'factor_totp');
hk_set('weight', 100, 'factor_totp');
hk_set('enabled', 1, 'factor_role');
hk_set('weight', 100, 'factor_role');
hk_set('roles', implode(',', $staffroles), 'factor_role');
hk_set('enabled', 1, 'factor_grace');
hk_set('weight', 100, 'factor_grace');
hk_set('graceperiod', 7 * DAYSECS, 'factor_grace');
hk_set('forcesetup', 1, 'factor_grace');
hk_set('enabled', 0, 'factor_email'); // Needs outgoing email, which is not set up yet.
hk_set('factor_order', 'totp,grace,role', 'tool_mfa');
hk_set('enabled', 1, 'tool_mfa');

// Alerts: the main administrator is told about repeated failed sign-ins (10 from one address or account),
// by email once outgoing email is set up and in Moodle's notifications until then.
hk_set('notifyloginfailures', get_admin()->username);
hk_set('notifyloginthreshold', 10);

hk_done(__FILE__);
