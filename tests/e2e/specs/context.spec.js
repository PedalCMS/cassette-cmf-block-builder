/**
 * E2E: "source: 'context'" — a child block reads a value its parent
 * provides, and its editor preview updates live when the parent attribute
 * changes.
 *
 * Fixture: acme-e2e/form (providesContext "acme-e2e/formId" from its
 * "form_id" attribute) and acme-e2e/form-field (a context-sourced "form_id"
 * shown in its preview).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/form-field — block context', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'the child preview shows the parent value and follows changes to it', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			attributes: { form_id: 'form-a' },
			innerBlocks: [ { name: 'acme-e2e/form-field' } ],
		} );

		const preview = editor.canvas.locator(
			'.acme-e2e-form-field__form-id'
		);
		await expect( preview ).toHaveText( 'form-a' );

		// Select the parent and change its attribute in the inspector.
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form"]' )
		);
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'textbox', { name: 'Form ID' } ).fill( 'form-b' );

		await expect( preview ).toHaveText( 'form-b' );
	} );

	test( 'the child has no inspector control for the context field', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			innerBlocks: [ { name: 'acme-e2e/form-field' } ],
		} );
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/form-field"]' )
		);
		await editor.openDocumentSettingsSidebar();

		await expect(
			page.getByRole( 'textbox', { name: 'Field name' } )
		).toBeVisible();
		await expect( page.getByRole( 'textbox', { name: 'Form ID' } ) ).toHaveCount( 0 );
	} );

	test( 'nothing is stored for the context field', async ( { editor } ) => {
		await editor.insertBlock( {
			name: 'acme-e2e/form',
			attributes: { form_id: 'form-a' },
			innerBlocks: [ { name: 'acme-e2e/form-field' } ],
		} );

		const [ form ] = await editor.getBlocks();

		expect( form.innerBlocks[ 0 ].attributes ).not.toHaveProperty( 'form_id' );
	} );
} );
