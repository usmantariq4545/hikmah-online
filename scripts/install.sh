#!/usr/bin/env bash
# First install: starts the stack and installs Moodle into an EMPTY database, then applies the
# settings-as-code. Refuses to touch a database that already has Moodle in it.
#   scripts/install.sh                    (dev)
#   HIKMAH_ENV=staging scripts/install.sh
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

say "Starting services ($HIKMAH_ENV)"
dc up -d
wait_healthy postgres
wait_healthy moodle

if is_installed; then
    echo "Moodle is already installed in this database; nothing to install."
    echo "Use scripts/upgrade.sh after an image change, or scripts/configure.sh to re-apply settings."
    exit 0
fi

if [ -z "$(env_value HIKMAH_ADMIN_PASSWORD)" ]; then
    echo "HIKMAH_ADMIN_PASSWORD is empty in .env.$HIKMAH_ENV. Set a strong password first." >&2
    exit 1
fi

say "Installing Moodle database"
# The administrator password is read from the container environment, never printed.
moodle_exec sh -c 'php admin/cli/install_database.php \
    --lang=en \
    --adminuser="$HIKMAH_ADMIN_USER" \
    --adminpass="$HIKMAH_ADMIN_PASSWORD" \
    --adminemail="$HIKMAH_ADMIN_EMAIL" \
    --fullname="$HIKMAH_SITE_NAME" \
    --shortname="Hikmah" \
    --agree-license'

say "Applying settings"
"$SCRIPTS_DIR/configure.sh"

say "Smoke test"
"$SCRIPTS_DIR/smoke-test.sh" || echo "Smoke test reported problems; see above."

echo
echo "Installed. Site: $(env_value MOODLE_WWWROOT)"
echo "Administrator: $(env_value HIKMAH_ADMIN_USER) (password is HIKMAH_ADMIN_PASSWORD in .env.$HIKMAH_ENV)"
