# Simple Example - JSON Configuration

The same capabilities as [`01-simple-array`](../01-simple-array/), registered from an external JSON file instead, and validated against `Json\Block_Schema_Validator` first (`register_from_json()`'s default `$validate = true`).

## What This Example Creates

### Block: Inline Note (`acme/inline-note`)

- **Pinned** (`toolbar_toggle`, toolbar)
- **Note** (`textarea`, required)
- **Type** (`select`) - Info or Warning; drives a `--{{ attributes.note_type }}` class modifier on the front end.
- **Show author** (`toggle`)
- **Author name** (`text`) - Shown only once "Show author" is switched on, via `conditional`.

## File Structure

```
02-simple-json/
├── example.php    # Plugin file (PHP loader)
├── config.json     # Configuration (JSON)
└── README.md       # This file
```

## Usage

### Loading JSON Config

```php
use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;

add_action( 'init', function () {
    CassetteCmfBlocks::register_from_json( __DIR__ . '/config.json' );
}, 5 );
```

Registering at `init` priority 5 (not the default 10) matters here too — see [`01-simple-array`'s README](../01-simple-array/README.md#usage) for why.

### JSON Schema Validation

```php
// With validation (default)
CassetteCmfBlocks::register_from_json( $path );

// Skip validation
CassetteCmfBlocks::register_from_json( $path, false );
```

`Json\Block_Schema_Validator` reads its valid control types from `Schema\Control_Catalog` and its valid areas from `Schema\Area_Resolver` rather than a hardcoded list, so it never drifts out of sync with what the runtime actually accepts.

## Conditional Fields

This example includes conditional visibility in JSON, using the same `conditional` shape a PHP array config uses:

```json
{
    "name": "author_name",
    "type": "text",
    "conditional": {
        "field": "show_author",
        "operator": "equals",
        "value": true
    }
}
```

## JSON vs Array Configuration

| Feature | JSON | Array |
|---|---|---|
| External file | Yes | No |
| Schema validation | Yes | No |
| PHP knowledge needed | No | Yes |
| Translated labels (`__()`) | No — plain strings only | Yes |
| PHP callbacks (`render.callback`) | No | Yes |
| CI/CD friendly | Yes | Limited |

## For Advanced Features

See [`04-advanced-json`](../04-advanced-json/) for every editor area, meta binding, static save with a deprecation, and the `repeater` control, all from JSON — plus what genuinely can't be expressed in JSON at all.
