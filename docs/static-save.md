# Static save

`render.mode` accepts three values:

| Mode | Front-end output comes from | `save()` |
|---|---|---|
| `dynamic` (default) | `render_callback`, computed fresh on every request | always returns `null` |
| `static` | the block's own saved content, embedded directly in `post_content` | serializes `render.markup` |
| `none` | nothing — an intentionally output-less block | always returns `null` |

**Changing a static block's markup (or its attribute schema) without adding a `render.deprecated` entry invalidates every post that already contains it.** WordPress compares a block's freshly-computed `save()` output against what's actually stored in `post_content` every time the editor opens a post; a mismatch shows "This block contains unexpected or invalid content" — the same failure mode a hand-written static block has if its `save()` changes incompatibly. This library doesn't remove that risk — nothing can, it's how block validation fundamentally works — it just gives you a declarative way to describe old versions instead of hand-writing a second `save()` function.

Dynamic mode has none of this burden: there's no saved markup to validate against, only attributes, so changing `render.markup` (or even switching to a `render.callback`/`render.template`) is always safe for existing content.

## A static block

```php
[
    'id'     => 'acme/quote',
    'fields' => [ /* ... */ ],
    'render' => [
        'mode'   => 'static',
        'markup' => [
            'tag'      => 'blockquote',
            'class'    => 'acme-quote',
            'children' => [
                [ 'tag' => 'p', 'text' => '{{ attributes.quote_text }}' ],
                [
                    'tag'  => 'cite',
                    'text' => '{{ attributes.cite }}',
                    'when' => [ 'rules' => [ [ 'field' => 'cite', 'operator' => 'not_empty' ] ] ],
                ],
            ],
        ],
    ],
]
```

The exact same node tree a `dynamic` block's `render.markup` would use — one declarative tree, every runtime. `save()` renders it using `useBlockProps.save()` for the root (the static equivalent of a dynamic block's `get_block_wrapper_attributes()`/`useBlockProps()`) and `<InnerBlocks.Content />` in place of a `slot: 'inner_blocks'`/`'content'` node — there's no rendered content string to inject during `save()`; WordPress itself expands that component when serializing.

`when` is evaluated in `save()`'s own output, and in the editor's live preview, exactly as it is on the front end — a node whose condition fails is omitted from the saved markup entirely; the root node's own `when` (if it fails) still produces an empty wrapper rather than nothing, so a static block's saved content always has a real root element to re-render from.

## `render.deprecated` — declaring history

```php
'render' => [
    'mode'       => 'static',
    'markup'     => [ /* current tree */ ],
    'deprecated' => [
        [
            'markup'     => [ /* v1 tree */ ],
            'attributes' => [ /* v1's WP attribute schema — omit to reuse the current schema */ ],
            'migrate'    => 'acme_migrate_v1',
        ],
    ],
]
```

Each entry becomes one of `registerBlockType()`'s own `deprecated` array items. WordPress tries each one's `save()` in turn against a block's stored HTML until one matches, then (if `migrate` is set) runs it to bring old attributes up to the current shape.

- **`markup`** (required) — the old tree, rendered through its own fresh `save()`, so an earlier deprecation's markup never leaks into a later one's output.
- **`attributes`** (optional) — the old WP attribute schema. Omit it for a markup-only change (the common case) and it falls back to the block's *current* schema. Declare it explicitly when the attribute shape itself changed too (a renamed/retyped/removed attribute) — WordPress validates old saved content against *this* schema, not the current one.
- **`migrate`** (optional) — a **name**, not a function: PHP/JSON config has no way to embed real code, so `migrate` is resolved client-side against a small registry. Register the actual migration via the same undocumented JS escape hatch `docs/control-catalog.md#the-undocumented-js-escape-hatch` describes:

  ```js
  window.cassetteCmfBlocks.registerMigration( 'acme_migrate_v1', ( attributes ) => ( {
      ...attributes,
      cite: attributes.author ?? '', // e.g. an attribute rename.
  } ) );
  ```

  A name with nothing registered for it is silently dropped — WordPress then carries the old attributes over unchanged, its own safe default when no `migrate` is present. **This registry must be populated before this library's own editor script finishes running** — `registerBlockType()`'s `deprecated` array is built once, synchronously, at registration time, and a migration reference gets baked in or omitted at that moment, not re-resolved later. In practice this means enqueuing your own script as a dependency of `cassette-cmf-block-builder-editor` and calling `registerMigration()` at the top level of that script, not inside a deferred callback.

## What isn't auto-detected

There is no automatic "markup changed since last deploy, deprecation synthesized for you" mechanism. A content hash of the current markup + attribute schema is exposed in the editor payload (`render.hash`, for your own tooling), but nothing here persists *previous* hashes or markup trees to diff against — that needs a storage design this library doesn't specify, and inventing one speculatively risks getting the wrong shape for your deployment model. `render.deprecated`, declared explicitly, already fully covers WordPress's actual requirement — no core block auto-detects this either; a hand-written block that changes its `save()` needs a hand-written `deprecated` entry too.

## Known limitations

The full round-trip canary the design called for — "re-open a published post containing a static-save block in a real browser and assert zero validation errors" — needs a live editor session (Playwright) this library's own automated suite exercises only as configuration, not a running assertion; see `docs/RELEASE.md`'s note on the E2E suite. What *is* verified automatically: the PHP and JS markup renderers produce structurally identical output for the same node tree, across a shared fixture set covering every node kind (tag, class, text, attrs, void elements, children, repeat) — the two-language parity gate that keeps them honest against each other.
