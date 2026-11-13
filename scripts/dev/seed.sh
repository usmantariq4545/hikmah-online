#!/usr/bin/env bash
# Loads fictional demo data into the dev site (courses, accounts, payments, a live class).
# Demo accounts share one generated password, stored as HIKMAH_DEMO_PASSWORD in .env.dev.
set -euo pipefail
HIKMAH_ENV=dev
. "$(dirname "${BASH_SOURCE[0]}")/../lib.sh"

ENVFILE="$ROOT_DIR/.env.dev"
if ! grep -q '^HIKMAH_DEMO_PASSWORD=' "$ENVFILE"; then
    printf '\n# Password shared by the fictional demo.* accounts (dev only).\nHIKMAH_DEMO_PASSWORD=%s\n' \
        "$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 16)aZ9!" >> "$ENVFILE"
    echo "Generated HIKMAH_DEMO_PASSWORD in .env.dev"
fi

dc exec -T -u www-data -w /var/www/moodle -e "HIKMAH_DEMO_PASSWORD=$(env_value HIKMAH_DEMO_PASSWORD)" \
    moodle php /opt/hikmah/dev/seed.php

echo
echo "Demo accounts: demo.student1 … demo.student6, demo.teacher, demo.teacher2, demo.finance, demo.manager, demo.support, demo.assistant"
echo "Their password is HIKMAH_DEMO_PASSWORD in .env.dev"
