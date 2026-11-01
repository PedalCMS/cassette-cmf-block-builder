# BC baseline dump (cassette-cmf-block-builder)

Boots WordPress with this repository's own examples (`examples/01`, `03`, plus the
E2E fixture plugin's `config/blocks.json`; `02` is registered inside a try/catch
because its config fails validation, and `04` is skipped because it duplicates
the block ids of `03`) on default settings and writes one deterministic JSON file:

- `block_types` (full `WP_Block_Type` args), `block_bindings_sources`
- `editor_payload`: `Editor_Payload::build()` plus the inline script attached to the editor handle
- `rest_routes`, `scripts` / `styles` per firing context
- `rendered_blocks`: `render_block()` output with default and generated sample attributes, as an anonymous visitor on a published post
- `settings` / `options` (empty unless CMF settings pages are registered), `public_api` (reflection over `src/`), `hooks`, `notices`, `example_load_errors`

Paths, URLs, nonces and asset versions are replaced by `{REPO}`, `{WP}`, `{HOME}`, `{CMF}`, `{NONCE}`, `{VER}`; callables are `Class::method` / `function` / `{closure}`.

## Run

```bash
cd cassette-cmf-block-builder
BC_DUMP_OUT=/path/to/bb-after.json WP_CORE_DIR=/path/to/wordpress \
  vendor/bin/phpunit -c tests/bc/phpunit-bc.xml
diff bb-before.json bb-after.json
```

Without `BC_DUMP_OUT` the test still builds the dump but writes nothing. The suite
is not part of the default `phpunit.xml.dist` run.

For the block builder the dump is a changelog aid, not a gate: use the
before/after diff to write the "Breaking" entries and migration notes so no change
goes unrecorded.
