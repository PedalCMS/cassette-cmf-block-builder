/**
 * E2E: repeater rows reorder by drag and drop and with the Move buttons.
 *
 * Fixture: acme-e2e/rows (see fixture-plugin.php).
 *
 * The reorder logic, the announcements and the row wiring are covered by Jest
 * (tests/js/repeater-dnd.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'repeater drag and drop', () => {
	test.beforeEach( async ( { admin, editor, page } ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: 'acme-e2e/rows' } );
		await editor.openDocumentSettingsSidebar();

		const add = page.getByRole( 'button', { name: 'Add row' } );
		for ( const title of [ 'One', 'Two', 'Three' ] ) {
			await add.click();
			await page
				.getByRole( 'textbox', { name: 'Title' } )
				.last()
				.fill( title );
		}
	} );

	const titles = ( page ) =>
		page
			.getByRole( 'textbox', { name: 'Title' } )
			.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );

	test( 'dragging the handle of the first row onto the last row moves it', async ( {
		page,
	} ) => {
		const handles = page.locator(
			'.cassette-cmf-block-builder-repeater__drag-handle'
		);
		const rows = page.locator( '.cassette-cmf-block-builder-repeater__row' );

		await handles.first().dragTo( rows.last() );

		expect( await titles( page ) ).toEqual( [ 'Two', 'Three', 'One' ] );
	} );

	test( 'Move down / Move up keep working for the keyboard, and announce the new position', async ( {
		page,
	} ) => {
		await page.getByRole( 'button', { name: 'Move down' } ).first().click();

		expect( await titles( page ) ).toEqual( [ 'Two', 'One', 'Three' ] );
		await expect(
			page.locator( '#a11y-speak-assertive' )
		).toHaveText( /Row moved to position 2 of 3/ );

		await page.getByRole( 'button', { name: 'Move up' } ).nth( 1 ).click();

		expect( await titles( page ) ).toEqual( [ 'One', 'Two', 'Three' ] );
	} );
} );
