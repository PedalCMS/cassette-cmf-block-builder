/**
 * E2E: "args.parent" genuinely constrains the inserter (a parent-constrained
 * block is absent from the root inserter), and a declared "inner_blocks"
 * template materializes real, editable child blocks in the canvas.
 *
 * Fixture: acme-e2e/container (inner_blocks.template = one core/paragraph)
 * and acme-e2e/child (args.parent = ["acme-e2e/container"]).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/container — parent constraint + InnerBlocks template', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'the parent-constrained child block is absent from the root inserter', async ( {
		editor,
		page,
	} ) => {
		await page
			.getByRole( 'button', { name: 'Toggle block inserter' } )
			.click();

		await page
			.getByRole( 'searchbox', { name: 'Search' } )
			.fill( 'E2E Child' );

		await expect(
			page.getByRole( 'option', { name: 'E2E Child' } )
		).toHaveCount( 0 );
	} );

	test( 'inserting the container materializes its InnerBlocks template', async ( {
		editor,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/container' } );

		await expect(
			editor.canvas.getByRole( 'document', { name: /Paragraph block/i } )
		).toBeVisible();
	} );

	test( 'the child block becomes insertable once a container instance exists', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/container' } );

		// Select the container itself (not its templated paragraph child) so
		// the inserter's "allowed inside this block" context is the container.
		const containerBlock = editor.canvas.getByRole( 'document', {
			name: /E2E Container block/i,
		} );
		await containerBlock.click();

		await page
			.getByRole( 'button', { name: 'Toggle block inserter' } )
			.click();
		await page
			.getByRole( 'searchbox', { name: 'Search' } )
			.fill( 'E2E Child' );

		await expect(
			page.getByRole( 'option', { name: 'E2E Child' } )
		).toBeVisible();
	} );
} );
