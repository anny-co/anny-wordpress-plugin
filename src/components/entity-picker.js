/**
 * Entity picker: searches anny entities through the plugin's REST proxy.
 *
 * Organizations are not searched: the site is scoped to the one organization
 * chosen in the anny settings, so that organization is the only offer.
 *
 * Falls back to a free-text slug input when the site is not connected, the
 * proxy errors (e.g. missing capability) or the entity has no list endpoint
 * (packages).
 */

import apiFetch from '@wordpress/api-fetch';
import {
	ComboboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

export const ENTITY_LABELS = {
	resource: __( 'Resource', 'anny-bookings' ),
	service: __( 'Service', 'anny-bookings' ),
	organization: __( 'Organization', 'anny-bookings' ),
	plan: __( 'Plan', 'anny-bookings' ),
	package: __( 'Package', 'anny-bookings' ),
	category: __( 'Category', 'anny-bookings' ),
};

/**
 * Entity → REST proxy collection. `package` has no list endpoint upstream
 * and always uses the free-text fallback; `organization` is not listed at
 * all — see OrganizationControl.
 */
const ENTITY_ENDPOINTS = {
	resource: 'resources',
	service: 'services',
	plan: 'plans',
	category: 'categories',
};

/**
 * Organization control: the site organization is the only selectable value.
 * An already-stored foreign slug stays selectable so existing content keeps
 * rendering what it was published with, and a site without a selected
 * organization falls back to free text.
 *
 * @param {Object}   props
 * @param {string}   props.value             Stored slug.
 * @param {Function} props.onChange          Slug setter.
 * @param {string}   props.label             Control label.
 * @param {string}   [props.help]            Help text override.
 * @param {boolean}  [props.inheritsSiteOrg] Whether an empty value renders as
 *                                           the site organization (widgets
 *                                           whose entity is the organization)
 *                                           rather than as no organization.
 * @return {JSX.Element} The control.
 */
function OrganizationControl( { value, onChange, label, help, inheritsSiteOrg } ) {
	const org = window.ANNY_WP?.org || null;
	const orgSlug = ( org?.slug || '' ).trim();

	if ( ! orgSlug ) {
		return (
			<TextControl
				label={ label }
				value={ value || '' }
				onChange={ onChange }
				help={ help || __( 'Select an organization on the anny settings page to scope this site.', 'anny-bookings' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	}

	const options = [
		{
			value: '',
			label: inheritsSiteOrg
				? __( 'Site organization', 'anny-bookings' )
				: __( 'No organization', 'anny-bookings' ),
		},
		{
			value: orgSlug,
			label: org.name ? `${ org.name } (${ orgSlug })` : orgSlug,
		},
	];

	if ( value && value !== orgSlug ) {
		options.push( { value, label: value } );
	}

	return (
		<SelectControl
			label={ label }
			value={ value || '' }
			options={ options }
			onChange={ ( next ) => onChange( next || '' ) }
			help={
				help ||
				( inheritsSiteOrg
					? __( 'Follows the organization selected in the anny settings.', 'anny-bookings' )
					: __( 'Only the organization selected in the anny settings can be embedded.', 'anny-bookings' ) )
			}
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

export default function EntityPicker( {
	entity,
	value,
	onChange,
	label,
	help,
	inheritsSiteOrg,
} ) {
	const endpoint = ENTITY_ENDPOINTS[ entity ];
	const connected = !! window.ANNY_WP?.connected;
	const [ items, setItems ] = useState( [] );
	const [ failed, setFailed ] = useState( false );
	const requestRef = useRef( 0 );

	const useFreeText = ! endpoint || ! connected || failed;

	const fetchItems = useCallback(
		( search ) => {
			if ( ! endpoint ) {
				return;
			}
			const requestId = ++requestRef.current;
			apiFetch( {
				path: addQueryArgs(
					`/anny/v1/entities/${ endpoint }`,
					search ? { search } : {}
				),
			} )
				.then( ( result ) => {
					if ( requestId !== requestRef.current ) {
						return;
					}
					setItems( Array.isArray( result ) ? result : [] );
					setFailed( false );
				} )
				.catch( () => {
					if ( requestId !== requestRef.current ) {
						return;
					}
					setFailed( true );
				} );
		},
		[ endpoint ]
	);

	const debouncedFetch = useDebounce( fetchItems, 300 );

	useEffect( () => {
		if ( endpoint && connected ) {
			fetchItems( '' );
		}
	}, [ endpoint, connected, fetchItems ] );

	const controlLabel = label || ENTITY_LABELS[ entity ] || __( 'Entity', 'anny-bookings' );

	// Declared after the hooks above so the hook order stays stable.
	if ( entity === 'organization' ) {
		return (
			<OrganizationControl
				value={ value }
				onChange={ onChange }
				label={ controlLabel }
				help={ help }
				inheritsSiteOrg={ inheritsSiteOrg }
			/>
		);
	}

	if ( useFreeText ) {
		return (
			<TextControl
				label={ controlLabel }
				value={ value || '' }
				onChange={ onChange }
				help={
					help ||
					( entity === 'package'
						? __( 'Enter the package slug from your anny admin. Packages cannot be searched automatically.', 'anny-bookings' )
						: __( 'Enter the slug from your anny admin.', 'anny-bookings' ) )
				}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	}

	const options = items
		.map( ( item ) => ( {
			value: item.slug || '',
			label: item.name && item.slug ? `${ item.name } (${ item.slug })` : item.slug || item.name || '',
		} ) )
		.filter( ( option ) => option.value );

	// Keep an already-stored slug selectable even when it is not in the
	// current result page.
	if ( value && ! options.some( ( option ) => option.value === value ) ) {
		options.unshift( { value, label: value } );
	}

	return (
		<ComboboxControl
			label={ controlLabel }
			value={ value || '' }
			options={ options }
			onChange={ ( next ) => onChange( next || '' ) }
			onFilterValueChange={ ( search ) => debouncedFetch( search || '' ) }
			help={ help }
			allowReset
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}
