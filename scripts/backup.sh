#!/usr/bin/env bash
# Backup: PostgreSQL dump (custom format) + moodledata (without caches, sessions, temp, trash),
# with checksums. Archives land in ./backups/<env>/ and, when BACKUP_REMOTE is set and rclone is
# installed, are copied off-host. Keeps 7 daily, 4 weekly and 6 monthly sets, here and off-host.
#
#   scripts/backup.sh                 nightly, site stays up
#   scripts/backup.sh --maintenance   consistent snapshot (before upgrades and go-live)
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

MAINTENANCE=0
[ "${1:-}" = "--maintenance" ] && MAINTENANCE=1

STAMP="$(date -u +%Y%m%d-%H%M%S)"
DEST="$ROOT_DIR/backups/$HIKMAH_ENV/$STAMP"
mkdir -p "$DEST"
umask 077

if [ "$MAINTENANCE" = "1" ]; then
    say "Maintenance mode on"
    moodle_cli maintenance.php --enable
    trap 'moodle_cli maintenance.php --disable || true' EXIT
fi

say "Dumping database"
dc exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$DEST/database.dump"

say "Archiving moodledata"
# The site stays up during nightly and deploy backups, so files can change while tar reads them.
# GNU tar then exits 1 ("some files differ") but the archive is complete; only 2+ is a failure.
status=0
dc exec -T -u www-data moodle tar -czf - -C /var/moodledata --warning=no-file-changed \
    --exclude=./cache --exclude=./localcache --exclude=./sessions --exclude=./temp \
    --exclude=./trashdir --exclude=./lock --exclude=./muc . > "$DEST/moodledata.tgz" || status=$?
[ "$status" -le 1 ] || { echo "moodledata archive failed (tar exit $status)" >&2; exit "$status"; }
gzip -t "$DEST/moodledata.tgz"

# The MUC configuration is small and needed to keep the Redis mapping after a restore.
dc exec -T -u www-data moodle sh -c 'cat /var/moodledata/muc/config.php 2>/dev/null || true' > "$DEST/muc-config.php"

cp "$ROOT_DIR/versions.lock" "$DEST/versions.lock"
( cd "$DEST" && sha256sum database.dump moodledata.tgz muc-config.php versions.lock > SHA256SUMS )

if [ "$MAINTENANCE" = "1" ]; then
    moodle_cli maintenance.php --disable
    trap - EXIT
fi

ls -lh "$DEST"

REMOTE="$(env_value BACKUP_REMOTE || true)"
if [ -n "$REMOTE" ]; then
    if command -v rclone >/dev/null 2>&1; then
        say "Copying off-host to $REMOTE"
        rclone copy "$DEST" "$REMOTE/$HIKMAH_ENV/$STAMP"
    else
        echo "BACKUP_REMOTE is set but rclone is not installed; backup stayed on this host." >&2
    fi
else
    echo "Note: BACKUP_REMOTE is not set. This backup exists only on this machine."
fi

# Retention: keep the newest 7, plus the first of each of the last 4 ISO weeks and 6 months.
# Reads set names (one per line) and prints the ones to delete. The same rule runs here and on
# the off-host copy, so both hold the same sets.
expired_sets() {
    local all keep weekly monthly
    all="$(grep -E '^20[0-9]{6}-[0-9]{6}$' | sort)"
    [ -n "$all" ] || return 0
    keep="$(printf '%s\n' "$all" | sort -r | head -n 7)"
    weekly="$(printf '%s\n' "$all" | awk '{ cmd = "date -u -d " substr($1,1,8) " +%G%V"; cmd | getline w; close(cmd); if (!(w in seen)) { seen[w] = 1; print $1 } }' | tail -n 4)"
    monthly="$(printf '%s\n' "$all" | awk '{ m = substr($1,1,6); if (!(m in seen)) { seen[m] = 1; print $1 } }' | tail -n 6)"
    printf '%s\n' "$all" | grep -vxF -f <(printf '%s\n%s\n%s\n%s\n' "$keep" "$weekly" "$monthly" "$STAMP") || true
}

say "Applying retention (7 daily, 4 weekly, 6 monthly)"
cd "$ROOT_DIR/backups/$HIKMAH_ENV"
for dir in $(ls -1d 20* 2>/dev/null | expired_sets); do
    rm -rf "$dir"
    echo "removed old backup $dir"
done

if [ -n "$REMOTE" ] && command -v rclone >/dev/null 2>&1; then
    # Only prune when the listing works and still holds tonight's set; a failed or partial listing
    # must never lead to deleting the off-host copies.
    if remote_sets="$(rclone lsf --dirs-only "$REMOTE/$HIKMAH_ENV/" | sed 's#/$##')" \
        && printf '%s\n' "$remote_sets" | grep -qx "$STAMP"; then
        for dir in $(printf '%s\n' "$remote_sets" | expired_sets); do
            rclone purge "$REMOTE/$HIKMAH_ENV/$dir"
            echo "removed old off-host backup $dir"
        done
    else
        echo "Could not list $REMOTE/$HIKMAH_ENV; off-host retention skipped." >&2
    fi
fi

echo "Backup complete: backups/$HIKMAH_ENV/$STAMP"

# Heartbeat for outside monitoring: a failed backup exits above, so the ping goes missing and alerts.
PING="$(env_value HEALTHCHECK_BACKUP_URL || true)"
if [ -n "$PING" ]; then
    curl -fsS -m 10 --retry 3 -o /dev/null "$PING" || echo "Backup heartbeat ping failed." >&2
fi
