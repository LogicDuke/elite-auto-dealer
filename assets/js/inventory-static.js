/**
 * Vehicle filters on the static export (Cloudflare Pages). Inert in WordPress, which filters on
 * the server (inc/inventory-query.php); the export marks the listing with data-eda-static-inventory
 * (eda_static_inventory()).
 *
 * With filters in the query string (/vehicles/?make=bmw&price_max=50000), every page of the
 * exported listing is loaded and its cards are filtered and sorted in the browser exactly like
 * eda_inventory_query(): make, model (ignored when it belongs to another make), fuel, maximum price
 * (vehicles without a price drop out) and sort, always available → reserved → sold first; ties keep
 * the listing's own (newest first) order. The form updates the URL without a reload; Back/Forward
 * follow. Without JS, or without filters, the exported listing shows as it is.
 */
( () => {
	const root = document.querySelector( '[data-eda-static-inventory]' );
	const form = root && root.querySelector( 'form.vehicle-search' );
	const grid = root && root.querySelector( '.vehicle-grid' );
	if ( ! form || ! grid ) {
		return;
	}

	let strings = {};
	try {
		strings = JSON.parse( root.dataset.edaStrings || '{}' );
	} catch ( error ) {
		// Keep the numbers without words.
	}
	const KEYS = [ 'make', 'model', 'fuel', 'price_max', 'sort' ];
	const SORTS = { price_asc: [ 'price', 1 ], price_desc: [ 'price', -1 ], mileage_asc: [ 'mileage', 1 ], year_desc: [ 'year', -1 ] };
	const base = location.pathname.replace( /page\/\d+\/$/, '' );
	const count = root.querySelector( '.inventory__count' );
	const pagination = root.querySelector( '.pagination' );
	const original = { cards: [ ...grid.children ], count: count && count.textContent };
	let models = [];
	try {
		models = JSON.parse( form.querySelector( 'select[data-role="model"]' ).dataset.options || '[]' );
	} catch ( error ) {
		// No make check for models.
	}

	// Every card of the exported listing, in its order: page 1, then /page/2/ … /page/N/.
	let all;
	const load = () => {
		if ( ! all ) {
			const get = async ( url ) => new DOMParser().parseFromString( await ( await fetch( url ) ).text(), 'text/html' );
			all = get( base ).then( async ( first ) => {
				const pages = [ ...first.querySelectorAll( '.pagination a[href]' ) ].map( ( a ) => +( ( a.getAttribute( 'href' ).match( /\/page\/(\d+)\/?(?:[?#]|$)/ ) || [] )[ 1 ] || 1 ) );
				const last = Math.max( 1, ...pages );
				const rest = await Promise.all( Array.from( { length: last - 1 }, ( _, i ) => get( `${ base }page/${ i + 2 }/` ) ) );
				const seen = new Set();
				return [ first, ...rest ].flatMap( ( doc ) => [ ...doc.querySelectorAll( '.vehicle-grid > .vehicle-card' ) ] ).filter( ( card ) => {
					const href = card.querySelector( 'a' ).getAttribute( 'href' );
					return ! seen.has( href ) && seen.add( href );
				} ).map( ( card ) => document.importNode( card, true ) );
			} );
		}
		return all;
	};

	const state = () => {
		const params = new URLSearchParams( location.search );
		const s = Object.fromEntries( KEYS.map( ( key ) => [ key, ( params.get( key ) || '' ).trim() ] ) );
		const model = models.find( ( m ) => m.v === s.model );
		if ( s.make && model && model.m !== s.make ) {
			s.model = ''; // A model of another make is ignored, as on the server.
		}
		return s;
	};

	const has = ( card, key, value ) => ! value || ( card.dataset[ key ] || '' ).split( ' ' ).includes( value );
	const num = ( card, key ) => ( '' === ( card.dataset[ key ] || '' ) ? -Infinity : +card.dataset[ key ] ); // Missing values sort first ascending, last descending (SQL NULL).
	const rank = ( card ) => ( { reserved: 1, sold: 2 }[ card.dataset.status ] || 0 );

	const results = ( cards, s ) => {
		const max = parseInt( s.price_max, 10 ) || 0;
		const list = cards.filter( ( card ) => has( card, 'make', s.make ) && has( card, 'model', s.model ) && has( card, 'fuel', s.fuel ) && ( ! max || ( '' !== ( card.dataset.price || '' ) && +card.dataset.price <= max ) ) );
		const [ key, dir ] = SORTS[ s.sort ] || [];
		return list.sort( ( a, b ) => rank( a ) - rank( b ) || ( key ? dir * ( num( a, key ) - num( b, key ) ) : 0 ) ); // Stable: ties keep the listing order.
	};

	const say = ( n ) => ( strings[ 1 === n ? 'one' : 'other' ] || '%s' ).replace( '%s', n.toLocaleString( document.documentElement.lang || undefined ) );

	const empty = () => {
		const box = document.createElement( 'div' );
		box.className = 'empty-state';
		box.dataset.edaStaticEmpty = '';
		const h = document.createElement( 'h2' );
		h.textContent = strings.empty || '';
		const p = document.createElement( 'p' );
		p.textContent = strings.emptyText || '';
		const a = document.createElement( 'a' );
		a.className = 'button button--primary';
		a.href = base;
		a.textContent = strings.all || '';
		box.append( h, p, a );
		return box;
	};

	const syncForm = ( s ) => {
		const make = form.elements.make;
		if ( make && make.value !== s.make ) {
			make.value = s.make;
			make.dispatchEvent( new Event( 'change' ) ); // make-model.js re-lists the models.
		}
		KEYS.forEach( ( key ) => {
			const field = form.elements[ key ];
			if ( field ) {
				field.value = s[ key ];
				if ( field.value !== s[ key ] ) {
					field.value = ''; // Unknown value: the field shows its default.
				}
			}
		} );
		let clear = form.querySelector( '[data-eda-static-clear]' );
		const filtered = KEYS.some( ( key ) => s[ key ] );
		if ( filtered && ! clear ) {
			clear = document.createElement( 'a' );
			clear.className = 'button button--quiet';
			clear.href = base;
			clear.dataset.edaStaticClear = '';
			clear.textContent = strings.clear || '';
			form.querySelector( '.vehicle-search__actions' ).append( clear );
		} else if ( ! filtered && clear ) {
			clear.remove();
		}
	};

	let ticket = 0;
	const render = async () => {
		const s = state();
		const mine = ++ticket;
		syncForm( s );
		root.querySelector( '[data-eda-static-empty]' )?.remove();
		if ( ! KEYS.some( ( key ) => s[ key ] ) ) {
			grid.replaceChildren( ...original.cards );
			grid.hidden = false;
			if ( pagination ) {
				pagination.hidden = false;
			}
			if ( count ) {
				count.textContent = original.count;
			}
			return;
		}
		grid.setAttribute( 'aria-busy', 'true' );
		let list;
		try {
			list = results( await load(), s );
		} catch ( error ) {
			all = null;
			grid.removeAttribute( 'aria-busy' );
			return; // Offline: keep the listing as it is.
		}
		if ( mine !== ticket ) {
			return; // A newer choice is already rendering.
		}
		grid.replaceChildren( ...list );
		grid.hidden = ! list.length;
		grid.removeAttribute( 'aria-busy' );
		if ( pagination ) {
			pagination.hidden = true;
		}
		if ( ! list.length ) {
			grid.after( empty() );
		}
		if ( count ) {
			count.textContent = say( list.length );
		}
	};

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		event.stopPropagation(); // navigation.js would disable the empty fields for a real submit.
		const params = new URLSearchParams();
		KEYS.forEach( ( key ) => form.elements[ key ] && form.elements[ key ].value && params.set( key, form.elements[ key ].value ) );
		const query = params.toString();
		history.pushState( null, '', base + ( query ? '?' + query : '' ) );
		render();
	} );
	window.addEventListener( 'popstate', render );
	render();
} )();
