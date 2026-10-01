# Simple Example - PHP Array Configuration

A minimal example demonstrating `cassette-cmf-block-builder` basics using PHP array configuration: one block, registered with `CassetteCMFBlocks::register_from_array()`, with a working toolbar control, an inspector panel, a conditional field, and a declarative `render.markup` tree that drives both the front-end HTML and the editor's instant canvas preview.

## What This Example Creates

### Block: Callout (`acme/callout`)

- **Featured** (`toolbar_toggle`, toolbar) - A toggle shown directly in the block toolbar.
- **Heading** (`text`, required) - The callout's heading text.
- **Dismissible** (`toggle`) - Whether the callout shows a dismiss button.
- **Dismiss button label** (`text`) - Shown only once "Dismissible" is switched on, via `conditional`.

The block's `render.markup` renders a `<div class="acme-callout">` with a heading and, when `is_dismissible` is true, a dismiss `<button>` — the exact same tree renders the front-end HTML (PHP), the editor's canvas preview (React, no network request), and reacts live as `is_dismissible`/the two text fields are edited in the inspector.

## File Structure

```
01-simple-array/
├── example.php    # Plugin file (registration + comments)
└── README.md      # This file
```

## Usage

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action( 'init', function () {
    CassetteCMFBlocks::register_from_array( [
        'blocks' => [
            [
                'id'     => 'acme/callout',
                'args'   => [ 'title' => 'Callout', 'category' => 'common', 'icon' => 'megaphone' ],
                'fields' => [ /* ... */ ],
                'render' => [ 'markup' => [ /* ... */ ] ],
            ],
        ],
    ] );
}, 5 );
```

**Register at `init` priority 5, not the default 10.** `Block_Manager` self-hooks the actual `register_block_type()` call at `init` priority 10, the first time it's created — which happens on your first `register_from_array()`/`register_from_json()` call. If that call happens from *inside* a priority-10 `init` callback, the self-hook is added too late to run in the same request and the block silently never reaches `WP_Block_Type_Registry`. Priority 5 sidesteps this entirely; every example in this library follows it.

## Key Concepts Demonstrated

1. **`register_from_array()`** - The PHP-array registration entry point.
2. **`args`** - A verbatim `block.json`-shaped passthrough (`title`, `category`, `icon`, ...).
3. **`fields`** - The same flat field shape `cassette-cmf` itself uses, plus block-specific keys (`area`, `source`).
4. **`area`** - `toolbar` for the toolbar toggle; the default (unset) area is the inspector.
5. **`conditional`** - Live, in both the inspector and the canvas preview, not just on the front end.
6. **`render.markup`** - One declarative tree, three runtimes (PHP render, editor preview, and — for `render.mode: 'static'` — the block's own saved content). See [../../docs/markup-templates.md](../../docs/markup-templates.md).

## For Advanced Features

See [`03-advanced-array`](../03-advanced-array/) for:
- Every editor area (toolbar, a named inspector group, a document-wide panel, a sidebar)
- Meta binding (`source: 'meta'`)
- Static save (`render.mode: 'static'`) with a deprecation
- The `repeater` control
