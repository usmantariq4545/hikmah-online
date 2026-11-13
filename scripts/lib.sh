#!/usr/bin/env bash
# Shared helpers for the Hikmah Online scripts. Source it: . "$(dirname "$0")/lib.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPTS_DIR/.." && pwd)"
HIKMAH_ENV="${HIKMAH_ENV:-dev}"
export HIKMAH_ENV

dc() {
    "$SCRIPTS_DIR/dc.sh" "$@"
}

# Run a command in the moodle container as the web user (never as root).
moodle_exec() {
    dc exec -T -u www-data -w /var/www/moodle moodle "$@"
}

# Run a Moodle CLI script: moodle_cli purge_caches.php
moodle_cli() {
    script="$1"; shift
    moodle_exec php "admin/cli/$script" "$@"
}

# Value of a variable from the active env file (no shell evaluation of the file).
env_value() {
    grep -E "^$1=" "$ROOT_DIR/.env.$HIKMAH_ENV" | tail -n 1 | cut -d= -f2-
}

is_installed() {
    moodle_exec php -r '
        define("CLI_SCRIPT", true);
        define("ABORT_AFTER_CONFIG", true);
        require "/var/www/moodle/config.php";
        $dsn = "pgsql:host={$CFG->dbhost};dbname={$CFG->dbname}" . (empty($CFG->dboptions["dbport"]) ? "" : ";port=" . $CFG->dboptions["dbport"]);
        try {
            $pdo = new PDO($dsn, $CFG->dbuser, $CFG->dbpass);
            $ok = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = \x27{$CFG->prefix}config\x27")->fetchColumn();
            exit($ok ? 0 : 1);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(2);
        }
    ' >/dev/null 2>&1
}

wait_healthy() {
    service="$1"; tries="${2:-60}"
    for _ in $(seq 1 "$tries"); do
        status="$(dc ps --format '{{.Health}}' "$service" 2>/dev/null | head -n 1)"
        if [ "$status" = "healthy" ]; then
            return 0
        fi
        sleep 3
    done
    echo "Service $service did not become healthy." >&2
    return 1
}

say() {
    printf '\n==> %s\n' "$*"
}
