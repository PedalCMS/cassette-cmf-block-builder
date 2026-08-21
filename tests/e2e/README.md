# E2E tests

Playwright specs against a real block editor session, via `@wordpress/env`. `tests/e2e/fixture-plugin/` registers a demo block set (`config/blocks.json`) from a plain PHP config array and contains no JavaScript file of its own — that absence is itself the proof of this library's zero-consumer-JS claim.

## Running

```bash
npm run env start
npm run test:e2e
```

`wp-env` needs Docker. **These specs were authored against this repository's actual API surface (`@wordpress/e2e-test-utils-playwright`'s real `Editor`/`Admin`/`RequestUtils` methods, verified by reading that package's own source) and each fixture block in `config/blocks.json` was confirmed to register successfully through the real `Block_Schema_Validator`/`Block_Manager` pipeline — but the specs themselves have not been executed in a real browser in this environment**, since no container runtime is available here. Run them for real before relying on them as a release gate.

## What each spec covers

| Spec | Canary |
|---|---|
| `callout-conditional.spec.js` | A field's `conditional` shows/hides live in the inspector; editing a control updates the canvas preview with zero network requests. |
| `publish-and-render.spec.js` | Published front-end HTML matches what the editor showed, including a `when`-gated node's correct absence. |
| `parent-constraint-and-inner-blocks.spec.js` | `args.parent` removes a block from the root inserter but allows it inside its declared parent; `inner_blocks.template` materializes real child blocks. |
| `document-panel.spec.js` | A `document_scope.when_present: true` panel appears only once an instance of the owning block exists in the post. |
| `static-save-deprecation.spec.js` | The deprecation canary — a post saved with an older, deprecated markup shape opens with no block-validation error. |

Not covered yet: a server-preview block's request debouncing (`preview.mode: 'server'`, the `acme-e2e/server-preview` fixture block exists for this but has no spec yet).
