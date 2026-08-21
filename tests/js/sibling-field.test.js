/**
 * controls/sibling-field.js test.
 */

import SiblingField from '../../src/assets/src/editor/controls/sibling-field';

describe( 'SiblingField', () => {
	it( 'offers every scope.fieldOptions entry except the field itself', () => {
		const element = SiblingField( {
			field: { name: 'is_dismissible' },
			value: '',
			onChange: () => {},
			scope: {
				fieldOptions: [
					{ value: 'heading', label: 'Heading' },
					{ value: 'is_dismissible', label: 'Dismissible' },
				],
			},
		} );

		const values = element.props.options.map( ( option ) => option.value );

		expect( values ).toContain( 'heading' );
		expect( values ).not.toContain( 'is_dismissible' );
	} );

	it( 'includes a placeholder "— Select a field —" option first', () => {
		const element = SiblingField( {
			field: { name: 'x' },
			value: '',
			onChange: () => {},
			scope: { fieldOptions: [] },
		} );

		expect( element.props.options[ 0 ].value ).toBe( '' );
	} );

	it( 'defaults to an empty option list when scope is absent', () => {
		const element = SiblingField( { field: { name: 'x' }, value: '', onChange: () => {} } );

		expect( element.props.options ).toHaveLength( 1 );
	} );
} );
