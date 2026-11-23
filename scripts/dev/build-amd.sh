#!/usr/bin/env bash
# Rebuild a Hikmah plugin's amd/build the way CI's Grunt check does: Moodle 5.3's own grunt (rollup + babel)
# under Node 22, the version Moodle requires. No Docker and no Moodle checkout needed: the first run downloads
# a portable Node 22 and Moodle's grunt files into a cache folder and installs their npm packages (a few
# minutes); later runs take seconds.
#
# Usage (from anywhere in the repo, Git Bash on Windows or Linux):
#   scripts/dev/build-amd.sh plugins/theme/hikmah [plugins/payment/gateway/hikmahtransfer ...]
# Then commit amd/build/*.min.js and *.min.js.map.
#
# Cache: $HIKMAH_GRUNT_CACHE, default ~/.cache/hikmah-moodle-grunt. Delete it to start fresh.
set -euo pipefail

NODE_VERSION=22.12.0            # Moodle 5.3 package.json: "node": ">=22.11.0 <23".
MOODLE_REF=MOODLE_503_STABLE    # The branch CI runs moodle-plugin-ci against.
CACHE="${HIKMAH_GRUNT_CACHE:-$HOME/.cache/hikmah-moodle-grunt}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"

[ $# -gt 0 ] || { sed -n '2,11p' "$0"; exit 1; }
mkdir -p "$CACHE"

# 1. Portable Node 22.
case "$(uname -s)" in
    MINGW*|MSYS*|CYGWIN*) os=win ;;
    Linux) os=linux ;;
    Darwin) os=darwin ;;
    *) echo "Unsupported OS: $(uname -s)" >&2; exit 1 ;;
esac
case "$(uname -m)" in
    x86_64|amd64) arch=x64 ;;
    aarch64|arm64) arch=arm64 ;;
    *) echo "Unsupported CPU: $(uname -m)" >&2; exit 1 ;;
esac
nodedir="$CACHE/node-v$NODE_VERSION-$os-$arch"
if [ ! -d "$nodedir" ]; then
    echo "Downloading Node $NODE_VERSION ($os-$arch)..."
    base="https://nodejs.org/dist/v$NODE_VERSION"
    if [ "$os" = win ]; then
        curl -fsSL --retry 3 -o "$CACHE/node.zip" "$base/node-v$NODE_VERSION-$os-$arch.zip"
        unzip -q -o "$CACHE/node.zip" -d "$CACHE" && rm "$CACHE/node.zip"
    else
        curl -fsSL --retry 3 "$base/node-v$NODE_VERSION-$os-$arch.tar.xz" | tar -xJ -C "$CACHE"
    fi
fi
if [ "$os" = win ]; then export PATH="$nodedir:$PATH"; else export PATH="$nodedir/bin:$PATH"; fi
node --version | grep -q "^v22\." || { echo "Node 22 not found in $nodedir" >&2; exit 1; }

# 2. Moodle's grunt files (only what the rollup task needs) and their npm packages.
mg="$CACHE/moodle-$MOODLE_REF"
files="Gruntfile.js package.json npm-shrinkwrap.json .eslintrc .stylelintrc tsconfig.json
    .grunt/babel-plugin-add-module-to-define.js .grunt/components.js .grunt/upgradenotes.mjs
    lib/components.json lib/plugins.json"
for t in componentlibrary eslint esm gherkinlint ignorefiles javascript jsconfig jsdoc sass shifter startup \
        style stylelint swizzle upgradablelibs watch; do
    files="$files .grunt/tasks/$t.js"
done
if [ ! -f "$mg/.complete" ]; then
    echo "Downloading Moodle grunt files ($MOODLE_REF)..."
    for f in $files; do
        mkdir -p "$mg/$(dirname "$f")"
        curl -fsSL --retry 3 -o "$mg/$f" "https://raw.githubusercontent.com/moodle/moodle/$MOODLE_REF/$f"
    done
    touch "$mg/.complete"
fi
if [ ! -f "$mg/node_modules/.installed" ]; then
    echo "Installing Moodle's npm packages (first run only)..."
    # --ignore-scripts: Moodle's postinstall updates its own vendored libraries, not needed to build.
    (cd "$mg" && npm ci --ignore-scripts --no-audit --no-fund --loglevel=error)
    touch "$mg/node_modules/.installed"
fi

# 3. Build each plugin inside the Moodle layout (public/<type path>), then copy amd/build back.
for plugin in "$@"; do
    src="$(cd "$plugin" && pwd)"
    rel="${src#"$REPO"/plugins/}"
    [ "$rel" != "$src" ] && [ -d "$src/amd/src" ] || { echo "Not a plugin with amd/src under plugins/: $plugin" >&2; exit 1; }
    dest="$mg/public/$rel"
    rm -rf "$dest" && mkdir -p "$(dirname "$dest")" && cp -r "$src" "$dest"
    (cd "$mg" && npx grunt rollup --root="public/$rel")
    mkdir -p "$src/amd/build"
    cp "$dest"/amd/build/* "$src/amd/build/"
    echo "Rebuilt $plugin/amd/build"
done
