# Coexistence: the `prefix` boot option

Two plugins on one site can each ship their own copy of this library, plain or namespace-prefixed (for example with [Strauss](https://github.com/BrianHenryIE/strauss)). Anything the copies would otherwise share by name, such as a script handle, a REST namespace, a binding source or a JS global, has to differ. You give each copy its own **prefix** once, and the library derives all of them from it. Nothing else in the library spells those names by hand.

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
    'init',
    function () {
        CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] );   // before any register_from_*()
        CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
    },
    5
);
```

## What the prefix derives

With `{p}` the prefix and `{P}` its camelCase form (`my-shop` gives `myShop`; a word that starts with a digit keeps an underscore, so `a-1b` gives `a_1b` and never collides with the prefix `a1b`):

| Identifier | Value (prefix `acme`) |
|---|---|
| Editor script handle | `{p}-blocks-editor` (`acme-blocks-editor`) |
| Editor style handle | `{p}-blocks-editor-style` (`acme-blocks-editor-style`) |
| REST namespace | `{p}-blocks/v1` (`acme-blocks/v1`; the config route is `GET /acme-blocks/v1/config`) |
| Block bindings source (opt-in) | `{p}/field` (`acme/field`) |
| JS payload global | `window.cassetteCmfBlocks_{P}` (`window.cassetteCmfBlocks_acme`) |
| Editor payload cache (object-cache group; keys the transient fallback) | `cassette_cmf_blocks_{p_snake}` |
| Script translations | follow the script handle |

Read them from PHP with `Core\Identity` (`editor_script_handle()`, `editor_style_handle()`, `rest_namespace()`, `binding_source_name()`, `js_global()`, `payload_cache_group()`).

## Picking a prefix

- `^[a-z][a-z0-9-]{1,30}[a-z0-9]$`: lowercase letters, digits and hyphens, 3 to 32 characters, starting with a letter and not ending with a hyphen.
- Use something that identifies your plugin (`acme`, `acme-shop`). The prefix is required. A missing or invalid prefix, including `register_from_*()` before `boot()`, raises `_doing_it_wrong()` and falls back to `cassette-cmf` (yielding `cassette-cmf-blocks-editor`, `cassette-cmf-blocks/v1` and `cassette-cmf/field`). Two plugins that both rely on the fallback collide, which is exactly what the notice is for.
- It becomes part of public names (the binding source name `acme/field` is stored in post content by anything bound to it), so choose it once. Changing it later means re-saving content that uses the binding source.

## Two copies on one page

The editor bundle does not hard-code its global. The inline script printed before it first sets the one-shot `window.cassetteCmfBlocksBoot = 'cassetteCmfBlocks_acme'`, then publishes the payload at `window.cassetteCmfBlocks_acme`. The bundle reads and deletes the hand-over, and registers only the blocks in that payload. The editor script is registered without a `defer`/`async` strategy on purpose: a blocking script runs right after its own inline script, so two bundles cannot interleave between the hand-over and the read.

Every registry in the bundle (control types, migrations, id-in-use checks, unique-value conflicts, the instance watcher) is module-level state, and each loaded bundle evaluates its own modules, so one bundle's registrations never reach another's. Each bundle's instance watcher acts only on the block names in its own payload. The built editor is a single file with no lazy chunks, so no webpack chunk-loading global is shared either; if chunks are ever introduced, give the build its own `output.uniqueName`.

JS extension points hang off the payload global of your prefix:

```js
window.cassetteCmfBlocks_acme.registerIdInUseCheck( ( id, blockName ) => /* boolean | Promise<boolean> */ );
```

## What is shared on purpose

- The text domain `cassette-cmf-block-builder`: the library's own translations.
- The `cassette_cmf_blocks_*` hooks (`cassette_cmf_blocks_register_config`, `..._block_config`, `..._render`, `..._binding_allowed`, `..._payload_cache_skip`, ...). A callback on them is a statement about every copy of the library; use the block name to tell blocks apart. The one filter that must differ per copy, `cassette_cmf_blocks_assets_url`, receives the prefix as its second argument.
- The `CASSETTE_CMF_BLOCKS_ASSETS_URL` constant, if you define it, applies to every copy. With two copies use the filter instead.
- CSS class names (`cassette-cmf-block-builder-*`) and the editor stylesheet, which each copy ships identically.

Block names are yours: two copies registering the same block name (`acme/callout`) is a conflict no prefix can resolve.

## Namespace-prefixed copies (Strauss)

The library builds no class name from a string and compares no namespace string, so a Strauss rewrite of `PedalCMS\CassetteCMFBlocks` (and of `PedalCMS\CassetteCMF`, which it uses) is safe. The test suite does exactly that: `tests/Unit/Test_Coexistence.php` generates a prefixed copy of `src/` and of the parent library into a temporary directory (`tests/Support/class-prefixed-copy.php`), boots the plain copy as `alpha` and the prefixed one as `beta`, and checks that both register their block, that the handles differ, that each editor payload and inline script contains only its own block, that `alpha-blocks/v1` and `beta-blocks/v1` each serve only their own config, and that both binding sources exist.
