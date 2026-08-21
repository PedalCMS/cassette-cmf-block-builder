/**
 * Expression <-> Render\Expression (PHP) parity fixture test.
 *
 * Reads the same tests/fixtures/markup.json Test_Markup_Fixtures.php reads.
 * See that file's docblock (and markup/expr.js's) for why this tests
 * expression *values*, not full markup output.
 */

import { readFileSync } from 'fs';
import { join } from 'path';
import { interpolate } from '../../src/assets/src/editor/markup/expr';

const fixtures = JSON.parse(
	readFileSync( join( __dirname, '../fixtures/markup.json' ), 'utf8' )
);

describe( 'markup/expr.js fixture parity', () => {
	it.each(
		fixtures.expressions.map( ( testCase ) => [
			testCase.description,
			testCase,
		] )
	)( '%s', ( description, testCase ) => {
		expect( interpolate( testCase.template, testCase.scope ) ).toBe(
			testCase.expected
		);
	} );
} );
