#!/usr/bin/env bash
# Applies config/settings/*.php in order. Every script is idempotent: run this twice and the
# second run reports "0 change(s)" for every file.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

failed=0
for file in "$ROOT_DIR"/config/settings/[0-9][0-9][0-9]-*.php; do
    name="$(basename "$file")"
    if ! moodle_exec php "/opt/hikmah/settings/$name"; then
        echo "FAILED: $name" >&2
        failed=1
    fi
done

moodle_cli purge_caches.php >/dev/null
echo "Caches purged."
exit "$failed"
