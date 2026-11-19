#!/usr/bin/env bash
# Upgrade after new images are in place: maintenance mode, admin/cli/upgrade.php, settings,
# purge caches, smoke test. Take a backup first (scripts/backup.sh).
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

say "Recreating containers with the current images"
dc up -d
wait_healthy moodle

# Requests cut off by the restart leave their session locks behind; clear them so nobody's
# session hangs ("Cannot obtain session lock").
dc exec -T redis sh -c 'redis-cli --scan --pattern "hikmah_*_sess_*.lock" | xargs -r redis-cli del' >/dev/null

# The Caddyfile is mounted, not built in: reload it so changes take effect (no downtime).
if dc ps --services --status running | grep -qx caddy; then
    say "Reloading Caddy"
    dc exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
fi

say "Maintenance mode on"
moodle_cli maintenance.php --enable

trap 'moodle_cli maintenance.php --disable || true' EXIT

say "Upgrading database"
moodle_cli upgrade.php --non-interactive

say "Applying settings"
"$SCRIPTS_DIR/configure.sh"

say "Maintenance mode off"
moodle_cli maintenance.php --disable
trap - EXIT

"$SCRIPTS_DIR/smoke-test.sh"
