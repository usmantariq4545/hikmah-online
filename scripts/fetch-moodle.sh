#!/bin/sh
# Downloads the pinned Moodle release and third-party plugins listed in versions.lock.
# Usage: fetch-moodle.sh <versions.lock> <destination dir> [download cache dir]
# Runs inside the image build (docker/moodle/Dockerfile); Moodle source is never committed.
# Downloads are resumable: on a slow or dropped connection the next attempt continues the
# partial archive kept in the cache directory instead of starting again.
set -eu

LOCK="${1:?path to versions.lock}"
DEST="${2:?destination directory}"
CACHE="${3:-/tmp/hikmah-dl}"

# shellcheck disable=SC1090
. "$LOCK"

mkdir -p "$CACHE"

fetch() {
    repo="$1"; commit="$2"; target="$3"
    archive="$CACHE/$(echo "$repo" | tr '/' '_')-$commit.tar.gz"
    echo "==> ${repo}@${commit} -> ${target}"

    attempt=1
    until [ -f "$archive.ok" ]; do
        if curl --fail --location --silent --show-error --continue-at - \
                --connect-timeout 30 --speed-time 60 --speed-limit 1000 \
                --output "$archive" "https://codeload.github.com/${repo}/tar.gz/${commit}" \
                && gzip -t "$archive" 2>/dev/null; then
            touch "$archive.ok"
            break
        fi
        if [ "$attempt" -ge 30 ]; then
            echo "Giving up on ${repo} after ${attempt} attempts." >&2
            exit 1
        fi
        # A complete-but-corrupt file cannot be resumed; start that one again.
        if [ -f "$archive" ] && ! gzip -t "$archive" 2>/dev/null && [ "$((attempt % 5))" -eq 0 ]; then
            rm -f "$archive"
        fi
        attempt=$((attempt + 1))
        echo "    download interrupted; retry ${attempt} in 5 s"
        sleep 5
    done

    rm -rf "$target"
    mkdir -p "$target"
    tar -xzf "$archive" -C "$target" --strip-components=1
}

fetch "$MOODLE_REPO" "$MOODLE_COMMIT" "$DEST"

echo "$PLUGINS" | while IFS='|' read -r name repo commit path; do
    [ -n "$name" ] || continue
    fetch "$repo" "$commit" "$DEST/$path"
done

echo "$MOODLE_TAG" > "$DEST/.hikmah-moodle-tag"
echo "Fetched Moodle $MOODLE_TAG and pinned plugins."
