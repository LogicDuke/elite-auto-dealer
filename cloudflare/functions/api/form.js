/**
 * POST /api/form — enquiry endpoint of the static (Cloudflare Pages) showcase of Elite Auto Dealer.
 * Adapted from Elite Nail Studio's proven endpoint; see docs/static-deployment.md.
 *
 * Receives JSON from assets/js/enquiry.js and validates it like eda_validate_enquiry()
 * (inc/enquiries.php; keep the types and limits in sync). Demonstration only: with FORM_MODE=demo a
 * valid enquiry is answered "demo"; nothing is sent, forwarded or stored, in any mode. Any other
 * FORM_MODE refuses every submission (503): delivery is deliberately not implemented, the real
 * enquiry flow is the WordPress theme's.
 *
 *   FORM_MODE        "demo". Required (Pages → Settings → Variables and Secrets).
 *   ALLOWED_ORIGINS  comma-separated origins allowed to post (default: the request's own origin).
 *
 * Responses are always {"ok":bool,"status":"demo|sent|invalid|error"}.
 */

const TYPES = ['general', 'test_drive', 'finance', 'trade_in'];
const FIELDS = ['type', 'vehicle_id', 'name', 'email', 'phone', 'message', 'consent', 'page'];
const MAX = { type: 20, vehicle_id: 10, name: 100, email: 254, phone: 30, message: 2000, consent: 1, page: 300 };
const MAX_BODY = 16 * 1024;
const MIN_FILL_MS = 3000; // Faster than a person can fill in the form.
const EMAIL = /^[^\s@<>()",;:]+@[^\s@<>()",;:]+\.[a-z]{2,}$/i;

const reply = (httpStatus, status, headers = {}) =>
	new Response(JSON.stringify({ ok: status === 'sent' || status === 'demo', status }), {
		status: httpStatus,
		headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff', ...headers },
	});

/** Validated, trimmed fields of one enquiry, or null. */
export function validate(data) {
	if (!data || typeof data !== 'object' || Array.isArray(data)) return null;
	const allowed = new Set([...FIELDS, 'website', 't']);
	if (Object.keys(data).some((key) => !allowed.has(key))) return null;
	const out = {};
	for (const key of FIELDS) {
		const raw = data[key] ?? '';
		if (typeof raw !== 'string') return null;
		const value = raw.trim();
		if (value.length > MAX[key]) return null;
		// Control characters are refused; only the message may contain line breaks.
		if (/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/.test(value) || (key !== 'message' && /[\r\n]/.test(value))) return null;
		out[key] = value;
	}
	if (!TYPES.includes(out.type) || !/^\d*$/.test(out.vehicle_id)) return null;
	if (!out.name || !EMAIL.test(out.email) || out.consent !== '1') return null;
	return out;
}

export async function onRequestPost({ request, env }) {
	const origin = request.headers.get('Origin');
	const allowed = (env.ALLOWED_ORIGINS || new URL(request.url).origin).split(',').map((s) => s.trim());
	if (!origin || !allowed.includes(origin)) return reply(403, 'error');
	if (!(request.headers.get('Content-Type') || '').toLowerCase().startsWith('application/json')) return reply(415, 'error');
	if (Number(request.headers.get('Content-Length') || 0) > MAX_BODY) return reply(413, 'error');

	const raw = await request.text();
	if (raw.length > MAX_BODY) return reply(413, 'error');
	let data;
	try { data = JSON.parse(raw); } catch { return reply(400, 'invalid'); }

	// Honeypot filled in: answer as if sent, do nothing.
	if (data && typeof data.website === 'string' && data.website !== '') return reply(200, 'sent');
	if (!(Number(data && data.t) >= MIN_FILL_MS)) return reply(400, 'invalid');

	// A well-formed request with invalid fields is a normal visitor outcome: 200 with status "invalid"
	// (browsers log every 4xx as a console error). Malformed and abusive requests keep their 4xx.
	if (!validate(data)) return reply(200, 'invalid');

	return env.FORM_MODE === 'demo' ? reply(200, 'demo') : reply(503, 'error');
}

export const onRequest = () => reply(405, 'error', { Allow: 'POST' });
