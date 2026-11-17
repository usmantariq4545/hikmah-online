#!/usr/bin/env bash
# Creates .env.dev from .env.example with freshly generated passwords. Never overwrites.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="$ROOT/.env.dev"

if [ -f "$TARGET" ]; then
    echo ".env.dev already exists; leaving it untouched."
    exit 0
fi

rand() {
    # 24 url-safe characters plus fixed suffix so Moodle's password policy is always met.
    printf '%s' "$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 24)"
}

DBPASS="$(rand)"
ADMINPASS="$(rand)aZ9!"

sed -E \
    -e 's/[[:space:]]+#.*$//' \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DBPASS}|" \
    -e "s|^HIKMAH_ADMIN_PASSWORD=.*|HIKMAH_ADMIN_PASSWORD=${ADMINPASS}|" \
    -e "s|^HIKMAH_BANK_TITLE=.*|HIKMAH_BANK_TITLE=Hikmah Institute (demo account)|" \
    -e "s|^HIKMAH_BANK_NAME=.*|HIKMAH_BANK_NAME=Demo Bank Limited|" \
    -e "s|^HIKMAH_BANK_IBAN=.*|HIKMAH_BANK_IBAN=PK00DEMO0000000000000000|" \
    -e "s|^HIKMAH_JAZZCASH=.*|HIKMAH_JAZZCASH=0300-0000000|" \
    -e "s|^HIKMAH_EASYPAISA=.*|HIKMAH_EASYPAISA=0345-0000000|" \
    -e "s|^HIKMAH_RAAST=.*|HIKMAH_RAAST=demo@hikmah|" \
    "$ROOT/.env.example" > "$TARGET"
chmod 600 "$TARGET" 2>/dev/null || true

echo "Created .env.dev with generated database and administrator passwords."
echo "The administrator password is in .env.dev (HIKMAH_ADMIN_PASSWORD). Do not share that file."
