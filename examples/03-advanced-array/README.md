# Advanced Example - PHP Array Configuration

Five form-field blocks — Text, Textarea, Email, Select, Checkbox — registered from a single PHP array. Beyond the field-level controls every example in this library already demonstrates, this one is about **how the block looks in the canvas**: each block's `render.markup` renders real (disabled) HTML form controls inside a bordered "field preview" card, so it reads as a native WordPress admin element rather than plain unstyled text.

## What Each Block Demonstrates

Every block shares the same shape: a **Label**, a **Field name (slug)** that auto-derives from the label until manually edited, a type-specific control, **Help text**, and **Required**.

- **Text** (`cassette-cmf-forms/field-text`) — a plain text input, plus a **Placeholder** field.
- **Textarea** (`cassette-cmf-forms/field-textarea`) — same shape, a 3-row textarea preview.
- **Email** (`cassette-cmf-forms/field-email`) — same shape, an `type="email"` input.
- **Select** (`cassette-cmf-forms/field-select`) — an **Options** field using the `options_list` control (repeatable value/label rows), rendered as a real `<select disabled>` with one `<option>` per row.
- **Checkbox** (`cassette-cmf-forms/field-checkbox`) — its own **Checkbox label** and **Checked value** text fields, rendered as a real `<input type="checkbox" disabled>` with its label.

## The Label → Slug Auto-Derive

The **slug** field on every block uses:

```php
[
    'name'        => 'slug',
    'type'        => 'text',
    'derive_from' => 'label',
    'format'      => 'slug',
]
```

`derive_from` continuously mirrors the `label` field's value into `slug` (through `format: 'slug'`, i.e. `sanitize_title()`-style slugification) — until the user types into the slug field themselves, at which point it stops mirroring. The same auto-slug pattern WordPress's own post editor uses for its permalink field. See [`../../docs/control-catalog.md`](../../docs/control-catalog.md)'s `text` control section.

## Looking Native in the Canvas

Each block's `render.markup` wraps its control in shared chrome classes (`cassette-cmf-block-builder-field-preview` and its `__label-row`/`__badge`/`__required`/`__control`/`__help` parts), shipped by the library itself in `src/assets/src/editor/chrome.scss` — a bordered card, a label row with a type badge, a red "*" only when **Required** is on, and help text only once it's filled in (both via `when`). The actual `<input>`/`<select>`/`<textarea>` elements need no styling of their own: they inherit WordPress admin's own `forms.css` the moment they render inside the block editor, which is what makes a `disabled` control in the canvas look like a real form field rather than a custom widget.

These classes are entirely optional — nothing in the library requires them. A block's `render.markup` opts in the same way it would use any other class name.

## File Structure

```
03-advanced-array/
├── example.php    # All five blocks, fully commented
└── README.md      # This documentation
```
