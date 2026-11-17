#!/usr/bin/env bash
# Creates .env.staging or .env.prod on the server from .env.example, with strong generated
# passwords and the site address filled in. Never overwrites an existing file.
#
#   scripts/server/new-env.sh prod  learn.example.org  admin@example.org
#   scripts/server/new-env.sh staging staging.example.org admin@example.org
#
# Afterwards open the file (nano .env.prod) and fill in what only you know: SMTP, bank/wallet
# details, BBB server, backup remote. The generated passwords stay on this server only.
set -euo pipefail

ENVNAME="${1:-}"
HOST="${2:-}"
EMAIL="${3:-}"
case "$ENVNAME" in
    staging|prod) ;;
    *) echo "Usage: $0 <staging|prod> <site host, e.g. learn.example.org> <email for Let's Encrypt>" >&2; exit 1 ;;
esac
if [ -z "$HOST" ] || [ -z "$EMAIL" ]; then
    echo "Usage: $0 <staging|prod> <site host> <email for Let's Encrypt>" >&2
    exit 1
fi
case "$HOST" in
    http://*|https://*|*/*) echo "Give the host only, without https:// or a path (e.g. learn.example.org)." >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TARGET="$ROOT/.env.$ENVNAME"
if [ -f "$TARGET" ]; then
    echo "$TARGET already exists; leaving it untouched." >&2
    exit 1
fi

rand() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-28}"; }

umask 077
sed -E \
    -e 's/[[:space:]]+#.*$//' \
    -e "s|^SITE_ENV=.*|SITE_ENV=${ENVNAME}|" \
    -e "s|^MOODLE_DEBUG=.*|MOODLE_DEBUG=|" \
    -e "s|^MOODLE_WWWROOT=.*|MOODLE_WWWROOT=https://${HOST}|" \
    -e "s|^SITE_HOST=.*|SITE_HOST=${HOST}|" \
    -e "s|^ACME_EMAIL=.*|ACME_EMAIL=${EMAIL}|" \
    -e "s|^HIKMAH_ADMIN_EMAIL=.*|HIKMAH_ADMIN_EMAIL=${EMAIL}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(rand 32)|" \
    -e "s|^HIKMAH_ADMIN_PASSWORD=.*|HIKMAH_ADMIN_PASSWORD=$(rand 20)aZ9!|" \
    -e "s|^MOODLE_UPGRADE_KEY=.*|MOODLE_UPGRADE_KEY=$(rand 32)|" \
    "$ROOT/.env.example" > "$TARGET"
chmod 600 "$TARGET"

echo "Created $TARGET (readable only by $(id -un))."
echo "Generated: database password, administrator password, upgrade key."
echo "Still to fill in with: nano $TARGET"
echo "  MOODLE_IMAGE / NGINX_IMAGE (or IMAGE_PULL_POLICY=never to build here), SMTP_*,"
echo "  HIKMAH_BANK_* / HIKMAH_JAZZCASH / HIKMAH_EASYPAISA / HIKMAH_RAAST, BBB_SERVER_URL / BBB_SHARED_SECRET,"
echo "  BACKUP_REMOTE$( [ "$ENVNAME" = staging ] && echo ', STAGING_MAIL_DIVERT, STAGING_MAIL_ALLOWLIST' )."
echo "The administrator login is HIKMAH_ADMIN_USER / HIKMAH_ADMIN_PASSWORD in that file."
