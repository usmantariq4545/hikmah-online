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

hk_done(__FILE__);
