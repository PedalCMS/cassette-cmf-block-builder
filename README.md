# Cassette-CMF Blocks

[![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg)](https://www.php.net)
[![Version](https://img.shields.io/badge/version-0.0.1-blue.svg)](https://github.com/PedalCMS/cassette-cmf-block-builder/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Declarative Gutenberg blocks for [Cassette-CMF](https://github.com/PedalCMS/cassette-cmf). Register full-featured blocks — inspector panels, toolbars, canvas previews, conditional logic, InnerBlocks, meta binding — from the same PHP array and JSON patterns as the core library, writing no JavaScript and running no build step.

## Status

`v0.0.1` — first tagged release. Feature-complete against the original design for a first release: registration, the full inspector/toolbar/canvas/document/sidebar editor runtime, dynamic and static rendering, conditionals, InnerBlocks, meta binding, the complete control catalog, variations, patterns, and a bounded set of block-to-block transforms. Pre-1.0: per SemVer, no compatibility guarantee is made between `0.x` releases yet — see `docs/RELEASE.md`.

## What it does

- **Zero consumer JavaScript.** A block is declared as a PHP array or a JSON file, in the same field-declaration shape `cassette-cmf` uses for post types and settings pages. The compiled editor bundle that turns that declaration into a working block ships inside the package — no build step in the consuming plugin.
- **One declarative markup tree, three runtimes.** A single `render.markup` tree drives the front-end HTML, an instant network-free editor canvas preview, and (optionally) a static block's own saved content — always in agreement, because it's the same declaration.
- **A full control catalog** — every common inspector/toolbar/canvas widget, entity pickers, a real repeater, and document/sidebar-area panels — see `docs/control-catalog.md`.
- **Real meta binding**, both as a field's own `source: 'meta'` and as a `cassette-cmf/field` block bindings source any core block can use — see `docs/meta-binding.md`.

## Requirements

| | Minimum |
|---|---|
| PHP | 8.2 |
| WordPress | 6.8 |
| `pedalcms/cassette-cmf` | 0.1.0 (hard Composer dependency — see [Relationship to cassette-cmf](#relationship-to-cassette-cmf)) |

Enforced at runtime, not just declared: `Compat\Requirements::check()` verifies all three before anything registers. If any floor isn't met, this library registers **nothing at all** — never a partial or corrupted registration — and shows an admin notice explaining why.

## Installation

```bash
composer require pedalcms/cassette-cmf-block-builder
```

This also pulls in `pedalcms/cassette-cmf`. No `npm install` or build step is required in a consumer plugin — the compiled editor bundle ships inside the package at `src/assets/build/`.

**Submitting a plugin that bundles this library to the WordPress.org Plugin Directory?** See `docs/wordpress-org-compliance.md` — what this library has already been audited for, and what your own plugin still needs to supply (a `readme.txt`, `vendor/` committed to your distribution, and so on).

## Quick start

```php
use PedalCMS\CassetteCMF\CassetteCMF;
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
	'init',
	function () {
		CassetteCMF::register_from_json( __DIR__ . '/config/content.json' );
		CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
	},
	5
);
```

Priority 5, one tick ahead of both libraries' own `init@10` registration.

## Documentation

| Document | Covers |
|---|---|
| [`docs/block-config-reference.md`](docs/block-config-reference.md) | The full registration API: `blocks`, `fields`, `args`, `render`, `preview`, `inner_blocks`, `transforms`, `document_scope`, `patterns`, filters, and known limitations. |
| [`docs/control-catalog.md`](docs/control-catalog.md) | Every control type the editor renders, what it produces, and how an unimplemented type behaves. |
| [`docs/markup-templates.md`](docs/markup-templates.md) | The declarative `render.markup` node tree: expressions, escaping, conditionals, repeats, InnerBlocks slots. |
| [`docs/meta-binding.md`](docs/meta-binding.md) | Binding a field to real post/term/user/comment meta, and binding *any* block to a `cassette-cmf` value via block bindings. |
| [`docs/static-save.md`](docs/static-save.md) | `render.mode: 'static'`, deprecations, and the migration escape hatch. |
| [`docs/wordpress-org-compliance.md`](docs/wordpress-org-compliance.md) | What's been audited for WordPress.org Plugin Directory guidelines, and what a host plugin still has to supply. |
| [`docs/RELEASE.md`](docs/RELEASE.md) | Version numbering and the tag-triggered release process. |

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
