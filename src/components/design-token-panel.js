/**
 * Collapsed "Design overrides" inspector panels: color pickers for the
 * canonical color tokens plus text inputs for radii/shadows/dimensions.
 * Only non-empty values are written into the `tokens` object attribute;
 * clearing a control removes the key again.
 */

import { PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { DESIGN_TOKENS, TOKEN_HINTS } from '../manifest';

export default function DesignTokenPanel( { attributes, setAttributes } ) {
	const tokens = attributes.tokens || {};

	const setToken = ( key, value ) => {
		const next = { ...tokens };
		if ( value === undefined || value === null || String( value ) === '' ) {
			delete next[ key ];
		} else {
			next[ key ] = value;
		}
		setAttributes( { tokens: next } );
	};

	return (
		<>
			<PanelColorSettings
				title={ __( 'Design overrides: colors', 'anny-bookings' ) }
				initialOpen={ false }
				enableAlpha
				colorSettings={ DESIGN_TOKENS.colors.map( ( token ) => ( {
					value: tokens[ token.key ],
					label: token.label,
					onChange: ( value ) => setToken( token.key, value ),
				} ) ) }
			/>
			<PanelBody
				title={ __( 'Design overrides: details', 'anny-bookings' ) }
				initialOpen={ false }
			>
				{ DESIGN_TOKENS.fields.map( ( token ) => (
					<TextControl
						key={ token.key }
						label={ token.label }
						value={ tokens[ token.key ] || '' }
						onChange={ ( value ) => setToken( token.key, value ) }
						help={ TOKEN_HINTS[ token.key ] }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				) ) }
			</PanelBody>
		</>
	);
}
