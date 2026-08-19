/**
 * Registers one Gutenberg block per anny widget from the shared manifest.
 */

import { registerBlockType, updateCategory } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import { WIDGETS, LABELS, ICONS, BOOLEAN_OPTIONS, camelize } from './manifest';
import Edit from './components/edit';
import { ReactComponent as AnnyLogo } from '../assets/anny-icon.svg';
import './editor.scss';

// Brand the "anny" block category in the inserter with the anny logo.
updateCategory( 'anny', {
	icon: <AnnyLogo width="20" height="20" />,
} );

/**
 * Block attribute schema. Must stay IDENTICAL to the PHP registration in
 * includes/Blocks/Blocks.php (shared attribute-mapping contract).
 *
 * @param {Object} widget Manifest entry.
 * @return {Object} registerBlockType attributes definition.
 */
function buildAttributes( widget ) {
	const attributes = {};

	if ( widget.entity ) {
		attributes.entitySlug = { type: 'string' };
	}

	( widget.options || [] ).forEach( ( option ) => {
		attributes[ camelize( option ) ] = {
			type: BOOLEAN_OPTIONS.includes( option ) ? 'boolean' : 'string',
		};
	} );

	attributes.tokens = { type: 'object', default: {} };

	if ( widget.innerBlocks ) {
		attributes.useCustomTrigger = { type: 'boolean', default: false };
	}

	return attributes;
}

/**
 * Binds the shared Edit component to a specific widget.
 *
 * @param {string} slug   Block slug (manifest key).
 * @param {Object} widget Manifest entry.
 * @return {Function} Edit component for registerBlockType.
 */
function withWidget( slug, widget ) {
	return ( props ) => <Edit { ...props } slug={ slug } widget={ widget } />;
}

Object.keys( WIDGETS ).forEach( ( slug ) => {
	const widget = WIDGETS[ slug ];
	const labels = LABELS[ slug ] || {};

	registerBlockType( `anny/${ slug }`, {
		apiVersion: 3,
		title: labels.title || slug,
		description: labels.description || '',
		category: 'anny',
		icon: ICONS[ widget.icon ] || 'admin-generic',
		keywords: labels.keywords || [],
		attributes: buildAttributes( widget ),
		supports: {
			html: false,
			anchor: true,
			align: widget.align ? [ 'wide', 'full' ] : false,
		},
		edit: withWidget( slug, widget ),
		save: widget.innerBlocks ? () => <InnerBlocks.Content /> : () => null,
	} );
} );
