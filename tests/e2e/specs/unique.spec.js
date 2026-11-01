/**
 * E2E: the "unique" setting — a collision flag on every block but the
 * oldest owner, an auto-suffix on duplicate, a nested scope, and two
 * separate forms reusing a name.
 *
 * Fixture: acme-e2e/form-field's "name" is unique within its nearest
 * acme-e2e/form ({ scope: 'ancestor', block: 'acme-e2e/form' }, slug format).
 *
 * The same rules are covered by Jest (tests/js/unique.test.js,
 * tests/js/instances-reducer.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Every form-field's "name", in document order, at any depth.
 *
 * @param {Object} editor The editor fixture.
 * @return {Promise<string[]>} The names.
 */
async function getNames( editor ) {
	const names = [];
	const visit = ( blocks ) =>
		blocks.forEach( ( block ) => {
			if ( 'acme-e2e/form-field' === block.name ) {
				names.push( block.attributes.name );
			}
			visit( block.innerBlocks || [] );
		} );
	visit( await editor.getBlocks() );
	return names;
}

const field = ( name ) => ( {
	name: 'acme-e2e/form-field',
	attributes: { name },
} );

test.describe( 'unique field names', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'typing an existing name flags the newer block and not the older one, and does not block saving', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [ field( 'email' ), field( 'phone' ) ],
		} );

		const second = editor.canvas.locator(
			'[data-type="acme-e2e/form-field"]'
		).nth( 1 );
		await editor.selectBlocks( second );
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'textbox', { name: 'Field name' } ).fill( 'email' );
		await page.getByRole( 'textbox', { name: 'Field name' } ).blur();

		await expect(
			page.getByText( 'This value is already used elsewhere in this content.' )
		).toBeVisible();
		await expect(
			page.getByRole( 'textbox', { name: 'Field name' } )
		).toHaveAttribute( 'aria-invalid', 'true' );

		// The older block is not flagged.
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' ).first()
		);
		await expect(
			page.getByText( 'This value is already used elsewhere in this content.' )
		).toHaveCount( 0 );

		// Saving is not blocked.
		await editor.saveDraft();
	} );

	test( 'duplicating a field auto-suffixes the copy and never renames the original', async ( {
		editor,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [ field( 'email' ) ],
		} );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' )
		);

		await editor.clickBlockOptionsMenuItem( 'Duplicate' );
		await expect.poll( async () => await getNames( editor ) ).toEqual( [
			'email',
			'email-2',
		] );

		// "Duplicate" selects the copy, so select the original again:
		// duplicating "email-2" itself would correctly give "email-2-2".
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' ).first()
		);
		await editor.clickBlockOptionsMenuItem( 'Duplicate' );
		// A copy lands right after its source, so the newest sits second.
		await expect.poll( async () => await getNames( editor ) ).toEqual( [
			'email',
			'email-3',
			'email-2',
		] );
	} );

	test( 'uniqueness reaches nested blocks inside the same form', async ( {
		editor,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [
				field( 'email' ),
				{ name: 'core/group', innerBlocks: [ field( 'phone' ) ] },
			],
		} );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' ).nth( 1 )
		);

		// Duplicating the nested field auto-suffixes it within the same form.
		await editor.clickBlockOptionsMenuItem( 'Duplicate' );

		await expect.poll( async () => ( await getNames( editor ) ).length ).toBe( 3 );
		await expect
			.poll( async () => new Set( await getNames( editor ) ).size )
			.toBe( 3 );
	} );

	test( 'two separate forms may reuse the same field name', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [ field( 'email' ) ],
		} );
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [ field( 'email' ) ],
		} );

		await expect.poll( async () => await getNames( editor ) ).toEqual( [
			'email',
			'email',
		] );

		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' ).nth( 1 )
		);
		await editor.openDocumentSettingsSidebar();
		await expect(
			page.getByText( 'This value is already used elsewhere in this content.' )
		).toHaveCount( 0 );
	} );
} );
