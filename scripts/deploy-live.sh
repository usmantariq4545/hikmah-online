#!/usr/bin/env bash
# Deploy the pushed commit to the live server (commit first, then deploy).
#   scripts/deploy-live.sh
# Refuses uncommitted or unpushed work, ships exactly that commit (git archive), then on the
# server: backup, rebuild images, upgrade (maintenance mode, upgrade.php, settings, smoke test).
# The work runs on the server under nohup, so a dropped connection cannot stop it halfway.
# The server records the commit it runs in ~/hikmah-online/DEPLOYED_COMMIT.
set -euo pipefail
HOST="${HIKMAH_DEPLOY_HOST:-hikmah@157.173.114.25}"
SSH="ssh -o BatchMode=yes -o ConnectTimeout=20"
cd "$(dirname "${BASH_SOURCE[0]}")/.."

[ -z "$(git status --porcelain)" ] || { echo "Uncommitted changes: commit and push first." >&2; exit 1; }
git fetch -q origin
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || { echo "This commit is not on GitHub (origin/main): push first." >&2; exit 1; }
COMMIT="$(git rev-parse --short HEAD)"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
git archive --format=tar.gz -o "$TMP/code.tgz" HEAD
for attempt in 1 2 3 4 5; do
    scp -q -o BatchMode=yes -o ConnectTimeout=20 "$TMP/code.tgz" "$HOST:code.tgz" && $SSH "$HOST" 'gzip -t code.tgz' && break
    [ "$attempt" = 5 ] && { echo "Upload failed." >&2; exit 1; }
    sleep 10
done

$SSH "$HOST" "rm -f deploy.log; nohup sh -c 'cd hikmah-online && tar -xzf ~/code.tgz && rm ~/code.tgz \
  && export HIKMAH_ENV=prod && scripts/backup.sh && scripts/dc.sh build moodle nginx && scripts/upgrade.sh \
  && echo $COMMIT > DEPLOYED_COMMIT && echo DEPLOY-OK $COMMIT || echo DEPLOY-FAILED $COMMIT' > deploy.log 2>&1 < /dev/null &"
echo "Deploying $COMMIT to $HOST (server log: ~/deploy.log)..."

until result="$($SSH "$HOST" 'grep -E "^DEPLOY-(OK|FAILED)" deploy.log' 2>/dev/null)"; do sleep 20; done
$SSH "$HOST" 'grep -E "^(  ok|  FAIL|Smoke|==>)" deploy.log | tail -16'
echo "$result"
[[ "$result" == DEPLOY-OK* ]]
