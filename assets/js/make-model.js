/**
 * Make → Model cascading for every [data-make-model] scope (homepage search, inventory
 * filters, admin vehicle editor). The server already renders the correct models for the
 * selected make (works without JS); this only re-filters the options when the make changes.
 *
 * Contract: select[data-role="make"] + select[data-role="model"] whose data-options is a JSON
 * list of { v: value, l: label, m: make value, g: make label }. The model select's first
 * option ("All models" / "Select model") is kept as the placeholder. With data-require-make
 * (admin), no models are listed until a make is chosen.
 */
( () => {
	document.querySelectorAll( '[data-make-model]' ).forEach( ( scope ) => {
		const make = scope.querySelector( 'select[data-role="make"]' );
		const model = scope.querySelector( 'select[data-role="model"]' );
		if ( ! make || ! model ) {
			return;
		}

		let options;
		try {
			options = JSON.parse( model.dataset.options || '[]' );
		} catch ( error ) {
			return; // Keep the server-rendered options.
		}
		const placeholder = model.options[ 0 ].cloneNode( true );
		placeholder.selected = false;

		const render = () => {
			const previous = model.value;
			let list = make.value
				? options.filter( ( item ) => item.m === make.value )
				: options;
			if ( ! make.value && 'requireMake' in model.dataset ) {
				list = []; // Admin: choose a make first.
			}
			const fragment = document.createDocumentFragment();
			const groups = new Map();

			fragment.append( placeholder.cloneNode( true ) );
			list.forEach( ( item ) => {
				let parent = fragment;
				if ( ! make.value ) {
					// All makes: group models under their make, like the server does.
					if ( ! groups.has( item.g ) ) {
						const group = document.createElement( 'optgroup' );
						group.label = item.g || model.dataset.otherLabel || '—';
						groups.set( item.g, group );
						fragment.append( group );
					}
					parent = groups.get( item.g );
				}
				parent.append( new Option( item.l, item.v ) );
			} );

			model.replaceChildren( fragment );
			// Keep the chosen model only if it still belongs to the selected make.
			model.value = list.some( ( item ) => item.v === previous ) ? previous : '';
		};

		make.addEventListener( 'change', render );
	} );
} )();
