/**
 * E2E: a "document_scope.when_present: true" panel appears only once at
 * least one instance of the owning block exists in the post — not on
 * every post of a matching post type unconditionally.
 *
 * Fixture: acme-e2e/document-panel (document_scope.post_types: ["post"],
 * when_present: true), one "sidebar"-area meta-sourced field.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/document-panel — when_present gating', () => {
	test( 'the sidebar panel is absent until a block instance is inserted, then appears', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();

		await page
			.getByRole( 'button', { name: 'Options' } )
			.click();
		await page.getByRole( 'menuitem', { name: 'Plugins' } ).click();

		await expect(
			page.getByRole( 'menuitemcheckbox', { name: 'E2E Document Panel' } )
		).toHaveCount( 0 );

		await page.keyboard.press( 'Escape' );

		await editor.insertBlock( { name: 'acme-e2e/document-panel' } );

		await page
			.getByRole( 'button', { name: 'Options' } )
			.click();
		await page.getByRole( 'menuitem', { name: 'Plugins' } ).click();

		await expect(
			page.getByRole( 'menuitemcheckbox', { name: 'E2E Document Panel' } )
		).toBeVisible();
	} );
} );
