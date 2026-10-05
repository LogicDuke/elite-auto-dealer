/* global wp */
/**
 * Media picker: stores attachment IDs as a comma-separated list in a hidden input.
 * Vehicle gallery (multiple) and page header image (data-multiple="false").
 */
document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '.eda-gallery-select' );
	if ( ! button ) {
		return;
	}

	const input = document.getElementById( button.dataset.target );
	const preview = button.previousElementSibling;
	const frame = wp.media( {
		title: button.textContent,
		multiple: 'false' === button.dataset.multiple ? false : 'add',
		library: { type: 'image' },
	} );

	frame.on( 'open', () => {
		const selection = frame.state().get( 'selection' );
		input.value
			.split( ',' )
			.filter( Boolean )
			.forEach( ( id ) => {
				const attachment = wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
	} );

	frame.on( 'select', () => {
		const attachments = frame.state().get( 'selection' ).toJSON();
		input.value = attachments.map( ( a ) => a.id ).join( ',' );
		preview.replaceChildren(
			...attachments.map( ( a ) => {
				const img = document.createElement( 'img' );
				img.src = a.sizes?.thumbnail?.url || a.url;
				img.alt = '';
				img.style.cssText = 'width:80px;height:auto;margin:0 4px 4px 0';
				return img;
			} )
		);
	} );

	frame.open();
} );
