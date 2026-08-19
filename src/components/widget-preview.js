/**
 * Renders the real anny web component in the editor canvas.
 *
 * React passes unknown props on custom elements (tag names containing a
 * dash) straight through as HTML attributes, so the kebab-case attribute
 * map produced by toElementAttributes() can be spread directly. The custom
 * element upgrades once the anny CDN script has loaded in the canvas
 * iframe; until then it renders as an inert unknown element.
 *
 * The widgets read their attributes when they upgrade and do not observe
 * later changes, so a settings edit would update the DOM attribute without
 * changing anything on screen. Keying the element on its attribute map
 * makes React drop and recreate it, which re-runs the upgrade with the new
 * values. The key is committed on a short debounce so that typing in a text
 * control does not recreate the widget on every keystroke.
 */

import { useDebounce } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';

/**
 * How long the attribute map has to stay unchanged before the preview is
 * recreated, in milliseconds.
 */
const REMOUNT_DELAY = 500;

export default function WidgetPreview( { tagName, attributes, children } ) {
	const Tag = tagName;
	const signature = JSON.stringify( attributes );
	const [ mountKey, setMountKey ] = useState( signature );
	const commitMountKey = useDebounce( setMountKey, REMOUNT_DELAY );

	useEffect( () => {
		commitMountKey( signature );
	}, [ signature, commitMountKey ] );

	return (
		<Tag key={ mountKey } { ...attributes }>
			{ children }
		</Tag>
	);
}
