/**
 * E2E: dynamic-mode `render.deprecated`. A post stored with the old attribute
 * shape (title / kind) opens migrated (heading / tone) in the editor, and the
 * front end renders the new output without re-saving.
 *
 * Fixture: acme-e2e/legacy-note (tests/e2e/fixture-plugin/config/blocks.json).
 *
 * The migration itself is covered by Jest and PHPUnit against
 * tests/fixtures/deprecations.json.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const OLD_CONTENT =
	'<!-- wp:acme-e2e/legacy-note {"title":"Hello","kind":"hot"} /-->';

test.describe( 'dynamic render.deprecated', () => {
	test( 'the editor migrates old-shape attributes without a validation error', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		// createNewPost( { content } ) HTML-escapes the markup into a
		// classic block, so store the post through the REST API instead.
		const post = await requestUtils.createPost( {
			title: 'E2E dynamic deprecation',
			content: OLD_CONTENT,
			status: 'draft',
		} );
		await admin.visitAdminPage(
			'post.php',
			`post=${ post.id }&action=edit`
		);

		const [ note ] = await editor.getBlocks( { full: true } );
		expect( note.name ).toBe( 'acme-e2e/legacy-note' );
		expect( note.isValid ).toBe( true );
		expect( note.attributes.heading ).toBe( 'Hello' );
		expect( note.attributes.tone ).toBe( 'warm' );
		await expect(
			editor.canvas.locator( '.acme-e2e-note--warm h3' )
		).toHaveText( 'Hello' );
	} );

	test( 'the published page renders the new output from stored old-shape markup', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		// createNewPost( { content } ) HTML-escapes the markup into a
		// classic block, so store the post through the REST API instead.
		const post = await requestUtils.createPost( {
			title: 'E2E dynamic deprecation',
			content: OLD_CONTENT,
			status: 'draft',
		} );
		await admin.visitAdminPage(
			'post.php',
			`post=${ post.id }&action=edit`
		);
		const postId = await editor.publishPost();

		await page.goto( `/?p=${ postId }` );
		await expect( page.locator( '.acme-e2e-note--warm h3' ) ).toHaveText( 'Hello' );
	} );
} );
