/**
 * Enquiry form: fetch a fresh nonce right before submitting, so forms on cached
 * pages keep working. The server still verifies the nonce. If the request fails,
 * the form submits with the nonce already in the HTML (valid while the page is fresh).
 */
document.addEventListener( 'submit', async ( event ) => {
	const form = event.target;
	if ( ! form.matches( 'form[data-nonce-url]' ) ) {
		return;
	}

	event.preventDefault();
	if ( form.dataset.submitting ) {
		return; // Ignore double submits while the nonce request is in flight.
	}
	form.dataset.submitting = '1';

	try {
		const response = await fetch( form.dataset.nonceUrl, {
			credentials: 'same-origin',
			cache: 'no-store',
		} );
		const json = await response.json();
		if ( json.success ) {
			form.querySelector( '[name="eda_enquiry_nonce"]' ).value =
				json.data.nonce;
		}
	} catch ( error ) {
		// Keep the nonce from the HTML.
	}

	form.submit(); // Native submit: no second submit event.
} );
