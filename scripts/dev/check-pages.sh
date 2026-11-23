#!/usr/bin/env bash
# Dev check: sign in as each demo role and request the pages that role uses, reporting the HTTP
# status and any Moodle error or debugging output found in the page. Also checks the access rules
# that matter most: no paid access before approval, receipts are private, staff pages are closed
# to learners. Needs the demo data (scripts/dev/seed.sh). Passwords are read from .env.dev and
# never printed.
set -uo pipefail
HIKMAH_ENV=dev
. "$(dirname "${BASH_SOURCE[0]}")/../lib.sh"

BASE="$(env_value MOODLE_WWWROOT)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fail=0

login() {
    user="$1"; pass="$2"; jar="$TMP/$user.jar"
    token="$(curl -s -c "$jar" "$BASE/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -n 1 | sed 's/.*value="//; s/"$//')"
    curl -s -o /dev/null -b "$jar" -c "$jar" --data-urlencode "username=$user" --data-urlencode "password=$pass" \
        --data-urlencode "logintoken=$token" "$BASE/login/index.php"
    # A page that needs a real (non-guest) session answers 200 only when the login worked.
    if [ "$(curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' --max-time 120 "$BASE/user/preferences.php")" = "200" ]; then
        echo "signed in: $user"
        return 0
    fi
    echo "LOGIN FAILED: $user"
    fail=1
    return 1
}

page() {
    user="$1"; path="$2"; expected="${3:-200}"
    out="$TMP/page.html"
    code="$(curl -s -b "$TMP/$user.jar" -o "$out" -w '%{http_code}' --max-time 120 "$BASE$path")"
    problems=""
    grep -q -e 'class="errormessage"' -e 'data-rel="fatalerror"' -e 'Coding error detected' "$out" && problems="$problems error-page"
    grep -q -e 'debuggingmessage' -e 'class="notifytiny' "$out" && problems="$problems debugging-output"
    grep -q '\[\[[a-z_:0-9]*\]\]' "$out" && problems="$problems missing-string"
    grep -qi -e 'Warning</b>:' -e 'Notice</b>:' -e 'Fatal error</b>' "$out" && problems="$problems php-notice"
    if [ "$code" = "$expected" ] && [ -z "$problems" ]; then
        printf '  ok    %-16s %s %s\n' "$user" "$code" "$path"
    else
        printf '  FAIL  %-16s %s (expected %s)%s  %s\n' "$user" "$code" "$expected" "$problems" "$path"
        grep -o -e 'class="errormessage"[^<]*<' -e 'debuggingmessage[^<]*<[^<]*<[^<]*' -e '\[\[[a-z_:0-9]*\]\]' "$out" \
            | head -n 3 | cut -c1-300 | sed 's/^/          /'
        fail=1
    fi
}

# The page must refuse this user with Moodle's "no permission" message.
page_denied() {
    user="$1"; path="$2"
    curl -s -b "$TMP/$user.jar" -o "$TMP/page.html" --max-time 120 "$BASE$path"
    if grep -q 'do not currently have permissions' "$TMP/page.html"; then
        printf '  ok    %-16s denied %s\n' "$user" "$path"
    else
        printf '  FAIL  %-16s NOT denied %s\n' "$user" "$path"
        fail=1
    fi
}

sql() {
    moodle_exec php -r 'define("CLI_SCRIPT", true); require "/var/www/moodle/config.php"; echo $DB->get_field_sql($argv[1]);' "$1" 2>/dev/null | tail -n 1
}

DEMO="$(env_value HIKMAH_DEMO_PASSWORD)"
ADMINPASS="$(env_value HIKMAH_ADMIN_PASSWORD)"
ADMIN="$(env_value HIKMAH_ADMIN_USER)"

tajweed="$(sql "SELECT id FROM {course} WHERE idnumber = 'demo-tajweed'")"
seerah="$(sql "SELECT id FROM {course} WHERE idnumber = 'demo-seerah'")"
arabic="$(sql "SELECT id FROM {course} WHERE idnumber = 'demo-arabic'")"
fee="$(sql "SELECT id FROM {enrol} WHERE enrol = 'fee' AND courseid = $tajweed")"
fiqhfee="$(sql "SELECT e.id FROM {enrol} e JOIN {course} c ON c.id = e.courseid WHERE e.enrol = 'fee' AND c.idnumber = 'demo-fiqh'")"
link="$(sql "SELECT MIN(id) FROM {local_hikmahattendance_link} WHERE courseid = $tajweed")"
pending="$(sql "SELECT MIN(id) FROM {paygw_hikmahtransfer_req} WHERE status = 'pending'")"
code="$(sql "SELECT code FROM {tool_certificate_issues} ORDER BY id DESC LIMIT 1")"

echo "== anonymous"
touch "$TMP/anon.jar"
for p in "/?redirect=0" /theme/hikmah/catalog.php "/theme/hikmah/catalog.php?mode=live&price=paid" \
    "/theme/hikmah/catalog.php?q=arabic" "/theme/hikmah/course.php?id=$tajweed" "/theme/hikmah/course.php?id=$arabic" \
    /theme/hikmah/faq.php /theme/hikmah/verify.php "/theme/hikmah/verify.php?code=$code" \
    "/theme/hikmah/verify.php?code=NOSUCHCODE" /login/index.php /login/forgot_password.php; do
    page anon "$p"
done
page anon /login/signup.php 303                                         # Policy consent comes before the sign-up form.
page anon /payment/gateway/hikmahtransfer/review.php 303
page anon "/local/hikmahattendance/index.php?courseid=$tajweed" 303

echo "== learner (enrolled in Tajweed and Seerah)"
if login demo.student1 "$DEMO"; then
    for p in /my/ /my/courses.php "/course/view.php?id=$tajweed" "/course/view.php?id=$seerah" \
        "/theme/hikmah/course.php?id=$tajweed" "/theme/hikmah/course.php?id=$arabic" "/enrol/index.php?id=$arabic" \
        /payment/gateway/hikmahtransfer/status.php /grade/report/overview/index.php /user/profile.php \
        /admin/tool/certificate/my.php; do
        page demo.student1 "$p"
    done
    page_denied demo.student1 /payment/gateway/hikmahtransfer/review.php
    page_denied demo.student1 "/local/hikmahattendance/index.php?courseid=$tajweed"
    page_denied demo.student1 "/local/hikmahattendance/reconcile.php?id=$link"
fi

echo "== learner with a payment under review"
# Whoever owns the oldest pending request (the demo data changes as payments are reviewed).
payer="$(sql "SELECT u.username FROM {paygw_hikmahtransfer_req} r JOIN {user} u ON u.id = r.userid WHERE r.id = 0$pending")"
payercourse="$(sql "SELECT courseid FROM {paygw_hikmahtransfer_req} WHERE id = 0$pending")"
if [ -z "$payer" ]; then
    echo "  note  no pending payment left in the demo data; skipped"
elif login "$payer" "$DEMO"; then
    for p in /my/ "/theme/hikmah/course.php?id=$payercourse" /payment/gateway/hikmahtransfer/status.php \
        "/payment/gateway/hikmahtransfer/status.php?id=$pending"; do
        page "$payer" "$p"
    done
    # Paid access must not exist before approval.
    curl -s -b "$TMP/$payer.jar" -o "$TMP/page.html" -L --max-time 120 "$BASE/course/view.php?id=$payercourse"
    if grep -q 'class="course-content"' "$TMP/page.html"; then
        echo "  FAIL  unpaid learner sees course content"; fail=1
    else
        printf '  ok    %-16s no course access before approval\n' "$payer"
    fi
    # Another learner must not be able to open this request.
    page_denied demo.student1 "/payment/gateway/hikmahtransfer/status.php?id=$pending"
fi

echo "== learner asked to resubmit"
if login demo.student3 "$DEMO"; then
    page demo.student3 "/payment/gateway/hikmahtransfer/pay.php?component=enrol_fee&paymentarea=fee&itemid=$fee"
    page demo.student3 /my/
fi

echo "== learner starting a payment"
if login demo.student4 "$DEMO"; then
    page demo.student4 "/payment/gateway/hikmahtransfer/pay.php?component=enrol_fee&paymentarea=fee&itemid=$fiqhfee"
fi

echo "== instructor"
if login demo.teacher "$DEMO"; then
    for p in /my/ "/course/view.php?id=$tajweed" "/local/hikmahattendance/index.php?courseid=$tajweed" \
        "/local/hikmahattendance/reconcile.php?id=$link" "/local/hikmahattendance/link.php?courseid=$tajweed" \
        "/local/hikmahattendance/link.php?courseid=$tajweed&id=$link" "/grade/report/grader/index.php?id=$tajweed"; do
        page demo.teacher "$p"
    done
    page_denied demo.teacher /payment/gateway/hikmahtransfer/review.php
    page_denied demo.teacher "/local/hikmahattendance/report.php?courseid=$tajweed"
fi

echo "== finance reviewer"
if login demo.finance "$DEMO"; then
    for p in /my/ /payment/gateway/hikmahtransfer/review.php "/payment/gateway/hikmahtransfer/review.php?status=all" \
        "/payment/gateway/hikmahtransfer/review.php?id=$pending"; do
        page demo.finance "$p"
    done
    # The receipt is served to the reviewer, and never to another learner or an anonymous visitor.
    receipt="$(grep -o "$BASE/pluginfile.php/[^\"]*paygw_hikmahtransfer/receipt/[^\"?]*" "$TMP/page.html" | head -n 1)"
    if [ -n "$receipt" ]; then
        a="$(curl -s -o /dev/null -w '%{http_code}' -b "$TMP/demo.finance.jar" "$receipt")"
        b="$(curl -s -o /dev/null -w '%{http_code}' -b "$TMP/demo.student1.jar" "$receipt")"
        c="$(curl -s -o /dev/null -w '%{http_code}' "$receipt")"
        if [ "$a" = "200" ] && [ "$b" != "200" ] && [ "$c" != "200" ]; then
            echo "  ok    receipt: reviewer $a, other learner $b, anonymous $c"
        else
            echo "  FAIL  receipt access: reviewer $a, other learner $b, anonymous $c"; fail=1
        fi
    else
        echo "  FAIL  no receipt link on the review page"; fail=1
    fi
    page demo.finance "/course/view.php?id=$tajweed" 303                # Not enrolled: sent to enrolment, no coursework.
    page demo.finance "/local/hikmahattendance/index.php?courseid=$tajweed" 303   # Not enrolled, no teaching role.
fi

echo "== manager"
if login demo.manager "$DEMO"; then
    for p in /my/ "/course/view.php?id=$tajweed" "/local/hikmahattendance/index.php?courseid=$tajweed" \
        "/local/hikmahattendance/report.php?courseid=$tajweed" \
        "/local/hikmahattendance/report.php?courseid=$tajweed&download=1" \
        /payment/gateway/hikmahtransfer/review.php /admin/tool/certificate/manage_templates.php \
        "/user/index.php?id=$tajweed" "/course/edit.php?id=$tajweed"; do
        page demo.manager "$p"
    done
fi

echo "== administrator"
jar="$TMP/$ADMIN.jar"
token="$(curl -s -c "$jar" "$BASE/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -n 1 | sed 's/.*value="//; s/"$//')"
curl -s -o /dev/null -b "$jar" -c "$jar" --data-urlencode "username=$ADMIN" --data-urlencode "password=$ADMINPASS" \
    --data-urlencode "logintoken=$token" "$BASE/login/index.php"
where="$(curl -s -b "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/admin/search.php")"
case "$where" in
    200*)
        for p in /admin/search.php "/admin/settings.php?section=paymentgatewayhikmahtransfer" \
            "/admin/settings.php?section=local_hikmahattendance" /admin/environment.php; do
            page "$ADMIN" "$p"
        done
        ;;
    *user/edit.php*)
        echo "  note  the administrator signed in but must complete the required profile fields once"
        echo "        (gender, date of birth, phone) before administration pages open; not checked here"
        ;;
    *)
        echo "  FAIL  administrator sign-in: $where"; fail=1
        ;;
esac

if [ "$fail" = "0" ]; then
    echo "All page checks passed."
else
    echo "Some page checks FAILED."
fi
exit "$fail"
