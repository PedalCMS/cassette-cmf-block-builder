import { Notice } from '@wordpress/components';

/**
 * "notice" control — display-only chrome, no attribute.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config: "message"/"text" and optional "status".
 * @return {JSX.Element} The control.
 */
export default function NoticeControl( { field } ) {
	return (
		<Notice status={ field.status || 'info' } isDismissible={ false }>
			{ field.message || field.text }
		</Notice>
	);
}
