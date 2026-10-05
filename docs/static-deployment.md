# Static demo deployment (Simply Static → Cloudflare Pages)

The public Aurelis Motors demo is a static export of the WordPress site, served by Cloudflare Pages.
The process is the one proven on Elite Nail Studio (finalizer, form Function, export filter), adapted.
WordPress itself is unchanged: the theme's enquiry flow (`admin-post.php`, stored enquiries, optional
email) is what a dealership installs.

## Forms

`admin-post.php` and `admin-ajax.php` do not exist on a static host. During a Simply Static export,
`eda_static_enquiry_form()` (`inc/enquiries.php`) rewrites the enquiry form to
`action="/api/form" data-eda-static`, adds its status messages and removes the WordPress-only fields
(admin-post action, nonce, referer, nonce refresh URL). `assets/js/enquiry.js` submits those forms as
JSON to the Pages Function `cloudflare/functions/api/form.js`, which:

* checks origin, JSON content type, 16 KB body limit, honeypot, a 3 s minimum fill time and the
  field allowlist and limits of `eda_validate_enquiry()` (keep the two in sync);
* with `FORM_MODE=demo` answers `demo`: the visitor sees that this is a demonstration and that the
  enquiry was not sent or stored. **Nothing is sent, forwarded or stored in any mode**;
* with any other or no `FORM_MODE` refuses every submission (503). Delivery is deliberately not
  implemented; add it only with an approved provider.

## Vehicle filters

WordPress filters and sorts the listing on the server (`inc/inventory-query.php`); a static host
ignores the query string. The export marks listings with `data-eda-static-inventory`
(`eda_static_inventory()`), and `assets/js/inventory-static.js` then, for a URL with filters
(`/vehicles/?make=bmw&fuel=electric&price_max=50000&sort=price_asc`), loads every page of the
exported listing and filters and sorts its cards (`data-make`, `data-model`, `data-fuel`,
`data-price`, `data-mileage`, `data-year`, `data-status` on each card) exactly like the server:
model of another make ignored, no price = excluded by a price ceiling, available → reserved → sold
first, ties in the listing's newest-first order. The inventory form updates the URL without a
reload (Back/Forward work); the homepage search lands on the same URLs. Without JS the unfiltered
listing shows. Unlike WordPress, filtered static URLs cannot be `noindex`; their canonical is
`/vehicles/`. The listing's pagination URLs must all be exported (Additional URLs below).

## Simply Static (3.8.16)

Replacing URLs: Relative Path, path `/` · Force URL replacements on · Enhanced Crawl on · Generate
404 page on · Delivery: Local Directory (`Desktop/elite-auto-dealer-static`) · forms, search and
minify off · Settings → Reading → "Discourage search engines" off (otherwise every page is noindex).

Excluded URLs (demo hygiene): `/category`, `/hello-world`, `/sample-page`, `/author` (it reveals the
admin username), the theme's `bin`, `data`, `demo` and `cloudflare` folders, the admin-only
`admin-vehicle*` assets and `/wp-content/plugins/simply-static`. Additional URLs: every listing page
beyond the first (`/vehicles/page/2/`, `/vehicles/<taxonomy>/<term>/page/2/` …), which the crawler
misses; list them from WordPress (12 vehicles per page).

## Export, finalize, deploy

```
# 1. Fresh export (Simply Static → Generate) into an empty directory.
# 2. Absolute canonical/OG URLs, sitemap.xml, robots.txt, favicon.ico; fails on any .local/localhost page.
node cloudflare/finalize.mjs <export-dir> https://aurelis-motors-demo.pages.dev
# 3. Deploy from cloudflare/, so functions/ is uploaded with the export.
cd cloudflare
npx wrangler pages deploy <export-dir> --project-name aurelis-motors-demo
```

Pages → Settings → Variables and Secrets: `FORM_MODE=demo` (Production and Preview). Recommended:
a WAF rate-limiting rule for `POST /api/form`. Local check with the Function:
`npx wrangler pages dev <export-dir> --binding FORM_MODE=demo` from `cloudflare/`.

## Tests

`node cloudflare/test/form.test.mjs` · `node cloudflare/test/finalize.test.mjs` · the export filter
is covered by `tests/smoke-test.php`.
