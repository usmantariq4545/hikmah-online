<?php
// Hikmah Online — Moodle configuration. Every value comes from environment variables
// (see .env.example). No secrets live in Git or image layers.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$hikmahenv = static function (string $name, $default = null) {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
};

$siteenv = $hikmahenv('SITE_ENV', 'dev'); // One of dev, staging, prod.

// Database (PostgreSQL 17, private network only).
$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = $hikmahenv('DB_HOST', 'postgres');
$CFG->dbname    = $hikmahenv('DB_NAME', 'moodle');
$CFG->dbuser    = $hikmahenv('DB_USER', 'moodle');
$CFG->dbpass    = $hikmahenv('DB_PASSWORD', '');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => false,
    'dbsocket'  => false,
    'dbport'    => $hikmahenv('DB_PORT', ''),
];

$CFG->wwwroot       = rtrim($hikmahenv('MOODLE_WWWROOT', 'http://localhost:8080'), '/');
$CFG->dataroot      = '/var/moodledata';
$CFG->localcachedir = '/var/localcache';
$CFG->directorypermissions = 02770;
$CFG->admin = 'admin';
// Nginx sends unknown paths to /r.php (docker/nginx/default.conf.template), so Moodle's router is set up.
$CFG->routerconfigured = true;
// Clean links: local_hikmahurls prints links without ".php" and courses by name; Nginx maps them back.
// CLEAN_URLS=0 switches it off (every ".php" link keeps working either way).
if ($hikmahenv('CLEAN_URLS', '1') !== '0' && is_file(__DIR__ . '/public/local/hikmahurls/classes/rewriter.php')) {
    $CFG->urlrewriteclass = '\local_hikmahurls\rewriter';
}

// Sessions in Redis.
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = $hikmahenv('REDIS_HOST', 'redis');
$CFG->session_redis_port = (int) $hikmahenv('REDIS_PORT', 6379);
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'hikmah_' . $siteenv . '_sess_';
$CFG->session_redis_acquire_lock_timeout = 120;
// A lock outlives its request only if PHP died mid-request (e.g. a deploy restart); just over
// max_execution_time (300 s) so such a session recovers in minutes, not two hours.
$CFG->session_redis_lock_expire = 360;

// Files are handed to Nginx after Moodle's access check.
$CFG->xsendfile = 'X-Accel-Redirect';
$CFG->xsendfilealiases = ['/dataroot/' => $CFG->dataroot];

// TLS ends at the reverse proxy on staging and production.
if ($siteenv !== 'dev' || $hikmahenv('MOODLE_SSLPROXY', '0') === '1') {
    if (str_starts_with($CFG->wwwroot, 'https://')) {
        $CFG->sslproxy = true;
    }
}

// Outbound email.
$smtphost = $hikmahenv('SMTP_HOST');
if ($smtphost) {
    $CFG->smtphosts  = $smtphost . ':' . $hikmahenv('SMTP_PORT', '587');
    $CFG->smtpuser   = $hikmahenv('SMTP_USER', '');
    $CFG->smtppass   = $hikmahenv('SMTP_PASSWORD', '');
    $CFG->smtpsecure = $hikmahenv('SMTP_SECURE', '');
    $CFG->noreplyaddress = $hikmahenv('SMTP_NOREPLY', 'no-reply@example.invalid');
} else if ($siteenv === 'dev') {
    $CFG->noemailever = true;
}

$CFG->forced_plugin_settings = [
    'theme_hikmah' => ['siteenv' => $siteenv],
];

if ($siteenv === 'staging') {
    // Only allow-listed addresses receive real mail; everything else goes to the divert inbox.
    $CFG->divertallemailsto = $hikmahenv('STAGING_MAIL_DIVERT', 'staging-inbox@example.invalid');
    $CFG->divertallemailsexcept = $hikmahenv('STAGING_MAIL_ALLOWLIST', '');
}

if ($siteenv === 'dev') {
    // Developer debugging makes Moodle rescan every plugin folder on each request (about 180 ms
    // per request on the laptop, and a page makes around a hundred). It is therefore opt-in:
    // set MOODLE_DEBUG=developer in .env.dev while writing code, leave it unset for testing.
    $CFG->debug = $hikmahenv('MOODLE_DEBUG', '') === 'developer' ? 32767 : 0;
    $CFG->debugdisplay = 0;
    $CFG->cachejs = true;
    // PHPUnit (docker compose dev only).
    $CFG->phpunit_prefix = 'phpu_';
    $CFG->phpunit_dataroot = '/var/phpunitdata';
} else {
    $CFG->debug = 0;
    $CFG->debugdisplay = 0;
    $CFG->preventexecpath = true;
    $CFG->disableupdateautodeploy = true;
}

$CFG->upgradekey = $hikmahenv('MOODLE_UPGRADE_KEY', null);

require_once(__DIR__ . '/lib/setup.php');
