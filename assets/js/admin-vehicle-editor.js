/* global wp, edaVehicleEditor */
/**
 * Dealer-friendly vehicle editor: photo manager, kW → hp, EV fields, title preview, equipment
 * options and validation before publishing. The server validates again (inc/admin-vehicle-editor.php).
 */
( () => {
	const editor = document.querySelector( '[data-vehicle-editor]' );
	if ( ! editor ) {
		return;
	}
	const { messages, i18n } = edaVehicleEditor;
	const form = editor.closest( 'form' );
	const $ = ( selector, scope = editor ) => scope.querySelector( selector );
	const format = ( text, ...values ) => values.reduce( ( out, value, i ) => out.replace( `%${ i + 1 }$d`, value ).replace( '%d', value ), text );

	// ---------- Photos: one ordered list; photo 1 = main photo (featured image), the rest = gallery.
	const photos = $( '[data-photos]' );
	const list = $( '[data-photos-list]', photos );
	const input = $( 'input[name="eda_photos"]', photos );
	const status = $( '[data-photos-status]', photos );
	const announce = ( text ) => ( status.textContent = text );
	const items = () => [ ...list.children ];

	const button = ( label, action, extra = '' ) => {
		const el = document.createElement( 'button' );
		el.type = 'button';
		el.className = `button eda-photo__button ${ extra }`;
		el.dataset.action = action;
		el.textContent = label;
		return el;
	};

	const render = () => {
		const all = items();
		input.value = all.map( ( li ) => li.dataset.id ).join( ',' );
		all.forEach( ( li, i ) => {
			const label = 0 === i ? i18n.main : format( i18n.photo, i + 1 );
			li.querySelector( '.eda-photo__label' ).textContent = 0 === i ? `1 · ${ i18n.main }` : label;
			li.querySelector( 'img' ).alt = label;
			li.classList.toggle( 'is-main', 0 === i );
			li.querySelector( '[data-action="left"]' ).disabled = 0 === i;
			li.querySelector( '[data-action="right"]' ).disabled = i === all.length - 1;
			li.querySelectorAll( '[data-action]' ).forEach( ( el ) => el.setAttribute( 'aria-label', `${ el.textContent }: ${ label }` ) );
		} );
		photos.classList.toggle( 'is-empty', ! all.length );
	};

	const build = ( li ) => {
		li.className = 'eda-photo';
		li.draggable = true;
		li.replaceChildren();
		const img = document.createElement( 'img' );
		img.src = li.dataset.src;
		img.alt = '';
		const label = document.createElement( 'span' );
		label.className = 'eda-photo__label';
		const actions = document.createElement( 'div' );
		actions.className = 'eda-photo__actions';
		actions.append( button( '←', 'left' ), button( '→', 'right' ), button( i18n.replace, 'replace' ), button( i18n.remove, 'remove', 'eda-photo__remove' ) );
		actions.querySelector( '[data-action="left"]' ).textContent = `← ${ i18n.left }`;
		actions.querySelector( '[data-action="right"]' ).textContent = `${ i18n.right } →`;
		li.append( img, label, actions );
		return li;
	};

	const add = ( attachment ) => {
		if ( items().some( ( li ) => li.dataset.id === String( attachment.id ) ) ) {
			return null;
		}
		const li = document.createElement( 'li' );
		li.dataset.id = attachment.id;
		li.dataset.src = attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url;
		list.append( build( li ) );
		return li;
	};

	const media = ( multiple, title, buttonText, onSelect ) => {
		const frame = wp.media( {
			title,
			button: { text: buttonText },
			multiple: multiple ? 'add' : false,
			library: { type: 'image' },
		} );
		frame.on( 'select', () => onSelect( frame.state().get( 'selection' ).toJSON() ) );
		frame.open();
	};

	items().forEach( build );
	render();

	$( '[data-photos-add]', photos ).addEventListener( 'click', () => {
		media( true, i18n.addTitle, i18n.addButton, ( selection ) => {
			selection.forEach( add );
			render();
			announce( i18n.added );
			clearError( 'photos' );
		} );
	} );

	list.addEventListener( 'click', ( event ) => {
		const action = event.target.closest( '[data-action]' );
		if ( ! action ) {
			return;
		}
		const li = action.closest( 'li' );
		const all = items();
		const index = all.indexOf( li );
		if ( 'left' === action.dataset.action || 'right' === action.dataset.action ) {
			const target = 'left' === action.dataset.action ? index - 1 : index + 1;
			if ( target < 0 || target >= all.length ) {
				return;
			}
			list.insertBefore( li, 'left' === action.dataset.action ? all[ target ] : all[ target ].nextSibling );
			render();
			( li.querySelector( `[data-action="${ action.dataset.action }"]` ).disabled ? li.querySelector( '[data-action="replace"]' ) : action ).focus();
			announce( format( i18n.moved, target + 1, all.length ) );
		} else if ( 'remove' === action.dataset.action ) {
			const next = all[ index + 1 ] || all[ index - 1 ];
			li.remove();
			render();
			( next ? next.querySelector( '[data-action="remove"]' ) : $( '[data-photos-add]', photos ) ).focus();
			announce( i18n.removed );
		} else if ( 'replace' === action.dataset.action ) {
			media( false, i18n.replace, i18n.replaceWith, ( [ attachment ] ) => {
				if ( ! attachment || items().some( ( other ) => other !== li && other.dataset.id === String( attachment.id ) ) ) {
					return;
				}
				li.dataset.id = attachment.id;
				li.dataset.src = attachment.sizes?.medium?.url || attachment.url;
				build( li );
				render();
				li.querySelector( '[data-action="replace"]' ).focus();
			} );
		}
	} );

	// Drag and drop (mouse); the arrow buttons are the keyboard equivalent.
	let dragged = null;
	list.addEventListener( 'dragstart', ( event ) => {
		dragged = event.target.closest( 'li' );
		dragged?.classList.add( 'is-dragging' );
		event.dataTransfer.effectAllowed = 'move';
	} );
	list.addEventListener( 'dragover', ( event ) => {
		const over = event.target.closest( 'li' );
		if ( ! dragged || ! over || over === dragged ) {
			return;
		}
		event.preventDefault();
		const box = over.getBoundingClientRect();
		list.insertBefore( dragged, event.clientX > box.left + box.width / 2 ? over.nextSibling : over );
	} );
	list.addEventListener( 'dragend', () => {
		dragged?.classList.remove( 'is-dragging' );
		dragged = null;
		render();
	} );

	// ---------- kW → hp (metric horsepower), unless the dealer corrected hp by hand.
	const kw = $( '#eda-meta-power_kw' );
	const hp = $( '#eda-meta-power_hp' );
	if ( kw && hp ) {
		let auto = '' === hp.value || Math.round( kw.value * 1.35962 ) === Number( hp.value );
		hp.addEventListener( 'input', () => ( auto = '' === hp.value ) );
		kw.addEventListener( 'input', () => {
			if ( auto ) {
				hp.value = kw.value ? Math.round( kw.value * 1.35962 ) : '';
			}
		} );
	}

	// ---------- EV fields only for electric and plug-in hybrid (hidden values are still kept).
	const fuel = $( '#eda-tax-vehicle_fuel_type' );
	const ev = () => {
		const slug = fuel?.selectedOptions[ 0 ]?.dataset.slug || '';
		editor.querySelectorAll( '[data-ev]' ).forEach( ( el ) => ( el.hidden = ! [ 'electric', 'plug-in-hybrid' ].includes( slug ) ) );
	};
	fuel?.addEventListener( 'change', ev );
	ev();

	// ---------- Live title preview: Make + Model + Variant unless a title is typed.
	const make = $( '#eda-vehicle-make' );
	const model = $( '#eda-vehicle-model' );
	const variant = $( '#eda-meta-variant' );
	const override = $( '#eda-title-override' );
	const preview = $( '[data-title-preview]' );
	const title = () => {
		const auto = [ make, model ].map( ( select ) => ( select.value ? select.selectedOptions[ 0 ].textContent : '' ) ).concat( variant.value.trim() ).filter( Boolean ).join( ' ' );
		override.placeholder = auto;
		preview.textContent = override.value.trim() || auto;
	};
	[ make, model, variant, override ].forEach( ( el ) => el.addEventListener( 'input', title ) );
	[ make, model ].forEach( ( el ) => el.addEventListener( 'change', () => setTimeout( title ) ) );

	// ---------- Equipment: add a new option as a ticked checkbox (created when saved).
	const grid = $( '[data-equipment-grid]' );
	const newOption = $( '#eda-equipment-new' );
	const addOption = () => {
		const name = newOption.value.trim();
		if ( ! name ) {
			return;
		}
		const label = document.createElement( 'label' );
		label.className = 'eda-check';
		const box = Object.assign( document.createElement( 'input' ), { type: 'checkbox', name: 'eda_equipment_new[]', value: name, checked: true } );
		label.append( box, ` ${ name }` );
		grid.append( label );
		newOption.value = '';
		box.focus();
	};
	$( '[data-equipment-add]' ).addEventListener( 'click', addOption );
	newOption.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault(); // Enter must not submit the whole vehicle.
			addOption();
		}
	} );

	// ---------- Validation before publishing (Save draft is always allowed).
	const summary = $( '.eda-editor__summary' );
	const fieldFor = ( key ) => editor.querySelector( `[data-required="${ key }"]` );
	const errorFor = ( key ) => document.getElementById( fieldFor( key )?.getAttribute( 'aria-describedby' )?.split( ' ' ).pop() );
	function clearError( key ) {
		const error = errorFor( key );
		if ( error ) {
			error.hidden = true;
			error.textContent = '';
		}
		fieldFor( key )?.removeAttribute( 'aria-invalid' );
	}
	const missing = () => Object.keys( messages ).filter( ( key ) => {
		const field = fieldFor( key );
		if ( ! field ) {
			return false;
		}
		if ( 'price' === key ) {
			return ! ( Number( field.value ) > 0 );
		}
		return '' === field.value.trim();
	} );

	editor.querySelectorAll( '[data-required]' ).forEach( ( field ) => field.addEventListener( 'change', () => clearError( field.dataset.required ) ) );

	form.addEventListener( 'submit', ( event ) => {
		const submitter = event.submitter;
		if ( ! submitter || ! submitter.hasAttribute( 'data-validate' ) ) {
			return;
		}
		Object.keys( messages ).forEach( clearError );
		const keys = missing();
		if ( ! keys.length ) {
			summary.hidden = true;
			return;
		}
		event.preventDefault();
		const ul = summary.querySelector( 'ul' );
		ul.replaceChildren();
		keys.forEach( ( key ) => {
			const field = fieldFor( key );
			const error = errorFor( key );
			field.setAttribute( 'aria-invalid', 'true' );
			if ( error ) {
				error.textContent = messages[ key ];
				error.hidden = false;
			}
			const li = document.createElement( 'li' );
			const link = Object.assign( document.createElement( 'a' ), { href: `#${ 'hidden' === field.type ? 'eda-photos' : field.id }`, textContent: messages[ key ] } );
			link.addEventListener( 'click', ( clickEvent ) => {
				clickEvent.preventDefault();
				( 'hidden' === field.type ? $( '[data-photos-add]', photos ) : field ).focus();
			} );
			li.append( link );
			ul.append( li );
		} );
		summary.hidden = false;
		summary.focus();
	} );
} )();
