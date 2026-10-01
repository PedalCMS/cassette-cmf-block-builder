# Advanced Example - JSON Configuration

The same 5 field blocks as [`03-advanced-array`](../03-advanced-array/), registered from a single external `config.json` instead of a PHP array. Read that example's README for what each block demonstrates (the label→slug auto-derive, `options_list`, the native-looking canvas preview) — this file covers only what changes when the same config moves from PHP to JSON.

## File Structure

```
04-advanced-json/
├── example.php    # Plugin file (PHP loader)
├── config.json     # All five blocks
└── README.md       # This file
```

## Usage

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action( 'init', function () {
    CassetteCMFBlocks::register_from_json( __DIR__ . '/config.json' );
}, 5 );
```

Priority 5, not the default 10 — see [`01-simple-array`'s README](../01-simple-array/README.md#usage) for why this matters for every example in this library.

## What Doesn't Translate to JSON

- **No `__()`.** Every label/title in `config.json` is a plain string.
- **No PHP helper functions.** `03-advanced-array/example.php` builds the label/slug/help/required field set once (`cassette_cmf_forms_common_fields()`/`cassette_cmf_forms_help_and_required_fields()`) and reuses it across all five blocks; JSON has no equivalent, so the same field list is written out in full for each block in `config.json` instead.

Everything else — `derive_from`/`format` on the `slug` field, `options_list`, `when`-gated nodes, the shared chrome classes — is plain data and translates one-for-one, verified by comparing `config.json` against `03-advanced-array/example.php`'s array shape field-for-field.

## JSON Schema Validation

`register_from_json()`'s default `$validate = true` runs `config.json` through `Json\Block_Schema_Validator` before anything registers — a typo in a control type or an unknown area is caught at registration time, not discovered later in the editor.

## For a Simpler Starting Point

See [`02-simple-json`](../02-simple-json/) for a single block, single field type, minimal `config.json` to start from.
