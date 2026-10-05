/**
 * Privacy preferences (inc/consent.php). Vanilla, no requests, static-safe.
 *
 * Record (localStorage, key from edaConsentConfig): {"v":1,"policy":"1","ts":<unix s>,"cats":{"<slug>":bool}}.
 * Asked again after maxAgeDays or when the policy version changes. With no optional categories
 * nothing is ever stored: the panel is information only and the banner is not printed.
 * A category may name a placeholder: until it is allowed, a local button stands in for the
 * service's own control (e.g. the GTranslate selector) and opens the preferences.
 * API (EDS Consent contract): edsConsent.allowed(cat) · get() · set(cats) · acceptAll() · rejectAll() · open();
 * event `eds:consent` on document, detail { categories, previous, source }.
 */
( () => {
	const cfg = window.edaConsentConfig;
	if ( ! cfg || window.edsConsent ) {
		return;
	}

	const banner = document.querySelector( '[data-eda-consent-banner]' );
	const panel = document.querySelector( '[data-eda-consent-panel]' );
	const cats = cfg.categories;
	let returnFocus = null;

	const read = () => {
		try {
			const r = JSON.parse( localStorage.getItem( cfg.key ) );
			if ( r && 1 === r.v && r.policy === cfg.version && r.cats && Date.now() / 1000 - r.ts < cfg.maxAgeDays * 86400 ) {
				return r;
			}
		} catch ( e ) {
			// Storage blocked or corrupt: undecided.
		}
		return null;
	};
	let record = cats.length ? read() : null;
	const allowed = ( cat ) => 'necessary' === cat || !! ( record && record.cats[ cat ] );
	const all = ( value ) => Object.fromEntries( cats.map( ( c ) => [ c, value ] ) );

	// Storage of a category that is not allowed (before a decision, or after a withdrawal) goes.
	const cleanup = () => {
		cats.filter( ( c ) => ! allowed( c ) ).forEach( ( c ) => {
			try {
				( cfg.cleanup.localStorage[ c ] || [] ).forEach( ( k ) => localStorage.removeItem( k ) );
			} catch ( e ) {
				// Blocked.
			}
			const host = location.hostname;
			( cfg.cleanup.cookies[ c ] || [] ).forEach( ( name ) => [ '', host, '.' + host ].forEach( ( d ) => {
				document.cookie = name + '=; Max-Age=0; Path=/' + ( d ? '; Domain=' + d : '' );
			} ) );
		} );
	};

	// Start inert scripts whose category is now allowed (same attributes, real src).
	const activate = () => {
		document.querySelectorAll( 'script[type="text/plain"][data-eds-consent]' ).forEach( ( old ) => {
			if ( ! old.dataset.edsConsent.split( ' ' ).some( allowed ) ) {
				return;
			}
			const s = document.createElement( 'script' );
			[ ...old.attributes ].forEach( ( a ) => {
				if ( ! [ 'type', 'data-src', 'data-eds-consent' ].includes( a.name ) ) {
					s.setAttribute( a.name, a.value );
				}
			} );
			s.src = old.dataset.src;
			// A category with a placeholder: once the service's control exists, make it keyboard-operable
			// if it is not (e.g. GTranslate's float switcher is a <div>), and focus it when the visitor
			// allowed the category from the placeholder.
			const cat = old.dataset.edsConsent.split( ' ' ).find( ( c ) => cfg.placeholders[ c ] && cfg.placeholders[ c ].focus );
			if ( cat ) {
				const fromPlaceholder = !! ( returnFocus && returnFocus.dataset && cat === returnFocus.dataset.edaConsentPlaceholder );
				s.addEventListener( 'load', () => {
					let tries = 40;
					const ready = () => {
						const el = document.querySelector( cfg.placeholders[ cat ].focus );
						if ( ! el ) {
							return tries-- && setTimeout( ready, 50 );
						}
						if ( el.tabIndex < 0 ) {
							el.tabIndex = 0;
							el.setAttribute( 'role', 'button' );
							el.setAttribute( 'aria-label', cfg.placeholders[ cat ].label + ': ' + el.textContent.trim() );
							el.addEventListener( 'keydown', ( e ) => {
								if ( 'Enter' === e.key || ' ' === e.key ) {
									e.preventDefault();
									el.click();
								}
							} );
						}
						if ( fromPlaceholder ) {
							el.focus();
						}
					};
					ready();
				}, { once: true } );
			}
			old.replaceWith( s );
		} );
	};

	// Until its category is allowed, a service's control is a local button that explains and asks.
	const placeholders = () => {
		Object.keys( cfg.placeholders ).forEach( ( cat ) => {
			const ph = cfg.placeholders[ cat ];
			document.querySelectorAll( ph.selector ).forEach( ( el ) => {
				const button = el.querySelector( '[data-eda-consent-placeholder]' );
				if ( allowed( cat ) ) {
					return button && button.remove();
				}
				if ( button ) {
					return;
				}
				const b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'eda-consent-placeholder';
				b.dataset.edaConsentPlaceholder = cat;
				b.dataset.edsConsentOpen = '';
				b.textContent = ph.label;
				b.setAttribute( 'aria-label', ph.hint );
				el.append( b );
			} );
		} );
	};

	const set = ( choice, source ) => {
		if ( ! cats.length ) {
			return; // Nothing optional: no decision to store.
		}
		const previous = record ? { ...record.cats } : null;
		record = { v: 1, policy: cfg.version, ts: Math.floor( Date.now() / 1000 ), cats: Object.fromEntries( cats.map( ( c ) => [ c, !! choice[ c ] ] ) ) };
		try {
			localStorage.setItem( cfg.key, JSON.stringify( record ) );
		} catch ( e ) {
			// This page only.
		}
		if ( banner ) {
			banner.hidden = true;
		}
		document.dispatchEvent( new CustomEvent( 'eds:consent', { detail: { categories: { necessary: true, ...record.cats }, previous, source } } ) );
		cleanup();
		if ( previous && cats.some( ( c ) => previous[ c ] && ! record.cats[ c ] ) ) {
			location.reload(); // A script that already ran cannot be unloaded.
			return;
		}
		activate();
		placeholders();
	};

	const open = () => {
		if ( ! panel || panel.open ) {
			return;
		}
		returnFocus = document.activeElement;
		panel.querySelectorAll( '[data-eds-consent-category]' ).forEach( ( t ) => {
			t.checked = allowed( t.dataset.edsConsentCategory );
		} );
		panel.showModal();
	};
	const close = () => panel && panel.open && panel.close();
	if ( panel ) {
		panel.addEventListener( 'close', () => {
			const target = returnFocus && returnFocus.isConnected && returnFocus !== document.body ? returnFocus : document.querySelector( 'a[href$="#eds-consent"]' );
			if ( target ) {
				target.focus();
			}
		} );
		// Clicking the backdrop (outside the panel box) closes without changes.
		panel.addEventListener( 'click', ( e ) => {
			if ( e.target === panel ) {
				close();
			}
		} );
	}

	const act = ( action ) => {
		const fromPanel = panel && panel.open;
		if ( 'manage' === action ) {
			return open();
		}
		if ( 'close' === action ) {
			return close();
		}
		let choice = all( 'accept' === action );
		if ( 'save' === action ) {
			choice = {};
			panel.querySelectorAll( '[data-eds-consent-category]' ).forEach( ( t ) => {
				choice[ t.dataset.edsConsentCategory ] = t.checked;
			} );
		}
		set( choice, ( fromPanel ? 'panel-' : 'banner-' ) + action );
		close();
	};

	document.addEventListener( 'click', ( e ) => {
		const a = e.target.closest( '[data-eds-consent-action]' );
		if ( a ) {
			act( a.dataset.edsConsentAction );
			return;
		}
		const o = e.target.closest( '[data-eds-consent-open], a[href$="#eds-consent"]' );
		if ( o ) {
			e.preventDefault();
			open();
		}
	} );
	// Links to #eds-consent act as buttons.
	document.querySelectorAll( 'a[href$="#eds-consent"]' ).forEach( ( a ) => a.setAttribute( 'role', 'button' ) );

	window.edsConsent = {
		allowed,
		get: () => ( { decided: !! record, categories: { necessary: true, ...Object.fromEntries( cats.map( ( c ) => [ c, allowed( c ) ] ) ) }, version: cfg.version } ),
		set: ( choice ) => set( choice || {}, 'api' ),
		acceptAll: () => set( all( true ), 'api' ),
		rejectAll: () => set( all( false ), 'api' ),
		open,
	};

	cleanup();
	placeholders();
	activate();
	if ( ! record && banner ) {
		banner.hidden = false;
	}
	if ( '#eds-consent' === location.hash ) {
		open();
	}
} )();
