/**
 * Shared widget manifest helpers for the anny editor bundle.
 *
 * The manifest JSON (plugin root) is the single source of truth for all
 * widgets; this module adds the translatable labels and the block-attribute
 * → element-attribute mapping.
 */

import { __ } from '@wordpress/i18n';

import widgetsManifest from '../widgets-manifest.json';

/**
 * All widgets, keyed by block slug (`anny/{slug}`).
 */
export const WIDGETS = widgetsManifest;

/**
 * Manifest options that are booleans; every other option is a string.
 * Must match the PHP attribute registration in includes/Blocks/Blocks.php.
 */
export const BOOLEAN_OPTIONS = [
	'fullscreen',
	'should-login',
	'close-on-backdrop',
	'show-close-button',
	'show-icon',
];

/**
 * Relative `start` keywords supported by non-calendar widgets.
 * Calendar widgets (manifest `calendarStart`) accept ISO dates only.
 */
export const RELATIVE_START_KEYWORDS = [
	'today',
	'tomorrow',
	'this_week',
	'next_week',
	'this_month',
	'next_month',
];

/**
 * Manifest icon key → dashicon name.
 */
export const ICONS = {
	building: 'building',
	calendar: 'calendar',
	'calendar-alt': 'calendar-alt',
	hammer: 'hammer',
	location: 'location',
	'location-alt': 'location-alt',
	'money-alt': 'money-alt',
	archive: 'archive',
	'list-view': 'list-view',
	button: 'button',
	external: 'external',
	'admin-users': 'admin-users',
	cart: 'cart',
};

/**
 * Translatable title/description/keywords per block slug.
 * The manifest JSON stays string-free; all user-facing copy lives here.
 */
export const LABELS = {
	'organization-page': {
		title: __( 'anny Organization Page', 'anny-bookings' ),
		description: __(
			'Embed the complete booking page of an anny organization, with resource lists, search and checkout.',
			'anny-bookings'
		),
		keywords: [
			__( 'booking', 'anny-bookings' ),
			__( 'organization', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'resource-page': {
		title: __( 'anny Resource Page', 'anny-bookings' ),
		description: __(
			'Embed the booking page of a single anny resource, such as a room, desk or piece of equipment.',
			'anny-bookings'
		),
		keywords: [
			__( 'booking', 'anny-bookings' ),
			__( 'resource', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'service-page': {
		title: __( 'anny Service Page', 'anny-bookings' ),
		description: __(
			'Embed the booking page of a single anny service, such as a tour, class or appointment.',
			'anny-bookings'
		),
		keywords: [
			__( 'booking', 'anny-bookings' ),
			__( 'service', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'resource-calendar': {
		title: __( 'anny Resource Calendar', 'anny-bookings' ),
		description: __(
			'Show the availability calendar of a single anny resource.',
			'anny-bookings'
		),
		keywords: [
			__( 'calendar', 'anny-bookings' ),
			__( 'availability', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'organization-calendar': {
		title: __( 'anny Organization Calendar', 'anny-bookings' ),
		description: __(
			'Show the availability calendar for the bookable resources of an anny organization.',
			'anny-bookings'
		),
		keywords: [
			__( 'calendar', 'anny-bookings' ),
			__( 'availability', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'resource-map': {
		title: __( 'anny Resource Map', 'anny-bookings' ),
		description: __(
			'Show a single anny resource on an interactive map.',
			'anny-bookings'
		),
		keywords: [
			__( 'map', 'anny-bookings' ),
			__( 'resource', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'organization-map': {
		title: __( 'anny Organization Map', 'anny-bookings' ),
		description: __(
			'Show the bookable resources of an anny organization on an interactive map.',
			'anny-bookings'
		),
		keywords: [
			__( 'map', 'anny-bookings' ),
			__( 'organization', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'subscription-page': {
		title: __( 'anny Subscription Page', 'anny-bookings' ),
		description: __(
			'Embed the sign-up page for an anny subscription plan.',
			'anny-bookings'
		),
		keywords: [
			__( 'subscription', 'anny-bookings' ),
			__( 'plan', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'package-page': {
		title: __( 'anny Package Page', 'anny-bookings' ),
		description: __(
			'Embed the booking page for an anny package.',
			'anny-bookings'
		),
		keywords: [
			__( 'package', 'anny-bookings' ),
			__( 'booking', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'my-bookings': {
		title: __( 'anny My Bookings', 'anny-bookings' ),
		description: __(
			'Let signed-in customers view and manage their anny bookings.',
			'anny-bookings'
		),
		keywords: [
			__( 'bookings', 'anny-bookings' ),
			__( 'account', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'resource-booking-panel': {
		title: __( 'anny Resource Booking Panel', 'anny-bookings' ),
		description: __(
			'Compact booking panel for a single anny resource that embeds seamlessly into your layout.',
			'anny-bookings'
		),
		keywords: [
			__( 'booking', 'anny-bookings' ),
			__( 'panel', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'service-booking-panel': {
		title: __( 'anny Service Booking Panel', 'anny-bookings' ),
		description: __(
			'Compact booking panel for a single anny service that embeds seamlessly into your layout.',
			'anny-bookings'
		),
		keywords: [
			__( 'booking', 'anny-bookings' ),
			__( 'panel', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'resource-button': {
		title: __( 'anny Resource Button', 'anny-bookings' ),
		description: __(
			'A button that opens the booking dialog for an anny resource.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'resource', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'service-button': {
		title: __( 'anny Service Button', 'anny-bookings' ),
		description: __(
			'A button that opens the booking dialog for an anny service.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'service', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'organization-button': {
		title: __( 'anny Organization Button', 'anny-bookings' ),
		description: __(
			'A button that opens the booking dialog for an anny organization.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'organization', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'subscription-button': {
		title: __( 'anny Subscription Button', 'anny-bookings' ),
		description: __(
			'A button that opens the sign-up dialog for an anny subscription plan.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'subscription', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'package-button': {
		title: __( 'anny Package Button', 'anny-bookings' ),
		description: __(
			'A button that opens the booking dialog for an anny package.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'package', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'modal-button': {
		title: __( 'anny Modal Button', 'anny-bookings' ),
		description: __(
			'A button that opens an anny booking dialog for a resource or organization of your choice.',
			'anny-bookings'
		),
		keywords: [
			__( 'button', 'anny-bookings' ),
			__( 'modal', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'login-button': {
		title: __( 'anny Login Button', 'anny-bookings' ),
		description: __(
			'A button that lets visitors sign in to their anny account.',
			'anny-bookings'
		),
		keywords: [
			__( 'login', 'anny-bookings' ),
			__( 'account', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
	'cart-modal-button': {
		title: __( 'anny Cart Button', 'anny-bookings' ),
		description: __(
			'A button that opens the anny shopping cart.',
			'anny-bookings'
		),
		keywords: [
			__( 'cart', 'anny-bookings' ),
			__( 'checkout', 'anny-bookings' ),
			__( 'anny', 'anny-bookings' ),
		],
	},
};

/**
 * Canonical design tokens (kebab-case), grouped for the token panel.
 * The `*-rgb` variants are derived at render time and never appear in a GUI.
 *
 * IMPORTANT: the grouping is the twin of Settings::COLOR_TOKENS /
 * Settings::TEXT_TOKENS in PHP. The overlay tokens are text fields on both
 * sides because anny brands express them as `rgba(…)`, which the color
 * controls cannot represent.
 */
export const DESIGN_TOKENS = {
	colors: [
		{ key: 'primary-color', label: __( 'Primary color', 'anny-bookings' ) },
		{ key: 'primary-color-hover', label: __( 'Primary color (hover)', 'anny-bookings' ) },
		{ key: 'secondary-color', label: __( 'Secondary color', 'anny-bookings' ) },
		{ key: 'primary-stroke-color', label: __( 'Primary stroke color', 'anny-bookings' ) },
		{ key: 'text-primary-color', label: __( 'Text color (primary)', 'anny-bookings' ) },
		{ key: 'text-secondary-color', label: __( 'Text color (secondary)', 'anny-bookings' ) },
		{ key: 'text-tertiary-color', label: __( 'Text color (tertiary)', 'anny-bookings' ) },
		{ key: 'primary-background', label: __( 'Primary background', 'anny-bookings' ) },
		{ key: 'panel-background', label: __( 'Panel background', 'anny-bookings' ) },
		{ key: 'panel-background-light', label: __( 'Panel background (light)', 'anny-bookings' ) },
		{ key: 'panel-background-dark', label: __( 'Panel background (dark)', 'anny-bookings' ) },
		{ key: 'input-background', label: __( 'Input background', 'anny-bookings' ) },
		{ key: 'light-border-color', label: __( 'Light border color', 'anny-bookings' ) },
		{ key: 'table-border-color', label: __( 'Table border color', 'anny-bookings' ) },
		{ key: 'button-background', label: __( 'Button background', 'anny-bookings' ) },
		{ key: 'button-text', label: __( 'Button text color', 'anny-bookings' ) },
	],
	fields: [
		{ key: 'primary-color-overlay', label: __( 'Primary color (overlay)', 'anny-bookings' ) },
		{ key: 'panel-background-overlay', label: __( 'Panel background (overlay)', 'anny-bookings' ) },
		{ key: 'panel-background-overlay-dense', label: __( 'Panel background (dense overlay)', 'anny-bookings' ) },
		{ key: 'small-border-radius', label: __( 'Small border radius', 'anny-bookings' ) },
		{ key: 'panel-border-radius', label: __( 'Panel border radius', 'anny-bookings' ) },
		{ key: 'detail-border-radius', label: __( 'Detail border radius', 'anny-bookings' ) },
		{ key: 'panel-border-style', label: __( 'Panel border style', 'anny-bookings' ) },
		{ key: 'panel-shadow', label: __( 'Panel shadow', 'anny-bookings' ) },
		{ key: 'detail-shadow', label: __( 'Detail shadow', 'anny-bookings' ) },
		{ key: 'button-width', label: __( 'Button width', 'anny-bookings' ) },
		{ key: 'button-height', label: __( 'Button height', 'anny-bookings' ) },
		{ key: 'logo-url', label: __( 'Logo URL', 'anny-bookings' ) },
	],
};

/**
 * Value-format hints for the tokens whose accepted input is not obvious from
 * the label. Tokens sharing a format share the string, so they share a
 * translation too.
 *
 * IMPORTANT: the wording is duplicated in AdminPage::tokenHints() in PHP,
 * which renders the same hints on the settings screen.
 */
export const TOKEN_HINTS = {
	'primary-color-overlay': __(
		'Any CSS color. Overlays are usually semi-transparent, e.g. rgba(97, 149, 237, 0.14).',
		'anny-bookings'
	),
	'small-border-radius': __( 'CSS length, e.g. 8px or 0.', 'anny-bookings' ),
	'panel-border-style': __(
		'CSS border shorthand, e.g. 1px solid #e5e5e5 — or none.',
		'anny-bookings'
	),
	'panel-shadow': __(
		'CSS box-shadow, e.g. 0 2px 8px rgba(0, 0, 0, 0.1) — or none.',
		'anny-bookings'
	),
	'button-width': __( 'CSS length, e.g. 200px or 100%.', 'anny-bookings' ),
	'logo-url': __(
		'Full URL of an image, e.g. https://example.com/logo.svg.',
		'anny-bookings'
	),
};

// Tokens that take the same kind of value reuse one hint.
TOKEN_HINTS[ 'panel-background-overlay' ] = TOKEN_HINTS[ 'primary-color-overlay' ];
TOKEN_HINTS[ 'panel-background-overlay-dense' ] = TOKEN_HINTS[ 'primary-color-overlay' ];
TOKEN_HINTS[ 'panel-border-radius' ] = TOKEN_HINTS[ 'small-border-radius' ];
TOKEN_HINTS[ 'detail-border-radius' ] = TOKEN_HINTS[ 'small-border-radius' ];
TOKEN_HINTS[ 'detail-shadow' ] = TOKEN_HINTS[ 'panel-shadow' ];
TOKEN_HINTS[ 'button-height' ] = TOKEN_HINTS[ 'button-width' ];

/**
 * Derived token → hex sibling it is computed from.
 */
const RGB_DERIVED = {
	'primary-color-rgb': 'primary-color',
	'primary-background-rgb': 'primary-background',
	'panel-background-rgb': 'panel-background',
};

/**
 * kebab-case → camelCase (block attribute names).
 *
 * @param {string} kebab e.g. `modal-layout`.
 * @return {string} e.g. `modalLayout`.
 */
export function camelize( kebab ) {
	return kebab.replace( /-([a-z0-9])/g, ( match, char ) => char.toUpperCase() );
}

/**
 * Effective entity slug of a block: the stored value, falling back to the
 * organization selected in the plugin settings for organization widgets —
 * the site as a whole is scoped to that organization, so its widgets follow
 * it instead of carrying their own copy of the slug.
 *
 * IMPORTANT: twin of Blocks::entitySlug() in PHP.
 *
 * @param {Object} attributes Block attributes.
 * @param {Object} widget     Manifest entry.
 * @param {Object} config     Global config (window.ANNY_WP shape).
 * @return {string} Slug to render with, empty when the block is unconfigured.
 */
export function entitySlugFor( attributes, widget, config ) {
	const stored = ( attributes.entitySlug || '' ).trim();

	if ( stored || widget.entity !== 'organization' ) {
		return stored;
	}

	return ( ( config || {} ).org?.slug || '' ).trim();
}

/**
 * `#rrggbb` → `"r, g, b"`; null for anything that is not a 6-digit hex color.
 *
 * @param {string} value CSS color value.
 * @return {?string} rgb triple string or null.
 */
function hexToRgbString( value ) {
	const match = /^#([0-9a-fA-F]{6})$/.exec( String( value || '' ).trim() );
	if ( ! match ) {
		return null;
	}
	const int = parseInt( match[ 1 ], 16 );
	/* eslint-disable no-bitwise */
	return `${ ( int >> 16 ) & 255 }, ${ ( int >> 8 ) & 255 }, ${ int & 255 }`;
	/* eslint-enable no-bitwise */
}

/**
 * Builds the HTML attribute map for a widget custom element from block
 * attributes + global plugin config.
 *
 * IMPORTANT: this is the JavaScript twin of the PHP renderer in
 * includes/Blocks/Blocks.php — both implement the shared attribute-mapping
 * contract and MUST stay structurally in sync. Any change here needs the
 * same change on the PHP side (and vice versa).
 *
 * @param {Object} attributes Block attributes.
 * @param {Object} widget     Manifest entry.
 * @param {Object} config     Global config (window.ANNY_WP shape).
 * @return {Object} Ordered map of kebab-case HTML attributes.
 */
export function toElementAttributes( attributes, widget, config ) {
	const settings = config || {};
	const defaults = settings.defaults || {};
	const attrs = {};

	// 1. Region base URL — always present.
	attrs[ 'base-url' ] = settings.widgetBase || '';

	// 2. Entity slug (PHP renders nothing when a required slug is empty;
	// the editor shows the Placeholder instead, so this stays best-effort).
	if ( widget.entity && widget.entityAttr ) {
		attrs[ widget.entityAttr ] = entitySlugFor( attributes, widget, settings );
	}

	// 3. Manifest options, in manifest order.
	( widget.options || [] ).forEach( ( option ) => {
		// A custom trigger replaces the built-in label on button widgets.
		if ( option === 'label' && widget.innerBlocks && attributes.useCustomTrigger ) {
			return;
		}

		let value = attributes[ camelize( option ) ];

		// Global fallbacks for locale / should-login / idp-uuid.
		if ( value === undefined || value === '' ) {
			if ( option === 'locale' ) {
				value = defaults.locale;
			} else if ( option === 'idp-uuid' ) {
				value = defaults.idpUuid;
			}
		}
		if ( value === undefined && option === 'should-login' ) {
			value = defaults.shouldLogin;
		}

		if ( BOOLEAN_OPTIONS.includes( option ) ) {
			// Booleans are emitted as "true"/"false" only when actually set.
			if ( typeof value === 'boolean' ) {
				attrs[ option ] = value ? 'true' : 'false';
			}
		} else if ( value !== undefined && value !== null && String( value ) !== '' ) {
			attrs[ option ] = String( value );
		}
	} );

	// 4. Merged design tokens (global < per-block), then derived *-rgb.
	// Like the PHP twin: block tokens must be non-empty strings, and only
	// canonical token keys are ever emitted (defense against stray keys).
	const canonicalTokens = [
		...DESIGN_TOKENS.colors,
		...DESIGN_TOKENS.fields,
		...Object.keys( RGB_DERIVED ),
	];
	const blockTokens = {};
	Object.keys( attributes.tokens || {} ).forEach( ( key ) => {
		const value = ( attributes.tokens || {} )[ key ];
		if ( typeof value === 'string' && value !== '' ) {
			blockTokens[ key ] = value;
		}
	} );
	const tokens = {
		...( settings.tokens || {} ),
		...blockTokens,
	};
	Object.keys( RGB_DERIVED ).forEach( ( rgbKey ) => {
		if ( ! tokens[ rgbKey ] && tokens[ RGB_DERIVED[ rgbKey ] ] ) {
			const rgb = hexToRgbString( tokens[ RGB_DERIVED[ rgbKey ] ] );
			if ( rgb ) {
				tokens[ rgbKey ] = rgb;
			}
		}
	} );
	canonicalTokens.forEach( ( key ) => {
		const value = tokens[ key ];
		if ( value !== undefined && value !== null && String( value ) !== '' ) {
			attrs[ key ] = String( value );
		}
	} );

	return attrs;
}
