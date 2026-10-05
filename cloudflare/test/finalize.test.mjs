// node cloudflare/test/finalize.test.mjs — checks cloudflare/finalize.mjs on a miniature export.
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { finalize } from '../finalize.mjs';

const dir = mkdtempSync(join(tmpdir(), 'eda-finalize-'));
const put = (path, body) => { mkdirSync(join(dir, path, '..'), { recursive: true }); writeFileSync(join(dir, path), body); };
const png = Buffer.concat([Buffer.from('89504e470d0a1a0a0000000d49484452', 'hex'), Buffer.from([0, 0, 0, 32, 0, 0, 0, 32]), Buffer.alloc(20)]);
const head = (path) => `<link rel="canonical" href="${path}" /><meta property="og:url" content="${path}"><meta property="og:image" content="/wp-content/uploads/a.jpg"><meta property="og:image:width" content="1536"><meta name="twitter:image" content="/wp-content/uploads/a.jpg"><link rel="icon" href="/wp-content/uploads/icon-32x32.png" sizes="32x32" /><a href="/about/">About</a>`;

put('index.html', head('/'));
put('about/index.html', head('/about/'));
put('vehicle/audi-rs6-avant/index.html', head('/vehicle/audi-rs6-avant/'));
put('hidden/index.html', `<meta name='robots' content='noindex, follow' />`);
put('404.html', '<title>Page not found</title>');
put('wp-content/uploads/icon-32x32.png', png);
put('wp-content/plugins/x/readme.html', 'http://localhost:8080/'); // assets are not pages

const r = finalize(dir, 'https://aurelis-motors-demo.pages.dev/');
const about = readFileSync(join(dir, 'about/index.html'), 'utf8');
assert.match(about, /rel="canonical" href="https:\/\/aurelis-motors-demo\.pages\.dev\/about\/"/);
assert.match(about, /og:url" content="https:\/\/aurelis-motors-demo\.pages\.dev\/about\/"/);
assert.match(about, /og:image" content="https:\/\/aurelis-motors-demo\.pages\.dev\/wp-content\/uploads\/a\.jpg"/);
assert.match(about, /twitter:image" content="https:\/\/aurelis-motors-demo\.pages\.dev\/wp-content\/uploads\/a\.jpg"/);
assert.match(about, /og:image:width" content="1536"/, 'other tags untouched');
assert.match(about, /<a href="\/about\/">/, 'links stay relative');
assert.equal(r.rewritten, 12);

const sitemap = readFileSync(join(dir, 'sitemap.xml'), 'utf8');
assert.deepEqual([...sitemap.matchAll(/<loc>([^<]+)/g)].map((m) => m[1]), ['https://aurelis-motors-demo.pages.dev/', 'https://aurelis-motors-demo.pages.dev/about/', 'https://aurelis-motors-demo.pages.dev/vehicle/audi-rs6-avant/'], 'pages only: no 404, noindex or assets');
assert.equal(readFileSync(join(dir, 'robots.txt'), 'utf8'), 'User-agent: *\nAllow: /\n\nSitemap: https://aurelis-motors-demo.pages.dev/sitemap.xml\n');
const ico = readFileSync(join(dir, 'favicon.ico'));
assert.deepEqual([ico.readUInt16LE(2), ico.readUInt16LE(4), ico[6], ico[7], ico.readUInt32LE(18)], [1, 1, 32, 32, 22]);
assert.ok(ico.subarray(22).equals(png), 'ICO wraps the 32 px PNG');
assert.ok(r.has404);

// Domain change: rerunning replaces the previous origin.
finalize(dir, 'https://www.example.com');
assert.match(readFileSync(join(dir, 'about/index.html'), 'utf8'), /href="https:\/\/www\.example\.com\/about\/"/);
assert.doesNotMatch(readFileSync(join(dir, 'about/index.html'), 'utf8'), /pages\.dev/);

// Refusals: development origins, paths, and a development host left in a page.
assert.throws(() => finalize(dir, 'http://elite-auto-dealer.local'), /Not a public origin/);
assert.throws(() => finalize(dir, 'http://localhost:8788'), /Not a public origin/);
assert.throws(() => finalize(dir, 'https://example.com/sub/'), /Not a public origin/);
put('contact/index.html', '<img src="http://elite-auto-dealer.local/x.jpg">');
assert.throws(() => finalize(dir, 'https://www.example.com'), /contact\/index\.html: elite-auto-dealer\.local/);

rmSync(dir, { recursive: true });
console.log('finalize: all checks passed');
