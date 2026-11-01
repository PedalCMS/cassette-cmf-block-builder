# Cassette-CMF Blocks

[![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net)
[![Version](https://img.shields.io/badge/version-0.2.0-blue.svg)](https://github.com/PedalCMS/cassette-cmf-block-builder/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Declarative Gutenberg blocks for [Cassette-CMF](https://github.com/PedalCMS/cassette-cmf). Register full-featured blocks — inspector panels, toolbars, canvas previews, conditional logic, InnerBlocks, meta binding — from the same PHP array and JSON patterns as the core library, writing no JavaScript and running no build step.

## Status

`v0.0.1` — first tagged release; **do not use it with `pedalcms/cassette-cmf` 0.1.1 or newer** (it imports the old namespace casing and fatals) — use the first release after it; see [`CHANGELOG.md`](CHANGELOG.md).

Original `v0.0.1` notes: Feature-complete against the original design for a first release: registration, the full inspector/toolbar/canvas/document/sidebar editor runtime, dynamic and static rendering, conditionals, InnerBlocks, meta binding, the complete control catalog, variations, patterns, block-to-block, shortcode and raw transforms. Pre-1.0: per SemVer, no compatibility guarantee is made between `0.x` releases yet — see [`docs/RELEASE.md`](docs/RELEASE.md).

## What it does

- **Zero consumer JavaScript.** A block is declared as a PHP array or a JSON file, in the same field-declaration shape `cassette-cmf` uses for post types and settings pages. The compiled editor bundle that turns that declaration into a working block ships inside the package — no build step in the consuming plugin.
- **One declarative markup tree, three runtimes.** A single `render.markup` tree drives the front-end HTML, an instant network-free editor canvas preview, and (optionally) a static block's own saved content — always in agreement, because it's the same declaration.
- **A full control catalog** — every common inspector/toolbar/canvas widget, entity pickers, a real repeater, and document/sidebar-area panels — see `docs/control-catalog.md`.
- **Real meta binding**, both as a field's own `source: 'meta'` and as an opt-in `{prefix}/field` block bindings source any core block can use (only for fields you explicitly mark `bindings: true`) — see `docs/meta-binding.md`.

## Requirements

| | Minimum |
|---|---|
| PHP (`Requires PHP`) | 7.4 |
| WordPress (`Requires at least`) | 6.8 |
| `pedalcms/cassette-cmf` | 0.2.0 (the release with the block bindings helpers and the PHP 7.4 floor; hard Composer dependency — see [Relationship to cassette-cmf](#relationship-to-cassette-cmf)) |

Enforced at runtime, not just declared: `Compat\Requirements::check()` verifies all three before anything registers. If any floor isn't met, this library registers **nothing at all** — never a partial or corrupted registration — and shows an admin notice explaining why.

## Installation

```bash
composer require pedalcms/cassette-cmf-block-builder
```

This also pulls in `pedalcms/cassette-cmf`. No `npm install` or build step is required in a consumer plugin — the compiled editor bundle ships inside the package at `src/assets/build/`.

**Submitting a plugin that bundles this library to the WordPress.org Plugin Directory?** See [`docs/wordpress-org-compliance.md`](docs/wordpress-org-compliance.md) for what the library does and what your own plugin still needs to supply (prefixing, a `readme.txt` source-code section, stripping dev files from the bundled package, and so on).

## Quick start

```php
use PedalCMS\CassetteCMF\CassetteCMF;
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
	'init',
	function () {
		// Your own prefix: it derives every handle, the REST namespace, the
		// binding source and the JS global (see docs/coexistence.md).
		CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] );

		CassetteCMF::register_from_json( __DIR__ . '/config/content.json' );
		CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
	},
	5
);
```

Priority 5, one tick ahead of both libraries' own `init@10` registration.

### Boot options

Call `CassetteCMFBlocks::boot( [ 'prefix' => '...' ] )` once, **before** any `register_from_*()`. The `prefix` is required: calling `register_from_*()` first, or `boot()` without a valid prefix, raises a `_doing_it_wrong()` notice and uses the fallback `cassette-cmf`, which can collide with another plugin that did the same. A later `boot()` with different options is ignored with a `_doing_it_wrong()` notice (as are unknown option keys).

| Option | Default | Effect |
|---|---|---|
| `prefix` | none (required) | Your own identifier, `^[a-z][a-z0-9-]{1,30}[a-z0-9]$` (for example `acme`). Derives the editor script and style handles (`{prefix}-blocks-editor[-style]`), the REST namespace (`{prefix}-blocks/v1`), the binding source (`{prefix}/field`) and the JS payload global (`window.cassetteCmfBlocks_{camelPrefix}`), so two plugins that ship this library never collide. See [`docs/coexistence.md`](docs/coexistence.md). |
| `bindings_source` | `false` | Registers the `{prefix}/field` block bindings source. Off by default: see [`docs/meta-binding.md`](docs/meta-binding.md#mechanism-b--the-prefixfield-block-bindings-source). |

## Documentation

| Document | Covers |
|---|---|
| [`docs/block-config-reference.md`](docs/block-config-reference.md) | The full registration API: `blocks`, `fields`, `args` (allow-listed keys, asset keys), the server-side attribute API, `render`, `preview`, `inner_blocks`, `transforms`, `document_scope`, `patterns`, filters, and known limitations. |
| [`docs/control-catalog.md`](docs/control-catalog.md) | Every control type the editor renders, what it produces, and how an unimplemented type behaves. |
| [`docs/markup-templates.md`](docs/markup-templates.md) | The declarative `render.markup` node tree: expressions, escaping, conditionals, repeats, InnerBlocks slots. |
| [`docs/coexistence.md`](docs/coexistence.md) | The required `prefix` boot option: what it derives, how to pick one, running two copies (plain or Strauss-prefixed) on one site. |
| [`docs/meta-binding.md`](docs/meta-binding.md) | Binding a field to real post/term/user/comment meta, and binding *any* block to a `cassette-cmf` value via block bindings. |
| [`docs/static-save.md`](docs/static-save.md) | `render.mode: 'static'`, deprecations for static and dynamic blocks (declarative `migrate`, `migrate_php`), and the `registerMigration()` extension point. |
| [`docs/wordpress-org-compliance.md`](docs/wordpress-org-compliance.md) | Bundling the library in a WordPress.org plugin: prefixing (Strauss and the `prefix` option), no remote assets, escaping, i18n, GPL, the `readme.txt` source-code section, Plugin Check notes. |
| [`docs/RELEASE.md`](docs/RELEASE.md) | Version numbering, `bin/set-version.sh`, the changelog, tagging, the committed `src/assets/build/` bundle and the release workflow. |
| [`docs/STABILITY.md`](docs/STABILITY.md) | The 1.0 stability promise: what is frozen (attribute mapping, render pipeline, sanitizer outputs, `@api` PHP, JS extension points, hooks, identity, JSON shapes), what is not, and the semver rule. |

Worked, runnable examples live in `examples/` — a minimal PHP array config and its JSON equivalent (`01-simple-array`, `02-simple-json`), then a set of 5 form-field blocks and its JSON equivalent (`03-advanced-array`, `04-advanced-json`) demonstrating the label→slug auto-derive, `options_list`, and a native-looking canvas preview built from real (disabled) HTML form controls. Each example directory has its own README.

## Relationship to cassette-cmf

This library is a **completely optional** companion to `pedalcms/cassette-cmf`. It reuses the parent's field `sanitize()`/`validate()` implementations and conditional-logic normalisation through a single compatibility class (`src/compat/class-cmf-bridge.php`); it never calls the parent's `render()`, which produces admin HTML unsuited to the block editor.

## Development

```bash
composer install
npm install

composer run ci      # phpcs + phpstan + phpunit
npm run build         # compile the editor bundle into src/assets/build/
npm run lint:js
npm run test:unit:js
```

PHPUnit requires a WordPress test environment. Either run `composer run test-install` (needs `svn`) or point `WP_TESTS_DIR`/`WP_CORE_DIR` at an existing WordPress checkout and a `wp-phpunit/wp-phpunit` install (already pulled in as a dev dependency), then set `WP_TESTS_DB_*` environment variables for a scratch database.

An end-to-end suite (Playwright, via `@wordpress/env`) lives in `tests/e2e/` — `npm run env start` then `npm run test:e2e`. The fixture plugin it drives (`tests/e2e/fixture-plugin/`) registers its demo blocks from a plain PHP config array and contains no JavaScript file of its own — that absence is itself the proof of the zero-consumer-JS claim.

## License

GPL-2.0-or-later. See `LICENSE`.
