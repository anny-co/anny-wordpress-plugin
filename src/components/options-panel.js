/**
 * Inspector panel with one control per manifest option, rendered in
 * manifest order. Control types follow the shared implementation contract.
 */

import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { BOOLEAN_OPTIONS, RELATIVE_START_KEYWORDS, camelize } from '../manifest';
import EntityPicker, { ENTITY_LABELS } from './entity-picker';

const OPTION_LABELS = {
	height: __( 'Height', 'anny-bookings' ),
	fullscreen: __( 'Fullscreen', 'anny-bookings' ),
	'nav-height': __( 'Navigation height', 'anny-bookings' ),
	'default-list': __( 'Default list', 'anny-bookings' ),
	view: __( 'Initial view', 'anny-bookings' ),
	'default-category': __( 'Default category', 'anny-bookings' ),
	'calendar-view': __( 'Calendar view', 'anny-bookings' ),
	start: __( 'Start date', 'anny-bookings' ),
	end: __( 'End date', 'anny-bookings' ),
	locale: __( 'Language', 'anny-bookings' ),
	'should-login': __( 'Require login', 'anny-bookings' ),
	'idp-uuid': __( 'Identity provider UUID', 'anny-bookings' ),
	label: __( 'Button label', 'anny-bookings' ),
	'modal-layout': __( 'Modal layout', 'anny-bookings' ),
	'modal-size': __( 'Modal size', 'anny-bookings' ),
	'modal-width': __( 'Modal width', 'anny-bookings' ),
	'modal-title': __( 'Modal title', 'anny-bookings' ),
	'close-on-backdrop': __( 'Close on backdrop click', 'anny-bookings' ),
	'show-close-button': __( 'Show close button', 'anny-bookings' ),
	'aria-label': __( 'ARIA label', 'anny-bookings' ),
	'show-icon': __( 'Show icon', 'anny-bookings' ),
	resource: __( 'Resource slug', 'anny-bookings' ),
	organization: __( 'Organization slug', 'anny-bookings' ),
};

/**
 * Explanations for the options whose effect or accepted value is not obvious
 * from the label alone. Options left out here are self-explanatory.
 */
const OPTION_HINTS = {
	height: __(
		'Height of the embedded widget. Ignored while Fullscreen is on.',
		'anny-bookings'
	),
	fullscreen: __(
		'Make the widget as tall as the browser window instead of using the height above.',
		'anny-bookings'
	),
	'nav-height': __(
		'Height of your fixed site header. In fullscreen it is subtracted from the window height so your header stays visible.',
		'anny-bookings'
	),
	'modal-layout': __(
		'How the dialog opens: centred, as a side drawer, or full screen.',
		'anny-bookings'
	),
	'modal-size': __(
		'Preset dialog width. Modal width overrides it.',
		'anny-bookings'
	),
	'modal-width': __( 'Exact dialog width, e.g. 720px or 90%.', 'anny-bookings' ),
	'close-on-backdrop': __(
		'Close the dialog when the visitor clicks the dimmed area around it.',
		'anny-bookings'
	),
	'aria-label': __(
		'Describes the button for screen readers when the visible label is not descriptive on its own.',
		'anny-bookings'
	),
	'idp-uuid': __(
		'Single sign-on provider for this block, overriding the site default.',
		'anny-bookings'
	),
};

const SELECT_OPTIONS = {
	'modal-layout': [
		{ value: '', label: __( 'Default', 'anny-bookings' ) },
		{ value: 'dialog', label: __( 'Dialog', 'anny-bookings' ) },
		{ value: 'drawer', label: __( 'Drawer', 'anny-bookings' ) },
		{ value: 'fullscreen', label: __( 'Fullscreen', 'anny-bookings' ) },
	],
	'modal-size': [
		{ value: '', label: __( 'Default', 'anny-bookings' ) },
		{ value: 'sm', label: __( 'Small', 'anny-bookings' ) },
		{ value: 'md', label: __( 'Medium', 'anny-bookings' ) },
		{ value: 'lg', label: __( 'Large', 'anny-bookings' ) },
	],
	'calendar-view': [
		{ value: '', label: __( 'Default', 'anny-bookings' ) },
		{ value: 'day', label: __( 'Day', 'anny-bookings' ) },
		{ value: 'week', label: __( 'Week', 'anny-bookings' ) },
		{ value: 'month', label: __( 'Month', 'anny-bookings' ) },
		{ value: 'list', label: __( 'List', 'anny-bookings' ) },
	],
	view: [
		{ value: '', label: __( 'Default', 'anny-bookings' ) },
		{ value: 'list', label: __( 'List', 'anny-bookings' ) },
		{ value: 'map', label: __( 'Map', 'anny-bookings' ) },
		{ value: 'calendar', label: __( 'Calendar', 'anny-bookings' ) },
	],
	'default-list': [
		{ value: '', label: __( 'Default', 'anny-bookings' ) },
		{ value: 'resources', label: __( 'Resources', 'anny-bookings' ) },
		{ value: 'services', label: __( 'Services', 'anny-bookings' ) },
		{ value: 'plans', label: __( 'Plans', 'anny-bookings' ) },
		{ value: 'packages', label: __( 'Packages', 'anny-bookings' ) },
	],
};

const UNIT_OPTIONS = [ 'height', 'nav-height' ];

const UNITS = [
	{ value: 'px', label: 'px', default: 0 },
	{ value: '%', label: '%', default: 0 },
	{ value: 'vh', label: 'vh', default: 0 },
	{ value: 'em', label: 'em', default: 0 },
	{ value: 'rem', label: 'rem', default: 0 },
];

/**
 * `start` control: relative keywords + custom ISO date for most widgets,
 * ISO date only for calendar widgets (manifest `calendarStart`).
 */
function StartControl( { widget, value, onChange } ) {
	const isCustom = !! value && ! RELATIVE_START_KEYWORDS.includes( value );
	const [ customMode, setCustomMode ] = useState( isCustom );

	if ( widget.calendarStart ) {
		return (
			<TextControl
				label={ OPTION_LABELS.start }
				value={ value || '' }
				onChange={ onChange }
				placeholder="YYYY-MM-DD"
				help={ __( 'ISO date, e.g. 2026-07-01. Calendars do not support relative keywords.', 'anny-bookings' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	}

	const showCustom = customMode || isCustom;

	return (
		<>
			<SelectControl
				label={ OPTION_LABELS.start }
				value={ showCustom ? 'custom' : value || '' }
				options={ [
					{ value: '', label: __( 'Default', 'anny-bookings' ) },
					{ value: 'today', label: __( 'Today', 'anny-bookings' ) },
					{ value: 'tomorrow', label: __( 'Tomorrow', 'anny-bookings' ) },
					{ value: 'this_week', label: __( 'This week', 'anny-bookings' ) },
					{ value: 'next_week', label: __( 'Next week', 'anny-bookings' ) },
					{ value: 'this_month', label: __( 'This month', 'anny-bookings' ) },
					{ value: 'next_month', label: __( 'Next month', 'anny-bookings' ) },
					{ value: 'custom', label: __( 'Custom date…', 'anny-bookings' ) },
				] }
				onChange={ ( next ) => {
					if ( next === 'custom' ) {
						setCustomMode( true );
						if ( ! isCustom ) {
							onChange( '' );
						}
						return;
					}
					setCustomMode( false );
					onChange( next );
				} }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ showCustom && (
				<TextControl
					label={ __( 'Custom start date', 'anny-bookings' ) }
					value={ isCustom ? value : '' }
					onChange={ onChange }
					placeholder="YYYY-MM-DD"
					help={ __( 'ISO date, e.g. 2026-07-01.', 'anny-bookings' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) }
		</>
	);
}

function OptionControl( { option, widget, value, onChange, config } ) {
	const controlLabel = OPTION_LABELS[ option ] || option;

	// The organization page can open on one of the organization's resource
	// categories; those are listed through the same proxy as the entities.
	if ( option === 'default-category' ) {
		return (
			<EntityPicker
				entity="category"
				label={ controlLabel }
				value={ value || '' }
				onChange={ onChange }
				help={ __( 'Category the organization page opens on. Leave empty to show everything.', 'anny-bookings' ) }
			/>
		);
	}

	// The modal button targets an organization through a plain option; it is
	// scoped to the site organization like every other organization field.
	if ( option === 'organization' ) {
		return (
			<EntityPicker
				entity="organization"
				label={ controlLabel }
				value={ value || '' }
				onChange={ onChange }
			/>
		);
	}

	if ( option === 'start' ) {
		return <StartControl widget={ widget } value={ value } onChange={ onChange } />;
	}

	if ( option === 'end' ) {
		return (
			<TextControl
				label={ controlLabel }
				value={ value || '' }
				onChange={ onChange }
				placeholder="YYYY-MM-DD"
				help={ __( 'ISO date, e.g. 2026-07-31.', 'anny-bookings' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	}

	if ( BOOLEAN_OPTIONS.includes( option ) ) {
		return (
			<ToggleControl
				label={ controlLabel }
				checked={ !! value }
				onChange={ onChange }
				help={ OPTION_HINTS[ option ] }
				__nextHasNoMarginBottom
			/>
		);
	}

	if ( SELECT_OPTIONS[ option ] ) {
		const options = [ ...SELECT_OPTIONS[ option ] ];
		// Preserve values the select does not know about.
		if ( value && ! options.some( ( item ) => item.value === value ) ) {
			options.push( { value, label: value } );
		}
		return (
			<SelectControl
				label={ controlLabel }
				value={ value || '' }
				options={ options }
				onChange={ onChange }
				help={ OPTION_HINTS[ option ] }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	}

	if ( UNIT_OPTIONS.includes( option ) ) {
		return (
			<UnitControl
				label={ controlLabel }
				value={ value || '' }
				onChange={ ( next ) => onChange( next ?? '' ) }
				units={ UNITS }
				help={ OPTION_HINTS[ option ] }
				__next40pxDefaultSize
			/>
		);
	}

	// TextControl fallback: label, modal-title, modal-width, aria-label,
	// idp-uuid, default-category, locale, resource, organization.
	const extraProps = { help: OPTION_HINTS[ option ] };
	if ( option === 'locale' ) {
		extraProps.placeholder = config?.defaults?.locale || '';
		extraProps.help = __( 'Widget language code, e.g. "de" or "de-formal" for formal German. Leave empty to use the site default.', 'anny-bookings' );
	}

	return (
		<TextControl
			label={ controlLabel }
			value={ value || '' }
			onChange={ onChange }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...extraProps }
		/>
	);
}

export default function OptionsPanel( { widget, attributes, setAttributes } ) {
	const config = window.ANNY_WP || {};

	return (
		<PanelBody title={ __( 'Widget settings', 'anny-bookings' ) } initialOpen>
			{ widget.entity && (
				<EntityPicker
					entity={ widget.entity }
					label={ ENTITY_LABELS[ widget.entity ] }
					value={ attributes.entitySlug || '' }
					onChange={ ( value ) => setAttributes( { entitySlug: value } ) }
					inheritsSiteOrg
				/>
			) }
			{ widget.innerBlocks && (
				<ToggleControl
					label={ __( 'Use custom trigger', 'anny-bookings' ) }
					help={ __( 'Design the trigger with your own blocks instead of the built-in button.', 'anny-bookings' ) }
					checked={ !! attributes.useCustomTrigger }
					onChange={ ( value ) => setAttributes( { useCustomTrigger: value } ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ( widget.options || [] )
				.filter( ( option ) => ! ( option === 'label' && widget.innerBlocks && attributes.useCustomTrigger ) )
				.map( ( option ) => (
					<OptionControl
						key={ option }
						option={ option }
						widget={ widget }
						value={ attributes[ camelize( option ) ] }
						onChange={ ( value ) => setAttributes( { [ camelize( option ) ]: value } ) }
						config={ config }
					/>
				) ) }
		</PanelBody>
	);
}
