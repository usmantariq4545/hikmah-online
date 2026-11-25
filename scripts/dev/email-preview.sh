#!/usr/bin/env bash
# Mails a sample of every Hikmah Online email to one address, using the email layout, wording and email code in this
# checkout, through the live site's mail settings. Nothing on the site changes. From the laptop, in the repository:
#   scripts/dev/email-preview.sh you@example.com [course id for the course card]
set -euo pipefail
cd "$(dirname "$0")/../.."
to="${1:?email address}"
course="${2:-}"
server="${HIKMAH_SERVER:-hikmah@157.173.114.25}"
b64() { base64 -w0 "$1"; }
{
    printf '<?php $hkfiles = [\n'
    printf "'email_html.mustache' => '%s',\n" "$(b64 plugins/theme/hikmah/templates/core/email_html.mustache)"
    printf "'email-strings.php' => '%s',\n" "$(b64 config/settings/email-strings.php)"
    printf "'theme_lang.php' => '%s',\n" "$(b64 plugins/theme/hikmah/lang/en/theme_hikmah.php)"
    printf "'paygw_lang.php' => '%s',\n" "$(b64 plugins/payment/gateway/hikmahtransfer/lang/en/paygw_hikmahtransfer.php)"
    printf "'email.php' => '%s',\n" "$(b64 plugins/theme/hikmah/classes/local/email.php)"
    printf "'notifier.php' => '%s',\n" "$(b64 plugins/payment/gateway/hikmahtransfer/classes/notifier.php)"
    printf ']; ?>'
    cat scripts/dev/email-preview.php
} | ssh -o ConnectTimeout=20 "$server" "cd ~/hikmah-online && export HIKMAH_ENV=prod && . scripts/lib.sh &&
    dc exec -T -u www-data -w /var/www/moodle -e HK_PREVIEW_TO='$to' -e HK_PREVIEW_COURSE='$course' moodle php"
