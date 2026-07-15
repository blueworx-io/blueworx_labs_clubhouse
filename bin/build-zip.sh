#!/usr/bin/env bash
#
# Build the deployable plugin zip from an explicit allowlist, then verify the
# artifact it just built.
#
#   bash bin/build-zip.sh [output-dir]      # default output-dir: parent of the repo
#
# WHY THIS EXISTS
# The zip used to be assembled by hand. Nothing enforced what went into it, and
# the obvious way to build one — zip up the repo — ships preview/index.php, which
# defines its own ABSPATH and therefore renders a full page without WordPress
# bootstrapping it. That would be reachable unauthenticated on a live club site.
# The allowlist below is the single source of truth for what ships; CI runs this
# script so the rule is enforced rather than remembered.
#
# WHY NOT Compress-Archive / GNU tar
# PowerShell's Compress-Archive writes backslash entry paths on Windows, and
# WordPress (Linux) then reports "Plugin file does not exist." on activate. GNU
# tar cannot write zip format at all. This script insists on a tool that produces
# correct forward-slash zip entries, and proves it afterwards.

set -euo pipefail

SLUG="blueworx-labs-clubhouse"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$(cd "$ROOT/.." && pwd)}"
ZIP="$OUT_DIR/$SLUG.zip"

# ---------------------------------------------------------------------------
# THE ALLOWLIST — what ships. Allowlist, not denylist: a new dev directory is
# excluded by default rather than shipped because nobody remembered to add it.
# ---------------------------------------------------------------------------
INCLUDE=(
	"$SLUG.php"
	"uninstall.php"
	"CHANGELOG.md"
	"includes"
	"assets"
	"templates"
)

# Belt and braces. The allowlist alone already excludes these, so a hit here means
# one is nested inside a shipped directory — exactly the case a human misses.
FORBIDDEN_SEGMENTS=( "preview" "tests" "docs" "node_modules" "vendor" ".superpowers" ".github" ".git" )
FORBIDDEN_FILES=( "*.spec.js" "phpunit.xml*" "phpcs.xml*" "composer.json" "composer.lock" "package.json" "playwright.config.js" "CLAUDE.md" ".gitignore" )

say() { printf '%s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# --- pick an archiver that writes real zip entries with forward slashes --------
ZIP_TOOL=""
for candidate in "/c/Windows/System32/tar.exe" "$(command -v bsdtar || true)" "$(command -v tar || true)"; do
	[ -n "$candidate" ] && [ -x "$candidate" ] || continue
	if "$candidate" --version 2>&1 | grep -qi 'bsdtar\|libarchive'; then
		ZIP_TOOL="bsdtar:$candidate"
		break
	fi
done
if [ -z "$ZIP_TOOL" ] && command -v zip >/dev/null 2>&1; then
	# Info-ZIP on Linux/CI: GNU tar cannot write zip, but zip(1) can, and writes
	# forward slashes natively.
	ZIP_TOOL="zip:$(command -v zip)"
fi
[ -n "$ZIP_TOOL" ] || die "no zip-capable archiver found (need bsdtar or zip; GNU tar cannot write zip)"

TOOL_KIND="${ZIP_TOOL%%:*}"
TOOL_BIN="${ZIP_TOOL#*:}"
say "Archiver : $TOOL_KIND ($TOOL_BIN)"

VERSION="$(grep -oE "define\( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '[^']+'" "$ROOT/$SLUG.php" | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")"
[ -n "$VERSION" ] || die "could not read the plugin version from $SLUG.php"
say "Version  : $VERSION"

# --- stage -------------------------------------------------------------------
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$SLUG"
for item in "${INCLUDE[@]}"; do
	[ -e "$ROOT/$item" ] || die "allowlisted path is missing from the repo: $item"
	cp -R "$ROOT/$item" "$STAGE/$SLUG/"
done

# --- build -------------------------------------------------------------------
mkdir -p "$OUT_DIR"
rm -f "$ZIP"
case "$TOOL_KIND" in
	bsdtar) ( cd "$STAGE" && "$TOOL_BIN" -a -c -f "$ZIP" "$SLUG" ) ;;
	zip)    ( cd "$STAGE" && "$TOOL_BIN" -q -r -X "$ZIP" "$SLUG" ) ;;
esac
[ -f "$ZIP" ] || die "no zip was produced at $ZIP"

# --- verify the artifact, not the intent -------------------------------------
if command -v unzip >/dev/null 2>&1; then
	ENTRIES="$(unzip -Z1 "$ZIP")"
elif [ "$TOOL_KIND" = "bsdtar" ]; then
	ENTRIES="$("$TOOL_BIN" -tf "$ZIP")"
else
	die "need unzip (or bsdtar) to list the zip — refusing to ship an unverified artifact"
fi

fail=0
check() { # check <description> <offending-entries>
	if [ -n "$2" ]; then
		printf 'FAIL: %s\n%s\n' "$1" "$(printf '%s\n' "$2" | sed 's/^/    /')" >&2
		fail=1
	else
		say "  ok: $1"
	fi
}

say "Verifying $ZIP"

# A backslash entry mis-extracts on a Linux host: WordPress then reports
# "Plugin file does not exist." on activate. This is the Compress-Archive bug.
check "every entry uses forward slashes" "$(printf '%s\n' "$ENTRIES" | grep -F '\' || true)"
check "every entry is nested under $SLUG/" "$(printf '%s\n' "$ENTRIES" | grep -vE "^$SLUG/" || true)"

offenders=""
for seg in "${FORBIDDEN_SEGMENTS[@]}"; do
	hit="$(printf '%s\n' "$ENTRIES" | grep -E "(^|/)$(printf '%s' "$seg" | sed 's/\./\\./g')(/|$)" || true)"
	[ -n "$hit" ] && offenders="$offenders$hit"$'\n'
done
check "no development directories ship" "$(printf '%s' "$offenders" | sed '/^$/d')"

offenders=""
for pat in "${FORBIDDEN_FILES[@]}"; do
	hit="$(printf '%s\n' "$ENTRIES" | grep -E "(^|/)${pat//\*/[^/]*}$" || true)"
	[ -n "$hit" ] && offenders="$offenders$hit"$'\n'
done
check "no development files ship" "$(printf '%s' "$offenders" | sed '/^$/d')"

check "the main plugin file sits directly inside $SLUG/" \
	"$(printf '%s\n' "$ENTRIES" | grep -qxF "$SLUG/$SLUG.php" && true || echo "missing $SLUG/$SLUG.php")"

[ "$fail" -eq 0 ] || die "the zip is not shippable — see the failures above"

say ""
say "Built $ZIP ($SLUG $VERSION, $(printf '%s\n' "$ENTRIES" | grep -c . ) entries)"
