/**
 * E2E: the deprecation canary the design plan calls for — re-opening a
 * published post whose stored content matches an OLDER (deprecated)
 * markup shape must show zero block-validation errors, because
 * "render.deprecated" describes that old shape declaratively.
 *
 * Fixture: acme-e2e/quote (render.mode: "static") declares one
 * render.deprecated entry — { tag: "blockquote", class:
 * "acme-e2e-quote-v1", text: "{{ attributes.quote_text }}" } — a strict
 * subset of the current markup (no "cite" wrapper). The post below is
 * seeded via the REST API with hand-authored content matching that OLD
 * shape's own save() output (useBlockProps.save()'s default
 * "wp-block-acme-e2e-quote" class plus the node's own declared class;
 * WordPress's own block-validity check compares class tokens as an
 * unordered set, not a literal string, so token order here is not
 * significant). If wp-block-editor ever changes how useBlockProps.save()
 * derives its default class name, this fixture string needs regenerating
 * from a real save — it was authored by hand against this repository's
 * own save.js, not captured from an actual editor session.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const OLD_CONTENT =
	'<!-- wp:acme-e2e/quote {"quote_text":"An old quote"} -->\n' +
	'<blockquote class="wp-block-acme-e2e-quote acme-e2e-quote-v1">An old quote</blockquote>\n' +
	'<!-- /wp:acme-e2e/quote -->';

test.describe( 'acme-e2e/quote — deprecation canary', () => {
	test( 'a post saved with the old (deprecated) markup shape opens with no block-validation error', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'E2E deprecation canary',
			content: OLD_CONTENT,
			status: 'publish',
		} );

		await admin.visitAdminPage(
			'post.php',
			`post=${ post.id }&action=edit`
		);

		// A validation failure shows a warning notice offering "Attempt
		// Block Recovery" instead of the block's own normal content —
		// its absence, alongside the real rendered quote text, is the
		// canary passing.
		await expect(
			page.getByRole( 'button', { name: 'Attempt Block Recovery' } )
		).toHaveCount( 0 );

		await expect(
			editor.canvas.getByText( 'An old quote' )
		).toBeVisible();
	} );
} );
