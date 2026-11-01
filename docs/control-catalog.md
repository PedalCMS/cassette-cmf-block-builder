# Control catalog

Every control type below renders a real `@wordpress/components`/`@wordpress/block-editor` widget, wired to the field's own value via `useFieldValue()` (`source: 'attribute'`, the default, or `source: 'meta'` — see [meta-binding.md](meta-binding.md)). A control type registered server-side (`Schema\Control_Catalog`) with no client-side component renders a visible amber `Notice` naming the field and the missing type — an unrendered field is a silent content-modeling bug waiting to happen, so a gap is always visible, never silent.

## Simple value controls

| Control type | Component | Notes |
|---|---|---|
| `text` | `TextControl` | `format` (`'slug'`/`'upper'`/`'lower'`, applied on blur) and `derive_from` (continuously mirrors another field's formatted value into this one, until the user edits the field themselves) are both supported. `unique` flags (and, on duplicate, auto-suffixes) a value that collides with another block's — see [Unique values](#unique-values). |
| `textarea` | `TextareaControl` | |
| `select` | `SelectControl` | Options from `field.options`: a `value => label` map, or a list of `{ value, label }` objects (normalized to the map when the block compiles). |
| `toggle` | `ToggleControl` | |
| `checkbox_group` | `CheckboxControl` × N | Array attribute; the allowed values go on the attribute's `items.enum`. |
| `radio` | `RadioControl` | |
| `toggle_group` | `ButtonGroup` + `Button` | A segmented-button choice among `field.options`. |
| `number` | `TextControl` (`type="number"`) | |
| `email` | `TextControl` (`type="email"`) | Supports `unique`. |
| `url` | `TextControl` (`type="url"`) | Supports `unique`. |
| `date` | `TextControl` (`type="date"`) | |
| `color` | `ColorPicker` | |
| `media` | `MediaUpload`/`MediaUploadCheck` | Select/replace/remove; the attribute is the attachment ID. No thumbnail preview. |
| `media_gallery` | `MediaUpload` (`multiple`/`gallery`) | An array of attachment IDs. |
| `link` | `LinkControl`, in a `Dropdown` | `{ url, opensInNewTab }`. |
| `rich_text` | `RichText` — canvas area only | Degrades to a plain `TextareaControl` (and logs) outside the `canvas` area — a rich-text widget only makes sense inline in the block's own canvas. |
| `uuid` | none | An instance ID the editor generates and keeps unique — see [Instance IDs](#instance-ids-the-uuid-field-type). Renders no control and no notice. |
| `icon` | `TextControl` + `Dashicon` preview | A dashicon slug, typed as text. |
| `code` | `TextareaControl` (monospace) | Not a syntax-highlighted editor. |
| `image_size` | `SelectControl` | The four WordPress built-in size slugs; `field.sizes` overrides with the site's real registered sizes. |

## Structured value controls

| Control type | Shape | Notes |
|---|---|---|
| `unit` | A CSS length string, e.g. `"10px"` | `TextControl` (number) + `SelectControl` (unit); `field.units` overrides the default list. |
| `letter_spacing` | Same as `unit` | Restricted to `px`/`em`/`rem`. |
| `box` / `spacing` | `{ top, right, bottom, left }` | Four `TextControl`s + a "link sides" toggle; `box` and `spacing` are the same widget under two names — margin vs. padding is a labeling distinction, not a structural one. |
| `border` | `{ width, style, color }` | |
| `border_box` | `{ top, right, bottom, left }`, each `{ width, style, color }` | Four `border`-shaped groups. |
| `shadow` | A CSS `box-shadow` string, or `''` | `SelectControl` over a preset list; `field.presets` overrides it. |
| `angle` | A number, 0–360 | `TextControl` (number). |
| `alignment_matrix` | `"<vertical> <horizontal>"`, e.g. `"center center"` | `SelectControl` over the nine standard positions. |
| `font_size` | A CSS font-size string | `FontSizePicker`; `field.sizes` overrides the built-in presets. |
| `font_family` | A CSS font-family stack | `SelectControl`; `field.options` overrides the default list. No live theme.json lookup. |
| `font_appearance` | `"<style> <weight>"`, e.g. `"italic 700"` | Two `SelectControl`s. |
| `line_height` | A unitless number string, e.g. `"1.5"` | `TextControl` (number). |
| `key_value` | A plain object | Repeatable key/value text rows. A duplicate key mid-edit is last-write-wins, the same as a plain object literal. |
| `options_list` | An array of `{ value, label }` | Repeatable rows — order and duplicate values are both meaningful, unlike `key_value`. |
| `duotone` | `[ shadowColor, highlightColor ]` | `DuotonePicker`; `field.palette` overrides the built-in presets. |
| `conditions` | `{ relation, rules[] }` | A rule-set builder using the same relation/operator vocabulary a field's own `conditional` uses. Content-time rule *authoring* (e.g. a form-builder block letting a site editor build "show field B when field A is filled") — distinct from a field's own `conditional`, declared once by the block's author. |
| `ancestor_field` | A string: the referenced block's `name` attribute value (never a clientId) | `SelectControl` (a searchable `ComboboxControl` above 20 entries) listing `{ name, label, blockName }` of the descendants of this block's nearest `ancestor` block. See [ancestor_field](#ancestor_field). |
| `query_builder` | `{ relation, rules: [ { key, compare, value } ] }` | A rule-set builder using WP_Query's own comparison vocabulary (`=`, `LIKE`, `EXISTS`, ...) — a sibling of `conditions`, not the same control, since the operator sets and what a "field" even means differ. Only *authors* the rule shape; interpreting it into a real `WP_Query` call is a consumer's own render logic. |

## ancestor_field

```php
[
    'name'            => 'target',
    'type'            => 'ancestor_field',
    'label'           => __( 'Field', 'acme' ),
    'ancestor'        => 'vendor/form',          // required: block name of the ancestor
    'blocks'          => [ 'vendor/form-field' ],  // optional allow-list of block names
    'has_attribute'   => 'name',                 // optional: only blocks where this attribute is non-empty
    'name_attribute'  => 'name',                 // default 'name': the attribute that holds the reference name
    'label_attribute' => 'label',                // default 'label': shown in the list (falls back to the name)
    'exclude_self'    => true,                   // default true
]
```

- The list is built from the subtree of the **nearest** `ancestor` block, at any depth, in document order, with duplicate names listed once. It updates as blocks are added, renamed or removed: the control reads that one subtree through `useSelect` (the store's memoized `getBlock()`); no extra global subscription is added.
- The stored value is the **name** (a string). A value that no longer exists is listed as `missing: <name>` and kept.
- Outside the ancestor the list is empty.
- Server: schema `{ type: string }`, sanitized as plain text. **The server cannot verify the reference**; whoever consumes the value (a renderer, a form handler) must validate it.
- A missing or malformed `ancestor` / `blocks` / attribute name is a registration error (`_doing_it_wrong()`, block skipped). The validation is `Schema\Ancestor_Options::normalize()` (`@api`).

## Cross-block `conditions` rules

A `conditions` control normally edits rules about the block's **own** attributes: `{ field, operator, value? }`. Give the field an `ancestor` setting and a rule can instead point at another block by its name (the `ancestor_field` picker, same options):

```php
[
    'name'       => 'show_when',
    'type'       => 'conditions',
    'conditions' => [ 'ancestor' => 'vendor/form', 'blocks' => [ 'vendor/form-field' ] ],
]
```

Emitted value (JSON Schema style; this shape is a candidate for freezing in `STABILITY.md`):

```json
{
  "type": "object",
  "properties": {
    "relation": { "enum": [ "AND", "OR" ], "default": "AND" },
    "rules": {
      "type": "array",
      "items": {
        "oneOf": [
          {
            "description": "own attribute (unchanged)",
            "required": [ "field", "operator" ],
            "properties": {
              "field": { "type": "string" },
              "operator": { "enum": [ "==", "!=", ">", ">=", "<", "<=", "in", "not_in", "empty", "not_empty" ] },
              "value": {}
            }
          },
          {
            "description": "another block, by its name attribute",
            "required": [ "source", "ref", "operator" ],
            "properties": {
              "source": { "const": "block" },
              "ref": { "type": "string" },
              "operator": { "enum": [ "==", "!=", ">", ">=", "<", "<=", "in", "not_in", "empty", "not_empty" ] },
              "value": {}
            }
          }
        ]
      }
    }
  }
}
```

```json
{ "relation": "AND", "rules": [
  { "field": "tone", "operator": "==", "value": "warm" },
  { "source": "block", "ref": "email", "operator": "not_empty" }
] }
```

A rule is cross-block when it has `source: "block"` or a `ref`; a rule with `field` and no `source` / `ref` is exactly the previous shape. `value` and the operators mean the same in both kinds.

**The builder does not evaluate cross-block rules.** The value lives in another block, so only the consumer (a front-end script or a server render) can resolve it. `Render\Conditional_Evaluator::evaluate()` (PHP) and `conditions/evaluate.js` (JS) **ignore** them, as if they were not in the list: AND / OR run over the remaining own-attribute rules, and a set that only holds cross-block rules evaluates true. Both are tested against the same cases in `tests/fixtures/conditions.json`. A consumer that evaluates them itself can use `Conditional_Evaluator::is_cross_block_rule()` (`@api`) to split the list.

## Entity pickers

Backed by `value/useEntityOptions.js` (`@wordpress/core-data`'s `getEntityRecords()`), rendered as a searchable `ComboboxControl`.

| Control type | Entity | Notes |
|---|---|---|
| `entity_select` | Generic | `field.entity_kind`/`field.entity_name` (e.g. `'postType'`/`'page'`) pick which entity. |
| `post_select` | `postType` | `field.post_type` overrides the default `post`. |
| `term_select` | `taxonomy` | `field.taxonomy` overrides the default `category`. |
| `user_select` | `root`/`user` | |
| `taxonomy_select` | — | Picks a **taxonomy itself** (a slug, e.g. `"category"`), not a term within one — reads the site's real registered taxonomies via `getTaxonomies()` directly, not `useEntityOptions()` (a taxonomy isn't a `getEntityRecords()`-shaped entity the way posts/terms/users are). |

## Unique values

`unique` on a `text` (or `email` / `url`) field marks a value that must not repeat:

```php
'unique' => true,                                              // unique across the whole post
'unique' => [ 'scope' => 'ancestor', 'block' => 'vendor/form' ], // unique under the same nearest vendor/form
'unique' => [ 'scope' => 'ancestor', 'block' => 'vendor/form', 'key' => 'fieldName' ],
```

- **Scopes.** `post`: all blocks that take part share one group. `ancestor`: blocks are grouped by their nearest ancestor with the given block name, at any depth; blocks with no such ancestor form one separate "unscoped" group. Two forms may therefore reuse the same name.
- **`key`** (optional, defaults to the field's `name`) is the uniqueness group. Every block type registered by this library that declares a `unique` field with the same key and the same scope rule is checked together. That is how one name can be unique across different field blocks: give a "text field" block and a "dropdown field" block `'key' => 'fieldName'` and the same scope, and their names can no longer collide.
- **Comparison.** Values are trimmed, then the field's `format` is applied (`slug`, `upper`, `lower`), then compared **case-sensitively**. Empty values never collide.
- **Editor.** The block that is not the oldest owner of a value shows an inline error under its control; saving is not blocked. When a block is **duplicated or pasted** into a scope where its value is already used, the copy is renamed with the first free `-2`, `-3`, … (format applied) and the original is never touched. A renamed value counts as a user edit, so `derive_from` stops overwriting it.
- **Server.** `Render\Unique_Checker::find_collisions( $blocks, $participants, $rule )` (`@api`) runs the same rule on `parse_blocks()` output, for example when validating saved content. Both implementations are tested against the shared `tests/fixtures/unique.json`.
- The setting is validated at registration (a bad shape, or `unique` on another control type, is an error).

## The repeater control

`repeater` is the one control type that is both a container (`fields` is a row **template**, not one tree per row) and a value-holder (an array of row objects). Rows can be added, removed and reordered: drag a row by its handle (core's own `Draggable` plus native drop events, no bundled DnD library) or use the Move up / Move down buttons, which stay for keyboard users. Both paths announce the new position to screen readers ("Row moved to position 3 of 5"). Each row gets its own "row-scoped" render context, so a row's own sub-fields read and write that row's own data — a sub-field's `source: 'attribute'` means "read this row's own object", not the block's top-level attributes. See the "Repeater sub-fields" section in [block-config-reference.md](block-config-reference.md) for why a repeater's own sub-fields never leak into the block's top-level attribute schema or a `register_meta()` call.

## Display-only chrome

No attribute; nothing to read or write.

| Control type | Renders |
|---|---|
| `notice` | `Notice`. `field.message`/`field.text`, optional `field.status`. |
| `static_html` | `RawHTML`, verbatim — this is block-author config (the same PHP array/JSON as the field's own `type`/`name`), not third-party input. |
| `separator` | `<hr>`. |
| `heading` | `<h3>`. `field.text`/`field.title`. |
| `help` | `<p>`, a standalone note distinct from a control's own `description`. |
| `server_preview` | The `preview.mode: 'server'` canvas preview — see `docs/block-config-reference.md`'s `preview` section. |

## Toolbar-only controls

| Control type | Component | Notes |
|---|---|---|
| `toolbar_toggle` | `ToolbarButton` (pressable) | Boolean attribute. |
| `toolbar_dropdown` | `ToolbarDropdownMenu` | Icon-first, for a small option set. |
| `toolbar_menu` | `DropdownMenu` | Free-form textual menu. |
| `toolbar_align` | `AlignmentToolbar` | |
| `toolbar_button` | `ToolbarButton`, no attribute | Chrome only — renders but has no wired action. |
| `toolbar_link` | `ToolbarButton`, no attribute | Same "no wired action yet" gap as `toolbar_button`. |

## Containers

| Control type | Renders as |
|---|---|
| `panel` / `metabox` | `PanelBody` |
| `tab_panel` | `TabPanel` — see [The tab_panel/tab shape](#the-tab_paneltab-shape) |
| `group` | `ToolsPanel` when `resettable`, else a plain `<fieldset>` |
| `row` | `Flex` |
| `stack` | `Flex` (`direction="column"`) |
| `toolbar_group` | `ToolbarGroup` |

## The `tab_panel`/`tab` shape

Every other container takes a flat `fields` array of children. `tab_panel` is the one exception: its direct children must **all** be `tab` containers, each representing one tab.

```php
[
    'name'   => 'appearance',
    'type'   => 'tab_panel',
    'fields' => [
        [
            'name'   => 'style',       // optional — derived from "title" if omitted
            'type'   => 'tab',
            'title'  => 'Style',       // the tab's own display label
            'icon'   => 'admin-appearance',
            'fields' => [ /* ... */ ],
        ],
        [ 'type' => 'tab', 'title' => 'Advanced', 'fields' => [ /* ... */ ] ],
    ],
]
```

A `tab_panel` with a non-`tab` direct child, or a `tab` outside a `tab_panel`, throws `InvalidArgumentException` at registration.

## Instance IDs: the `uuid` field type

```php
[ 'name' => 'instanceId', 'type' => 'uuid' ]
```

A `uuid` field is a string attribute (default `''`) that identifies one block instance. It renders **no** inspector control (not even a notice) and the user cannot edit it. The editor maintains it:

- **Generated on insert.** A block that appears after the editor has loaded and has an empty value gets a lowercase v4 UUID (`crypto.randomUUID()`, falling back to `crypto.getRandomValues()`, and to `Math.random()` only if neither exists).
- **Regenerated on duplicate.** When the same value appears on two or more blocks (Duplicate, copy and paste, pasting a block from another post, at any depth), the block that already owned it keeps it and every other one gets a new value. If none of them owned it before (for example two copies pasted at once), the first in document order keeps it.
- **Never changed otherwise.** Not on load, undo/redo, transforms that keep the attribute, moves, or save. Blocks that were already in the post when the editor opened are *loaded content*: an old post with empty IDs is left alone and never marked dirty, so a consumer must handle blocks with a missing ID itself.
- The fix-up is merged into the user's own undo step, so undoing a duplicate removes the copy cleanly.
- **Server side** the value is validated (a UUID of version 1–8, any case, returned lowercased; anything else, including braces and non-strings, becomes `''`). The library never generates an ID in PHP.

### Checking your own index: `registerIdInUseCheck`

A pasted block can carry an ID that is unique in this post but already used elsewhere (another post, a custom table). To catch that, register a check on your prefix's editor payload global (`window.cassetteCmfBlocks_{camelPrefix}`, here the prefix is `acme`) before the library's script runs:

```js
window.cassetteCmfBlocks_acme.registerIdInUseCheck( ( id, blockName ) =>
    fetch( '/my/index/' + id ).then( ( r ) => r.json() ) // boolean | Promise<boolean>
);
```

It is called only for blocks that *appeared* in the editor (inserted or pasted) and kept their ID; if any registered check returns or resolves `true`, that copy gets a new ID. Errors and rejections are ignored (logged once in development builds). It is not called for loaded content, for duplicates (they are regenerated anyway), or for a transform that merely moved the value to a new block. This is a documented JS extension point; its stability is decided with the 1.0 API review.

## Field sourcing scope

Fields with `source: 'attribute'` (the default) or `source: 'meta'` render normally.

A field with `source: 'context'` reads a value that an ancestor block provides through `providesContext`:

```php
[ 'name' => 'formId', 'type' => 'text', 'source' => 'context', 'context' => 'example/formId' ]
```

`context` names the block-context key and is required (a missing or empty value is a registration error). The key is added to the block's `usesContext` automatically (merged with any explicit `args.usesContext`, de-duplicated). The field is **read-only**: no attribute is created, nothing is stored, and the inspector renders nothing for it (a read-only display was considered and left out: the value is visible in the canvas, and a disabled input would only add noise). Its value is available under the field's `name` everywhere an attribute is: markup templates (`{{ attributes.formId }}`), PHP templates (`$attributes['formId']`), conditions (`conditional` / `when` rules), and the editor's markup preview, which updates live when the provider's attribute changes. Render callbacks can also read `$block->context['example/formId']`. A key the provider did not supply is `null`. The `preview.mode: 'server'` preview cannot show it (the REST block renderer takes no block context), so use the markup preview for blocks that rely on context.

**Inside a document/sidebar/more_menu-area panel** (editor-wide UI with no single block instance behind it — see `docs/block-config-reference.md`'s document-scope section), `source: 'attribute'` is *also* treated as unsupported: there is no block instance to read attributes from there, only `source: 'meta'` has a real read/write path.

## Conditionals

A field's `conditional` is evaluated live in the editor against the block's own attributes plus its `source: 'context'` fields — a failing condition hides the whole field, container children included. A rule can reference a context-sourced field by name like any attribute; it still can't reference a sibling block or post meta.

## The `placeholder` area

Fields with `area: 'placeholder'` render inside a core `<Placeholder>` in place of the canvas while the block is **unconfigured**; once it is configured they move to the inspector (area `inspector`), so the values stay editable. See [block-config-reference.md](block-config-reference.md#placeholder-and-the-variation-picker).

## A note on experimental APIs

`@wordpress/components` ships several genuinely useful controls under a permanent `__experimental` prefix that, in practice, has outlived several major WordPress releases — an unstable-prefixed export can be renamed or removed without a deprecation path, which `@wordpress/no-unsafe-wp-apis` (part of the standard `@wordpress/scripts` lint config) flags. This library takes different positions depending on whether a genuinely stable substitute exists:

- **`number`** uses `TextControl` (`type="number"`) instead of `NumberControl` — `TextControl` spreads unrecognized props straight onto the underlying `<input>`, so `min`/`max`/`step` reach the native number input unchanged, with zero functional loss.
- **`toggle_group`** uses `ButtonGroup` + `Button` (`isPressed`) instead of `ToggleGroupControl` — both stable, visually a very close match.
- **`stack`** uses `Flex` (`direction="column"`) instead of `VStack` — functionally identical, `Flex`/`FlexItem` are stable.
- **`angle`** uses a plain bounded number input instead of `AnglePickerControl`'s dial widget; **`alignment_matrix`** uses a `SelectControl` instead of `AlignmentMatrixControl`'s interactive grid — same value shape either way, only the editing widget differs.
- **`unit`**/**`letter_spacing`** use `TextControl` (number) + `SelectControl` (unit) instead of `__experimentalUnitControl`.
- **`box`**/**`spacing`**/**`border`**/**`border_box`** use hand-rolled grids instead of `BoxControl`/`BorderControl`/`BorderBoxControl`, all still `__experimental`-only.
- **`font_family`**/**`font_appearance`** use plain `SelectControl`(s) instead of `__experimentalFontFamilyControl`/`__experimentalFontAppearanceControl` — both of those also depend on live theme.json font-family data this library doesn't fetch, so a declarative `field.options` list is the more honest fit regardless of the experimental-prefix question.
- **`font_size`** and **`duotone`** are the two typography-adjacent controls that genuinely ARE stable — `FontSizePicker` and `DuotonePicker` carry no experimental prefix, so they're used directly.
- **`group`**'s `resettable` mode uses `ToolsPanel`/`ToolsPanelItem` anyway, with a scoped `eslint-disable` — there is no stable equivalent for "a set of controls with a reset-to-default affordance," and WordPress core's own blocks (`core/group`, `core/cover`, ...) depend on the same experimental exports for the same reason. If a future WordPress release renames or removes these exports, `resettable` groups break until this library is updated; the plain (non-resettable) `<fieldset>` path is unaffected.

## Custom controls and the JS extension points

These are **public and stable** from 1.0.0 (see [STABILITY.md](STABILITY.md)). Custom controls are the main extension need of a consumer with a zero-JS config: you declare the type on the server, then supply its editor component.

**1. Declare the type (PHP, before `init`):**

```php
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

Control_Catalog::register_type( 'star_rating', [
    'value_type' => 'integer',  // required: string | number | integer | boolean | array | object | null
    // optional: 'cmf_type' => 'number'  (reuse a cassette-cmf sanitizer/validator; default null = value kept as is)
    // optional: 'is_container' => false, 'holds_value' => true  (default: true when value_type is not null)
] );
```

The type id must match `^[a-z][a-z0-9_]*$`; a malformed id or `value_type` throws `InvalidArgumentException`. The `cassette_cmf_blocks_control_catalog` filter is the equivalent for code that cannot call `register_type()` early (a malformed entry is dropped with a `_doing_it_wrong()` notice).

**2. Register the editor component (JS)** in a script whose dependencies include the editor handle (`{prefix}-blocks-editor`), on the payload global `window.cassetteCmfBlocks_{camelPrefix}`:

```js
const blocks = window.cassetteCmfBlocks_acme;

blocks.registerControlType( 'star_rating', ( { field, value, onChange } ) => /* element */ );
```

The component receives `{ field, value, onChange, type }`: `field` is the raw field config, `value` the current attribute value, `onChange( next )` writes it. `scope` and `area` are also passed but are best-effort, not part of the contract. An unknown type shows the "unsupported control" notice.

The three extension points on the payload global:

| Function | Purpose |
|---|---|
| `registerControlType( type, Component )` | The editor half of a custom control (above). Overrides a built-in of the same name. |
| `registerMigration( name, fn )` | The function behind `render.deprecated[].migrate: 'name'` ([static-save.md](static-save.md)); `fn` is WordPress's `migrate( attributes, innerBlocks )`. |
| `registerIdInUseCheck( fn )` | The `uuid` veto hook, [described above](#instance-ids-the-uuid-field-type). |

**Load order.** The editor bundle registers every block (and builds each block's `deprecated` list) the moment it runs. Calls made by a script that depends on the editor handle run *before* the bundle; the library therefore installs queueing stubs on the payload global first and replays the queued calls, in order, before registering any block. Calls made after the bundle has run work too, except `registerMigration()`, which must be called before (it is baked into the block type at registration). So: depend on `{prefix}-blocks-editor` and call all three at the top level of your script.
