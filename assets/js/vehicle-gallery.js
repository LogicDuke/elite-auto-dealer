/**
 * Vehicle detail gallery: arrows, thumbnails, keyboard and swipe over the server-rendered slides.
 *
 * Progressive enhancement: without JS the first image shows and the thumbnails link to the full
 * images. Slides other than the current one stay `hidden`, so the browser only fetches an image
 * when it is first shown; it fades in once decoded (short crossfade in CSS).
 */
( () => {
	const gallery = document.querySelector( '[data-gallery]' );
	const slides = gallery ? [ ...gallery.querySelectorAll( '[data-gallery-slide]' ) ] : [];
	if ( slides.length < 2 ) {
		return;
	}

	const main = gallery.querySelector( '.vehicle-gallery__main' );
	const thumbs = [ ...gallery.querySelectorAll( '[data-gallery-thumb]' ) ];
	const buttons = [ ...gallery.querySelectorAll( '[data-gallery-prev], [data-gallery-next]' ) ];
	const status = gallery.querySelector( '[data-gallery-status]' );
	let current = 0;
	let request = 0;

	// Wait until a newly shown slide has loaded (it stays lazy: once visible the browser fetches it at
	// the size of its real box, `sizes="auto"`), so the crossfade never fades in an empty frame.
	const ready = ( slide ) => {
		const img = slide.querySelector( 'img' );
		if ( ! img || ( img.complete && img.naturalWidth ) ) {
			return Promise.resolve();
		}
		const loaded = new Promise( ( done ) => {
			img.addEventListener( 'load', done, { once: true } );
			img.addEventListener( 'error', done, { once: true } );
		} );
		return Promise.race( [ loaded, new Promise( ( done ) => setTimeout( done, 1500 ) ) ] );
	};

	const show = ( index ) => {
		const next = ( index + slides.length ) % slides.length;
		if ( next === current ) {
			return;
		}
		const ticket = ++request;
		current = next;
		thumbs.forEach( ( thumb, i ) => ( i === next ? thumb.setAttribute( 'aria-current', 'true' ) : thumb.removeAttribute( 'aria-current' ) ) );
		if ( status ) {
			status.textContent = status.dataset.template.replace( '%1$s', next + 1 ).replace( '%2$s', slides.length );
		}
		slides[ next ].hidden = false;
		ready( slides[ next ] ).then( () => {
			if ( ticket === request ) {
				slides.forEach( ( slide, i ) => slide.classList.toggle( 'is-active', i === next ) );
			}
		} );
	};

	buttons.forEach( ( button ) => {
		button.hidden = false;
		button.addEventListener( 'click', () => show( current + ( button.hasAttribute( 'data-gallery-next' ) ? 1 : -1 ) ) );
	} );

	thumbs.forEach( ( thumb, i ) => {
		thumb.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			show( i );
		} );
	} );

	// Keyboard: Left/Right anywhere in the gallery (the image area itself is focusable).
	main.tabIndex = 0;
	main.setAttribute( 'role', 'group' );
	main.setAttribute( 'aria-roledescription', 'carousel' );
	main.setAttribute( 'aria-label', gallery.dataset.label || document.title );
	gallery.addEventListener( 'keydown', ( event ) => {
		if ( 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
			event.preventDefault();
			show( current + ( 'ArrowRight' === event.key ? 1 : -1 ) );
		}
	} );

	// Swipe (touch/pen) on the image: left = next, right = previous. Vertical scrolling stays native.
	let start = null;
	main.addEventListener( 'pointerdown', ( event ) => {
		start = 'mouse' === event.pointerType ? null : { x: event.clientX, y: event.clientY };
	} );
	main.addEventListener( 'pointerup', ( event ) => {
		if ( ! start ) {
			return;
		}
		const dx = event.clientX - start.x;
		const dy = event.clientY - start.y;
		start = null;
		if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
			show( current + ( dx < 0 ? 1 : -1 ) );
		}
	} );
	main.addEventListener( 'pointercancel', () => ( start = null ) );
} )();
