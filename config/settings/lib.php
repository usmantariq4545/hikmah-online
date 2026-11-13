<?php
// Hikmah Online — shared helpers for the settings-as-code scripts (build guide section 5).
// Every script is idempotent: a value is only written when it differs, and every write is
// printed, so a second run of scripts/configure.sh prints "0 change(s)" for each file.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

// Settings scripts act as the site administrator.
\core\session\manager::set_user(get_admin());

/** @var int Number of changes made by the running script. */
$GLOBALS['hkchanges'] = 0;

/**
 * Read an environment variable.
 *
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function hk_env(string $name, $default = null) {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

/**
 * Record and print one change.
 *
 * @param string $what
 */
function hk_changed(string $what): void {
    $GLOBALS['hkchanges']++;
    cli_writeln('  changed: ' . $what);
}

/**
 * Set a config value if it differs from what is stored.
 *
 * @param string $name
 * @param mixed $value
 * @param string|null $plugin component, or null for core
 * @param bool $secret do not print the value
 */
function hk_set(string $name, $value, ?string $plugin = null, bool $secret = false): void {
    $current = get_config($plugin ?? 'core', $name);
    if ($current !== false && (string) $current === (string) $value) {
        return;
    }
    set_config($name, $value, $plugin);
    hk_changed(($plugin ? $plugin . ' | ' : '') . $name . ' = ' . ($secret ? '(hidden)' : var_export($value, true)));
}

/**
 * Print the summary line for a script.
 *
 * @param string $script
 */
function hk_done(string $script): void {
    cli_writeln(basename($script) . ': ' . $GLOBALS['hkchanges'] . ' change(s)');
}

/**
 * Allow, prevent or remove a capability for a role in the system context, if it differs.
 *
 * @param int $roleid
 * @param string $capability
 * @param int $permission CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT or CAP_INHERIT
 */
function hk_role_cap(int $roleid, string $capability, int $permission): void {
    global $DB;
    if (!get_capability_info($capability)) {
        cli_writeln('  skipped unknown capability: ' . $capability);
        return;
    }
    $context = context_system::instance();
    $current = $DB->get_field('role_capabilities', 'permission',
        ['roleid' => $roleid, 'capability' => $capability, 'contextid' => $context->id]);
    $current = $current === false ? CAP_INHERIT : (int) $current;
    if ($current === $permission) {
        return;
    }
    assign_capability($capability, $permission, $roleid, $context->id, true);
    hk_changed("role $roleid: $capability => $permission");
}
