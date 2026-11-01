# Release Process

This document describes how to create a new release of Cassette-CMF Blocks (`pedalcms/cassette-cmf-block-builder`). It follows the parent library's process (`cassette-cmf/docs/RELEASE.md`) with the differences that come from this package having a compiled JavaScript bundle.

## Prerequisites

- All PHP tests passing (`composer test`), on the WordPress versions in the CI matrix (`.github/workflows/phpunit.yml`)
- All code standards passing (`composer cs:all`)
- PHPStan passing (`composer analyse`)
- JavaScript lint and unit tests passing (`npm run lint:js`, `npm run test:unit:js`)
- **`npm run build` run, and `src/assets/build/` committed** (see [Built assets](#built-assets))
- Changes documented in `CHANGELOG.md`

`composer ci` runs the code standards, PHPStan and PHPUnit in one go.

## Version Numbering

The library follows [Semantic Versioning](https://semver.org/). Before `1.0.0`, a minor release may contain breaking changes; every one is listed under "Breaking" in `CHANGELOG.md` with a one-line migration note.

- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (0.X.0): New features (before 1.0 also breaking changes, documented)
- **PATCH** (0.0.X): Bug fixes, backward compatible

Tags are `vX.Y.Z`. The release workflow only accepts a plain `X.Y.Z` version (see [Pre-release versions](#pre-release-versions)).

### Which version is the library's "own" version?

The tag. Composer infers the package version from git tags, which is why `composer.json` deliberately has **no** `version` key. The only version-bearing files in the repository are the README badge, `package.json` and `package-lock.json`; `bin/set-version.sh` keeps those in step with the tag.

## Release Steps

### 1. Update CHANGELOG.md

Turn the `[Unreleased]` heading into the version section and start a fresh empty `[Unreleased]` above it:

```markdown
## [Unreleased]

## [0.2.0] - 2026-11-15

### Breaking
- ... with a migration note

### Added
- ...

### Fixed
- ...
```

Keep the heading in the `## [X.Y.Z] - date` form: the release workflow extracts the GitHub Release notes with an `awk` pattern that looks for `## [X.Y.Z]` and stops at the next `## [x.y.z]` heading. If the section is missing the release is still created, with only "Release vX.Y.Z" as its notes.

### 2. Rebuild and commit the editor bundle

```bash
npm ci
npm run build
git status src/assets/build/    # must be clean, or commit what changed
```

### 3. Commit

You do not need to bump the version by hand. `bin/set-version.sh` runs from the tag (step 5) and writes it into `README.md` (the `badge/version-X.Y.Z-blue.svg` badge) and `package.json` (the top-level `"version"`). It also updates `package-lock.json` (the top-level `"version"` and `packages[""].version`). It does not touch `composer.json` (on purpose, see above).

To preview or apply it yourself:

```bash
bin/set-version.sh 0.2.0      # also accepts v0.2.0; fails if a pattern matched nothing
git diff README.md package.json
```

```bash
git add CHANGELOG.md src/assets/build/
git commit -m "Prepare release v0.2.0"
git push origin main
```

### 4. Create and push the tag

```bash
git tag -a v0.2.0 -m "Release v0.2.0"
git push origin v0.2.0
```

### 5. Automated release

Pushing the tag triggers two workflows:

- **Sync Version From Tag** (`.github/workflows/version-sync.yml`) normalizes the tag (`v0.2.0` becomes `0.2.0`), runs `bin/set-version.sh`, and, if anything was out of sync, commits the fix to the default branch and re-points the tag at the corrected commit (`git tag -f` plus a forced push of that tag).
- **Release** (`.github/workflows/release.yml`) checks out the tag and:
  1. Validates the version format (`X.Y.Z`, or `X.Y.Z-alpha.N`, `-beta.N`, `-rc.N`)
  2. Re-runs `bin/set-version.sh` so the package always shows the right version even if version-sync has not landed yet
  3. Rebuilds the editor bundle (`npm ci`, `npm run build`) and fails if `src/assets/build/` differs from the committed files (or is missing)
  4. Installs production dependencies (`composer install --no-dev --optimize-autoloader`)
  5. Builds the distribution package (`.zip` and `.tar.gz`) from the repository files minus everything `.distignore` lists, and checks that the bundle, `vendor/autoload.php` and `languages/` are in it and that sources and tooling are not
  6. Extracts the release notes from `CHANGELOG.md`
  7. Creates a GitHub Release with the archives attached; a tag with a `-alpha.N`, `-beta.N` or `-rc.N` suffix is marked as a pre-release

### 6. Verify the release

1. Open the [Releases](https://github.com/PedalCMS/cassette-cmf-block-builder/releases) page and check the notes.
2. Download an archive and check its contents (below).
3. Confirm the README badge and `package.json` on `main` show the new version.

## What is in the release archive

`release.yml` copies the repository files with `rsync --exclude-from=.distignore` (so `.distignore` decides what is left out), after installing the production `vendor/`. The archive therefore contains:

- the PHP source, and the compiled editor bundle in `src/assets/build/` (`editor.js`, `editor.asset.php`, `editor.css`, `editor-rtl.css`);
- `languages/` (the `.pot` template), `README.md`, `LICENSE`, `CHANGELOG.md`, `composer.json` and the production `vendor/`;
- no JavaScript source (`src/assets/src/`), `tests/`, `bin/`, `docs/`, `examples/`, `.github/`, lock files, `block-schema.json` or Node tooling.

### `.distignore`

`.distignore` lists the development paths in the format the WordPress.org tooling understands, and the release workflow reads it directly. To change what ships, edit `.distignore`. A pattern without a leading `/` also matches inside `vendor/` (so a dependency's own `tests/`, `docs/` and `bin/` are left out too). Consumers who bundle the library into a plugin can use the list as a starting point for their own packaging; see [wordpress-org-compliance.md](wordpress-org-compliance.md).

## Built assets

The compiled editor bundle in `src/assets/build/` **is committed** (`.gitignore` explicitly un-ignores it), so a consumer never needs Node and a Composer install works from a tag. Three workflows protect it:

- **Build Editor Bundle** (`build.yml`): on a push to `main` that touches `src/assets/src/**`, `package.json` or `package-lock.json`, builds and, if the output changed, pushes it to a `build-assets` branch and opens a pull request.
- **Assets Drift** (`assets-drift.yml`): on pull requests that touch the sources or the build, fails if `npm run build` produces output different from the committed files.
- **Release** (`release.yml`): rebuilds the bundle and fails if it differs from the committed one.

Always run `npm run build` after changing anything in `src/assets/src/` and commit the result in the same change. The build hash in `editor.asset.php` is the script version WordPress uses for cache busting.

## Pre-release versions

The tag filter (`v*.*.*`) matches `v1.0.0-rc.1`. The version check accepts `X.Y.Z` and `X.Y.Z-alpha.N`, `-beta.N`, `-rc.N`, and any tag with such a suffix is published as a GitHub pre-release. `bin/set-version.sh` accepts the suffix too.

## Quick release commands

```bash
# Run all checks
composer ci
npm run lint:js && npm run test:unit:js
npm run build && git status --short src/assets/build/

# Create release
VERSION="0.2.0"
git add CHANGELOG.md src/assets/build/
git commit -m "Prepare release v$VERSION"
git push origin main
git tag -a "v$VERSION" -m "Release v$VERSION"
git push origin "v$VERSION"
```

## Hotfix Process

For urgent bug fixes to a released version:

```bash
# Create hotfix branch from tag
git checkout -b hotfix/0.2.1 v0.2.0

# Make fixes, rebuild the bundle if JS changed, update CHANGELOG.md
git add .
git commit -m "Fix critical bug X"

# Merge to main
git checkout main
git merge hotfix/0.2.1
git push origin main

# Tag the release
git tag -a v0.2.1 -m "Release v0.2.1"
git push origin v0.2.1

# Clean up
git branch -d hotfix/0.2.1
```

## Troubleshooting

### Release workflow failed

1. Check the [Actions tab](https://github.com/PedalCMS/cassette-cmf-block-builder/actions) for error details.
2. Common issues:
   - Invalid version format (must be `vX.Y.Z`)
   - `src/assets/build/editor.js` missing from the tag
   - Missing `CHANGELOG.md` section for the version (the release is created with a one-line note)
   - A pre-release suffix in the tag (see above)

### The release contains a stale editor bundle

The release workflow does not rebuild. Run `npm run build`, commit `src/assets/build/`, delete the release and the tag (below), and tag again.

### Need to delete a release

```bash
# Delete the tag locally and remotely
git tag -d v0.2.0
git push origin :refs/tags/v0.2.0
```

Then delete the release from the GitHub UI.

### Refreshing `composer.lock` after a cassette-cmf release

`composer.json` requires `pedalcms/cassette-cmf: ^0.2.0`. Until that version is tagged, `composer.lock` cannot be refreshed against it (`composer validate` reports the lock as out of date). After tagging cassette-cmf 0.2.0, run `composer update pedalcms/cassette-cmf` here and commit `composer.lock` before tagging this package.

### PHP 7.4 cell in CI

`phpunit.yml` includes a PHP 7.4 x WordPress 6.8 cell. It can only resolve once a `pedalcms/cassette-cmf` release declaring `php >=7.4` (0.2.0 or newer) is published, because 0.1.2 requires PHP 8.2. If that cell fails with a Composer "requires php" error, that is the cause.
