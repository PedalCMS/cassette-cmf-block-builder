/**
 * E2E: the "placeholder" area and the block variation picker.
 *
 * Fixtures: acme-e2e/embed (a required "url" field in the placeholder area)
 * and acme-e2e/columns (two `scope: ['block']` variations with innerBlocks).
 *
 * The decisions are covered by Jest (tests/js/placeholder.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'placeholder area', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'an empty block shows the placeholder with its field; filling it configures the block', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/embed' } );

		const placeholder = editor.canvas.locator(
			'[data-type="acme-e2e/embed"] .cassette-cmf-block-builder-placeholder'
		);
		await expect( placeholder ).toBeVisible();
		await expect( placeholder ).toContainText( 'Paste an address to embed.' );

		await placeholder
			.getByRole( 'textbox', { name: 'Address' } )
			.fill( 'https://example.com' );

		await expect( placeholder ).toHaveCount( 0 );
		await expect(
			editor.canvas.locator( '[data-type="acme-e2e/embed"] a' )
		).toHaveAttribute( 'href', 'https://example.com' );

		// Once configured the field lives in the inspector.
		await editor.openDocumentSettingsSidebar();
		await expect(
			page.getByRole( 'textbox', { name: 'Address' } )
		).toHaveValue( 'https://example.com' );
	} );
} );

test.describe( 'block variation picker', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'an empty block offers its block-scope variations; choosing one applies attributes and inner blocks', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/columns' } );

		const block = editor.canvas.locator( '[data-type="acme-e2e/columns"]' );
		await expect( block.getByRole( 'button', { name: 'Two columns' } ) ).toBeVisible();
		await expect( block.getByRole( 'button', { name: 'Three columns' } ) ).toBeVisible();

		await block.getByRole( 'button', { name: 'Three columns' } ).click();

		const [ columns ] = await editor.getBlocks();
		expect( columns.attributes.count ).toBe( 3 );
		expect( columns.innerBlocks ).toHaveLength( 3 );
		await expect(
			block.getByRole( 'button', { name: 'Two columns' } )
		).toHaveCount( 0 );
	} );

	test( 'a block that already has inner blocks does not show the picker', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/columns',
			innerBlocks: [ { name: 'core/paragraph' } ],
		} );

		await expect(
			editor.canvas
				.locator( '[data-type="acme-e2e/columns"]' )
				.getByRole( 'button', { name: 'Two columns' } )
		).toHaveCount( 0 );
	} );
} );
