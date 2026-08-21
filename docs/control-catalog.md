# Control catalog

Every control type below renders a real `@wordpress/components`/`@wordpress/block-editor` widget, wired to the field's own value via `useFieldValue()` (`source: 'attribute'`, the default, or `source: 'meta'` — see [meta-binding.md](meta-binding.md)). A control type registered server-side (`Schema\Control_Catalog`) with no client-side component renders a visible amber `Notice` naming the field and the missing type — an unrendered field is a silent content-modeling bug waiting to happen, so a gap is always visible, never silent.

## Simple value controls

| Control type | Component | Notes |
|---|---|---|
| `text` | `TextControl` | `format` (`'slug'`/`'upper'`/`'lower'`, applied on blur) and `derive_from` (continuously mirrors another field's formatted value into this one, until the user edits the field themselves) are both supported. `unique` is not implemented. |
| `textarea` | `TextareaControl` | |
| `select` | `SelectControl` | Options from `field.options` (`value => label`). |
| `toggle` | `ToggleControl` | |
| `checkbox_group` | `CheckboxControl` × N | Array attribute. |
| `radio` | `RadioControl` | |
| `toggle_group` | `ButtonGroup` + `Button` | A segmented-button choice among `field.options`. |
| `number` | `TextControl` (`type="number"`) | |
| `email` | `TextControl` (`type="email"`) | |
| `url` | `TextControl` (`type="url"`) | |
| `date` | `TextControl` (`type="date"`) | |
| `color` | `ColorPicker` | |
| `media` | `MediaUpload`/`MediaUploadCheck` | Select/replace/remove; the attribute is the attachment ID. No thumbnail preview. |
| `media_gallery` | `MediaUpload` (`multiple`/`gallery`) | An array of attachment IDs. |
| `link` | `LinkControl`, in a `Dropdown` | `{ url, opensInNewTab }`. |
| `rich_text` | `RichText` — canvas area only | Degrades to a plain `TextareaControl` (and logs) outside the `canvas` area — a rich-text widget only makes sense inline in the block's own canvas. |
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
| `query_builder` | `{ relation, rules: [ { key, compare, value } ] }` | A rule-set builder using WP_Query's own comparison vocabulary (`=`, `LIKE`, `EXISTS`, ...) — a sibling of `conditions`, not the same control, since the operator sets and what a "field" even means differ. Only *authors* the rule shape; interpreting it into a real `WP_Query` call is a consumer's own render logic. |

## Entity pickers

Backed by `value/useEntityOptions.js` (`@wordpress/core-data`'s `getEntityRecords()`), rendered as a searchable `ComboboxControl`.

| Control type | Entity | Notes |
|---|---|---|
| `entity_select` | Generic | `field.entity_kind`/`field.entity_name` (e.g. `'postType'`/`'page'`) pick which entity. |
| `post_select` | `postType` | `field.post_type` overrides the default `post`. |
| `term_select` | `taxonomy` | `field.taxonomy` overrides the default `category`. |
| `user_select` | `root`/`user` | |
| `taxonomy_select` | — | Picks a **taxonomy itself** (a slug, e.g. `"category"`), not a term within one — reads the site's real registered taxonomies via `getTaxonomies()` directly, not `useEntityOptions()` (a taxonomy isn't a `getEntityRecords()`-shaped entity the way posts/terms/users are). |

## The repeater control

`repeater` is the one control type that is both a container (`fields` is a row **template**, not one tree per row) and a value-holder (an array of row objects). Rows can be added, removed, and moved up/down (no drag-and-drop — no DnD dependency this library bundles). Each row gets its own "row-scoped" render context, so a row's own sub-fields read and write that row's own data — a sub-field's `source: 'attribute'` means "read this row's own object", not the block's top-level attributes. See the "Repeater sub-fields" section in [block-config-reference.md](block-config-reference.md) for why a repeater's own sub-fields never leak into the block's top-level attribute schema or a `register_meta()` call.

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

## Field sourcing scope

Fields with `source: 'attribute'` (the default) or `source: 'meta'` render normally. A field declaring `source: 'context'` is present in the resolved tree (so introspection/validation still sees it) but renders as `Unsupported` — reading a value from an ancestor block's `providesContext` isn't built.

**Inside a document/sidebar/more_menu-area panel** (editor-wide UI with no single block instance behind it — see `docs/block-config-reference.md`'s document-scope section), `source: 'attribute'` is *also* treated as unsupported: there is no block instance to read attributes from there, only `source: 'meta'` has a real read/write path.

## Conditionals

A field's `conditional` is evaluated live in the editor against the block's own attributes — a failing condition hides the whole field, container children included. Scope resolution only ever reaches the block's own attributes; a rule can't reference an ancestor's `providesContext`, a sibling block, or post meta.

## Not yet implemented

`placeholder`-area fields (shown inside a `<Placeholder>` overlay for an unconfigured block instance, distinct from the canvas area's own "no markup declared yet" fallback) are recognised but the editor doesn't render into that area yet — the one surface from the original design still outstanding.

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

## The undocumented JS escape hatch

`Control_Catalog::register_type()` (server) plus `window.cassetteCmfBlocks.registerControlType()` (client) let you register your own control component for a custom type, the same way this library's own controls are wired in. This exists in code and is used internally, but is **not part of the public contract** — the library's stated design is PHP/JSON configuration with zero consumer JavaScript, and this is the one place that isn't literally true. Reach for it only if a genuinely custom widget is unavoidable; a plain field type covers the overwhelming majority of real block needs.
