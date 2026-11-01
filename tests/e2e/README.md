# E2E tests

Playwright specs against a real block editor session, via `@wordpress/env`. `tests/e2e/fixture-plugin/` registers a demo block set (`config/blocks.json`) from a plain PHP config array and contains no JavaScript file of its own — that absence is itself the proof of this library's zero-consumer-JS claim.

## Running

With Docker and `@wordpress/env`:

```bash
npm run env start
npm run test:e2e
```

Without Docker, against any throwaway WordPress (the specs were run this way): install WordPress 6.8+ with an `admin` / `password` user, pretty permalinks and a block-capable theme, copy this repository next to a copy of `pedalcms/cassette-cmf` into `wp-content/plugins/`, activate both and `tests/e2e/fixture-plugin/` (as its own plugin, with a `vendor/autoload.php` that loads this library's autoloader), then point the tests at the site:

```bash
WP_BASE_URL=http://localhost:8889 npx wp-scripts test-playwright --reporter=list [spec-file]
```

`@wordpress/scripts` uses `WP_BASE_URL` (and `WP_USERNAME` / `WP_PASSWORD`, default `admin` / `password`) when `@wordpress/env` is not installed or running. Run `npm run build` first when the JavaScript changed. Failure screenshots, traces and `error-context.md` files land in `artifacts/test-results/`.

**Result of the first real run** (Chromium, WordPress 7.1.4 on SQLite served by `wp server`, Twenty Twenty-Five): **37 of 37 specs pass**, also with `--repeat-each=2`. The run found one product bug (a `uuid` field rendered an "unsupported control" notice in the inspector; fixed, see `CHANGELOG.md`) and a set of spec problems, now fixed. Things to remember when writing specs for the current editor:

- Query block content through `editor.canvas` (the editor is an iframe), not `page`; a `page.locator( '[data-type=...]' )` finds nothing, and an `expect( ... ).toHaveCount( 0 )` on it passes for the wrong reason.
- The inserter button is named "Block Inserter"; "Options" matches several buttons, so scope it to the "Editor top bar" region with `exact: true`.
- `admin.createNewPost( { content } )` escapes the markup into a classic block; create posts with `requestUtils.createPost()` and open them instead.
- `editor.getBlocks()` has no `isValid`; use `getBlocks( { full: true } )`.
- `pageUtils.pressKeys( 'primary+v' )` replaces the selected block; paste into an empty paragraph. A hand-written paste event has to be dispatched inside the canvas frame.

## What each spec covers

| Spec | Canary |
|---|---|
| `callout-conditional.spec.js` | A field's `conditional` shows/hides live in the inspector; editing a control updates the canvas preview with zero network requests. |
| `publish-and-render.spec.js` | Published front-end HTML matches what the editor showed, including a `when`-gated node's correct absence. |
| `parent-constraint-and-inner-blocks.spec.js` | `args.parent` removes a block from the root inserter but allows it inside its declared parent; `inner_blocks.template` materializes real child blocks. |
| `document-panel.spec.js` | A `document_scope.when_present: true` panel appears only once an instance of the owning block exists in the post. |
| `static-save-deprecation.spec.js` | The deprecation canary — a post saved with an older, deprecated markup shape opens with no block-validation error. |
| `uuid.spec.js` | A `uuid` field is generated on insert, regenerated on duplicate / copy-paste (the original keeps its ID), restored cleanly by undo, kept on reload, and correct inside `core/group` / `core/columns`. |
| `context.spec.js` | A child block with a `source: 'context'` field shows its parent's value in the preview and follows changes to it; nothing is stored for it. |
| `ancestor-field.spec.js` | The `ancestor_field` picker lists the form's named blocks, follows removals (`missing: name` is kept), and a cross-block `conditions` rule is stored as `{ source: 'block', ref }`. |
| `placeholder.spec.js` | A `placeholder`-area field shows in a `<Placeholder>` until the block is configured; the block variation picker appears on an empty block and applies attributes and inner blocks. |
| `dynamic-deprecation.spec.js` | A dynamic block stored with old attributes is migrated by the editor and renders the new output on the front end. |
| `remote-transforms.spec.js` | A pasted `[e2e-card …]` shortcode and a pasted `div.e2e-legacy` become the block through the PHP callback; a callback that returns null shows a notice and keeps the shortcode. |
| `repeater-dnd.spec.js` | Repeater rows reorder by dragging the handle and with Move up / Move down, which announce the new position. |
| `preview-when.spec.js` | A placeholder replaces the preview until the `preview.when` rule holds. |
| `unique.spec.js` | A `unique` field name is flagged on the newer duplicate, auto-suffixed on duplicate, checked at depth, and may be reused across two separate forms. |

Not covered yet: a server-preview block's request debouncing (`preview.mode: 'server'`, the `acme-e2e/server-preview` fixture block exists for this but has no spec yet).
