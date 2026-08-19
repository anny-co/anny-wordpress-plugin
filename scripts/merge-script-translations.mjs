/**
 * Merges the per-source JSON files produced by `wp i18n make-json` into one
 * file per script handle, and removes the per-source files.
 *
 * WordPress resolves script translations as
 * `{domain}-{locale}-{handle}.json` first, and only then falls back to an
 * md5 of the *registered* script src (`build/index.js`). `wp i18n make-json`
 * names its files after an md5 of the *source* path (`src/manifest.js`), so
 * nothing it writes is ever found at runtime. Merging per bundle fixes that.
 *
 * Usage (from the plugin root, after `wp i18n make-json languages/ languages/`):
 *
 *   npm run i18n:merge
 */

import fs from 'node:fs';
import path from 'node:path';

const LANGUAGES_DIR = 'languages';
const DOMAIN = 'anny-bookings';

/**
 * Script handle => predicate over the source path of a make-json file.
 * Mirrors the entry points in the `build` script.
 */
const BUNDLES = {
	'anny-admin': ( source ) => source.startsWith( 'src/admin/' ),
	'anny-editor': ( source ) => ! source.startsWith( 'src/admin/' ),
};

const files = fs
	.readdirSync( LANGUAGES_DIR )
	.filter( ( name ) => name.startsWith( `${ DOMAIN }-` ) && name.endsWith( '.json' ) );

/** locale => handle => { msgid: [translation] } */
const merged = {};
const consumed = [];

for ( const name of files ) {
	const parsed = JSON.parse(
		fs.readFileSync( path.join( LANGUAGES_DIR, name ), 'utf8' )
	);
	const source = parsed.source;

	// Handle files have no `source`; they are outputs, not inputs.
	if ( ! source ) {
		continue;
	}

	const locale = parsed.locale_data?.messages?.[ '' ]?.lang;
	if ( ! locale ) {
		throw new Error( `${ name } has no locale in its metadata.` );
	}

	const handle = Object.keys( BUNDLES ).find( ( key ) => BUNDLES[ key ]( source ) );
	if ( ! handle ) {
		throw new Error( `${ source } belongs to no known bundle.` );
	}

	merged[ locale ] ??= {};
	merged[ locale ][ handle ] ??= {};
	Object.assign( merged[ locale ][ handle ], parsed.locale_data.messages );
	consumed.push( name );
}

let written = 0;

for ( const [ locale, handles ] of Object.entries( merged ) ) {
	for ( const [ handle, messages ] of Object.entries( handles ) ) {
		const target = path.join(
			LANGUAGES_DIR,
			`${ DOMAIN }-${ locale }-${ handle }.json`
		);

		fs.writeFileSync(
			target,
			JSON.stringify( {
				'translation-revision-date': new Date().toISOString(),
				generator: 'merge-script-translations.mjs',
				domain: DOMAIN,
				locale_data: { messages },
			} )
		);

		// eslint-disable-next-line no-console
		console.log(
			`${ target } (${ Object.keys( messages ).length - 1 } strings)`
		);
		written++;
	}
}

for ( const name of consumed ) {
	fs.unlinkSync( path.join( LANGUAGES_DIR, name ) );
}

// eslint-disable-next-line no-console
console.log(
	`\n${ written } handle file(s) written, ${ consumed.length } per-source file(s) removed.`
);
