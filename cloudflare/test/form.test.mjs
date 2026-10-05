// node cloudflare/test/form.test.mjs — checks /api/form (cloudflare/functions/api/form.js) without Cloudflare.
import assert from 'node:assert/strict';
import { onRequestPost, onRequest } from '../functions/api/form.js';

const ORIGIN = 'https://demo.example';
let requests = 0;
globalThis.fetch = async () => { requests++; return new Response('{}'); };

const call = async (payload, { env = { FORM_MODE: 'demo' }, origin = ORIGIN, type = 'application/json', raw } = {}) => {
	const request = new Request(`${ORIGIN}/api/form`, { method: 'POST', headers: { 'Content-Type': type, ...(origin ? { Origin: origin } : {}) }, body: raw ?? JSON.stringify(payload) });
	const res = await onRequestPost({ request, env });
	return { code: res.status, ...(await res.json()) };
};
const ok = (extra = {}) => ({ type: 'test_drive', vehicle_id: '42', name: 'Ada', email: 'ada@demo.example', phone: '+32 2 000 00 00', message: 'Hello\nthere', consent: '1', website: '', t: 5000, page: '/vehicle/x/', ...extra });

const DEMO = { code: 200, ok: true, status: 'demo' };
for (const type of ['general', 'test_drive', 'finance', 'trade_in']) assert.deepEqual(await call(ok({ type })), DEMO, `${type} demo`);
assert.deepEqual(await call(ok({ vehicle_id: '0', phone: '', message: '' })), DEMO, 'general enquiry without vehicle or optional fields');

// Never delivers: any other mode refuses.
for (const env of [{}, { FORM_MODE: 'live' }, { FORM_MODE: 'live', FORM_TO: 'x@demo.example', EMAIL_API_KEY: 'k' }]) {
	assert.deepEqual(await call(ok(), { env }), { code: 503, ok: false, status: 'error' }, `refused: ${JSON.stringify(env)}`);
}
// Origin, method, content type, size.
assert.equal((await call(ok(), { origin: 'https://evil.example' })).code, 403);
assert.equal((await call(ok(), { origin: null })).code, 403);
assert.equal((await call(ok(), { env: { FORM_MODE: 'demo', ALLOWED_ORIGINS: 'https://other.example, https://demo.example' } })).code, 200);
assert.equal((await call(null, { type: 'application/x-www-form-urlencoded', raw: 'type=general' })).code, 415);
assert.equal((await call(null, { raw: JSON.stringify(ok({ message: 'x'.repeat(20000) })) })).code, 413);
assert.equal((await onRequest()).status, 405);
// Validation and abuse.
for (const [label, payload] of [
	['unknown type', ok({ type: 'admin' })],
	['missing name', ok({ name: '' })],
	['bad email', ok({ email: 'not-an-email' })],
	['no consent', ok({ consent: '' })],
	['bad vehicle id', ok({ vehicle_id: '1 OR 1' })],
	['unexpected field', ok({ cc: 'x@y.example' })],
	['header injection', ok({ name: 'Ada\nBcc: x@y.example' })],
	['non-string value', ok({ name: { $ne: 1 } })],
	['too long', ok({ name: 'x'.repeat(101) })],
]) assert.deepEqual(await call(payload), { code: 200, ok: false, status: 'invalid' }, label);
for (const [label, payload] of [['too fast (bot)', ok({ t: 400 })], ['no timing (no JS)', ok({ t: undefined })]]) {
	assert.deepEqual(await call(payload), { code: 400, ok: false, status: 'invalid' }, label);
}
assert.equal((await call(null, { raw: '{oops' })).status, 'invalid');
// Honeypot: answer "sent", do nothing.
assert.deepEqual(await call(ok({ website: 'http://spam.example' })), { code: 200, ok: true, status: 'sent' });
assert.equal(requests, 0, 'the endpoint never contacts any other service');

console.log('form endpoint: all checks passed');
