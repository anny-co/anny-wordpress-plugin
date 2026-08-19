/**
 * Shared Edit component for all anny widget blocks.
 *
 * Unconfigured (a required entity slug is missing) → placeholder card with
 * an entity picker; configured → live preview of the real web component.
 */

import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, Disabled, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ICONS, LABELS, entitySlugFor, toElementAttributes } from '../manifest';
import DesignTokenPanel from './design-token-panel';
import EntityPicker, { ENTITY_LABELS } from './entity-picker';
import OptionsPanel from './options-panel';
import WidgetPreview from './widget-preview';

const TRIGGER_TEMPLATE = [ [ 'core/button' ] ];

/**
 * A block is unconfigured while its required entity slug is missing.
 * The modal button needs either a resource or an organization; widgets
 * without an entity (my-bookings, login, cart) are always configured.
 * Organization widgets inherit the site organization, so they only count as
 * unconfigured while no organization is selected in the anny settings.
 */
function isUnconfigured( slug, widget, attributes, config ) {
	// Whitespace-only values count as unset — mirrors the PHP renderer.
	if ( slug === 'modal-button' ) {
		return (
			! ( attributes.resource || '' ).trim() &&
			! ( attributes.organization || '' ).trim()
		);
	}

	return !! widget.entity && ! entitySlugFor( attributes, widget, config );
}

export default function Edit( { slug, widget, attributes, setAttributes } ) {
	const config = window.ANNY_WP || {};
	const labels = LABELS[ slug ] || {};
	const blockProps = useBlockProps( { className: 'anny-block' } );
	const hasCustomTrigger = !! widget.innerBlocks && !! attributes.useCustomTrigger;

	const inspector = (
		<InspectorControls>
			<OptionsPanel
				widget={ widget }
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<DesignTokenPanel
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
		</InspectorControls>
	);

	if ( isUnconfigured( slug, widget, attributes, config ) ) {
		return (
			<div { ...blockProps }>
				{ inspector }
				<Placeholder
					className="anny-placeholder"
					icon={ ICONS[ widget.icon ] || 'admin-generic' }
					label={ labels.title || slug }
					instructions={ labels.description }
				>
					<div className="anny-placeholder__fields">
						{ slug === 'modal-button' ? (
							<>
								<EntityPicker
									entity="resource"
									label={ ENTITY_LABELS.resource }
									value={ attributes.resource || '' }
									onChange={ ( value ) => setAttributes( { resource: value } ) }
								/>
								<EntityPicker
									entity="organization"
									label={ ENTITY_LABELS.organization }
									value={ attributes.organization || '' }
									onChange={ ( value ) => setAttributes( { organization: value } ) }
								/>
							</>
						) : (
							<EntityPicker
								entity={ widget.entity }
								label={ ENTITY_LABELS[ widget.entity ] }
								value={ attributes.entitySlug || '' }
								onChange={ ( value ) => setAttributes( { entitySlug: value } ) }
								inheritsSiteOrg
							/>
						) }
					</div>
					{ ! config.connected && (
						<div className="anny-placeholder__notice">
							{ __( 'This site is not connected to anny yet.', 'anny-bookings' ) }
							{ config.settingsUrl && (
								<>
									{ ' ' }
									<Button variant="link" href={ config.settingsUrl }>
										{ __( 'Open anny settings', 'anny-bookings' ) }
									</Button>
								</>
							) }
						</div>
					) }
				</Placeholder>
			</div>
		);
	}

	const elementAttributes = toElementAttributes( attributes, widget, config );

	return (
		<div { ...blockProps }>
			{ inspector }
			{ /* The slotted InnerBlocks trigger must stay editable, so the
			     preview is only inert while the widget renders itself. */ }
			<Disabled
				className="anny-preview"
				isDisabled={ ! hasCustomTrigger }
			>
				<WidgetPreview
					tagName={ widget.element }
					attributes={ elementAttributes }
				>
					{ hasCustomTrigger && (
						<InnerBlocks
							template={ TRIGGER_TEMPLATE }
							templateLock={ false }
						/>
					) }
				</WidgetPreview>
			</Disabled>
		</div>
	);
}
