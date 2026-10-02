/**
 * Mobile navigation drawer + clean GET search URLs. Vanilla, no dependencies.
 *
 * Without JS the menu is simply shown (CSS keys the drawer off html.js), so navigation
 * never depends on this file. The breakpoint matches the CSS (60em); no UA detection.
 */
( () => {
	const toggle = document.querySelector( '.nav-toggle' );
	const panel = toggle && document.getElementById( toggle.getAttribute( 'aria-controls' ) );
	const desktop = window.matchMedia( '(min-width: 60em)' );

	if ( toggle && panel ) {
		const focusables = () =>
			[ toggle, ...panel.querySelectorAll( 'a[href], button:not([disabled])' ) ];

		const setOpen = ( open, restoreFocus = false ) => {
			toggle.setAttribute( 'aria-expanded', String( open ) );
			document.documentElement.classList.toggle( 'nav-open', open );
			if ( open ) {
				panel.querySelector( 'a[href]' )?.focus();
			} else if ( restoreFocus ) {
				toggle.focus();
			}
		};
		const isOpen = () => 'true' === toggle.getAttribute( 'aria-expanded' );

		toggle.addEventListener( 'click', () => setOpen( ! isOpen() ) );

		document.addEventListener( 'keydown', ( event ) => {
			if ( ! isOpen() ) {
				return;
			}
			if ( 'Escape' === event.key ) {
				setOpen( false, true );
			} else if ( 'Tab' === event.key ) {
				// Keep focus inside the open drawer (button + menu links).
				const items = focusables();
				const first = items[ 0 ];
				const last = items[ items.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		panel.addEventListener( 'click', ( event ) => {
			if ( event.target.closest( 'a[href]' ) ) {
				setOpen( false );
			}
		} );

		desktop.addEventListener( 'change', ( event ) => {
			if ( event.matches ) {
				setOpen( false );
			}
		} );
	}

	// Search forms: drop empty fields so URLs stay short (/vehicles/?make=bmw).
	document.addEventListener( 'submit', ( event ) => {
		const form = event.target;
		if ( form.matches( 'form[data-clean-get]' ) ) {
			form.querySelectorAll( 'select, input' ).forEach( ( field ) => {
				field.disabled = field.name && '' === field.value;
			} );
		}
	} );
	// Re-enable fields if the page is restored from the back/forward cache.
	window.addEventListener( 'pageshow', () => {
		document
			.querySelectorAll( 'form[data-clean-get] [disabled]' )
			.forEach( ( field ) => ( field.disabled = false ) );
	} );
} )();
