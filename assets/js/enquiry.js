/**
 * Enquiry form: fetch a fresh nonce right before submitting, so forms on cached
 * pages keep working. The server still verifies the nonce. If the request fails,
 * the form submits with the nonce already in the HTML (valid while the page is fresh).
 *
 * Static export (data-eda-static, set by eda_static_enquiry_form()): submit as JSON to the
 * form's action (Cloudflare Pages Function, cloudflare/functions/api/form.js) and show the
 * result inline.
 */
const edaShownAt = performance.now();

const edaStaticSubmit = async ( form ) => {
	let messages = {};
	try {
		messages = JSON.parse( form.dataset.edaMessages || '{}' );
	} catch ( error ) {
		// No messages: nothing to show beyond the generic fallback.
	}
	const payload = { t: Math.round( performance.now() - edaShownAt ), page: location.pathname };
	for ( const [ key, value ] of new FormData( form ) ) {
		const field = key.match( /^enquiry\[(\w+)\]$/ );
		if ( field ) {
			payload[ field[ 1 ] ] = String( value );
		}
	}
	let state = 'error';
	try {
		const response = await fetch( form.action, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify( payload ),
		} );
		const json = await response.json();
		if ( [ 'sent', 'demo', 'invalid', 'error' ].includes( json.status ) ) {
			state = json.status;
		}
	} catch ( error ) {
		// Network or invalid response: generic error.
	}
	let status = form.parentNode.querySelector( '.enquiry-status' );
	if ( ! status ) {
		status = document.createElement( 'p' );
		status.className = 'enquiry-status';
		form.before( status );
	}
	status.setAttribute( 'role', 'sent' === state || 'demo' === state ? 'status' : 'alert' );
	status.textContent = messages[ state ] || messages.error || '';
	if ( 'sent' === state || 'demo' === state ) {
		form.reset();
	}
};

document.addEventListener( 'submit', async ( event ) => {
	const form = event.target;
	const isStatic = form.matches( 'form[data-eda-static]' );
	if ( ! isStatic && ! form.matches( 'form[data-nonce-url]' ) ) {
		return;
	}

	event.preventDefault();
	if ( form.dataset.submitting ) {
		return; // Ignore double submits while a request is in flight.
	}
	form.dataset.submitting = '1';

	if ( isStatic ) {
		await edaStaticSubmit( form );
		delete form.dataset.submitting;
		return;
	}

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
