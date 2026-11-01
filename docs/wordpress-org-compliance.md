# WordPress.org compliance for plugins that bundle this library

This page is for a plugin author who ships Cassette-CMF Blocks inside a plugin meant for the WordPress.org Plugin Directory. It lists what the library already does, what it cannot do for you, and what to configure. Every statement about the library was checked against the code in this repository; statements about WordPress.org and about Plugin Check are marked as such, and Plugin Check itself was not run here (see [Plugin Check](#plugin-check-pcp)).

The library is a Composer package that you bundle. The review team treats bundled code as part of your plugin: it has to follow the [Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) exactly as your own code does.

Contents: [Prefixing](#prefixing) · [No remote assets](#no-remote-assets) · [Escaping and sanitising](#escaping-and-sanitising) · [i18n](#i18n) · [GPL](#gpl) · [Source code for built JS](#source-code-for-built-javascript-readmetxt) · [Plugin Check](#plugin-check-pcp) · [Checklist](#checklist)

## Prefixing

Two things need a prefix: your PHP symbols and the names the library puts into WordPress.

### 1. The `prefix` boot option (names in WordPress)

Boot the library with your own prefix before registering any block:

```php
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
    'init',
    function () {
        CassetteCMFBlocks::boot( [ 'prefix' => 'acme-shop' ] );
        CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
    },
    5
);
```

The prefix derives the editor script and style handles (`acme-shop-blocks-editor[-style]`), the REST namespace (`acme-shop-blocks/v1`), the block bindings source (`acme-shop/field`, opt-in) and the JS payload global (`window.cassetteCmfBlocks_acmeShop`). Without a prefix the library raises `_doing_it_wrong()` and uses the shared fallback `cassette-cmf`, which collides with any other plugin that does the same. Details, the allowed pattern and what stays shared on purpose: [coexistence.md](coexistence.md).

You still choose your own **block names** (`acme-shop/callout`) and your own hook callbacks; no prefix can do that for you.

### 2. Namespace prefixing of the PHP code (Strauss)

The library lives in the `PedalCMS\CassetteCMFBlocks` namespace and needs `PedalCMS\CassetteCMF`. If another plugin bundles a different version of either, a plain copy would fatal or silently pick one. Rewrite both into your own namespace with [Strauss](https://github.com/BrianHenryIE/strauss). The library builds no class name from a string and compares no namespace string, and defines no global function, so the rewrite is safe; `tests/Unit/Test_Coexistence.php` runs a Strauss-style rewrite and loads it next to the plain library.

Example `composer.json` of your plugin (check the keys against the Strauss version you use):

```json
{
    "require": {
        "pedalcms/cassette-cmf-block-builder": "^0.2"
    },
    "require-dev": {
        "brianhenryie/strauss": "^0.19"
    },
    "extra": {
        "strauss": {
            "target_directory": "vendor-prefixed",
            "namespace_prefix": "Acme\\Shop\\Vendor\\",
            "classmap_prefix": "Acme_Shop_Vendor_",
            "constant_prefix": "ACME_SHOP_",
            "packages": [
                "pedalcms/cassette-cmf-block-builder",
                "pedalcms/cassette-cmf"
            ],
            "update_call_sites": false,
            "delete_vendor_packages": true
        }
    },
    "scripts": {
        "prefix-namespaces": [ "vendor/bin/strauss" ],
        "post-install-cmd": [ "@prefix-namespaces" ],
        "post-update-cmd": [ "@prefix-namespaces" ]
    }
}
```

Then `use Acme\Shop\Vendor\PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;` in your code. Strauss rewrites PHP only. The `prefix` boot option above is still required: namespace prefixing does not rename handles, REST routes or the JS global, and the boot option does not rename PHP classes. Use both.

Things Strauss does not touch and that need no change: the text domain strings (see [i18n](#i18n)), the `cassette_cmf_blocks_*` hook names (shared on purpose), the CSS classes, and the built editor bundle (it contains no PHP names).

## No remote assets

Guideline 8 forbids loading executable code from third-party systems, and the directory discourages CDN-hosted libraries. This library loads nothing from outside the plugin:

- The editor bundle (`src/assets/build/editor.js`, `editor.css`, `editor-rtl.css`) ships inside the package and is enqueued from the package's own URL (`Core\Asset_Loader::get_assets_url()`: the `CASSETTE_CMF_BLOCKS_ASSETS_URL` constant, the `cassette_cmf_blocks_assets_url` filter, or the path of the package under `wp-content`).
- A search of the built `editor.js` and `editor.css` finds no `http://` or `https://` string at all, and the JS and SCSS sources import only `@wordpress/*` packages. `src/assets/build/editor.asset.php` lists the dependencies WordPress loads for it: `react-jsx-runtime` and `wp-*` core handles (`wp-block-editor`, `wp-blocks`, `wp-components`, `wp-core-data`, `wp-data`, `wp-editor`, `wp-element`, `wp-i18n`, `wp-plugins`, `wp-server-side-render`). React, jQuery and the block-editor packages are WordPress's own, not bundled copies (guideline 13).
- No fonts, images, analytics, update checks or license calls. The PHP code makes no HTTP request (`wp_remote_*`, cURL and `fsockopen` do not appear in `src/`), registers no activation hook, and uses no `eval`, `unserialize` or database query of its own. The one thing it stores is a transient (`ccb_payload_{hash}`, plus an object-cache entry) that caches the editor payload; it is written only when the block editor loads, expires on its own, and is skipped under `WP_DEBUG` / `SCRIPT_DEBUG`. No options are created.
- The editor stylesheet is enqueued on `enqueue_block_assets`, so it is also requested on the front end (about 1 KB, from your own plugin URL).

What stays your job: the assets you attach with `args.editorScript`, `viewScript`, `viewScriptModule` and the other [asset keys](block-config-reference.md#asset-keys) must be local files in your plugin too. The library cannot check where a registered handle points. The same goes for `cassette-cmf`, the parent library; its own assets are local (no external URL in its JS or CSS), but check yours the same way before each release.

## Escaping and sanitising

### What the library does

- **`render.markup`**: every node is escaped by kind, not optionally: `text` with `esc_html()`, attribute values with `esc_attr()` (`href`, `src` and `action` with `esc_url()`), class names token by token with `sanitize_html_class()`, `html` content with `wp_kses_post()`. The one opt-out is an explicit `escape => 'raw'` on a node with `text` or `html` content. See [markup-templates.md](markup-templates.md).
- **Attributes**: before any render callback, template or markup tree sees them, `Block_Definition::sanitize_attributes()` runs WordPress's schema validation (a value of the wrong type is dropped for its default) and then each field's sanitizer from `cassette-cmf` (`text`, `email`, `url`, `number`, `select` against its options, and so on; `uuid` has its own). See [block-config-reference.md](block-config-reference.md#server-side-attribute-api).
- **Wrapper attributes** come from core's `get_block_wrapper_attributes()`.
- **The editor payload** is printed with `wp_add_inline_script()` and `wp_json_encode()`, which escapes `/`, so a value cannot close the script tag.
- **REST**: the one route (`GET /{prefix}-blocks/v1/config`) requires `current_user_can( 'edit_posts' )`, reads only, and takes no input. The library registers no AJAX action and reads no `$_GET`, `$_POST` or `$_REQUEST`.
- **The bindings source** (opt-in) applies the visibility rules described in [meta-binding.md](meta-binding.md) and the `cassette_cmf_blocks_binding_allowed` filter; it can only narrow access.
- **`_doing_it_wrong()` messages** are developer notices and are not escaped (the calls carry a `phpcs:ignore` with that reason).

### What stays your job

- **Custom render callbacks and PHP templates** (`render.callback`, `render.template`) receive sanitised attributes, which are *sanitised*, not escaped for any particular output context. Escape on output: `esc_html( $attributes['title'] )`, `esc_url()`, `wp_kses_post()` and so on, late, at the point of `echo`. The library returns whatever your callback or template produces unchanged.
- **`escape => 'raw'`**, `slot` and any HTML you build yourself are trusted by definition.
- **Attributes without a sanitiser**: field types with no `cmf_type` in the control catalog (see [control-catalog.md](control-catalog.md)) are validated by WordPress against their schema only. Escape those at output as well.
- **Your own REST routes, AJAX handlers and forms** that use `Block_Definition::sanitize_attributes()` or `CassetteCMFBlocks::get_definition()`: capability checks and nonces are yours.
- **Data in your configs**: JSON or PHP config you load from user-writable locations is code-like input; keep it in your plugin directory.

## i18n

- **Text domain.** The library's own strings use the text domain `cassette-cmf-block-builder` (`Core\Identity::TEXT_DOMAIN`). One PHP string (the bindings source label) and the editor UI strings in the bundle. The template file is `languages/cassette-cmf-block-builder.pot`. Strauss does not rewrite text domains, and the domain is deliberately shared by every copy.
- **Script translations.** `Asset_Loader` calls `wp_set_script_translations( {prefix}-blocks-editor, 'cassette-cmf-block-builder', <package>/languages )`. WordPress looks for `cassette-cmf-block-builder-{locale}-{prefix}-blocks-editor.json` in that folder first (for the prefix `acme-shop` and German: `cassette-cmf-block-builder-de_DE-acme-shop-blocks-editor.json`). The `.po`/`.json` files for the library's domain do not come from translate.wordpress.org for your plugin slug, so ship them yourself or accept English for the library's UI: create the `.po` from the `.pot` and run `wp i18n make-json` on it, and keep the JSON files in the package's `languages` folder (or place copies there in your build step). The package does not ship translations today.
- **Strings in your configs.** The labels, titles, help texts and options in your block configs are your strings and belong to *your* text domain. In a PHP array config wrap them as usual: `'label' => __( 'Heading', 'acme-shop' )`. Translation tools cannot read strings inside a `.json` config, so when you need translations either build the config in PHP, or keep the JSON and translate its values in a `cassette_cmf_blocks_register_config` / `cassette_cmf_blocks_block_config` filter callback with `__()`. The editor payload cache is keyed by locale, so each locale gets its own translated payload.
- **`args.textdomain`** is passed through to `register_block_type()` for core's own translation of block metadata. Set it to your plugin's text domain.

## GPL

- The library is `GPL-2.0-or-later` (`composer.json`, `package.json`, `LICENSE`). Its only production dependency is `pedalcms/cassette-cmf`, also `GPL-2.0-or-later` (`composer licenses --no-dev`).
- The built bundle contains the library's own source and WordPress packages that WordPress supplies at runtime; no third-party library is compiled into it (the sources import only `@wordpress/*`, which webpack leaves external).
- GPL-compatible, so your plugin can ship it under GPL-2.0-or-later without conflict. Keep the `LICENSE` file in the bundled package (the release archive includes it), and declare your own plugin's license in its header and in `readme.txt` (`License: GPLv2 or later`).
- Anything you add (fonts, images, other libraries) needs a GPL-compatible license of its own.

## Source code for built JavaScript (`readme.txt`)

Guideline 4 allows minified or compiled code only when the unminified source is public and linked. `src/assets/build/editor.js` is the output of `@wordpress/scripts` (`npm run build`), and the readable source is `src/assets/src/` in the library's repository. Release archives do not contain `src/assets/src/` (the release workflow removes it), and Composer installs of the package may not either, so the link must point at the repository. Add a section like this to your plugin's `readme.txt`:

```
== Source code ==

The block editor script bundled in this plugin (`vendor-prefixed/pedalcms/cassette-cmf-block-builder/src/assets/build/editor.js`) is compiled from human-readable source that is publicly available:

* Source: https://github.com/PedalCMS/cassette-cmf-block-builder/tree/v0.2.0/src/assets/src
* Build: `npm install && npm run build` in that repository (uses @wordpress/scripts; output goes to `src/assets/build/`).

If your own plugin also builds JavaScript, list its source and build steps here as well.
```

Use the tag that matches the version you bundle. The `build/` folder is committed in the library so no consumer needs Node (see [RELEASE.md](RELEASE.md)). If you would rather avoid the link, you can copy `src/assets/src/` into your plugin's repository and point the section at that path instead.

## Plugin Check (PCP)

[Plugin Check](https://wordpress.org/plugins/plugin-check/) is what the review team and most authors run before submitting. It was not run for this document (it is not installed in this environment), so the notes below come from how its checks are documented and from what this code does; run it on your built plugin and treat its output as the authority.

- **It scans the whole plugin folder, vendored code included**, unless you exclude paths (the CLI has `--exclude-directories` and `--exclude-files`; check your version). Do not exclude the bundled library to get a clean report: the review team reads that code too.
- **Code standards findings in the library.** The repository's own `composer cs:all` runs WordPress Coding Standards on `src/`, with `phpcs:ignore` comments where a rule does not fit, each with its reason: `file_get_contents()` on local config and `composer.json` paths (`Core\Block_Manager`, `Compat\Requirements`), and unescaped `_doing_it_wrong()` messages. Plugin Check honours `phpcs:ignore` comments, so these should not surface; if one does, it is a developer notice or a local-file read, not output. Report anything else as an issue.
- **Text domain mismatch.** The i18n check compares the text domain of translation calls with your plugin slug. The library's calls use `cassette-cmf-block-builder`, so expect a "text domain mismatch" finding for the bundled files. Options: exclude the vendored directory from the i18n check only if your Plugin Check version supports excluding by check, or explain it in your submission (a bundled library keeps its own text domain, as described under [i18n](#i18n)).
- **Prefix findings** (non-prefixed globals): the library defines no global functions, constants or variables; its classes are namespaced, and Strauss rewrites the namespace.
- **File-type findings.** A Composer package contains files the directory rejects in a plugin: `composer.json`, `composer.lock`, dotfiles (`.github/`, `.editorconfig`, `.gitignore`), `tests/`, `bin/`, `node_modules/`, `package.json`, `phpunit.xml.dist`. Strip them in your build step. The library's `.distignore` lists what the library itself leaves out of its release archive; the release workflow does not read that file, it removes the same paths explicitly, so use `.distignore` as a list for your own packaging tool (`wp dist-archive`, rsync, Strauss `exclude_from_copy`) rather than expecting it to be applied for you. Also leave out `docs/`, `examples/` and `block-schema.json` (the runtime does not read the schema file).
- **Readme checks** apply to your `readme.txt`: `Requires at least: 6.8`, `Requires PHP: 7.4`, a valid license, a stable tag matching your plugin header, and the source-code section above.
- **Trademark / naming.** The library's name does not appear in your plugin's slug unless you intend it; the directory also rejects plugin names that begin with another project's trademark.

## Checklist

1. `CassetteCMFBlocks::boot( [ 'prefix' => '<your-plugin>' ] )` before any `register_from_*()`.
2. Strauss (or equivalent) rewrites `PedalCMS\CassetteCMFBlocks` and `PedalCMS\CassetteCMF`.
3. Your own assets, referenced through the asset keys, are local files.
4. Custom callbacks and templates escape on output.
5. Config strings use your text domain; translation files for `cassette-cmf-block-builder` are shipped if you want a translated editor UI.
6. Dev files stripped from the bundled package.
7. `readme.txt` has the Source code section.
8. Plugin Check run on the final package; findings understood.
