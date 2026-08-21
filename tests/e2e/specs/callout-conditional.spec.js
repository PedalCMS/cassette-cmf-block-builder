/**
 * E2E: a field's "conditional" takes effect live in the inspector, and the
 * canvas preview updates with zero network requests (the "markup" preview
 * mode's whole point) as an inspector control changes.
 *
 * Fixture: acme-e2e/callout (tests/e2e/fixture-plugin/config/blocks.json) —
 * a toolbar toggle ("is_dismissible") controlling a conditional inspector
 * field ("dismiss_label").
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/callout — conditional field + preview', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'the conditional "dismiss_label" field is hidden until "is_dismissible" is toggled on', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/callout' } );

		await expect(
			editor.canvas.getByRole( 'textbox', { name: 'Dismiss button label' } )
		).toBeHidden();

		await page
			.getByRole( 'toolbar', { name: 'Block tools' } )
			.getByRole( 'button', { name: 'Dismissible' } )
			.click();

		await expect(
			page.getByRole( 'textbox', { name: 'Dismiss button label' } )
		).toBeVisible();
	} );

	test( 'editing the "heading" field updates the canvas preview with no network request', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/callout' } );

		let requestFired = false;
		await page.route( '**/wp-json/**', ( route ) => {
			requestFired = true;
			route.continue();
		} );

		const headingField = page.getByRole( 'textbox', { name: 'Heading' } );
		await headingField.fill( 'Hello from E2E' );

		await expect(
			editor.canvas.locator( '.acme-e2e-callout__heading' )
		).toHaveText( 'Hello from E2E' );

		expect( requestFired ).toBe( false );
	} );
} );
