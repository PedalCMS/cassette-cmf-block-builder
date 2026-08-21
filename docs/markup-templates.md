# Markup templates

A block's `render.markup` (and optionally a distinct `preview.markup`) is a declarative tree that drives **all three** runtimes a block can have: the front-end HTML for a dynamic block (PHP), the editor's instant canvas preview (React, no network request), and — for `render.mode: 'static'` — the block's own saved content (also React; see [static-save.md](static-save.md)). One declaration, three outputs, always in agreement.

## A node

```php
[
    'tag'      => 'div',                                   // HTML tag; defaults to "div".
    'class'    => 'acme-callout acme-callout--{{ attributes.tone }}',
    'attrs'    => [ 'data-open' => '{{ attributes.is_open }}' ],
    'text'     => '{{ attributes.heading }}',                // esc_html()'d by default.
    'html'     => '{{ attributes.body }}',                    // wp_kses_post()'d by default.
    'escape'   => 'html',                                     // html (default for "text") | kses (default for "html") | attr | url | raw
    'when'     => [ 'rules' => [ [ 'field' => 'is_open', 'operator' => '==', 'value' => true ] ] ],
    'repeat'   => [ 'over' => 'attributes.items', 'as' => 'item' ],
    'slot'     => 'inner_blocks',                             // inner_blocks | content — inserts the block's own already-rendered InnerBlocks content, unescaped.
    'children' => [ /* nested nodes */ ],
]
```

Every property is optional; a node with none of them renders an empty tag. `text` and `html` are mutually exclusive in practice — a node is either a leaf with content or a container with `children` — nothing enforces that, but combining them rarely makes sense.

## Expressions

`{{ path | filter1 | filter2:arg }}` — a **fixed**, closed filter set. No arbitrary evaluation, no PHP callables reachable from config. `path` is dot-notation into the current scope (`attributes.tone`, or `item.label` inside a `repeat`).

| Filter | Effect |
|---|---|
| `default:VALUE` | Substitutes `VALUE` when the path resolves to `null`, `''`, or `[]` — never for `0`/`false`. |
| `upper` / `lower` | Case conversion. |
| `slug` | `sanitize_title()`. |
| `esc_url` | `esc_url()`. |
| `date:FORMAT` | `date_i18n()` (falls back to `gmdate()`), timestamp or `strtotime()`-parseable string; `FORMAT` defaults to `Y-m-d`. |
| `number:DECIMALS` | `number_format()`; `DECIMALS` defaults to `0`. |
| `join:SEP` | `implode()` for an array value; `SEP` defaults to `, `. |
| `count` | `count()` for an array/countable value. |
| `not` | Boolean negation, stringified as `1`/`''`. |

An unknown filter name is a silent no-op (the value passes through unchanged), and a missing path resolves to `null` — a typo in an expression never fatals the render. The PHP and JS evaluators are kept identical against a shared fixture of `{ template, scope, expected }` cases.

## Escaping — per-node-kind, non-optional

| Node part | Escaping |
|---|---|
| `text` | `esc_html()` (default), or `escape`'s value. |
| `html` | `wp_kses_post()` (default), or `escape`'s value. |
| `attrs` | `esc_attr()`, except `href`/`src`/`action` which get `esc_url()` instead. Never configurable. |
| `class` | Each whitespace-separated token through `sanitize_html_class()`. Never configurable. |

The **only** opt-out is an explicit `'escape' => 'raw'` on a node with `text`/`html` content — documented consumer-owned trust, never the default. `attrs`/`class` escaping cannot be turned off.

In the editor preview, React itself is the escaping layer for `text` content (a plain string child is always safe); `html` content and `escape: "raw"` both use `RawHTML` (React's `dangerouslySetInnerHTML` wrapper) unfiltered — an accepted editor-only scope, since the block editor is already restricted to `edit_posts` users, and the real front-end output always goes through the full PHP escaping regardless of what the editor preview shows.

## The root node and block supports

The tree's root node is handled specially: its `class`/`attrs` merge through `get_block_wrapper_attributes()` (PHP) / `useBlockProps()` (JS, editor) / `useBlockProps.save()` (JS, static save) rather than this library's own per-attribute-kind escaping, so a block's `supports` (color, spacing, align, anchor, ...) actually emit their classes/styles/id onto the real wrapper element — the same mechanism every hand-written dynamic block uses.

## `when` — conditional nodes

A node's `when` uses the exact same `{ relation?, rules[] }` shape as a field's `conditional` (see [block-config-reference.md](block-config-reference.md)). It's evaluated on the front end, in the editor's live canvas preview, and — for a static block — in `save()`'s own output: a node whose condition fails renders nothing at all, not even an empty tag. A **root** node whose own `when` fails is the one exception — it renders an empty wrapper instead of nothing, so the block stays selectable in the editor and a static block's saved content still has a real root element.

## `repeat` — repeating a node

```php
[
    'tag'    => 'li',
    'text'   => '{{ item.label }}',
    'repeat' => [ 'over' => 'attributes.items', 'as' => 'item' ],
]
```

The node repeats once per item in the array `repeat.over` resolves to, with the scope extended by `{as}` (and `{as}_index`) for each iteration. `repeat.over` resolving to anything other than an array renders nothing, not a fatal.

## `slot` — inserting InnerBlocks content

`'slot' => 'inner_blocks'` (or the `'content'` alias) renders the block's own child blocks at that position: a real, editable `<InnerBlocks />` in the editor canvas (when the block declares a top-level `inner_blocks` config — see [block-config-reference.md](block-config-reference.md), the "inner_blocks" section), `<InnerBlocks.Content />` in a static block's `save()` output, and the block's own already-rendered child HTML on the front end — never escaped, since it's WP-trusted rendered block markup, the same way a hand-written dynamic block simply echoes `$content`.

## `preview.markup` vs `render.markup`

A block's `preview.markup`, when set, overrides `render.markup` for the editor's canvas preview specifically — useful for a simplified editor-only preview that differs from the real front-end output. When unset (the common case), the editor preview falls back to `render.markup` itself: one tree, every runtime, no duplication.

## `render.mode: "static"` — the third runtime

`render.markup` also drives a static block's own saved content when `render.mode` is `"static"` — see [static-save.md](static-save.md) for the full reference, including deprecations and the PHP↔JS output-parity gate that keeps the two languages' renderers honest against each other.

## Known limitations

- **`preview.when`** (gating an editor preview, e.g. a server preview, until required fields are filled) isn't wired up, though the evaluator it would use already exists.
- **`preview.debounce`/`preview.skeleton`** config keys aren't read by anything — the server preview relies entirely on `@wordpress/server-side-render`'s own built-in debouncing and loading skeleton, which already cover the intent.
