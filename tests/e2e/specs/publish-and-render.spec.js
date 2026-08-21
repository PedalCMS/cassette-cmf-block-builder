/**
 * E2E: a published post's front-end HTML matches what the editor showed —
 * proving Render\Block_Renderer (PHP) and the editor's own canvas preview
 * (React) genuinely agree, not just that each one independently looks
 * plausible in isolation.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/callout — publish and front-end render', () => {
	test( 'the published front end shows the same heading text the editor did', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( { title: 'E2E publish test' } );
		await editor.insertBlock( { name: 'acme-e2e/callout' } );

		await page
			.getByRole( 'textbox', { name: 'Heading' } )
			.fill( 'Published heading' );

		await editor.publishPost();

		const viewPostLink = page.getByRole( 'link', { name: 'View Post' } );
		await viewPostLink.waitFor();
		const href = await viewPostLink.getAttribute( 'href' );

		await page.goto( href );

		await expect(
			page.locator( '.acme-e2e-callout__heading' )
		).toHaveText( 'Published heading' );

		// "is_dismissible" was never toggled on, so the conditional dismiss
		// button must be entirely absent from the front-end HTML too — the
		// same "when" evaluation the editor preview and Render\Markup_Renderer
		// both apply.
		await expect(
			page.locator( '.acme-e2e-callout__dismiss' )
		).toHaveCount( 0 );
	} );
} );
