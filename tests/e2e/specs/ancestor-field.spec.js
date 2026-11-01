/**
 * E2E: the "ancestor_field" control and the cross-block "conditions" rules.
 *
 * Fixtures: acme-e2e/form (parent), acme-e2e/form-field (has a "name"),
 * acme-e2e/form-rule (an ancestor_field "target" and a "conditions" control
 * with `conditions: { ancestor: 'acme-e2e/form' }`).
 *
 * The option logic is covered by Jest (tests/js/ancestor-field.test.js,
 * tests/js/conditions-control.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Insert a form with two named fields and a rule block.
 *
 * @param {Object} editor The editor fixture.
 */
async function insertForm( editor ) {
	await editor.insertBlock( {
		name: 'acme-e2e/form',
		innerBlocks: [
			{ name: 'acme-e2e/form-field', attributes: { name: 'email' } },
			{ name: 'acme-e2e/form-field', attributes: { name: 'phone' } },
			{ name: 'acme-e2e/form-rule' },
		],
	} );
}

test.describe( 'ancestor_field control', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'lists the sibling blocks of the form by name and stores the name', async ( {
		editor,
		page,
	} ) => {
		await insertForm( editor );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-rule"]' )
		);
		await editor.openDocumentSettingsSidebar();

		const select = page.getByRole( 'combobox', { name: 'Target field' } );
		await expect( select.locator( 'option' ) ).toContainText( [
			'— Select a block —',
			'email (email)',
			'phone (phone)',
		] );

		await select.selectOption( 'phone' );

		const blocks = await editor.getBlocks();
		const rule = blocks[ 0 ].innerBlocks.find(
			( block ) => 'acme-e2e/form-rule' === block.name
		);
		expect( rule.attributes.target ).toBe( 'phone' );
	} );

	test( 'follows renames and removals live; a vanished name shows as missing and is kept', async ( {
		editor,
		page,
	} ) => {
		await insertForm( editor );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-rule"]' )
		);
		await editor.openDocumentSettingsSidebar();
		await page
			.getByRole( 'combobox', { name: 'Target field' } )
			.selectOption( 'phone' );

		// Remove the "phone" field block.
		await editor.canvas.locator( '[data-type="acme-e2e/form-field"]' ).nth( 1 ).click();
		await page.keyboard.press( 'Backspace' );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-rule"]' )
		);

		await expect(
			page
				.getByRole( 'combobox', { name: 'Target field' } )
				.locator( 'option:checked' )
		).toHaveText( 'missing: phone' );
	} );
} );

test.describe( 'conditions control, cross-block rules', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'a rule can target another block and is stored as plain data', async ( {
		editor,
		page,
	} ) => {
		await insertForm( editor );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-rule"]' )
		);
		await editor.openDocumentSettingsSidebar();

		await page.getByRole( 'button', { name: 'Add rule' } ).click();
		await page
			.getByRole( 'combobox', { name: 'Compare' } )
			.selectOption( 'block' );
		await page
			.getByRole( 'combobox', { name: 'Block' } )
			.selectOption( 'email' );
		await page
			.getByRole( 'combobox', { name: 'Condition' } )
			.selectOption( 'not_empty' );

		const blocks = await editor.getBlocks();
		const rule = blocks[ 0 ].innerBlocks.find(
			( block ) => 'acme-e2e/form-rule' === block.name
		);
		// The control keeps the (now unused) "value" key of the rule it
		// created, so match the meaningful keys rather than the exact object.
		expect( rule.attributes.show_when.rules ).toHaveLength( 1 );
		expect( rule.attributes.show_when.rules[ 0 ] ).toMatchObject( {
			source: 'block',
			ref: 'email',
			operator: 'not_empty',
		} );
		expect( rule.attributes.show_when.rules[ 0 ] ).not.toHaveProperty(
			'field'
		);
	} );
} );
