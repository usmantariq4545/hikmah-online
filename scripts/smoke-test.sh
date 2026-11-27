#!/usr/bin/env bash
# Smoke test after every deploy: key pages answer, a static asset is served, the Router works,
# receipts are not public, and cron ran in the last five minutes.
set -uo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

BASE="${SMOKE_URL:-$(env_value MOODLE_WWWROOT)}"
fail=0

check() {
    label="$1"; url="$2"; expected="$3"
    code="$(curl -k -s -o /dev/null -w '%{http_code}' --max-time 60 "$url" || echo 000)"
    if [ "$code" = "$expected" ]; then
        printf '  ok    %-34s %s\n' "$label" "$code"
    else
        printf '  FAIL  %-34s got %s, expected %s  (%s)\n' "$label" "$code" "$expected" "$url"
        fail=1
    fi
}

echo "Smoke test against $BASE"
check "home page"                 "$BASE/?redirect=0"                               200
check "login page"                "$BASE/login/index.php"                           200
check "catalog"                   "$BASE/theme/hikmah/catalog.php"                  200
check "certificate verification"  "$BASE/theme/hikmah/verify.php"                   200
check "FAQ"                       "$BASE/theme/hikmah/faq.php"                      200
check "static asset (favicon)"    "$BASE/theme/hikmah/pix/favicon.svg"              200
check "theme CSS via Router"      "$BASE/theme/styles.php/hikmah/1/all"             200
check "clean link: login"         "$BASE/login/"                                   200
check "clean link: catalog"       "$BASE/courses"                                  200
check "clean link: FAQ"           "$BASE/faq"                                      200
check "clean link: script"        "$BASE/login/forgot_password"                    200
check "dotfile is hidden"         "$BASE/.git/config"                               404
# A receipt URL must never be served to an anonymous visitor (redirects to login).
check "receipt needs login"       "$BASE/pluginfile.php/1/paygw_hikmahtransfer/receipt/1/receipt.png" 303
check "review queue needs login"  "$BASE/payment/gateway/hikmahtransfer/review.php" 303

# Right after a long deploy cron has been paused for a while; give it up to 3 minutes to run again.
for attempt in $(seq 1 13); do
    last="$(moodle_exec php -r '
        define("CLI_SCRIPT", true);
        require "/var/www/moodle/config.php";
        echo (int) $DB->get_field_sql("SELECT MAX(lastruntime) FROM {task_scheduled}");
    ' 2>/dev/null | tail -n 1)"
    now="$(date +%s)"
    if [ -n "$last" ] && [ "$last" -gt 0 ] && [ $((now - last)) -lt 300 ]; then
        break
    fi
    [ "$attempt" = 13 ] || sleep 15
done
if [ -n "$last" ] && [ "$last" -gt 0 ] && [ $((now - last)) -lt 300 ]; then
    printf '  ok    %-34s %s s ago\n' "cron last run" "$((now - last))"
else
    printf '  FAIL  %-34s last run %s\n' "cron last run" "${last:-unknown}"
    fail=1
fi

if [ "$fail" = "0" ]; then
    echo "Smoke test passed."
else
    echo "Smoke test FAILED."
fi
exit "$fail"
