/**
 * E2E: preview.when gating.
 *
 * Fixture: acme-e2e/gated (see fixture-plugin.php): the preview renders only
 * once its "url" field is not empty.
 *
 * The gate is covered by Jest (tests/js/preview-when.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'preview.when', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'a placeholder replaces the preview until the rule holds', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/gated' } );

		const block = editor.canvas.locator( '[data-type="acme-e2e/gated"]' );
		await expect(
			block.locator( '.cassette-cmf-block-builder-preview-gate' )
		).toBeVisible();
		// The preview's root node *is* the block wrapper element, so the
		// gated markup's class sits on the block itself, not a descendant.
		await expect( block ).not.toHaveClass( /(^|\s)acme-e2e-gated(\s|$)/ );

		await editor.openDocumentSettingsSidebar();
		await page
			.getByRole( 'textbox', { name: 'Address' } )
			.fill( 'https://example.com' );

		await expect(
			block.locator( '.cassette-cmf-block-builder-preview-gate' )
		).toHaveCount( 0 );
		await expect( block ).toHaveClass( /(^|\s)acme-e2e-gated(\s|$)/ );
		await expect( block ).toContainText(
			'https://example.com'
		);
	} );
} );
