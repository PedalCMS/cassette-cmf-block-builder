# Block configuration reference

`CassetteCMFBlocks` registers Gutenberg blocks from a plain PHP array or a JSON file — the same field-declaration style `cassette-cmf` uses for post types and settings pages. A consumer plugin writes PHP or JSON only; the compiled editor bundle that turns this configuration into inspector panels, toolbars, and a live canvas preview ships inside the package.

## Registration

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

CassetteCMFBlocks::boot( array $options = [] ): Core\Block_Manager;
CassetteCMFBlocks::init(): Core\Block_Manager;
CassetteCMFBlocks::register_from_array( array $config ): Core\Block_Manager;
CassetteCMFBlocks::register_from_json( string $path_or_json, bool $validate = true ): Core\Block_Manager;
CassetteCMFBlocks::get_block( string $name ): ?Core\Block_Definition;
CassetteCMFBlocks::render( string $name, array $attributes = [], string $content = '' ): string;
```

Call `boot()` once, **before** any `register_from_*()`, and always with a `prefix`. Calling `register_from_*()` / `init()` first, or `boot()` without a valid prefix, raises a `_doing_it_wrong()` notice and uses the fallback prefix `cassette-cmf`. A later `boot()` with different options is ignored with a `_doing_it_wrong()` notice (unknown keys and wrongly-typed values trigger one too).

| Boot option | Default | Effect |
|---|---|---|
| `prefix` (string) | none (required) | `^[a-z][a-z0-9-]{1,30}[a-z0-9]$`. Derives the editor handles, REST namespace, binding source name and JS global; see [coexistence.md](coexistence.md). |
| `bindings_source` (bool) | `false` | Registers the `{prefix}/field` block bindings source. See [meta-binding.md](meta-binding.md). A field config that declares `binding.source: '{prefix}/field'` while this is off triggers a `_doing_it_wrong()` at compile time. |

`register_from_json()` accepts either a filesystem path or a raw JSON string. With `$validate` true (the default), the config is checked against `Json\Block_Schema_Validator` first — a bad config throws `InvalidArgumentException` listing every problem found, not just the first.

Call these **on `init`, priority 5 or earlier — never priority 10 or later.** `Core\Block_Manager` is a lazily-created singleton: the first `register_from_array()`/`register_from_json()` call anywhere is what constructs it, and its constructor is what self-hooks the actual `register_block_type()` call onto `init` priority 10. If that first call itself happens *from inside* a priority-10 `init` callback, the self-hook is added too late to run within that same `init` dispatch — WordPress does not re-visit a priority level it has already started iterating — and the block silently never reaches `WP_Block_Type_Registry` for the rest of the request. Registering at priority 5 guarantees the singleton (and its self-hook) exists before priority 10 begins. Every example in `examples/` follows this. A registration call made after `init` has already fired entirely still registers the block (a late-loading plugin isn't broken outright) but logs a `_doing_it_wrong()`, since existing editor sessions — or content already parsed earlier in the same request — may not see it.

```php
add_action(
    'init',
    function () {
        CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] );
        CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
    },
    5
);
```

Priority 5, one tick ahead of the library's own priority-10 registration, is the recommended hook priority for your own call.

## Top-level config shape

> **Key casing.** Library-owned keys are `snake_case`; `args` follows `block.json` (`camelCase`); data shaped by WordPress keeps WordPress's casing. See [STABILITY.md](STABILITY.md#1-the-casing-rule-for-config-keys).

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
            'transforms'     => [ /* block remaps; shortcode / raw transforms with a PHP callback */ ],
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

## `args` — the block.json keys

`args` takes block.json-shaped keys, in block.json's camelCase, checked against an **allow-list**. A key that is not on the list gets a `_doing_it_wrong()` notice (naming the key and the block) and is dropped; nothing is passed through to `register_block_type()` unchecked. `Core\Block_Definition` translates the camelCase keys that differ from `WP_Block_Type`'s own PHP property names when it builds the server-side `register_block_type()` call; the client-side `registerBlockType()` call receives the untranslated camelCase form, exactly as block.json itself uses. `Block_Definition::allowed_arg_keys()` returns the full list.

| `args` key | Becomes (`register_block_type()` key) | Value | Sent to the editor |
|---|---|---|---|
| `apiVersion` | `api_version` | int, defaults to `3` | yes |
| `title` | `title` | string | yes |
| `category` | `category` | string | yes |
| `description` | `description` | string | yes |
| `icon` | `icon` | string or object, as in block.json | yes |
| `keywords` | `keywords` | string[] | yes |
| `textdomain` | `textdomain` | string | yes |
| `parent` | `parent` | string[] | yes |
| `ancestor` | `ancestor` | string[] | yes |
| `allowedBlocks` | `allowed_blocks` | string[] | yes |
| `blockHooks` | `block_hooks` | `{ anchor block: before / after / firstChild / lastChild }`; positions are translated to snake_case, and a block that hooks itself is skipped with a notice | yes |
| `providesContext` | `provides_context` | `{ context key: attribute name }` | yes |
| `usesContext` | `uses_context` | string[]; keys from `source: 'context'` fields and `meta` fields are merged in | yes |
| `selectors` | `selectors` | object | yes |
| `supports` | `supports` | object; `html` defaults to `false` for dynamic blocks | yes |
| `styles` | `styles` | list of `{ name, label, isDefault? }` | yes |
| `variations` | `variations` | list, validated (see [`args.variations`](#argsvariations)) | yes |
| `variation_callback` | `variation_callback` | PHP callable; `register_from_array()` only, never sent to the browser. The one snake_case key on the list | no |
| `example` | `example` | object | yes |
| `editorScript` | `editor_script_handles` | handle or handle[] | no |
| `editorStyle` | `editor_style_handles` | handle or handle[] | no |
| `script` | `script_handles` | handle or handle[] | no |
| `style` | `style_handles` | handle or handle[] | no |
| `viewScript` | `view_script_handles` | handle or handle[] | no |
| `viewStyle` | `view_style_handles` | handle or handle[] | no |
| `viewScriptModule` | `view_script_module_ids` | script module ID or ID[] | no |

Not on the list, on purpose: `attributes` (derived from `fields` and the top-level `attributes` key) and `render_callback` (derived from `render`), because the library builds them itself.

### Asset keys

The last seven rows load your own scripts, styles and script modules with the block, the way block.json's `editorScript` and friends do. Each takes one string or a list of strings and is stored as a list. A value that is neither (an empty string, an empty list, a number, a nested list) is dropped with a `_doing_it_wrong()` notice, and the JSON validator reports it as an error.

```php
wp_register_script( 'acme-callout-editor', plugins_url( 'build/callout-editor.js', __FILE__ ), [ 'wp-blocks' ], '1.0.0', true );
wp_register_style( 'acme-callout-view', plugins_url( 'build/callout-view.css', __FILE__ ), [], '1.0.0' );
wp_register_script_module( 'acme/callout-view', plugins_url( 'build/callout-view.js', __FILE__ ), [ '@wordpress/interactivity' ], '1.0.0' );

CassetteCMFBlocks::register_from_array( [
    'blocks' => [ [
        'id'   => 'acme/callout',
        'args' => [
            'editorScript'     => 'acme-callout-editor',                // string
            'viewStyle'        => [ 'acme-callout-view' ],              // or a list
            'viewScriptModule' => 'acme/callout-view',                  // a module ID, not a handle
        ],
    ] ],
] );
```

- The values are **registered handles or module IDs**, not files. block.json's `file:./view.js` paths are not supported: register the asset yourself (`wp_register_script()`, `wp_register_style()`, `wp_register_script_module()`) before the block renders, and pass its handle or ID. A handle or ID that is not registered when WordPress enqueues it simply loads nothing, as with any block.
- `viewScript`, `viewStyle` and `viewScriptModule` load on the front end only on a page that renders the block, on block themes and classic themes alike (WordPress enqueues them from `WP_Block::render()`). `editorScript` and `editorStyle` load in the editor. `script` and `style` load in both.
- These handles are separate from the library's own editor script and style (`{prefix}-blocks-editor[-style]`, see [coexistence.md](coexistence.md)). Both load in the editor; neither replaces the other.
- The keys are not sent to the browser (they are server-side registration data).

**Migration from earlier builds.** `args` used to be passed to `register_block_type()` verbatim, so snake_case spellings such as `view_script_module_ids`, `view_style_handles` or `editor_style_handles` happened to work. They are now unknown keys: rename them to the camelCase keys in the table (`viewScriptModule`, `viewStyle`, `editorStyle`). The same goes for any other snake_case key that used to slip through (`uses_context`, `api_version`, `allowed_blocks`, `block_hooks`, `provides_context`: use the camelCase form).

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

**Block picker.** A variation that lists `scope: [ 'block' ]` (alone or with `inserter` / `transform`) opens the block variation picker, the same as core Columns / Query: while the block is empty (no inner blocks, attributes still at their defaults, no variation active, not skipped) the editor shows the picker instead of the block UI. Choosing a variation applies its `attributes` and replaces the inner blocks with its `innerBlocks` templates (`[ [ 'core/paragraph', [ 'content' => 'A' ] ] ]`). A variation without `scope` never opens the picker. `scope` must be a list drawn from `block`, `inserter`, `transform`, and `innerBlocks` a list of templates; a malformed value is dropped with a `_doing_it_wrong()`. The picker component is `BlockVariationPicker` when the editor exports it and `__experimentalBlockVariationPicker` otherwise (WordPress 6.8 and 7.1 export only the experimental name). `uuid` fields are ignored when deciding whether the block is still empty. See also [the placeholder](#placeholder-and-the-variation-picker).

`variation_callback` (a real PHP callable, for variations WordPress computes dynamically) is a separate `args` key, already snake_case — it's a `register_block_type()`-only concept with no block.json equivalent, so there's nothing to translate. It only makes sense from `register_from_array()`, since JSON can't carry a callable, and it never reaches the client — `Core\Editor_Payload` strips it (and any other stray `Closure`) before building the browser payload, since `wp_json_encode()` on a Closure fails outright.

## Server-side attribute API

For code that has to read or validate a block's attributes outside a render (a REST endpoint, a save hook, an export), the compiled definition exposes the same pipeline the render callback uses. All three are marked `@api`.

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

$definition = CassetteCMFBlocks::get_definition( 'acme/callout' );   // ?Block_Definition

$clean  = $definition->sanitize_attributes( $raw_attributes );       // array
$schema = $definition->attribute_schema();                           // array
```

- `CassetteCMFBlocks::get_definition( string $block_name ): ?Block_Definition` returns the compiled definition for a full block name registered through this library, or `null` (library not booted yet, unknown block, or a config that does not compile).
- `Block_Definition::sanitize_attributes( array $attributes ): array` first runs WordPress's own preparation (`WP_Block_Type::prepare_attributes_for_render()`: a value that fails the attribute schema is dropped, a missing one gets the schema default), then the field sanitizers (the parent library's `sanitize()` for each field type, `sanitize_uuid()` for `uuid`). The render callback calls this very method, so for the same input the result equals the `$attributes` a `render.callback`, template or markup tree receives, for every control type. Two differences to know about: the key order can differ (WordPress prepares once before the callback and this method prepares again, which can move a default that fails its own `enum` to the end), and the values of `source: 'context'` fields are not included, because they have no attribute; the renderer adds them from the block's context at render time.
- `Block_Definition::attribute_schema(): array` is the `attributes` array passed to `register_block_type()` (attribute name => `{ type, default, enum, ... }`). WordPress adds its own `lock` and `metadata` attributes on top.

## `fields` — controls and panels

```php
[
    'name'        => 'heading',        // required, unique among all value-bearing fields in the block
    'type'        => 'text',           // required, a control type — see docs/control-catalog.md
    'area'        => 'inspector',      // dot-notation "<surface>[.<group>]"; inherited from the enclosing container
    'source'      => 'attribute',      // 'attribute' (default) | 'meta' | 'context' | 'none'
    'context'     => 'example/formId', // only when source => 'context': the block-context key to read (required then)
    'value_type'  => 'string',         // overrides the control's inferred WP attribute type
    'default'     => '',
    'required'    => false,
    'options'     => [ 'a' => 'Alpha' ],   // value => label, or [ [ 'value' => 'a', 'label' => 'Alpha' ], ... ]; produces an attribute "enum"
    'unique'      => true,             // text/email/url: see "Unique values" below
    'conditional' => [ 'rules' => [ [ 'field' => 'other', 'operator' => '==', 'value' => '1' ] ] ],
    'cmf_type'    => 'text',           // optional override of which parent field type provides sanitize()/validate()
    'meta'        => [ /* only when source => 'meta' — see docs/meta-binding.md */ ],
]
```

Reserved keys are never treated as control-specific props: `name type label description default required placeholder class conditional validation area source value_type role enum meta fields title initial_open group sanitize_callback`. Everything else is passed straight through to the control component — `options`, `rows`, `min`, `max`, `step`, and so on.

Container types (`panel`, `tab_panel`, `tab`, `group`, `row`, `stack`, `toolbar_group`, `repeater`) take a `fields` array of children, which inherit the container's resolved `area` unless they declare their own. `repeater` also holds its own value (an array of row objects) — see `docs/control-catalog.md`'s repeater section.

`conditional` uses the same shape as `cassette-cmf`'s own field conditionals verbatim: `[ 'relation' => 'AND'|'OR', 'rules' => [ [ 'field', 'operator', 'value' ] ] ]`, with the same ten operators (`==`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `empty`, `not_empty`). It's evaluated live in the editor (hiding the field when it fails) and again on the front end — both directions kept honest against each other by a shared fixture (`tests/fixtures/conditions.json`). A rule can reference the block's own attributes and its `source: 'context'` fields by name; it can't reference a sibling block or a `meta`-sourced field.

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
| `options` (values) or `enum` | `enum` (on `items` for an array attribute such as `checkbox_group`) |
| `role` | `role`, when set |

`meta`/`context`/`none`-sourced fields are excluded from the attribute schema — `meta` has a real, separate storage path (below); `context`/`none` don't persist a value at this block at all.

### `options`: two accepted shapes

`options` is either a `value => label` map or a list of `{ value, label }` objects (natural in JSON; `label` defaults to the value). Both are normalized **once, when the block compiles**, into the map, so the attribute schema, the sanitizer and the editor payload all see the same thing. Anything else (a non-array, a list item without a scalar `value`) triggers `_doing_it_wrong()` and the options are dropped; a repeated value in the list form is reported and the first wins.

## `source: 'context'` — reading a value from an ancestor

```php
// the provider
'args' => [ 'providesContext' => [ 'example/formId' => 'formId' ] ],   // key => the attribute it publishes
// the consumer
[ 'name' => 'formId', 'type' => 'text', 'source' => 'context', 'context' => 'example/formId' ],
```

The consumer's `usesContext` gains the key automatically. The field is read-only, creates no attribute, stores nothing and renders nothing in the inspector; its value is available under the field's `name` in markup, templates, conditions and the markup preview (live). See [control-catalog.md](control-catalog.md#field-sourcing-scope), and `examples/03-advanced-array` for a parent `example/form` and child `example/field`. `args.providesContext` and `args.usesContext` are passed to both `register_block_type()` and the editor's `registerBlockType()`.

## `unique` — values that must not repeat

`unique` applies to `text`, `email` and `url` fields. `true` means unique across the whole post; `[ 'scope' => 'ancestor', 'block' => 'vendor/form' ]` means unique among blocks under the same nearest `vendor/form`, at any depth (blocks with no such ancestor form one separate group). The optional `key` (default: the field name) is the uniqueness group: every block of any type registered by this library with a `unique` field of the same key and the same scope rule is checked together, so one name can be unique across different field blocks. Values are trimmed, formatted with the field's `format`, then compared case-sensitively; empty values never collide. The editor flags every colliding block except the oldest and auto-suffixes (`-2`, `-3`, …) a duplicated or pasted copy; saving is never blocked. `Render\Unique_Checker::find_collisions()` (`@api`) applies the same rule to saved content. Details: [control-catalog.md](control-catalog.md#unique-values).

## `source: 'meta'` — meta binding

A field can read and write real WordPress post/term/user/comment meta instead of a block attribute. See **[meta-binding.md](meta-binding.md)** for the full reference, including the `{prefix}/field` block bindings source that lets *any* block — not just ones this library registers — bind to a `cassette-cmf` value.

## Filters

One filter/action per registration step, mirroring `cassette-cmf`'s own `apply_id_filters()` convention: `{id}` normalises `/` and `-` to `_` (`acme/callout` → `acme_callout`).

| Hook | Fires |
|---|---|
| `cassette_cmf_blocks_register_config` (filter) | The whole config array, before any block is processed. |
| `cassette_cmf_blocks_block_config` / `cassette_cmf_blocks_block_config_{id}` (filter) | One block's raw config. |
| `cassette_cmf_blocks_control_catalog` (filter) | The full control-type catalog, after defaults are registered — the one extensibility gap `cassette-cmf`'s own `Field_Factory` lacks. |
| `cassette_cmf_blocks_block_variations` / `cassette_cmf_blocks_block_variations_{id}` (filter) | A block's validated `args.variations`. |
| `cassette_cmf_blocks_binding_allowed` (filter) | `( bool $allowed = true, array $source_args, WP_Block $block )`. Can only **refuse** a `{prefix}/field` binding that already passed the built-in rules; see [meta-binding.md](meta-binding.md). |
| `cassette_cmf_blocks_assets_url` (filter) | Override point for the editor bundle's resolved public URL. |
| `cassette_cmf_blocks_payload_cache_skip` (filter) | Overrides whether `Core\Editor_Payload::build_cached()` skips its cache (default: on under `WP_DEBUG`/`SCRIPT_DEBUG`). |
| `cassette_cmf_blocks_registered` (action) | After a batch of config has been registered, receiving the `Block_Manager` instance. |

## Requirements guard

`Compat\Requirements::check()` verifies `pedalcms/cassette-cmf` is loaded, exposes the interface this library relies on, and meets its version floor (`0.2.0`), plus a PHP floor (`7.4`) and a WordPress version floor (`6.8`). If any check fails, **nothing registers at all** — a half-registered block registry can corrupt content — and an admin notice explains why, rather than a fatal error.

## `render` — a block's rendering pipeline

```php
'render' => [
    'mode'       => 'dynamic',   // 'dynamic' (default) | 'static' | 'none'
    'callback'   => 'my_render_function',       // PHP callable, dynamic mode only
    'template'   => '/path/to/template.php',    // PHP file, dynamic mode only
    'markup'     => [ /* declarative tree — see docs/markup-templates.md */ ],
    'wrapper'    => [ 'use_block_wrapper_attributes' => true ],  // default true
    'deprecated' => [ /* static: old markup trees; dynamic: old attributes + migrate (migrate_php on the server) — see docs/static-save.md */ ],
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

`mode: 'markup'` (the default) renders `preview.markup` — or, when unset, `render.markup` itself — client-side, with zero network requests. `mode: 'server'` uses `@wordpress/server-side-render`, for output that genuinely needs PHP (a live query, a shortcode, an embed).

`preview.when` gates the preview with a condition rule (the same `{ relation, rules }` shape and operators as every other condition):

```php
'preview' => [
    'mode' => 'server',
    'when' => [ 'relation' => 'AND', 'rules' => [ [ 'field' => 'url', 'operator' => 'not_empty' ] ] ],
],
```

While the rule is false the editor shows a core `Placeholder` instead of the preview (label, instructions and icon come from the block's `placeholder` setting, else the block title and "Configure this block."), and a server preview sends no request. The block's fields stay in the inspector. Rules of the form `{ source: 'block', ref }` are ignored (the builder does not evaluate cross-block rules), so a rule set of only those is true. The rule is evaluated against the block's attributes plus its `source: 'context'` fields.

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

A sibling of `render`/`preview` — never part of `args`, since (unlike `variations`) this isn't literal `register_block_type()` data; the client bundle's own `transforms.js` interprets it. A `type: 'block'` entry is a plain `{ targetAttr: sourceAttr }` attribute remap: the key is the attribute on the block **being created**, the value is the attribute read from whichever block the transform function actually receives (the matched block for `from`, this block's own attributes for `to`). `enter` and `prefix` transforms need a JS predicate and are silently ignored.

### Shortcode and raw transforms (PHP callback)

```php
'transforms' => [
    'from' => [
        [ 'type' => 'shortcode', 'tag' => 'contact-form', 'callback' => 'acme_block_from_shortcode' ],
        [ 'type' => 'raw', 'selector' => 'div.legacy-card', 'callback' => 'acme_block_from_html' ],
    ],
],
```

```php
function acme_block_from_shortcode( array $atts, array $context ): ?array {
    // [contact-form id="7"] -> $atts = [ 'id' => '7' ]; positional values have integer keys.
    return [
        'attributes'  => [ 'formId' => (int) $atts['id'] ],
        'innerBlocks' => [ [ 'name' => 'acme/field', 'attributes' => [ 'label' => 'Name' ] ] ], // optional
    ];
}
```

- `shortcode`: `tag` (letters, digits, `_`, `-`). Core matches `[tag …]` when pasted or converted from a classic block, if it sits on its own line or wraps HTML. `callback` receives the attributes array.
- `raw`: `selector` is a CSS selector string, or a rule `{ tag?, class?: string|string[], attrs?: { name: value } }`. Core matches it against pasted HTML elements; `callback` receives the element's `outerHTML`. Core strips unknown elements and classes from pasted HTML before matching, so the library adds a paste schema for a tag-based selector (`div.legacy-card`, or a rule with `tag`); nested content is limited to phrasing content. A selector without a tag (`.legacy-card`, `[data-x]`) gets no schema and only matches if core's own cleanup keeps the element.
- `callback` is a PHP callable (so these entries exist in PHP configs only; JSON cannot carry a callable). It is `( array|string $input, array $context ): ?array`; `$context` holds `block`, `index`, `type` and `post_id`. Return `{ attributes, innerBlocks? }`, or `null` for "no match". `innerBlocks` is a list of `{ name, attributes?, innerBlocks? }`.
- A shortcode / raw entry without a callable `callback` (or without `tag` / `selector`) makes the block fail to register with `_doing_it_wrong()`.

**What the user sees.** Core's `transform()` is synchronous, so the block is created immediately with a pending marker (kept in the block's generic `metadata`, removed on completion) and shows a loading placeholder with a spinner while the request runs. Then the callback's attributes (and inner blocks) are applied. If the request fails or the callback returns null, a snackbar shows the message and the original content stays: a `core/shortcode` block for a shortcode, a `core/html` block for raw HTML. If the user deletes the block meanwhile, the result is discarded. Reloading mid-request leaves an empty block; the stale marker is ignored.

**The endpoint.** `POST /{prefix}-blocks/v1/transform` with `{ block, index, input, post_id? }`; `index` is the entry's position in `transforms.from`.

| Status | When |
|---|---|
| 401 / 403 | Not logged in / cannot `edit_posts` (or `edit_post` for a given `post_id`). The editor's `apiFetch` sends the core REST nonce. |
| 404 | `block` is not a library block, or `index` is not a shortcode / raw entry with a callable callback. Nothing from the request is ever used as a callable. |
| 400 | `input` is not an object (shortcode) or a string (raw), or an argument is missing. |
| 422 | The callback returned null or something without an `attributes` array. |
| 200 | `{ attributes, innerBlocks? }`. |

The returned attributes go through `Block_Definition::sanitize_attributes()` (the same pipeline as rendering) and only the keys the callback returned come back. Inner blocks must name a registered block and nest at most 8 levels; blocks of this library are sanitized the same way, other blocks keep only the attributes they declare, and strings are passed through `wp_kses_post()` unless the user has `unfiltered_html`.

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

## Placeholder and the variation picker

```php
[
    'id'          => 'acme/embed',
    'fields'      => [
        [ 'name' => 'url', 'type' => 'url', 'label' => 'Address', 'area' => 'placeholder', 'required' => true ],
        [ 'name' => 'caption', 'type' => 'text' ],
    ],
    'placeholder' => [                       // optional
        'label'        => __( 'Embed', 'acme' ),
        'instructions' => __( 'Paste an address.', 'acme' ),
        'icon'         => 'embed-generic',
        'when'         => [ 'rules' => [ [ 'field' => 'url', 'operator' => 'empty' ] ] ],
    ],
]
```

- Fields resolving to the `placeholder` area render inside a core `<Placeholder>` instead of the canvas while the block is **unconfigured**. Only top-level fields (or containers) can declare the area.
- **Unconfigured** means: `placeholder.when` is true (a `{ relation, rules }` condition over the block's attributes and context values, written like `conditional`), or, without `when`, **every required placeholder field is empty**. If no placeholder field is `required`, every placeholder field is empty. A block with no placeholder-area field is never unconfigured. `false` and `0` count as values.
- Once configured, the placeholder fields move to the **inspector** (area `inspector`), so they stay editable. Clearing a required field brings the placeholder back. Note that with the default rule the first character typed into a lone required field configures the block; use `when` with a separate field for a different rule.
- `label` defaults to the block title; `icon` to the block's string icon. Texts are plain strings, translate them in PHP.
- The variation picker comes first: an empty block with block-scope variations shows the picker, and the placeholder shows after a variation is chosen (or skipped).

## `Rest\Config_Controller` — debugging and introspection

`GET /{prefix}-blocks/v1/config` (requires `edit_posts`) returns the exact same payload shape the inline editor bootstrap ships, for `wp eval-file`/devtools-free introspection of what the editor currently sees. It is never consulted on the registration path — the editor's own synchronous parse of `post_content` depends on blocks already being registered by the time it runs, which a REST round-trip cannot guarantee.

## Known limitations

- `conditional`/`when` rules can only reference the block's own attributes and `source: 'context'` fields, not a sibling block or a `meta`-sourced field.
- Automatic deprecation synthesis doesn't exist — `render.deprecated` must be declared explicitly (see `docs/static-save.md`).
- Instance tracking (`uuid`, `unique`) sees the blocks in the editor's main block tree, including controlled inner blocks held in the same store; blocks edited inside a separate nested editor registry (a synced pattern or template part opened in its own editor) are not tracked.
