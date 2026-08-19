/**
 * Settings-screen helper (separate entry → build/brand-import.js).
 *
 * 1. Initializes wp-color-picker on all design-token color fields.
 * 2. Imports the organization brand from anny via the plugin's `/brand` REST
 *    proxy, which returns canonical kebab-case design tokens, and fills the
 *    matching token fields.
 *
 * Expected markup (rendered by Anny\Admin\AdminPage):
 * - token inputs:  <input data-anny-token="{kebab-token}" …>, color token
 *                  inputs additionally carry class "anny-color-field"
 * - import button: <button id="anny-brand-import">…</button>
 * - status area:   <span id="anny-brand-import-status"></span> (optional)
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __, _n, sprintf } from '@wordpress/i18n';
import jQuery from 'jquery';

import { DESIGN_TOKENS } from '../manifest';

const KNOWN_TOKENS = [
	...DESIGN_TOKENS.colors.map( ( token ) => token.key ),
	...DESIGN_TOKENS.fields.map( ( token ) => token.key ),
];

/**
 * Writes a value into the settings field for a token, keeping the
 * wp-color-picker swatch in sync for color fields.
 *
 * Color fields only accept hex: wp-color-picker parses their value through
 * Iris, which normalizes anything else back to hex and would drop the alpha
 * channel of an `rgba(…)` brand value. Such values are reported as not
 * applied rather than written and silently mangled.
 *
 * @param {string} token Canonical kebab-case token.
 * @param {string} value Value to set.
 * @return {boolean} Whether a field was updated.
 */
function setTokenField( token, value ) {
	const field = document.querySelector( `[data-anny-token="${ token }"]` );
	if ( ! field || typeof value !== 'string' || value === '' ) {
		return false;
	}

	const isColorField = field.classList.contains( 'anny-color-field' );
	if ( isColorField && ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( value.trim() ) ) {
		return false;
	}

	field.value = value;

	if ( jQuery && jQuery.fn.wpColorPicker && isColorField ) {
		jQuery( field ).wpColorPicker( 'color', value );
	} else {
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	return true;
}

domReady( () => {
	// 1. Color pickers for the design-token GUI.
	if ( jQuery && jQuery.fn.wpColorPicker ) {
		jQuery( '.anny-color-field' ).wpColorPicker();
	}

	// 2. Brand import.
	const button = document.getElementById( 'anny-brand-import' );
	if ( ! button ) {
		return;
	}
	const status = document.getElementById( 'anny-brand-import-status' );
	const setStatus = ( message ) => {
		if ( status ) {
			status.textContent = message;
		}
	};

	button.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		button.disabled = true;
		setStatus( __( 'Importing brand from anny…', 'anny-bookings' ) );

		apiFetch( { path: '/anny/v1/brand' } )
			.then( ( brand ) => {
				let applied = 0;

				Object.entries( ( brand && brand.tokens ) || {} ).forEach( ( [ token, value ] ) => {
					if ( KNOWN_TOKENS.includes( token ) && setTokenField( token, value ) ) {
						applied++;
					}
				} );

				setStatus(
					applied > 0
						? sprintf(
								/* translators: %d: number of imported design token values. */
								_n(
									'Imported %d value. Save changes to keep it.',
									'Imported %d values. Save changes to keep them.',
									applied,
									'anny-bookings'
								),
								applied
						  )
						: __( 'No brand values found for this organization.', 'anny-bookings' )
				);
			} )
			.catch( ( error ) => {
				setStatus(
					( error && error.message ) || __( 'Brand import failed.', 'anny-bookings' )
				);
			} )
			.finally( () => {
				button.disabled = false;
			} );
	} );
} );
