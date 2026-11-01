/**
 * E2E: shortcode and raw transforms backed by a PHP callback.
 *
 * Fixture: acme-e2e/card (see fixture-plugin.php): [e2e-card id="7" title="Hello"]
 * and <div class="e2e-legacy">Title #7</div> become a card block.
 *
 * The wiring and the pending resolution are covered by Jest (tests/js/remote-
 * transform.test.js) and the endpoint by PHPUnit (Test_Transform_Controller).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Paste HTML / plain text into the focused element of the editor canvas (an
 * iframe), the way a user would.
 *
 * @param {Object} editor The editor fixture.
 * @param {Object} data   { html?, text? }.
 */
async function paste( editor, { html = '', text = '' } ) {
	await editor.canvas.locator( ':focus' ).evaluate(
		( element, { html: h, text: t } ) => {
			const view = element.ownerDocument.defaultView;
			const transfer = new view.DataTransfer();
			transfer.setData( 'text/html', h );
			transfer.setData( 'text/plain', t );
			element.dispatchEvent(
				new view.ClipboardEvent( 'paste', {
					clipboardData: transfer,
					bubbles: true,
					cancelable: true,
				} )
			);
		},
		{ html, text }
	);
}

test.describe( 'shortcode and raw transforms', () => {
	test.beforeEach( async ( { admin, editor } ) => {
		await admin.createNewPost();
		await editor.canvas
			.getByRole( 'button', { name: 'Add default block' } )
			.click();
	} );

	test( 'a pasted shortcode becomes the block with the callback attributes', async ( {
		editor,
	} ) => {
		await paste( editor, {
			text: '[e2e-card id="7" title="Hello"]',
			html: '<p>[e2e-card id="7" title="Hello"]</p>',
		} );

		const block = editor.canvas.locator( '[data-type="acme-e2e/card"]' );
		await expect( block ).toContainText( 'Hello (7)' );
		await expect( block.locator( '.cassette-cmf-block-builder-pending' ) ).toHaveCount( 0 );
	} );

	test( 'pasted legacy HTML becomes the block', async ( { editor } ) => {
		await paste( editor, {
			html: '<div class="e2e-legacy">Old card #7</div>',
		} );

		await expect(
			editor.canvas.locator( '[data-type="acme-e2e/card"]' )
		).toContainText( 'Old card (7)' );
	} );

	test( 'a callback that does not match shows a notice and keeps the shortcode', async ( {
		editor,
		page,
	} ) => {
		await paste( editor, {
			text: '[e2e-card title="No id"]',
			html: '<p>[e2e-card title="No id"]</p>',
		} );

		await expect(
			page.locator( '.components-snackbar' ).first()
		).toContainText( 'did not produce a block' );
		await expect(
			editor.canvas.locator( '[data-type="core/shortcode"]' )
		).toBeVisible();
	} );
} );
