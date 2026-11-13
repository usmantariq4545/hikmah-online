#!/usr/bin/env bash
# Restore a backup set made by scripts/backup.sh into the current environment.
#   scripts/restore.sh backups/dev/20261113-120000
# This REPLACES the database and moodledata of the target environment. It asks for confirmation
# unless RESTORE_CONFIRM=yes is set.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

SRC="${1:?Usage: scripts/restore.sh <backup directory>}"
[ -d "$SRC" ] || SRC="$ROOT_DIR/$SRC"
[ -f "$SRC/database.dump" ] && [ -f "$SRC/moodledata.tgz" ] || { echo "Not a backup set: $SRC" >&2; exit 1; }

say "Verifying checksums"
( cd "$SRC" && sha256sum -c SHA256SUMS )

if [ "${RESTORE_CONFIRM:-}" != "yes" ]; then
    printf 'This replaces the %s database and moodledata with %s. Type "restore" to continue: ' "$HIKMAH_ENV" "$SRC"
    read -r answer
    [ "$answer" = "restore" ] || { echo "Cancelled."; exit 1; }
fi

say "Starting database and stopping web and cron"
dc up -d postgres redis
wait_healthy postgres
dc stop nginx moodle cron >/dev/null 2>&1 || true

say "Restoring database"
dc exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" --if-exists "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" "$POSTGRES_DB"'
dc exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --role="$POSTGRES_USER"' < "$SRC/database.dump"

say "Restoring moodledata"
dc run --rm --no-deps -T -u root --entrypoint sh moodle -c '
    find /var/moodledata -mindepth 1 -maxdepth 1 -exec rm -rf {} + &&
    tar -xzf - -C /var/moodledata &&
    mkdir -p /var/moodledata/muc &&
    chown -R www-data:www-data /var/moodledata' < "$SRC/moodledata.tgz"
if [ -s "$SRC/muc-config.php" ]; then
    dc run --rm --no-deps -T -u root --entrypoint sh moodle -c \
        'cat > /var/moodledata/muc/config.php && chown www-data:www-data /var/moodledata/muc/config.php' < "$SRC/muc-config.php"
fi

say "Starting services"
dc exec -T redis redis-cli FLUSHALL >/dev/null
dc up -d
wait_healthy moodle

say "Purging caches"
moodle_cli purge_caches.php
# A backup taken with --maintenance carries maintenance mode inside it; switch it off again.
moodle_cli maintenance.php --disable

"$SCRIPTS_DIR/smoke-test.sh"
echo "Restore complete."
