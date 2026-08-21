/**
 * conditions/evaluate.js <-> Render\Conditional_Evaluator (PHP) parity
 * fixture test. See that class's docblock and evaluate.js's for why this
 * is genuine byte-for-byte (boolean-for-boolean) two-language parity, not
 * a structural approximation.
 */

import { readFileSync } from 'fs';
import { join } from 'path';
import { evaluate } from '../../src/assets/src/editor/conditions/evaluate';

const fixtures = JSON.parse(
	readFileSync( join( __dirname, '../fixtures/conditions.json' ), 'utf8' )
);

describe( 'conditions/evaluate.js fixture parity', () => {
	it.each(
		fixtures.cases.map( ( testCase ) => [ testCase.description, testCase ] )
	)( '%s', ( description, testCase ) => {
		expect( evaluate( testCase.normalized, testCase.context ) ).toBe(
			testCase.expected
		);
	} );
} );
