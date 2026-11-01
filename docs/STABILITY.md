# Stability promise

From the **`1.0.0`** tag, everything on this page is frozen. A change to a frozen surface ships only in a **major** version, with a `Breaking` entry and a migration note in [CHANGELOG.md](../CHANGELOG.md). New control types, config keys, hooks and `@api` methods are additive and may arrive in any minor release; bug fixes that make the library behave as this page already says are patch releases.

Before 1.0 the library follows the usual 0.x rule (a minor release may break, always with a changelog entry and a migration note). The Playwright specs are listed in the README for contributors; every rule below that the editor enforces is also covered by Jest or PHPUnit, and the table cases in this page are executable in `tests/Unit/Test_Stability_Contract.php`.

## Contents

1. [The casing rule for config keys](#1-the-casing-rule-for-config-keys)
2. [Attribute-schema mapping](#2-attribute-schema-mapping)
3. [Render pipeline order](#3-render-pipeline-order)
4. [Sanitizer output per control type](#4-sanitizer-output-per-control-type)
5. [Public PHP API (`@api`)](#5-public-php-api-api)
6. [Hooks](#6-hooks)
7. [Public JS extension points](#7-public-js-extension-points)
8. [Identity: the prefix and the names derived from it](#8-identity-the-prefix-and-the-names-derived-from-it)
9. [Emitted `conditions` JSON](#9-emitted-conditions-json)
10. [`unique` rules](#10-unique-rules)
11. [`uuid` rules](#11-uuid-rules)
12. [What is not frozen](#12-what-is-not-frozen)
13. [Semver rule and the recommended 1.0.0 point](#13-semver-rule-and-the-recommended-100-point)

## 1. The casing rule for config keys

One rule decides every spelling, so a key is never a guess:

- **Library-owned keys are `snake_case`**: `id`, `fields`, `render`, `inner_blocks`, `template_lock`, `document_scope`, `post_types`, `block_categories`, and every field setting (`value_type`, `cmf_type`, `derive_from`, `has_attribute`, `exclude_self`, `migrate_php`, ...). This matches cassette-cmf.
- **`args` follows `block.json` (`camelCase`)**, because it is a pass-through to WordPress: `apiVersion`, `allowedBlocks`, `providesContext`, `editorScript`, `viewScriptModule`, ... The single exception is `variation_callback`, a `register_block_type()`-only key that has no `block.json` spelling.
- **Data shaped by WordPress keeps WordPress's casing**: `supports`, `variations[].isActive`, `variations[].innerBlocks`, a pattern's `blocks[].blockName` / `attrs`, the `innerBlocks` a transform callback returns, block templates.
- **Control type names are `snake_case`** (`checkbox_group`, `toggle_group`, `tab_panel`).

The allowed `args` keys are `Block_Definition::allowed_arg_keys()`; anything else is dropped with `_doing_it_wrong()`.

## 2. Attribute-schema mapping

Every value-bearing field with `source: 'attribute'` (the default) becomes one block attribute `{ type, default, enum? | items.enum?, role? }`. `meta`, `context` and `none` sourced fields have no attribute.

| Property | Rule |
|---|---|
| `type` | the field's `value_type` setting, else the control's catalog `value_type` (table below) |
| `default` | the field's `default`, else the zero value of the type: `string` `''`, `number` / `integer` `0`, `boolean` `false`, `array` `[]`, `object` `[]` |
| `enum` | from `options` (the keys of the value => label map; a list of `{ value, label }` objects is normalised to that map first) or from an explicit `enum`. For `string` and `array` attributes the values are cast to strings. |
| `items.enum` | the same values, for an `array` attribute such as `checkbox_group` (a top-level `enum` on an array would reject every real selection) |
| `role` | copied when set |
| `uuid` | `{ type: 'string', default: '' }`; no `enum`, no control |
| repeater sub-fields | never separate attributes; their values live in the repeater's own array attribute |

The `uuid` type, the instance rules and the sanitizer are in sections 4 and 11. A field name is the attribute name and must not collide with an `attributes` pass-through entry (the block fails to register).

### Control types

`value_type` is the attribute JSON type (`none` = the control holds no value). `cmf_type` is the cassette-cmf field type whose `sanitize()` / `validate()` is reused (`none` = the value is kept as WordPress validated it).

| Control type | value_type | cmf_type | Kind |
|---|---|---|---|
| `text` | string | text | value |
| `textarea` | string | textarea | value |
| `select` | string | select | value |
| `toggle` | boolean | checkbox | value |
| `checkbox_group` | array | checkbox | value |
| `radio` | string | radio | value |
| `number` | number | number | value |
| `email` | string | email | value |
| `url` | string | url | value |
| `date` | string | date | value |
| `color` | string | color | value |
| `media` | integer | upload | value |
| `uuid` | string | none | value |
| `rich_text` | string | none | value |
| `media_gallery` | array | none | value |
| `link` | object | none | value |
| `options_list` | array | none | value |
| `entity_select` | integer | none | value |
| `post_select` | integer | none | value |
| `term_select` | integer | none | value |
| `user_select` | integer | none | value |
| `taxonomy_select` | string | none | value |
| `sibling_field` | string | none | value |
| `ancestor_field` | string | text | value |
| `conditions` | object | none | value |
| `unit` | string | none | value |
| `box` | object | none | value |
| `border` | object | none | value |
| `border_box` | object | none | value |
| `spacing` | object | none | value |
| `shadow` | string | none | value |
| `angle` | number | none | value |
| `alignment_matrix` | string | none | value |
| `font_size` | string | none | value |
| `font_family` | string | none | value |
| `font_appearance` | string | none | value |
| `line_height` | string | none | value |
| `letter_spacing` | string | none | value |
| `icon` | string | none | value |
| `code` | string | none | value |
| `key_value` | object | none | value |
| `toggle_group` | string | radio | value |
| `duotone` | array | none | value |
| `image_size` | string | none | value |
| `query_builder` | object | none | value |
| `notice` | - | custom_html | display |
| `static_html` | - | custom_html | display |
| `separator` | - | none | display |
| `heading` | - | none | display |
| `help` | - | none | display |
| `server_preview` | - | none | display |
| `panel` | - | metabox | container |
| `metabox` | - | metabox | container |
| `tab_panel` | - | tabs | container |
| `tab` | - | none | container |
| `group` | - | group | container |
| `row` | - | none | container |
| `stack` | - | none | container |
| `toolbar_group` | - | none | container |
| `repeater` | array | repeater | container |
| `toolbar_button` | - | none | display |
| `toolbar_toggle` | boolean | checkbox | value |
| `toolbar_dropdown` | string | select | value |
| `toolbar_menu` | string | select | value |
| `toolbar_link` | - | none | display |
| `toolbar_align` | string | none | value |

A custom type is added with `Control_Catalog::register_type()` ([control-catalog.md](control-catalog.md#custom-controls-and-the-js-extension-points)); the ids above are frozen, new built-ins may be added in a minor release.

## 3. Render pipeline order

For a `mode: 'dynamic'` block, `Block_Definition::sanitize_attributes()` and the render callback run, in this order:

1. **Deprecation migration**: `render.deprecated` entries whose old shape matches are applied (declarative `migrate`, then `migrate_php`); a no-op for blocks that declare none and for static blocks.
2. **WordPress attribute preparation** (`WP_Block_Type::prepare_attributes_for_render()`): a value that fails its attribute schema is dropped and the schema default is used.
3. **Sanitize**: each attribute-sourced field through its sanitizer (section 4).
4. **Context values**: `source: 'context'` fields are added to the scope under the field's name (`null` when the provider did not supply the key).
5. **Conditions**: `when` rules on nodes inside a `render.markup` tree are evaluated against that scope while the tree renders. (`callback` and `template` blocks decide for themselves.)
6. **Render**: the first of `render.callback`, `render.template`, `render.markup` that is set.
7. **Filters**: `cassette_cmf_blocks_render`, then `cassette_cmf_blocks_render_{block}`.

`sanitize_attributes()` performs steps 1 to 3 (context values are added by the renderer, not by that method). The result for the same input is what a callback, template or markup tree receives.

## 4. Sanitizer output per control type

Step 3 of the pipeline. "Input" is the value after WordPress attribute preparation (so a value of the wrong JSON type has already been replaced by the default, and a value outside `enum` / `items.enum` too: one bad item in a `checkbox_group` drops the whole selection).

| cmf_type (used by) | Output |
|---|---|
| `text` (`text`, `ancestor_field`) | `sanitize_text_field()`: tags stripped, whitespace collapsed and trimmed, string |
| `textarea` (`textarea`) | `sanitize_textarea_field()` (newlines kept); with `allow_html: true`, `wp_kses_post()` |
| `select`, `radio` (`select`, `radio`, `toggle_group`, `toolbar_dropdown`, `toolbar_menu`) | the value if it is a key of `options`, else `''` |
| `checkbox` with `options` (`checkbox_group`) | the values that are keys of `options` (an array) |
| `checkbox` without `options` (`toggle`, `toolbar_toggle`) | **boolean**: `true` for `true`, `1`, `'1'`, otherwise `false` |
| `number` (`number`) | `int`, or `float` when the value contains a `.`; `''` is replaced by the default |
| `email` (`email`) | `sanitize_email()`: a valid address or `''` |
| `url` (`url`) | `esc_url_raw()`: a URL with an allowed protocol, else `''` |
| `date` (`date`) | `sanitize_text_field()` (the format is not checked; the editor control produces `Y-m-d`) |
| `color` (`color`) | `#rgb` or `#rrggbb` (a leading `#` is added); anything else is the field's `default`, else `#000000` |
| `upload` (`media`) | **integer** attachment ID; anything not numeric is the field's `default`, else `0` |
| `repeater` (`repeater`) | a list of rows; each sub-field is sanitized by its own type, rows that are not arrays are dropped |
| `custom_html` (`notice`, `static_html`) | no value (display only) |
| `uuid` | a lowercase UUID (versions 1 to 8, RFC 4122 variant), else `''` |
| none: `rich_text` | `wp_kses_post()`; a non-string is the default |
| none: `code` | a string, **not** cleaned (it is code); a non-string is the default |
| none: `icon` | a string of letters, digits and `_ - . : /` (max 100), else the default |
| none: `link` | an object with only the keys `url` (`esc_url_raw()`), `title` (text), `opensInNewTab` (boolean), `id` (positive integer, else `0`), `type` and `kind` (`sanitize_key()`); other keys are dropped, a non-object is the default; missing keys stay missing |
| none: `box`, `spacing` | an object with only the sides `top`, `right`, `bottom`, `left` that were sent; each is `''`, `auto`, a CSS length (`10px`, `-2em`, `0`; units `px em rem % vw vh vmin vmax ch ex pt pc cm mm in`) or a spacing preset (`var:preset\|spacing\|40`), else `''` |
| none: `border` | an object with only the keys `width` (a non-negative CSS length), `style` (a CSS border style keyword), `color` (hex, `rgb()`/`rgba()`/`hsl()`/`hsla()`, a keyword or a color preset); an invalid part is `''` |
| none: `border_box` | an object with only the sides sent, each a `border` object |
| none: `shadow` | `''`, a shadow preset, a value from the field's `presets`, or a plain box-shadow made of letters, digits, spaces and `. , ( ) # % -` (max 200; `url(`, `expression` and `image` are refused), else the default |
| none: `angle` | a number clamped to 0 to 360 (a numeric string is converted); anything else is the default |
| none: `alignment_matrix` | `''` or one of the nine `"<vertical> <horizontal>"` positions, else the default |
| none: `toolbar_align` | `''`, `left`, `center`, `right` or `justify`, else the default |
| none: `unit`, `letter_spacing` | `''`, or a number followed by one of the field's `units` (default `px em rem % vw vh`; `letter_spacing`: `px em rem`), else the default |
| none: `font_size` | `''`, a CSS length, a font-size preset, a size from the field's `sizes`, or a `calc()`/`min()`/`max()`/`clamp()` expression with no other function, else the default |
| none: `font_family` | a key of the field's `options` when it has any; otherwise `''`, a font-family preset or a stack of letters, digits, spaces and `, - _ . ' "` (max 200); else the default |
| none: `font_appearance` | `''` or `<normal\|italic\|oblique> <100..900 \| normal \| bold>`, else the default |
| none: `line_height` | `''`, a non-negative unitless number string or a non-negative CSS length, else the default |
| none: `duotone` | a list of at least two colors (as for `border`), or `[]`; one invalid color makes the whole value the default |
| none: `image_size` | `''`, a key of the field's `sizes` when it has any, otherwise `full` or a registered image size; else the default |
| none: `key_value` | a map of non-empty keys to string values; keys and values go through `sanitize_text_field()`, entries whose value is an array are dropped; a non-array is the default |
| none: `options_list` | a list of rows with only the keys `value` and `label` (both `sanitize_text_field()`); rows that are not arrays are dropped |
| none: `media_gallery` | a list of positive integers; other items are dropped, a non-array is the default |
| none: `entity_select`, `post_select`, `term_select`, `user_select` | a positive integer (an integer or a digit string); with `value_type: array`, a list of positive integers; anything else is the default |
| none: `taxonomy_select` | `''` or a taxonomy key (`a-z 0-9 _ -`, max 32), else the default |
| none: `sibling_field` | `''` or a field name (letters, digits, `_ - .`, max 100), else the default |
| none: `conditions` | `{ relation: AND\|OR, rules }` where each rule keeps only `field`, `ref` (text), `source` (only `block`), `operator` (one of `== != > >= < <= in not_in empty not_empty`, else `==`) and `value` (text, number, boolean or a list of those) |
| none: `query_builder` | `{ relation: AND\|OR, rules }` where each rule is `key` (text), `compare` (one of `= != > >= < <= LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `EXISTS`, `NOT EXISTS`, else `=`) and an optional `value` (as for `conditions`) |

"The default" above is the field's `default`, else the empty value of the control's JSON type (`''`, `0`, `[]`). A control type that a consumer registers with `Control_Catalog::register_type()` and no `cmf_type` is not sanitized by the library: its value is only checked by WordPress's attribute-schema validation, so its own render code must escape what it prints. `render.markup` escapes everything it prints in any case, and a `callback` / `template` should still escape what it echoes.

A field's `format` (`slug`, `upper`, `lower`) is an editor-side convenience, not a server sanitizer.

## 5. Public PHP API (`@api`)

Only items tagged `@api` are frozen. Their signatures, return types and documented behaviour do not change in a minor release.

**`PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks`**

| Method | Contract |
|---|---|
| `boot( array $options = [] ): Block_Manager` | Call once, before `register_from_*()`. Options (section 8): `prefix` (string, required), `bindings_source` (bool, default `false`). Unknown keys, wrong types and a conflicting second call raise `_doing_it_wrong()` and are ignored; an identical second call is silent. |
| `register_from_array( array $config ): Block_Manager` | Register `blocks`, `block_categories`, `editor_scope`, `patterns`, `pattern_categories`. |
| `register_from_json( string $path_or_json, bool $validate = true ): Block_Manager` | Same, from a file path or a JSON string, validated against `block-schema.json`. |
| `get_definition( string $block_name ): ?Block_Definition` | The compiled definition, or `null` (not booted, unknown block, or a config that does not compile). Never boots the library. |
| `init(): Block_Manager` | The singleton (auto-boots with defaults and a missing-prefix notice). |

**`Core\Block_Manager`**: `boot()`, `get_option( string $key )`, `register_from_array()` and `register_from_json()` (chainable; they return the manager). Its other public methods are internal.

**`Core\Block_Definition`**: `sanitize_attributes( array $attributes ): array` (steps 1 to 3 of the pipeline), `attribute_schema(): array` (the `attributes` passed to `register_block_type()`), `allowed_arg_keys(): array`, and the constant `ASSET_KEYS` (`editorScript`, `editorStyle`, `script`, `style`, `viewScript`, `viewStyle`, `viewScriptModule`).

**`Core\Identity`** (all of it): `prefix()`, `editor_script_handle()`, `editor_style_handle()`, `rest_namespace()`, `binding_source_name()`, `js_global()`, `payload_cache_group()`, `is_valid_prefix()`, and the constants `PREFIX_PATTERN`, `FALLBACK_PREFIX`, `JS_BOOT_KEY`, `TEXT_DOMAIN`.

**`Schema\Control_Catalog`**: `register_type( string $type, array $spec ): void`, `get_type()`, `has_type()`, `get_registered_types()`. The spec is `value_type` (required: `string`, `number`, `integer`, `boolean`, `array`, `object` or `null`), `cmf_type` (default `null`), `is_container` (default `false`), `holds_value` (default: `value_type` is not `null`); the type id matches `^[a-z][a-z0-9_]*$`; a malformed registration throws `InvalidArgumentException`.

**`Render\Attribute_Sanitizer::sanitize_uuid( $value ): string`**, **`Render\Unique_Checker`** (`normalize_rule()`, `normalize_value()`, `find_collisions()`, `SUPPORTED_CONTROLS`), **`Render\Conditional_Evaluator`** (`evaluate()`, `is_cross_block_rule()`), **`Render\Deprecation_Migrator`** (`apply_declarative()`, `is_eligible()`, `migrate()`), **`Schema\Ancestor_Options::normalize()`**.

Everything else is internal even when it is `public` in PHP (`Block_Manager::reset()`, `compile_block()`, getters; `Block_Definition::get_raw_config()`; `Control_Catalog::register_defaults()`, `unregister_type()`, `reset()`; `Cmf_Bridge`; `Requirements`; the registrars, `Editor_Payload`, `Asset_Loader` (apart from its constants named in section 7), the REST controllers' PHP classes).

## 6. Hooks

Names, argument lists and meaning are frozen. All are prefixed `cassette_cmf_blocks_`; a variant with `_{id}` appends the block name with `/` and `-` replaced by `_`.

| Hook | Type | Arguments |
|---|---|---|
| `cassette_cmf_blocks_register_config` | filter | `array $config` |
| `cassette_cmf_blocks_block_config`, `..._block_config_{id}` | filter | `array $block_config, string $id` (the `_{id}` form gets the config only) |
| `cassette_cmf_blocks_registered` | action | `Block_Manager $manager` |
| `cassette_cmf_blocks_block_variations`, `..._block_variations_{id}` | filter | `array $variations, string $id` (the `_{id}` form gets the list only) |
| `cassette_cmf_blocks_render` | filter | `string $html, string $block_name, array $attributes, WP_Block $block` |
| `cassette_cmf_blocks_render_{id}` | filter | `string $html, array $attributes, WP_Block $block` |
| `cassette_cmf_blocks_control_catalog` | filter | `array $types` (a malformed entry is dropped with a notice) |
| `cassette_cmf_blocks_binding_allowed` | filter | `bool $allowed, array $source_args, WP_Block $block`; narrowing only (a `false` refuses, a `true` never widens) |
| `cassette_cmf_blocks_assets_url` | filter | `string $url, string $prefix` |
| `cassette_cmf_blocks_payload_cache_skip` | filter | `bool $skip` |

The bindings source (`{prefix}/field`) is opt-in. Its security rules are frozen as a floor: it never resolves a field that is not registered with cassette-cmf and marked `bindings: true`, never resolves `password` / `secret` fields or protected meta, and never resolves a post the viewer cannot read.

## 7. Public JS extension points

On the bundle's payload global `window.cassetteCmfBlocks_{camelPrefix}` (section 8). Call them from a script that lists `{prefix}-blocks-editor` as a dependency; calls made before the bundle runs are queued and replayed before any block is registered, so call order between the three does not matter. `Asset_Loader::EXTENSION_POINTS` lists the names.

| Function | Contract |
|---|---|
| `registerControlType( type, Component )` | `type` matches `^[a-z][a-z0-9_]*$`; `Component` is a React component rendered with `{ field, value, onChange, type }` (`field` is the raw field config, `onChange( next )` writes the value). Props `scope` and `area` are also passed but are not frozen. Replaces a built-in of the same type. Pairs with `Control_Catalog::register_type()` on the server. |
| `registerMigration( name, fn )` | `fn( attributes, innerBlocks )` is WordPress's `deprecated.migrate` signature; `name` is the string used in `render.deprecated[].migrate`. Must be registered before the block is registered (the queue guarantees this). |
| `registerIdInUseCheck( fn )` | `fn( id, blockName ) => boolean \| Promise<boolean>`; a `true` answer makes the library regenerate that `uuid` on a newly inserted or pasted block. Any number may be registered. |

Malformed registrations are ignored. The hand-over variable `window.cassetteCmfBlocksBoot` is internal.

## 8. Identity: the prefix and the names derived from it

`boot( [ 'prefix' => 'acme' ] )` is required. The prefix matches `^[a-z][a-z0-9-]{1,30}[a-z0-9]$` (`Identity::PREFIX_PATTERN`). A missing or invalid prefix raises `_doing_it_wrong()` and uses `cassette-cmf`. With `{p}` the prefix and `{P}` its camelCase form (`my-shop` gives `myShop`; a word that starts with a digit keeps an underscore, so `a-1b` gives `a_1b`):

| Identifier | Value |
|---|---|
| Editor script handle | `{p}-blocks-editor` |
| Editor style handle | `{p}-blocks-editor-style` |
| REST namespace | `{p}-blocks/v1` (routes: `GET /config`, `POST /transform`) |
| Block bindings source | `{p}/field` |
| JS payload global | `window.cassetteCmfBlocks_{P}` |
| Payload cache group | `cassette_cmf_blocks_{p_snake}` |

These derivations are frozen: content stored by consumers (a binding source name inside post content) depends on them.

## 9. Emitted `conditions` JSON

The value a `conditions` control stores (and `preview.when` / markup `when` / a field's `conditional` accept):

```json
{
  "relation": "AND",
  "rules": [
    { "field": "tone", "operator": "==", "value": "warm" },
    { "source": "block", "ref": "email", "operator": "not_empty" }
  ]
}
```

- `relation`: `"AND"` (default) or `"OR"`.
- Own-attribute rule: `{ field: string, operator, value? }`.
- Cross-block rule: `{ source: "block", ref: string, operator, value? }`. A rule is cross-block when it has `source: "block"` or a `ref`.
- `operator` is one of `==`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `empty`, `not_empty` (`empty` / `not_empty` carry no `value`).
- Evaluation: an empty rule list is true. **The builder never evaluates cross-block rules**: both evaluators (`Conditional_Evaluator::evaluate()` and `conditions/evaluate.js`) ignore them as if absent, so AND / OR run over the own-attribute rules and a list that only holds cross-block rules is true. The two evaluators are tested against the shared `tests/fixtures/conditions.json`.

## 10. `unique` rules

On `text`, `email` and `url` fields only (anything else is a registration error): `true`, or `{ scope: 'post' | 'ancestor', block?, key? }`. Canonical payload form: `{ scope, block?, key }`.

- `scope: 'post'` groups every participating block of the post; `scope: 'ancestor'` groups blocks by their nearest ancestor named `block`, at any depth (blocks with no such ancestor form one separate group). `block` alone implies `ancestor`; `scope: 'post'` with `block` is an error.
- `key` (default: the field name) is the uniqueness group; every block type that declares the same `key` and scope rule is checked together.
- Comparison: trim, apply the field's `format` (`slug`, `upper`, `lower`), compare **case-sensitively**; an empty value never collides.
- Editor: the oldest owner (first seen; ties by document order) keeps the value; a newly duplicated or pasted copy gets the first free `-2`, `-3`, ... suffix; an existing non-owner is flagged inline; saving is never blocked.
- Server: `Unique_Checker::find_collisions()` applies the same rule to `parse_blocks()` output; the first entry of each group is the one to keep. PHP and JS share `tests/fixtures/unique.json`.

## 11. `uuid` rules

- Server: the value must be a UUID (versions 1 to 8, RFC 4122 variant, any case) and comes back lowercased; anything else is `''`. The library never generates a UUID in PHP.
- Editor: a block inserted after load with an empty value gets a lowercase v4 UUID; when one value is on two or more blocks, the existing owner keeps it and every other gets a new one (none was an owner: the first in document order keeps it); never changed on load, undo / redo, moves, transforms that keep the attribute, or save. Blocks that were in the post when the editor opened are loaded content and are not touched (a post with empty IDs is not dirtied). `registerIdInUseCheck()` can veto a value on a new block.
- The fix-ups are merged into the user's undo step.

## 12. What is not frozen

- Every class, method and constant not listed in section 5, even if `public`.
- The editor payload (`Editor_Payload`, the inline script, its JSON keys such as `templateLock`, `restNamespace`, `placeholder`) and the one-shot `cassetteCmfBlocksBoot` hand-over.
- CSS class names, DOM ids and markup of the editor UI (the `cassette-cmf-block-builder-*` classes), and the look and wording of any editor control or notice.
- The exact set of built-in control types beyond the ids in section 2 (new ones may be added) and the props of built-in components.
- `scope` and `area` props passed to custom controls.
- The compiled bundle (`src/assets/build/`), its dependencies and handles beyond those in section 8, and the npm tooling.
- The contents of `_doing_it_wrong()` messages and the order of keys in returned arrays.
- `block-schema.json` may gain keys in a minor release; it will only be tightened (rejecting a config that loads today) in a major release.
- Behaviour that is documented as best effort or as a known limitation.

## 13. Semver rule and the recommended 1.0.0 point

- **Major**: a change to anything in sections 1 to 11 (a renamed or removed key, a different default, `enum` derivation, sanitizer output, pipeline order, signature, hook argument, derived name, JSON shape). It carries a `Breaking` changelog entry with a one-line migration note.
- **Minor**: additive changes only (new control types, config keys, `@api` methods, hooks, optional arguments).
- **Patch**: bug fixes, including a fix that makes the library match this page.

**Recommended `1.0.0` tag point**: the commit that contains this file, once (1) the Playwright specs have run against a real editor (done once, Chromium on WordPress 7.1.4; other browsers and WordPress 6.8 in the editor are still unchecked), (2) `composer.lock` has been refreshed against the tagged `pedalcms/cassette-cmf` 0.2.0 (`composer update pedalcms/cassette-cmf`), so the PHP 7.4 CI cell resolves, and (3) CI is green on the PHP 7.4 and latest-PHP cells. Intermediate tag: `0.2.0`, then `1.0.0`.
