#!/usr/bin/env bash
# Upgrade after new images are in place: maintenance mode, admin/cli/upgrade.php, settings,
# purge caches, smoke test. Take a backup first (scripts/backup.sh).
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

say "Recreating containers with the current images"
dc up -d
wait_healthy moodle

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
