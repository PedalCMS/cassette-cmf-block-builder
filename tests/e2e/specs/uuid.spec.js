/**
 * E2E: the "uuid" field type — generated on insert, regenerated on
 * duplicate / copy-paste (the original keeps its ID), untouched by undo and
 * reload, and correct inside nested containers.
 *
 * Fixture: acme-e2e/uuid-item (tests/e2e/fixture-plugin/config/blocks.json),
 * whose "instance_id" attribute is a "uuid" field.
 *
 * The same rules are covered by Jest (tests/js/instances-reducer.test.js).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const UUID =
	/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

/**
 * Every uuid-item's instance_id in the post, in document order, at any depth.
 *
 * @param {Object} editor The editor fixture.
 * @return {Promise<string[]>} The ids.
 */
async function getIds( editor ) {
	const ids = [];
	const visit = ( blocks ) =>
		blocks.forEach( ( block ) => {
			if ( 'acme-e2e/uuid-item' === block.name ) {
				ids.push( block.attributes.instance_id );
			}
			visit( block.innerBlocks || [] );
		} );
	visit( await editor.getBlocks() );
	return ids;
}

test.describe( 'uuid field type', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test( 'a freshly inserted block gets a UUID', async ( { editor } ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );

		await expect
			.poll( async () => ( await getIds( editor ) )[ 0 ] )
			.toMatch( UUID );
	} );

	test( 'the inspector shows no control for the uuid field', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await editor.openDocumentSettingsSidebar();

		await expect( page.getByRole( 'textbox', { name: 'Label' } ) ).toBeVisible();
		await expect(
			page.getByRole( 'textbox', { name: /instance/i } )
		).toHaveCount( 0 );
		// Nor a placeholder notice ("uses control type ... doesn't render yet").
		await expect(
			page.getByText( /which the editor doesn.t render yet/ )
		).toHaveCount( 0 );
	} );

	test( 'duplicating gives the copy a new ID and leaves the original alone', async ( {
		editor,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await expect
			.poll( async () => ( await getIds( editor ) )[ 0 ] )
			.toMatch( UUID );
		const [ original ] = await getIds( editor );

		await editor.clickBlockOptionsMenuItem( 'Duplicate' );

		await expect.poll( async () => ( await getIds( editor ) ).length ).toBe( 2 );
		await expect
			.poll( async () => {
				const ids = await getIds( editor );
				return ids[ 0 ] === original && ids[ 1 ] !== original && UUID.test( ids[ 1 ] );
			} )
			.toBe( true );
	} );

	test( 'copy and paste gives the pasted block a new ID', async ( {
		editor,
		pageUtils,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await expect
			.poll( async () => ( await getIds( editor ) )[ 0 ] )
			.toMatch( UUID );
		const [ original ] = await getIds( editor );

		// Focus the block itself so the copy / paste shortcuts act on it.
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="acme-e2e/uuid-item"]' )
		);
		await pageUtils.pressKeys( 'primary+c' );

		// Pasting while a block is selected replaces that block, so paste
		// into a new empty paragraph below it (what a user would do).
		await editor.insertBlock( { name: 'core/paragraph' } );
		await editor.canvas
			.locator( '[data-type="core/paragraph"]' )
			.click();
		await pageUtils.pressKeys( 'primary+v' );

		await expect.poll( async () => ( await getIds( editor ) ).length ).toBe( 2 );
		await expect
			.poll( async () => {
				const ids = await getIds( editor );
				return ids[ 0 ] === original && ids[ 1 ] !== original && UUID.test( ids[ 1 ] );
			} )
			.toBe( true );
	} );

	test( 'undoing a duplicate restores the previous state and keeps the original ID', async ( {
		editor,
		pageUtils,
	} ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await expect
			.poll( async () => ( await getIds( editor ) )[ 0 ] )
			.toMatch( UUID );
		const [ original ] = await getIds( editor );

		await editor.clickBlockOptionsMenuItem( 'Duplicate' );
		await expect.poll( async () => ( await getIds( editor ) ).length ).toBe( 2 );
		await expect
			.poll( async () => ( await getIds( editor ) )[ 1 ] )
			.toMatch( UUID );

		await pageUtils.pressKeys( 'primary+z' );

		// One undo removes the whole duplicate (the new ID was merged into it).
		await expect.poll( async () => await getIds( editor ) ).toEqual( [ original ] );

		// And it stays that way: no regenerate fight.
		await new Promise( ( resolve ) => setTimeout( resolve, 300 ) );
		expect( await getIds( editor ) ).toEqual( [ original ] );
	} );

	test( 'saving and reloading keeps the IDs', async ( { editor, page } ) => {
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await editor.insertBlock( { name: 'acme-e2e/uuid-item' } );
		await expect
			.poll( async () => ( await getIds( editor ) ).every( ( id ) => UUID.test( id ) ) )
			.toBe( true );
		const before = await getIds( editor );

		await editor.saveDraft();
		await page.reload();
		await page.waitForFunction(
			() => window.wp.data.select( 'core/block-editor' ).getBlocks().length
		);
		await new Promise( ( resolve ) => setTimeout( resolve, 300 ) );

		expect( await getIds( editor ) ).toEqual( before );
	} );

	test( 'works inside core/group and core/columns', async ( { editor } ) => {
		await editor.insertBlock( {
			name: 'core/group',
			innerBlocks: [ { name: 'acme-e2e/uuid-item' } ],
		} );
		await editor.insertBlock( {
			name: 'core/columns',
			innerBlocks: [
				{
					name: 'core/column',
					innerBlocks: [ { name: 'acme-e2e/uuid-item' } ],
				},
				{
					name: 'core/column',
					innerBlocks: [ { name: 'acme-e2e/uuid-item' } ],
				},
			],
		} );

		await expect
			.poll( async () => {
				const ids = await getIds( editor );
				return (
					3 === ids.length &&
					ids.every( ( id ) => UUID.test( id ) ) &&
					3 === new Set( ids ).size
				);
			} )
			.toBe( true );
	} );

	test( 'duplicating a container regenerates the IDs of everything inside it', async ( {
		editor,
	} ) => {
		await editor.insertBlock( {
			name: 'core/group',
			innerBlocks: [ { name: 'acme-e2e/uuid-item' } ],
		} );
		await expect
			.poll( async () => ( await getIds( editor ) )[ 0 ] )
			.toMatch( UUID );
		const [ original ] = await getIds( editor );

		await editor.clickBlockOptionsMenuItem( 'Duplicate' );

		await expect
			.poll( async () => {
				const ids = await getIds( editor );
				return (
					2 === ids.length &&
					ids[ 0 ] === original &&
					ids[ 1 ] !== original &&
					UUID.test( ids[ 1 ] )
				);
			} )
			.toBe( true );
	} );
} );
