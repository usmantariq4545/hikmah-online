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

# Purging the JavaScript cache makes every browser download Moodle's whole script bundle again
# (about 750 KB), so deploy-live.sh passes HIKMAH_PURGE_JS=0 when no JavaScript changed.
purgejs=true
[ "${HIKMAH_PURGE_JS:-1}" = "0" ] && purgejs=false
moodle_exec php -r "define('CLI_SCRIPT', true); require '/var/www/moodle/config.php';
    purge_caches(['muc' => true, 'courses' => true, 'theme' => true, 'lang' => true, 'js' => $purgejs,
        'template' => true, 'filter' => true, 'other' => true]);" >/dev/null
echo "Caches purged (JavaScript: $purgejs)."
exit "$failed"
