#!/usr/bin/env bash
# Docker Compose wrapper: picks the env file and compose overlays for an environment.
#   scripts/dc.sh up -d              (dev, default)
#   HIKMAH_ENV=staging scripts/dc.sh ps
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Git Bash on Windows: hand docker.exe a C:/… path, since path conversion is switched off below.
if command -v cygpath >/dev/null 2>&1; then
    ROOT="$(cygpath -m "$ROOT")"
fi
ENVNAME="${HIKMAH_ENV:-dev}"
ENVFILE="$ROOT/.env.$ENVNAME"

if [ ! -f "$ENVFILE" ]; then
    echo "Missing $ENVFILE. Copy .env.example (or run scripts/init-env.sh for dev)." >&2
    exit 1
fi

case "$ENVNAME" in
    dev) OVERLAY="$ROOT/compose/compose.dev.yml" ;;
    staging|prod) OVERLAY="$ROOT/compose/compose.server.yml" ;;
    *) echo "Unknown HIKMAH_ENV '$ENVNAME' (dev|staging|prod)" >&2; exit 1 ;;
esac

# Git Bash on Windows rewrites /unix/paths in arguments; containers need them untouched.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

exec docker compose --env-file "$ENVFILE" \
    -f "$ROOT/compose/compose.base.yml" -f "$OVERLAY" "$@"
