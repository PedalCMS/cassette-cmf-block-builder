# Block configuration reference

`CassetteCmfBlocks` registers Gutenberg blocks from a plain PHP array or a JSON file — the same field-declaration style `cassette-cmf` uses for post types and settings pages. A consumer plugin writes PHP or JSON only; the compiled editor bundle that turns this configuration into inspector panels, toolbars, and a live canvas preview ships inside the package.

## Registration

```php
use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;

CassetteCmfBlocks::init(): Core\Block_Manager;
CassetteCmfBlocks::register_from_array( array $config ): Core\Block_Manager;
CassetteCmfBlocks::register_from_json( string $path_or_json, bool $validate = true ): Core\Block_Manager;
CassetteCmfBlocks::get_block( string $name ): ?Core\Block_Definition;
CassetteCmfBlocks::render( string $name, array $attributes = [], string $content = '' ): string;
```

`register_from_json()` accepts either a filesystem path or a raw JSON string. With `$validate` true (the default), the config is checked against `Json\Block_Schema_Validator` first — a bad config throws `InvalidArgumentException` listing every problem found, not just the first.

Call these **on `init`, priority 5 or earlier — never priority 10 or later.** `Core\Block_Manager` is a lazily-created singleton: the first `register_from_array()`/`register_from_json()` call anywhere is what constructs it, and its constructor is what self-hooks the actual `register_block_type()` call onto `init` priority 10. If that first call itself happens *from inside* a priority-10 `init` callback, the self-hook is added too late to run within that same `init` dispatch — WordPress does not re-visit a priority level it has already started iterating — and the block silently never reaches `WP_Block_Type_Registry` for the rest of the request. Registering at priority 5 guarantees the singleton (and its self-hook) exists before priority 10 begins. Every example in `examples/` follows this. A registration call made after `init` has already fired entirely still registers the block (a late-loading plugin isn't broken outright) but logs a `_doing_it_wrong()`, since existing editor sessions — or content already parsed earlier in the same request — may not see it.

```php
add_action(
    'init',
    function () {
        CassetteCmfBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
    },
    5
);
```

Priority 5, one tick ahead of the library's own priority-10 registration, is the recommended hook priority for your own call.

## Top-level config shape

```php
[
    'blocks' => [
        [
            'id'             => 'acme/callout',     // required: namespaced block name
            'args'           => [ /* block.json-style passthrough, including "variations" */ ],
            'fields'         => [ /* controls and panels */ ],
            'attributes'     => [ /* pure-data attributes with no control */ ],
            'render'         => [ 'mode' => 'dynamic' ],  // 'dynamic' (default) | 'static' | 'none'
            'preview'        => [ /* editor-only preview override */ ],
            'inner_blocks'   => [ /* allowed/template/orientation */ ],
            'transforms'     => [ /* block-to-block transforms */ ],
            'document_scope' => [ /* required for document/sidebar/more_menu-area fields */ ],
        ],
    ],
    'block_categories'   => [ /* custom inserter categories */ ],
    'editor_scope'       => [ /* restrict the inserter for specific post types */ ],
    'patterns'           => [ /* block patterns */ ],
    'pattern_categories' => [ /* custom pattern categories */ ],
]
```

`block_categories`, `editor_scope`, `patterns`, and `pattern_categories` are siblings of `blocks`, not per-block keys — they describe editor-wide behaviour, or (for patterns) behaviour with no single owning block at all. A block entry without `id` throws `InvalidArgumentException`. Registering the same `id` twice overwrites the earlier raw config; once a block has actually reached `register_block_type()`, WordPress itself refuses a second registration of the same name.

## `args` — the block.json passthrough

`args` accepts the same keys `block.json` does, in the same camelCase — `apiVersion`, `title`, `category`, `icon`, `description`, `keywords`, `parent`, `ancestor`, `supports`, `styles`, `variations`, `selectors`, `example`, `textdomain`, `allowedBlocks`, `blockHooks`, `usesContext`, `providesContext`. `Core\Block_Definition` translates the camelCase keys that differ from `WP_Block_Type`'s own PHP property names (`allowedBlocks` → `allowed_blocks`, `usesContext` → `uses_context`, `providesContext` → `provides_context`, `blockHooks` → `block_hooks`, including its position-value translation) when building the server-side `register_block_type()` call; the client-side `registerBlockType()` call receives the untranslated camelCase form, exactly as block.json itself uses.

Two defaults are applied automatically:

- **`apiVersion`** defaults to `3` (`WP_Block_Type::$api_version` itself defaults to `1`, which silently loses `useBlockProps()`-era behaviour).
- **`supports.html`** defaults to `false` for a dynamic block (the default `render.mode`) — a fully dynamic block's saved markup is never shown to a visitor, so leaving "Edit as HTML" enabled would edit dead code.

### `args.variations`

```php
'args' => [
    'variations' => [
        [
            'name'       => 'outline',
            'title'      => __( 'Outline', 'acme' ),
            'attributes' => [ 'style' => 'outline' ],
            'isActive'   => [ 'style' ],   // a plain attribute-name array; WordPress compares it itself
        ],
    ],
],
```

Variations are ordinary block.json data — no separate wiring needed, as long as `isActive` stays a plain array of attribute names (which WordPress compares on its own) rather than a function, which JSON/PHP-array config can't express anyway. Each entry must include `name` and `title`; one that doesn't is dropped (`_doing_it_wrong()`, not a fatal) rather than reaching `register_block_type()` and corrupting the inserter. `cassette_cmf_blocks_block_variations`/`cassette_cmf_blocks_block_variations_{id}` filter the final list.

`variation_callback` (a real PHP callable, for variations WordPress computes dynamically) is a separate `args` key, already snake_case — it's a `register_block_type()`-only concept with no block.json equivalent, so there's nothing to translate. It only makes sense from `register_from_array()`, since JSON can't carry a callable, and it never reaches the client — `Core\Editor_Payload` strips it (and any other stray `Closure`) before building the browser payload, since `wp_json_encode()` on a Closure fails outright.

## `fields` — controls and panels

```php
[
    'name'        => 'heading',        // required, unique among all value-bearing fields in the block
    'type'        => 'text',           // required, a control type — see docs/control-catalog.md
    'area'        => 'inspector',      // dot-notation "<surface>[.<group>]"; inherited from the enclosing container
    'source'      => 'attribute',      // 'attribute' (default) | 'meta' | 'context' | 'none'
    'value_type'  => 'string',         // overrides the control's inferred WP attribute type
    'default'     => '',
    'required'    => false,
    'options'     => [ 'a' => 'Alpha' ],   // value => label; produces an attribute "enum" of the keys
    'conditional' => [ 'rules' => [ [ 'field' => 'other', 'operator' => '==', 'value' => '1' ] ] ],
    'cmf_type'    => 'text',           // optional override of which parent field type provides sanitize()/validate()
    'meta'        => [ /* only when source => 'meta' — see docs/meta-binding.md */ ],
]
```

Reserved keys are never treated as control-specific props: `name type label description default required placeholder class conditional validation area source value_type role enum meta fields title initial_open group sanitize_callback`. Everything else is passed straight through to the control component — `options`, `rows`, `min`, `max`, `step`, and so on.

Container types (`panel`, `tab_panel`, `tab`, `group`, `row`, `stack`, `toolbar_group`, `repeater`) take a `fields` array of children, which inherit the container's resolved `area` unless they declare their own. `repeater` also holds its own value (an array of row objects) — see `docs/control-catalog.md`'s repeater section.

`conditional` uses the same shape as `cassette-cmf`'s own field conditionals verbatim: `[ 'relation' => 'AND'|'OR', 'rules' => [ [ 'field', 'operator', 'value' ] ] ]`, with the same ten operators (`==`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `empty`, `not_empty`). It's evaluated live in the editor (hiding the field when it fails) and again on the front end — both directions kept honest against each other by a shared fixture (`tests/fixtures/conditions.json`). Scope resolution only ever reaches the block's own attributes today — a rule can't reference an ancestor block's `providesContext`, a sibling block, or a `meta`-sourced field.

**Nesting rules, enforced at registration:**

- A `toolbar_group` may not contain an inspector-style container (`panel`/`tab_panel`/`group`/`row`/`stack`/`repeater`), and vice versa.
- A field resolving to the `canvas` area may not appear inside an inspector-style container.
- None of a repeater's sub-fields, at any depth, may declare `conditional` — a repeater row is dynamic, so a controlling field outside the row can't address one specific row's value.
- A `tab_panel`'s direct children must all be `tab` containers, and a `tab` may only appear directly inside a `tab_panel` — see `docs/control-catalog.md#the-tab_paneltab-shape`.
- Every value-bearing field's `name` must be unique within the block — they share one flat attribute namespace, **except** a repeater's own row fields, whose values live inside the repeater's own array attribute instead (see below).
- `password` is rejected outright with a security-specific `InvalidArgumentException`: block attributes live in `post_content`, readable via the REST API and often the front end, so there is no honest way to store a secret in one.

### Repeater sub-fields never become independent attributes

A `repeater` field's own `fields` is a row **template**, not one field tree per existing row — each row is an object whose keys match the template's field names, and the whole array is the repeater's own single attribute value. None of a repeater's sub-fields are mapped into the block's top-level attribute schema, registered as meta (if one declares `source: 'meta'`), or offered as an option in another field's `sibling_field`/`conditions` picker — their values only ever exist inside one row's own object.

## `attributes` — pure-data attributes with no control

```php
'attributes' => [
    'internal_id' => [ 'type' => 'string', 'default' => '' ],
]
```

Merged with the attributes derived from `fields`; a name collision between the two throws `InvalidArgumentException`.

## Attribute schema mapping

Every value-bearing field whose `source` is `attribute` (the default) becomes a WP block attribute:

| Field property | Attribute schema effect |
|---|---|
| `value_type` (or the control's catalog default) | `type` |
| `default` (or a type-appropriate zero value: `''`, `0`, `false`, `[]`) | `default` |
| `options` (keys) or `enum` | `enum` |
| `role` | `role`, when set |

`meta`/`context`/`none`-sourced fields are excluded from the attribute schema — `meta` has a real, separate storage path (below); `context`/`none` don't persist a value at this block at all.

## `source: 'meta'` — meta binding

A field can read and write real WordPress post/term/user/comment meta instead of a block attribute. See **[meta-binding.md](meta-binding.md)** for the full reference, including the `cassette-cmf/field` block bindings source that lets *any* block — not just ones this library registers — bind to a `cassette-cmf` value.

## Filters

One filter/action per registration step, mirroring `cassette-cmf`'s own `apply_id_filters()` convention: `{id}` normalises `/` and `-` to `_` (`acme/callout` → `acme_callout`).

| Hook | Fires |
|---|---|
| `cassette_cmf_blocks_register_config` (filter) | The whole config array, before any block is processed. |
| `cassette_cmf_blocks_block_config` / `cassette_cmf_blocks_block_config_{id}` (filter) | One block's raw config. |
| `cassette_cmf_blocks_control_catalog` (filter) | The full control-type catalog, after defaults are registered — the one extensibility gap `cassette-cmf`'s own `Field_Factory` lacks. |
| `cassette_cmf_blocks_block_variations` / `cassette_cmf_blocks_block_variations_{id}` (filter) | A block's validated `args.variations`. |
| `cassette_cmf_blocks_assets_url` (filter) | Override point for the editor bundle's resolved public URL. |
| `cassette_cmf_blocks_payload_cache_skip` (filter) | Overrides whether `Core\Editor_Payload::build_cached()` skips its cache (default: on under `WP_DEBUG`/`SCRIPT_DEBUG`). |
| `cassette_cmf_blocks_registered` (action) | After a batch of config has been registered, receiving the `Block_Manager` instance. |

## Requirements guard

`Compat\Requirements::check()` verifies `pedalcms/cassette-cmf` is loaded, exposes the interface this library relies on, and meets its version floor (`0.1.0`), plus a WordPress version floor (`6.8`). If any check fails, **nothing registers at all** — a half-registered block registry can corrupt content — and an admin notice explains why, rather than a fatal error.

## `render` — a block's rendering pipeline

```php
'render' => [
    'mode'       => 'dynamic',   // 'dynamic' (default) | 'static' | 'none'
    'callback'   => 'my_render_function',       // PHP callable, dynamic mode only
    'template'   => '/path/to/template.php',    // PHP file, dynamic mode only
    'markup'     => [ /* declarative tree — see docs/markup-templates.md */ ],
    'wrapper'    => [ 'use_block_wrapper_attributes' => true ],  // default true
    'deprecated' => [ /* static mode only — see docs/static-save.md */ ],
]
```

For `mode: 'dynamic'`, the first of `callback` → `template` → `markup` that's set wins; a dynamic block with none of the three declared gets no `render_callback` at all and renders nothing. `mode: 'static'` serializes `markup` into the block's own `save()` output instead — see **[static-save.md](static-save.md)**. `mode: 'none'` is an intentionally output-less block.

Rendering, in order: hydrate schema defaults → resolve `meta`/`context`-sourced values → sanitize attribute-sourced values through `cassette-cmf`'s own field sanitizers → drop any node whose `conditional`/`when` fails → render (`callback`/`template`/`markup`) → wrap (`get_block_wrapper_attributes()` when `wrapper.use_block_wrapper_attributes` is true, the default) → `cassette_cmf_blocks_render`/`cassette_cmf_blocks_render_{id}` filters.

## `preview` — the editor-only canvas preview

```php
'preview' => [
    'mode'   => 'markup',   // 'markup' (default) | 'server'
    'markup' => [ /* overrides render.markup for the editor preview specifically */ ],
]
```

`mode: 'markup'` (the default) renders `preview.markup` — or, when unset, `render.markup` itself — client-side, with zero network requests. `mode: 'server'` uses `@wordpress/server-side-render`, for output that genuinely needs PHP (a live query, a shortcode, an embed). `preview.when` (gating a preview until required fields are filled) is not implemented yet.

## `inner_blocks` — a block's own child blocks

```php
'inner_blocks' => [
    'enabled'       => true,               // implied by declaring this key at all, unless explicitly false
    'allowed'       => [ 'core/paragraph', 'core/image' ],
    'template'      => [ [ 'core/paragraph', [ 'placeholder' => 'Text...' ] ] ],
    'template_lock' => 'all',              // 'all' | 'insert' | 'contentOnly' | false
    'orientation'   => 'horizontal',
]
```

A markup node with `'slot' => 'inner_blocks'` (or the `'content'` alias) renders a real, editable `<InnerBlocks />` in the editor canvas when `inner_blocks` is enabled, `<InnerBlocks.Content />` in a static block's `save()` output, and the block's own already-rendered child content on the front end (passed as `$content` to a dynamic block's `callback`/`template`/`markup`).

## `transforms` — block-to-block transforms

```php
'transforms' => [
    'from' => [
        [ 'type' => 'block', 'blocks' => [ 'core/paragraph' ], 'map' => [ 'heading' => 'content' ] ],
    ],
    'to' => [
        [ 'type' => 'block', 'blocks' => [ 'core/paragraph' ], 'map' => [ 'content' => 'heading' ] ],
    ],
],
```

A sibling of `render`/`preview` — never part of `args`, since (unlike `variations`) this isn't literal `register_block_type()` data; the client bundle's own `transforms.js` interprets it. Only `type: 'block'` is supported, as a plain `{ targetAttr: sourceAttr }` attribute remap: the key is the attribute on the block **being created**, the value is the attribute read from whichever block the transform function actually receives (the matched block for `from`, this block's own attributes for `to`). `raw`/`shortcode`/`enter`/`prefix` transforms all fundamentally need a real predicate/transform *function*, which no declarative shape can express honestly — an entry declaring one of those types is silently dropped, a deliberate scope limit rather than a silent gap.

## `block_categories` — custom inserter categories

```php
'block_categories' => [
    [ 'slug' => 'acme', 'title' => __( 'Acme', 'acme' ), 'icon' => 'admin-generic' ],
]
```

Registered via the `block_categories_all` filter. An entry with no `slug`, or one that collides with an already-registered category (core's own, or another plugin's), is skipped rather than duplicated.

## `editor_scope` — restricting the inserter for specific post types

```php
'editor_scope' => [
    'post_types' => [ 'page' ],
    'allow_only' => [ 'acme/*', 'core/paragraph', 'core/heading' ],
]
```

Compiled into a single `allowed_block_types_all` filter, with glob support (`*`) in `allow_only`. A post type **not** named by any `editor_scope` entry is left completely unrestricted. This applies uniformly to every registered block, including this library's own — there is no implicit self-allowlisting.

## `patterns` / `pattern_categories` — block patterns

```php
'patterns' => [
    [
        'slug'        => 'acme/hero',
        'title'       => __( 'Hero', 'acme' ),
        'description' => __( 'A large intro section.', 'acme' ),
        'categories'  => [ 'acme-featured' ],
        'keywords'    => [ 'intro', 'banner' ],
        // Either raw "content" (the same WordPress comment-delimited HTML
        // register_block_pattern() always accepted) ...
        'content'     => '<!-- wp:heading --><h2>Welcome</h2><!-- /wp:heading -->',
        // ... or a declarative "blocks" tree — for a JSON config, which
        // can't comfortably embed a raw HTML string. "content" wins when
        // both are present.
        'blocks'      => [
            [ 'blockName' => 'core/heading', 'attrs' => [ 'content' => 'Welcome', 'level' => 2 ] ],
        ],
    ],
],
'pattern_categories' => [
    [ 'slug' => 'acme-featured', 'label' => __( 'Featured', 'acme' ) ],
],
```

Genuinely top-level, not per-block — `register_block_pattern()` has no relationship to any one block type. Categories register first (so a pattern's own declared category is always the described one, when both come from this library), then patterns, on `init`. A `blocks` tree is recursively converted into WordPress's own parsed-block array shape and serialized into the same HTML `content` would carry. A pattern missing `slug` or `title`, or declaring neither `content` nor `blocks`, is skipped rather than fataling or registering something broken.

## Document, sidebar, and more-menu areas — `document_scope`

A field resolving to the `document`, `document.status`, `document.excerpt`, `document.publish.pre`, `document.publish.post`, `sidebar`, or `more_menu` area belongs to the editor as a whole, not to one block instance — it can be relevant with zero instances of the block in the post, so there is no per-instance `attributes` to bind it to. Every such field must be `source: 'meta'` (an `attribute`-sourced field here renders as unsupported — there's nothing for it to read or write), and the owning block must declare a top-level `document_scope`:

```php
'document_scope' => [
    'post_types'   => [ 'post', 'page' ],   // required, non-empty
    'when_present' => false,                 // default false: shown on every matching post type regardless of block presence
    'target'       => 'acme-settings',       // optional; defaults to the block's own name ("/" replaced with "-")
],
```

Without a valid `document_scope`, a block's document/sidebar/more_menu-area fields are simply not mounted — logged once (`_doing_it_wrong()`), never fataled, never half-registered. `document`/`document.status`/`document.excerpt`/`document.publish.pre`/`document.publish.post` map onto `PluginDocumentSettingPanel`/`PluginPostStatusInfo`/`PluginPostExcerpt`/`PluginPrePublishPanel`/`PluginPostPublishPanel` (`@wordpress/editor`) respectively; `sidebar` and `more_menu` fields share one `PluginSidebar` panel, with a `PluginSidebarMoreMenuItem` rendered alongside it automatically. This UI registers once per block globally, gated live on the current post type and, when `when_present` is set, on at least one instance of the block actually existing in the post.

## `Rest\Config_Controller` — debugging and introspection

`GET /cassette-cmf-block-builder/v1/config` (requires `edit_posts`) returns the exact same payload shape the inline editor bootstrap ships, for `wp eval-file`/devtools-free introspection of what the editor currently sees. It is never consulted on the registration path — the editor's own synchronous parse of `post_content` depends on blocks already being registered by the time it runs, which a REST round-trip cannot guarantee.

## Known limitations

- `source: 'context'` (reading a value from an ancestor block's `providesContext`) is not implemented — such a field is recognised but renders nothing.
- `conditional`/`when` rules can only reference the block's own attributes, not an ancestor's context, a sibling block, or a `meta`-sourced field.
- Automatic deprecation synthesis doesn't exist — `render.deprecated` must be declared explicitly (see `docs/static-save.md`).
- `placeholder`-area fields (shown inside a `<Placeholder>` overlay for an unconfigured block instance) are recognised but the editor doesn't render into that area.
- `preview.when` isn't wired up.
- The `text` control's `unique` flag (flagging a value that collides with a sibling block instance elsewhere in the post) is not implemented.
- `transforms` only supports `type: 'block'`.
