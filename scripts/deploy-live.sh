#!/usr/bin/env bash
# Deploy the pushed commit to the live server (commit first, then deploy).
#   scripts/deploy-live.sh                       deploy the pushed main
#   scripts/deploy-live.sh live-2026-11-23-ab12cd3   go back to an earlier release (see docs/runbooks/contabo.md)
# Each successful deploy is tagged live-<date>-<commit> on GitHub, naming the backup taken just before it.
# Refuses uncommitted or unpushed work, ships exactly that commit (git archive), then on the
# server: backup, rebuild images, upgrade (maintenance mode, upgrade.php, settings, smoke test).
# The work runs on the server under nohup, so a dropped connection cannot stop it halfway.
# The server records the commit it runs in ~/hikmah-online/DEPLOYED_COMMIT.
set -euo pipefail
HOST="${HIKMAH_DEPLOY_HOST:-hikmah@157.173.114.25}"
# Keep-alive: on a flaky connection a dropped SSH session fails within a minute (and the wait loop
# below retries) instead of hanging forever.
SSHOPTS="-o BatchMode=yes -o ConnectTimeout=20 -o ServerAliveInterval=15 -o ServerAliveCountMax=4"
SSH="ssh $SSHOPTS"
cd "$(dirname "${BASH_SOURCE[0]}")/.."
# Any clone that deploys also gets the guard against pushing straight to main (.githooks/pre-push).
[ "$(git config --get core.hooksPath)" = .githooks ] || git config core.hooksPath .githooks

[ -z "$(git status --porcelain)" ] || { echo "Uncommitted changes: commit and push first." >&2; exit 1; }
git fetch -q origin
REF="${1:-HEAD}"
if [ "$REF" = HEAD ]; then
    [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || { echo "This commit is not on GitHub (origin/main): push first." >&2; exit 1; }
else
    git fetch -q origin --tags
    git merge-base --is-ancestor "$REF" origin/main || { echo "$REF is not an earlier commit of origin/main." >&2; exit 1; }
fi
COMMIT="$(git rev-parse --short "$REF^{commit}")"
# Keep browsers' cached script bundle unless JavaScript (or Moodle itself) changed since the last deploy.
PREV="$($SSH "$HOST" 'cat hikmah-online/DEPLOYED_COMMIT 2>/dev/null' || true)"
PURGEJS=1
if [ -n "$PREV" ] && git cat-file -e "$PREV^{commit}" 2>/dev/null \
    && git diff --quiet "$PREV" "$REF" -- '*/amd/*' docker/ versions.lock; then
    PURGEJS=0
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
git archive --format=tar.gz -o "$TMP/code.tgz" "$COMMIT"
for attempt in 1 2 3 4 5; do
    scp -q $SSHOPTS "$TMP/code.tgz" "$HOST:code.tgz" && $SSH "$HOST" 'gzip -t code.tgz' && break
    [ "$attempt" = 5 ] && { echo "Upload failed." >&2; exit 1; }
    sleep 10
done

$SSH "$HOST" "rm -f deploy.log; nohup sh -c 'cd hikmah-online && tar -xzf ~/code.tgz && rm ~/code.tgz \
  && export HIKMAH_ENV=prod HIKMAH_PURGE_JS=$PURGEJS && scripts/backup.sh && scripts/dc.sh build moodle nginx && scripts/upgrade.sh \
  && echo $COMMIT > DEPLOYED_COMMIT && echo DEPLOY-OK $COMMIT || echo DEPLOY-FAILED $COMMIT' > deploy.log 2>&1 < /dev/null &"
echo "Deploying $COMMIT to $HOST (server log: ~/deploy.log)..."

until result="$($SSH "$HOST" 'grep -E "^DEPLOY-(OK|FAILED)" deploy.log' 2>/dev/null)"; do sleep 20; done
$SSH "$HOST" 'grep -E "^(  ok|  FAIL|Smoke|==>)" deploy.log | tail -16'
echo "$result"
[[ "$result" == DEPLOY-OK* ]] || exit 1

# Version marker to come back to: the release tag names the backup taken just before this deploy.
BACKUP="$($SSH "$HOST" "grep -o 'backups/prod/[0-9-]*' deploy.log | tail -1" || true)"
TAG="live-$(date -u +%Y-%m-%d)-$COMMIT"
if ! git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
    git tag -a "$TAG" "$COMMIT" -m "Live release $COMMIT. Backup taken before it: ${BACKUP:-unknown}" && git push -q origin "$TAG"
    echo "Tagged $TAG (backup before it: ${BACKUP:-unknown})"
fi
