# Meta binding

Two independent mechanisms for reading and writing values that live outside a block's own attributes: **Mechanism A** (`source: 'meta'`) for editable, revisioned values that belong to the block's own field; **Mechanism B** (the `cassette-cmf/field` block bindings source) for read-only/derived and cross-entity references — a *core* block reading a value that lives somewhere else entirely (post meta, term meta, a settings-page option).

## Mechanism A — `source: 'meta'`

```php
[
    'name'   => 'internal_note',
    'type'   => 'textarea',
    'label'  => __( 'Internal note', 'acme' ),
    'source' => 'meta',
    'area'   => 'inspector.bindings',   // conventional, not required
    'meta'   => [
        'key'            => 'acme_internal_note',   // defaults to the field's own "name"
        'object_type'    => 'post',                 // 'post' (default) | 'term' | 'user' | 'comment'
        'object_subtype' => '',                      // e.g. a specific post type; '' registers for all
        'single'         => true,                     // default true
        'auth'           => 'edit_posts',              // a capability string, or a callable( $object_id, $meta_key ): bool
        'revisions'      => false,                     // 'post' object type only
    ],
]
```

`Binding\Meta_Registrar` calls WordPress core's `register_meta()` for every `source: 'meta'` field, on `init`, with:

- **`type`/`default`/`enum`** from `Schema\Meta_Schema_Mapper::to_json_schema()` — the same type/default/enum resolution the block-attribute schema mapper uses, so a field behaves consistently regardless of which source it declares.
- **`show_in_rest` with a real schema** — `['schema' => $schema]`, never a bare `true`/`false`. This is mandatory, not cosmetic: without a real schema, `@wordpress/core-data`'s `useEntityProp()` — the hook `value/useFieldValue.js`'s `'meta'` source reads and writes through — can neither read nor write the meta key at all. This directly fixes a real gap in `cassette-cmf` itself: the parent library reads/writes post and term meta via plain `get_post_meta()`/`update_post_meta()` calls and never registers meta with WordPress at all, so none of its own meta fields are reachable via the REST API on their own.
- **`sanitize_callback`** routed through `cassette-cmf`'s own sanitizer for the field's `cmf_type` — the same sanitizer every other value in this library goes through.
- **`auth_callback`** from `meta.auth` (a capability string checked via `current_user_can( $capability, $object_id )`, or a callable), defaulting to a sensible per-object-type capability (`edit_post`/`edit_term`/`edit_user`/`edit_comment`) when unset.

**A custom post type must also declare `'supports' => ['custom-fields']`** (or call `add_post_type_support( $post_type, 'custom-fields' )`) for its registered meta to be reachable via the REST API at all — a WordPress requirement, not this library's own. Nothing here adds that support for you; auto-adding it to an arbitrary post type would be a side effect this library has no business taking on unprompted.

A repeater's own sub-fields cannot be `source: 'meta'` in any meaningful way — a row field has no fixed meta key to register against, since its value lives inside one row of the repeater's own array attribute. Declaring one is silently excluded from meta registration rather than producing a broken `register_meta()` call.

### `postId`/`postType` context — added for you

A block with at least one `'meta'`-sourced field targeting `object_type: 'post'` (the default) automatically gains `'postId'` and `'postType'` in its own `usesContext`, merged with — never overwriting — anything the block already declares. This is what lets the client-side hook call `useEntityProp( 'postType', postType, 'meta' )` at all: it needs to know which post type it's reading/writing, and a block has no way to know that about itself unless it explicitly asks for it via block context.

## Mechanism B — the `cassette-cmf/field` block bindings source

```php
'metadata' => [
    'bindings' => [
        'content' => [
            'source' => 'cassette-cmf/field',
            'args'   => [
                'field'        => 'agency_phone',
                'context'      => 'agency-settings',   // post ID, term ID, or settings-page ID — omit for 'post' to use the block's own postId context
                'context_type' => 'settings',           // 'post' (default) | 'term' | 'settings'
            ],
        ],
    ],
],
```

This is declared the same way any WordPress block binding is — on the block instance's own `metadata.bindings`, in the post content, not in this library's own block-registration config — and works with **any** block, including core ones (`core/paragraph`, `core/heading`, ...), not just blocks this library registers. It resolves through `cassette-cmf`'s own public `CassetteCmf::get_field()` facade, so a block can bind to **any** parent-library value: post meta, term meta, or a settings-page option — the strongest argument for the two libraries existing together, since a site-wide setting authored once on a `cassette-cmf` settings page can render inside a core block with zero glue code.

- A `'post'`-type binding (the default) with no explicit `args.context` falls back to the *consuming* block's own `context.postId` — which means **the consuming block itself must declare `"usesContext": ["postId"]`** for that fallback to have anything to read (a block's own registered type only ever exposes the subset of context it explicitly declares via `uses_context`). `'term'`/`'settings'` bindings have no such fallback — there's no standard "current term"/"current settings page" block context — so `args.context` is required for those.
- An unresolvable value always resolves to `null`, never `''`, so WordPress falls back to the block's own static content instead of silently overwriting it with an empty value.
- Use Mechanism A for editable, revisioned values (something an author edits *from inside this specific block*); Mechanism B for read-only/derived and cross-entity references (something authored *elsewhere* and simply displayed here).

## Known limitations

- `source: 'context'` (reading a value from an ancestor block's `providesContext`) is not implemented — a field declaring it renders as unsupported.
- `conditional`/`when` scope resolution only ever reads a block's own **attributes** — a rule referencing a `'meta'`-sourced field's name isn't resolvable.
- Term/user/comment meta bindings (Mechanism A) register correctly server-side, but the client-side `'meta'` source only ever reads/writes **post** meta (`useEntityProp( 'postType', ... )`) — a term/user/comment-targeted meta field has no editor control wired to it yet, though `Binding\Meta_Registrar` itself handles all four object types.
