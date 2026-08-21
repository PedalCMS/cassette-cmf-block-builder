#!/usr/bin/env bash

# Writes a single version number into every version-bearing published file in
# the library: the README badge and package.json.
#
# Deliberately does NOT touch composer.json. The parent library
# (cassette-cmf) hardcodes a "version" key in composer.json and syncs it from
# git tags via a release script — which is exactly how its working branch
# ended up shipping "0.0.9" while the actual latest published tag is v0.1.0.
# Composer already infers the package version from VCS tags; adding a
# duplicate, driftable copy in composer.json buys nothing and repeats a bug
# we specifically decided not to inherit. Asset cache-busting here comes from
# @wordpress/scripts' content-hashed editor.asset.php, not a version string.
#
# Usage: bin/set-version.sh <version>
#   <version> may be given as "0.4.0" or "v0.4.0"; a single leading "v" is
#   stripped and the normalized "0.4.0" form is written everywhere.

set -euo pipefail

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 <version>" >&2
	exit 2
fi

# Normalize: strip a single leading "v" (v0.4.0 -> 0.4.0).
VERSION="${1#v}"

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$ ]]; then
	echo "Invalid version '${1}'. Expected X.Y.Z (optionally with a suffix), e.g. 0.4.0 or v0.4.0." >&2
	exit 1
fi

# Resolve the repository root from this script's location so it works from anywhere.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

# apply <file> <sed-expression> <verify-grep-pattern>
# Runs the substitution, then fails loudly if the expected value is not present
# afterwards (guards against a pattern that silently matched nothing).
apply() {
	local file="$1" expr="$2" verify="$3"
	[[ -f "${file}" ]] || { echo "Expected file '${file}' not found." >&2; exit 1; }
	sed -i -E "${expr}" "${file}"
	if ! grep -qE "${verify}" "${file}"; then
		echo "Failed to set version in ${file} (pattern did not match)." >&2
		exit 1
	fi
	echo "  ${file} -> ${VERSION}"
}

echo "Setting version to ${VERSION}"

# README badge:  badge/version-0.0.2-blue.svg
apply "README.md" \
	"s#(badge/version-).*(-blue\\.svg)#\\1${VERSION}\\2#" \
	"badge/version-${VERSION}-blue\\.svg"

# npm manifest:  "version": "0.4.0"  (first, top-level occurrence only)
apply "package.json" \
	"0,/\"version\":/ s/(\"version\":[[:space:]]*\")[^\"]*(\")/\\1${VERSION}\\2/" \
	"\"version\":[[:space:]]*\"${VERSION}\""

echo "Done."
