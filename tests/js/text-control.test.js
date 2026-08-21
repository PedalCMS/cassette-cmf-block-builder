/**
 * controls/text.js test.
 *
 * Only applyFormat() (a pure function) is unit-tested directly here — the
 * component itself uses real @wordpress/element hooks (useEffect/useRef)
 * for "derive_from"'s mirror-until-touched behaviour, which needs an
 * actual React render pass to exercise correctly (calling the component as
 * a bare function, the trick used elsewhere in this test suite for
 * hookless components, breaks React's "hooks must run during a render"
 * rule). This environment has no React test renderer available, so that
 * stateful behaviour is verified by code review and the live-site smoke
 * test instead, not by an automated JS test — a documented gap, not an
 * oversight.
 */

import { applyFormat } from '../../src/assets/src/editor/controls/text';

describe( 'applyFormat', () => {
	it( 'slugifies for format "slug"', () => {
		expect( applyFormat( 'Hello World!', 'slug' ) ).toBe( 'hello-world' );
	} );

	it( 'upper-cases for format "upper"', () => {
		expect( applyFormat( 'hello', 'upper' ) ).toBe( 'HELLO' );
	} );

	it( 'lower-cases for format "lower"', () => {
		expect( applyFormat( 'HELLO', 'lower' ) ).toBe( 'hello' );
	} );

	it( 'passes the value through unchanged for an unknown/unset format', () => {
		expect( applyFormat( 'Hello', undefined ) ).toBe( 'Hello' );
		expect( applyFormat( 'Hello', 'nope' ) ).toBe( 'Hello' );
	} );
} );
