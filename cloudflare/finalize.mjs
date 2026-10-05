/* Elite Auto Dealer — finish a relative Simply Static export for its public host (docs/static-deployment.md).
 * Adapted from Elite Nail Studio.
 *
 *   node cloudflare/finalize.mjs <export-dir> <public-origin>     e.g. https://aurelis-motors-demo.pages.dev
 *
 * Canonical, og:url, og:image and twitter:image need absolute URLs; the export has them root-relative.
 * This puts the origin back (rerun with a new origin when the domain changes), then writes
 * sitemap.xml (every exported page except 404.html and noindex pages), robots.txt and favicon.ico
 * (the 32 px site icon). Fails if a development host (.local, localhost) is left in any page. */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { pathToFileURL } from 'node:url';

export function finalize(dir, originArg) {
	const url = new URL(originArg);
	if (!/^https?:$/.test(url.protocol) || url.pathname !== '/' || url.search || /\.local$|^localhost$/.test(url.hostname)) {
		throw new Error(`Not a public origin: ${originArg}`);
	}
	const origin = url.origin;
	const absolute = /(<(?:link[^>]*\srel=["']canonical["']|meta[^>]*\s(?:property=["']og:(?:url|image)["']|name=["']twitter:image["']))[^>]*?\s(?:href|content)=["'])(?:https?:\/\/[^/"']+)?(\/[^"']*)/g;
	const devHost = /[\w-]+\.local\b|\blocalhost\b/;

	const pages = [];
	const problems = [];
	let rewritten = 0;
	const walk = (d) => readdirSync(d, { withFileTypes: true }).forEach((e) => {
		const file = join(d, e.name);
		if (e.isDirectory()) return ['wp-content', 'wp-includes'].includes(e.name) && d === dir ? undefined : walk(file);
		if (!e.name.endsWith('.html')) return;
		const html = readFileSync(file, 'utf8');
		const out = html.replace(absolute, (_, head, path) => (rewritten++, head + origin + path));
		if (out !== html) writeFileSync(file, out);
		const rel = relative(dir, file).split(sep).join('/');
		const host = out.match(devHost);
		if (host) problems.push(`${rel}: ${host[0]}`);
		if (e.name === 'index.html' && !/<meta[^>]+name=["']robots["'][^>]+noindex/i.test(out)) {
			pages.push('/' + rel.replace(/index\.html$/, ''));
		}
	});
	walk(dir);
	if (problems.length) throw new Error(`Development host in exported pages:\n  ${problems.join('\n  ')}`);

	pages.sort((a, b) => a.split('/').length - b.split('/').length || a.localeCompare(b));
	const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
	writeFileSync(join(dir, 'sitemap.xml'), '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
		+ pages.map((p) => `  <url><loc>${esc(origin + p)}</loc></url>\n`).join('') + '</urlset>\n');
	writeFileSync(join(dir, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${origin}/sitemap.xml\n`);

	// favicon.ico: the 32 px site icon PNG in an ICO container (one PNG entry, supported by every current browser).
	let favicon = false;
	const home = existsSync(join(dir, 'index.html')) ? readFileSync(join(dir, 'index.html'), 'utf8') : '';
	const icon = home.match(/<link[^>]+rel=["']icon["'][^>]+href=["'](?:https?:\/\/[^/"']+)?\/([^"']+\.png)["'][^>]*sizes=["']32x32["']/);
	if (icon && existsSync(join(dir, icon[1]))) {
		const png = readFileSync(join(dir, icon[1]));
		const head = Buffer.alloc(22);
		head.writeUInt16LE(0, 0); head.writeUInt16LE(1, 2); head.writeUInt16LE(1, 4); // ICO, 1 image
		head.writeUInt8(png.readUInt32BE(16) % 256, 6); head.writeUInt8(png.readUInt32BE(20) % 256, 7); // width, height (256 → 0)
		head.writeUInt16LE(1, 10); head.writeUInt16LE(32, 12); head.writeUInt32LE(png.length, 14); head.writeUInt32LE(22, 18);
		writeFileSync(join(dir, 'favicon.ico'), Buffer.concat([head, png]));
		favicon = true;
	}
	return { origin, pages, rewritten, favicon, has404: existsSync(join(dir, '404.html')) };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
	const [dir, origin] = process.argv.slice(2);
	if (!dir || !origin) {
		console.error('Usage: node cloudflare/finalize.mjs <export-dir> <public-origin>');
		process.exit(2);
	}
	try {
		const r = finalize(dir, origin);
		console.log(`${r.origin}: ${r.rewritten} URLs made absolute, ${r.pages.length} pages in sitemap.xml, robots.txt written, favicon.ico ${r.favicon ? 'written' : 'MISSING (no 32 px site icon)'}, 404.html ${r.has404 ? 'present' : 'MISSING (Simply Static: Generate 404 page)'}.`);
		if (!r.favicon || !r.has404) process.exitCode = 1;
	} catch (e) {
		console.error(e.message);
		process.exit(1);
	}
}
