/**
 * value/useFieldValue.js test.
 */

import { useFieldValue } from '../../src/assets/src/editor/value/useFieldValue';
import {
	__setMockMeta,
	__getLastCallArgs,
	__getLastSetMetaCall,
	__resetMock,
} from '@wordpress/core-data';

beforeEach( () => {
	__resetMock();
} );

describe( 'useFieldValue', () => {
	it( 'reads/writes attributes directly for source "attribute" (the default)', () => {
		const setAttributes = jest.fn();
		const [ value, setValue ] = useFieldValue(
			{ config: { name: 'heading' } },
			{ attributes: { heading: 'Hi' }, setAttributes }
		);

		expect( value ).toBe( 'Hi' );
		setValue( 'New' );
		expect( setAttributes ).toHaveBeenCalledWith( { heading: 'New' } );
	} );

	it( 'reads/writes the post meta object for source "meta"', () => {
		__setMockMeta( { reason: 'because' } );

		const [ value, setValue ] = useFieldValue(
			{ config: { name: 'reason', source: 'meta' } },
			{ attributes: {}, setAttributes: jest.fn(), context: { postType: 'post' } }
		);

		expect( value ).toBe( 'because' );

		setValue( 'updated' );
		expect( __getLastSetMetaCall() ).toEqual( { reason: 'updated' } );
		expect( __getLastCallArgs() ).toEqual( {
			kind: 'postType',
			name: 'post',
			prop: 'meta',
		} );
	} );

	it( '"meta.key" overrides which meta key is read/written', () => {
		__setMockMeta( { acme_reason: 'because' } );

		const [ value, setValue ] = useFieldValue(
			{ config: { name: 'reason', source: 'meta', meta: { key: 'acme_reason' } } },
			{ attributes: {}, setAttributes: jest.fn(), context: { postType: 'post' } }
		);

		expect( value ).toBe( 'because' );

		setValue( 'updated' );
		expect( __getLastSetMetaCall() ).toEqual( { acme_reason: 'updated' } );
	} );

	it( 'returns an inert [ undefined, noop ] for "meta" with no postType in context', () => {
		const setValue2 = jest.fn();
		const [ value, setValue ] = useFieldValue(
			{ config: { name: 'reason', source: 'meta' } },
			{ attributes: {}, setAttributes: setValue2, context: {} }
		);

		expect( value ).toBeUndefined();
		expect( () => setValue( 'x' ) ).not.toThrow();
		expect( setValue2 ).not.toHaveBeenCalled();
	} );

	it( 'returns an inert [ undefined, noop ] for an unimplemented source ("context")', () => {
		const [ value, setValue ] = useFieldValue(
			{ config: { name: 'x', source: 'context' } },
			{ attributes: {}, setAttributes: jest.fn() }
		);

		expect( value ).toBeUndefined();
		expect( () => setValue( 'x' ) ).not.toThrow();
	} );
} );
