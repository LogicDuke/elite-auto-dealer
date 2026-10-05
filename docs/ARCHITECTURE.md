# Elite Auto Dealer: architecture

Classic PHP theme (no page builder, no block theme, no runtime dependencies).
Content model is registered in the theme; see "Decisions" for the implication.

Related: [PROJECT-RULES.md](PROJECT-RULES.md) (binding build rules) and [REFERENCE-AUDIT-POTENZA.md](REFERENCE-AUDIT-POTENZA.md) (evidence behind them).

## Single-dealer product scope

**Elite Auto Dealer is built for one dealership and one dealership inventory. Multi-dealer functionality is outside the scope of this product.** It is not a marketplace, classifieds platform or multi-dealer platform, and it has no paid listings.

```
WordPress admin (dealership staff)  ->  Dealer inventory (`vehicle` posts)  ->  Website vehicle search  ->  Vehicle detail page  ->  Customer lead (enquiry)
```

- **Every `vehicle` post is stock owned by the one dealership.** Only dealership staff, meaning logged-in WordPress users with an editing role, create, edit, publish, price, photograph, feature, reserve or mark vehicles sold. They do this in WordPress admin.
- **Visitors only read and contact.** They can browse, filter and search, view vehicles, call, WhatsApp, and send an enquiry (general, test drive, finance, or trade-in valuation).
- **No public write path to inventory.**
  - There is no front-end vehicle submission, no seller or dealer accounts, no seller profiles and no listing payments.
  - The `vehicle` post type uses standard post capabilities. Anonymous REST writes are refused (401), and the subscriber role cannot create vehicles.
  - The only public form handler (`admin_post_nopriv_eda_enquiry`) can create nothing but private `eda_enquiry` posts. The only other public endpoint, `wp_ajax_nopriv_eda_enquiry_nonce`, is read-only and returns a nonce.
- **Trade-in is a private enquiry, never a listing.** A customer's own car (e.g. "BMW 320d, 2019, 85 000 km") is sent to the dealership inside the enquiry message and stored only as a private `eda_enquiry`. It never becomes a `vehicle` post or anything public.

## Layout

```
style.css               Theme header only
functions.php           Constants, textdomain, theme supports, menus, image sizes, enqueue, activation
inc/
  vehicle-post-type.php   `vehicle` CPT, URL bases, term archives -> inventory template
  vehicle-taxonomies.php  7 taxonomies, model -> make link, default term seeding
  vehicle-meta.php        Meta schema, registration, sanitising, admin meta box (incl. Make → Model selector), gallery picker enqueue
  vehicle-catalogue.php   Make/Model catalogue seeding + versioned updates (data/vehicle-catalogue.json)
  template-tags.php       Meta access/formatting, price format, phone helpers, breadcrumb trail
  seo.php                 Vehicle + breadcrumb JSON-LD, listing canonical / robots
  inventory-query.php     Inventory page size (12), price_max filter, sort options, shared make/model data + search state
  enquiries.php           Enquiry types, private `eda_enquiry` CPT, validation, storage, email, handler, nonce refresh
  enquiry-privacy.php     Enquiry retention clean-up, personal-data exporter and eraser
  customizer.php          Dealer contact (phone, WhatsApp, enquiry email) + homepage / Vehicles images
  page-header.php         Page header image (meta _eda_header_image + edit-screen box)
template-parts/
  page-intro.php          Shared inner-page header: full-width image hero or plain text header
  vehicle-card.php        Listing card
  vehicle-action-bar.php  Call / WhatsApp / Enquire (sticky on small screens)
  enquiry-form.php        The one reusable enquiry form
  breadcrumbs.php         Visible breadcrumb
  vehicle-search.php      Search/filter GET form (homepage strip + inventory filter bar)
page-templates/contact.php  "Contact" page template: page content + general enquiry form
front-page.php, archive-vehicle.php, single-vehicle.php, page.php, index.php, 404.php, header.php, footer.php
assets/css/main.css     Design system: tokens, layout, header/drawer, cards, inventory, vehicle page, forms
assets/js/admin-vehicle.js  Media picker for the gallery field and the page header image box
assets/js/enquiry.js    Refreshes the enquiry nonce before submit (cache-safe forms)
assets/js/navigation.js Mobile drawer (ESC, focus loop, scroll lock) + clean GET search URLs
assets/js/make-model.js Shared Make → Model cascading (homepage search, inventory filter, admin selector)
assets/js/vehicle-gallery.js  Vehicle page slideshow: arrows, thumbnails, keyboard, swipe (vehicle pages only, progressive enhancement)
data/vehicle-catalogue.json  Default Make → Model family catalogue (49 makes, 428 models), generated
bin/build-vehicle-catalogue.py  Catalogue source + generator (--check verifies the JSON); dev tool, not shipped
languages/              elite-auto-dealer.pot + nl_BE / fr_BE .po/.mo (see "Multilingual")
demo/vehicles.json      Canonical demo inventory (Aurelis Motors, 15 vehicles)
demo/seed.php           Idempotent demo seeder / validator / cleanup (wp-cli)
demo/import-images.php  Idempotent demo image importer / QA gate / cleanup (wp-cli)
demo/import-site-images.php  Website images (homepage hero, inner-page headers): import, assign, cleanup (wp-cli)
demo/images/            Approved demo JPEGs (vehicles + site/), local only (Git-ignored; archived in Drive)
assets/img, assets/fonts   Empty, reserved
tests/smoke-test.php    wp-cli smoke test (data model, sanitising, enquiries, SEO helpers)
tests/demo-test.php     Demo dataset rules, seeded state, idempotency, cleanup safety drill
tests/image-import-test.php  Image QA gate, import, idempotency, checksum replacement, cleanup safety, derivatives
tests/site-image-test.php    Website images: QA gate, import, assignment, cleanup, rendered hero and page headers
tests/catalogue-test.php  Catalogue data, seeding/versioning, sold-only filter rule, admin selector, generator check
```

Prefixes: functions `eda_`, constants `EDA_`, meta `_eda_`, image sizes `eda-`, text domain `elite-auto-dealer`.

## URL structure

| URL | What |
|---|---|
| `/vehicles/` | Inventory archive (`archive-vehicle.php`) |
| `/vehicles/page/2/` | Archive pagination |
| `/vehicle/{slug}/` | Single vehicle (`single-vehicle.php`) |
| `/vehicles/make/{make}/` | Make landing page (e.g. `/vehicles/make/bmw/`), also rendered by `archive-vehicle.php` |
| `/vehicles/model/{model}/` | Model landing page |
| `/vehicles/body/{type}/`, `/vehicles/fuel/{type}/`, `/vehicles/transmission/{type}/`, `/vehicles/condition/{type}/`, `/vehicles/equipment/{item}/` | Other term landing pages |

The bases `vehicle` / `vehicles` come from `eda_url_bases()` and can be changed with the `eda_url_bases` filter (re-save permalinks afterwards). Permalinks are flushed on theme activation. If URLs 404 after code changes, re-save Settings → Permalinks.

## Taxonomies

Rule: **anything finite you filter or facet on is a taxonomy, not meta.** Term queries are indexed, WordPress keeps term counts (free facet counts), and each term gets an SEO landing page.

| Taxonomy | Query var | Admin UI | Vocabulary |
|---|---|---|---|
| `vehicle_make` | `make` | tag-style | Dealer-entered |
| `vehicle_model` | `model` | tag-style | Dealer-entered **model family**, linked to a make |
| `vehicle_body_type` | `body` | checkboxes | Seeded: hatchback, sedan, estate, suv, coupe, convertible, mpv, van, pick-up |
| `vehicle_fuel_type` | `fuel` | checkboxes | Seeded: petrol, diesel, hybrid, plug-in-hybrid, electric, lpg, cng |
| `vehicle_transmission` | `transmission` | checkboxes | Seeded: manual, automatic |
| `vehicle_condition` | `condition` | checkboxes | Seeded: used, new, demo |
| `vehicle_equipment` | `equipment` | tag-style | Dealer-entered (Navigation, Tow bar, …) |

(Seeded values listed by slug.)

### Make / Model / Variant

| Level | Storage | Example | Example |
|---|---|---|---|
| Make | `vehicle_make` taxonomy | BMW | Audi |
| Model (family) | `vehicle_model` taxonomy | M4 | A4 |
| Variant / trim | `variant` meta (text) | Competition xDrive | 40 TFSI S line |

- Make and model are two flat taxonomies, not one hierarchical one. Each model term stores its make's term ID in term meta `eda_make` (selector on the Models admin screen), so filters can cascade (make → its models), and `?make=` / `?model=` stay independent query vars.
- **Trims never go into the Model taxonomy.** Otherwise the model filter fragments ("A4", "A4 Prestige", "A4 40 TFSI"…), as seen in the reference audit. The Models admin screen shows this rule; the variant field has a placeholder example.
- Model slugs are global, so name ambiguous models with the make prefix where needed (e.g. `mazda-3`).
- Variant is shown as a subtitle under the H1 and emitted as `vehicleConfiguration` in JSON-LD. It is not a filter.
- **Catalogue:** a bundled default catalogue (`data/vehicle-catalogue.json`, versioned) pre-fills makes and models on every install. Staff can still add their own.
- **Admin:** the "Vehicle details" box replaces the free-text tag boxes with a Make → Model selector, and the Models screen requires a make.
- **Public search:** shows only makes and models with **available or reserved** stock (sold-only terms are hidden), and models cascade from the selected make, both server-side and with JS.
- **Generator:** the catalogue source is `bin/build-vehicle-catalogue.py`, which generates the JSON; see the version bump procedure in the catalogue doc.

  Full details: [VEHICLE-CATALOGUE.md](VEHICLE-CATALOGUE.md).

**One value per vehicle** (make, model, body, fuel, transmission, condition) is a convention; WordPress allows several. Not enforced yet.

Seeding runs on theme activation and only adds missing terms (checked by slug). Slugs are fixed and language-neutral; names are translated (see "Multilingual").

## Metadata

All fields are defined once in `eda_vehicle_meta_fields()` (`inc/vehicle-meta.php`). That schema drives `register_post_meta()` (REST-exposed, typed, sanitised), the admin meta box, the front-end spec list and structured data. **To add a field, add one array entry.**

Stored as post meta `_eda_{key}` (underscore = hidden from the generic Custom Fields box). Empty input deletes the key; "not set" is never stored as `0` or `''`.

### Field types and sanitising

Sanitising is explicit per type in `eda_sanitize_vehicle_meta_value()`. Invalid input becomes "not set"; it is never silently coerced.

| Type | Accepts | Rejects |
|---|---|---|
| `integer` | Whole numbers ≥ 0 (`"86000"`, `86000`) | `"12.500"`, `"4.5"`, `"-5"`, text. Rejected rather than truncated, so a Belgian thousands separator can't turn 12 500 km into 12 |
| `number` | Decimals ≥ 0, dot **or comma** (`"77.4"`, `"77,4"`), rounded to the field's `decimals` | Negatives, `"1.234,5"`, text |
| `boolean` | Checkbox / REST booleans | — |
| `string` + `options` | Only the listed machine values (enum) | Anything else |
| `string` + `format: date` | Valid `Y-m-d` | Impossible dates |
| `string` + `format: vin` | Uppercased, non-alphanumerics stripped | — |
| `array` | Attachment IDs (array or comma list) | Non-IDs, zero |

### Fields

| Key | Type | Notes |
|---|---|---|
| `stock_id` | string | Dealer's internal reference |
| `availability` | enum | `available` / `reserved` / `sold`. Empty = not stated |
| `featured` | bool | Stored `'1'` or absent |
| `variant` | string | Trim / engine variant. See Make / Model / Variant |
| `price` | int | Whole euros, VAT included for consumers. Empty = "price on request" |
| `finance_monthly` | int | Whole euros per month. Stored, **not shown publicly** (see Design-phase requirements) |
| `vat_regime` | enum | See VAT regime |
| `year` | int | |
| `first_registration` | date | `Y-m-d` |
| `mileage` | int | km |
| `power_kw` | int | |
| `power_hp` | int | Stored separately; not derived |
| `engine_cc` | int | Engine size in cc |
| `battery_kwh` | number (1 decimal) | EV/PHEV usable battery capacity, kWh |
| `ev_range_km` | int | Electric range (WLTP), km |
| `exterior_colour`, `interior_colour` | string | Free text |
| `doors`, `seats` | int | |
| `co2` | int | g/km, WLTP |
| `euro_norm` | enum | `euro-1` … `euro-6d-temp`, `euro-6d`, `euro-6e`, `euro-7` |
| `carpass` | enum | `available` / `pending` / `not_required` |
| `warranty_months` | int | |
| `vin` | string | Uppercased, non-alphanumerics stripped |
| `gallery` | int[] | Attachment IDs, ordered |

### VAT regime

`vat_regime` is an enum stored as a machine value with translated admin/front-end labels:

| Stored value | Label |
|---|---|
| `deductible` | VAT deductible |
| `margin` | Margin scheme (VAT not deductible) |
| *(empty)* | Not stated |

To add a regime (e.g. `exempt`), add one entry to the field's `options`. Storage, REST schema (enum), sanitising and admin select update automatically. The former `vat_deductible` boolean was removed (no data existed).

### EV fields

`battery_kwh` (decimal, 1 place) and `ev_range_km` (integer) apply to electric and plug-in hybrid vehicles. They are not emitted in JSON-LD: schema.org `Car` has no standard battery or electric-range property, and we don't invent one.

### Not meta, by design

- **Description**: post content (editor).
- **Main image**: featured image. `gallery` holds the additional photos.
- **Make, model, body type, fuel, transmission**: taxonomies (single source of truth, no duplication).
- **Equipment**: `vehicle_equipment` taxonomy, so it can be filtered on.

Read values with `eda_vehicle_meta( 'price' )`; display with `eda_format_vehicle_meta_value()` (localised numbers, labels, units). Values come back as strings (WordPress stores meta as text); cast when doing arithmetic.

## Images

All vehicle sizes are hard-cropped **3:2**:

| Size | Dimensions | Typical use |
|---|---|---|
| `eda-vehicle-card` | 640 × 427 | Listing cards, gallery thumbs |
| `eda-vehicle-medium` | 960 × 640 | Cards on large/HiDPI screens, detail on tablets |
| `eda-vehicle-large` | 1536 × 1024 | Detail main image / lightbox (equals the source, so the original file is served) |

Because the ratios match, `wp_get_attachment_image()` / `the_post_thumbnail()` automatically emit all three in one `srcset` (verified in the smoke test). Templates only need to pass a correct `sizes` attribute once layouts exist. WordPress adds `loading="lazy"` and `decoding="async"` automatically; mark the single above-the-fold vehicle image `fetchpriority="high"` / not lazy when the gallery is built. Existing uploads need "Regenerate thumbnails" after size changes.

**Source contract:** vehicle photos are uploaded at exactly **1536 × 1024 px**, 3:2, sRGB JPEG, 180–350 KB (preferred maximum 450 KB). See [VEHICLE-IMAGE-ROADMAP.md](VEHICLE-IMAGE-ROADMAP.md) §3 and `image_contract` in `demo/image-roadmap.json`. No registered vehicle size exceeds the source. WordPress never upscales, so a smaller upload would simply lack the larger derivatives; the import QA rejects such files instead of enlarging them. Byte size and dimensions are checked at import, and the theme ships no compression or optimisation code.

**Homepage hero:** a separate art-directed asset, **not** bound by the vehicle-photo contract. `eda_home_hero_image()` renders a `<picture>` (desktop image plus a portrait-mobile source) from the Customizer "Homepage hero" settings, falling back to the placeholder. Vehicles, About, Finance and Contact share one full-width header hero (`template-parts/page-intro.php`, 260 / 320 / 400–512 px, light text over a subtle overlay, slow image-only zoom that is off for reduced motion); without a header image it is the plain text header. The header image is separate from the body image: pages use page meta `_eda_header_image` ("Header image" box, `inc/page-header.php`), Vehicles the Customizer `eda_inventory_image`. A page's featured image stays in its content. See [WEBSITE-IMAGE-ROADMAP.md](WEBSITE-IMAGE-ROADMAP.md).

## Enquiries

One form component, many contexts. No separate test-drive / finance / trade-in forms.

```
template-parts/enquiry-form.php  --POST-->  admin-post.php?action=eda_enquiry  -->  eda_handle_enquiry()
   args: vehicle_id, type                     nonce, honeypot                     eda_validate_enquiry()  (pure)
                                                                                  eda_store_enquiry()      -> private eda_enquiry post
                                                                                  eda_notify_enquiry()     -> wp_mail to dealer
                                                                                  redirect ?enquiry=sent|error#enquiry
```

- **Usage:** `get_template_part( 'template-parts/enquiry-form', null, array( 'vehicle_id' => get_the_ID(), 'type' => 'test_drive' ) );`. Both args are optional. The visitor can still change the subject.
- **Types** (`eda_enquiry_types()`): `general`, `test_drive`, `finance`, `trade_in` (optional). Adding a type is one array entry. `trade_in` is a private valuation request to this dealership; the customer's car is described in the message and never becomes a vehicle listing (see "Single-dealer product scope").
- **Fields collected:**
  - required: name, email, consent;
  - optional: phone, message;
  - context: vehicle ID, type;
  - timestamp: the post date.

  Nothing else: no date of birth, no address, no income or credit history. Finance and trade-in details go in the free message; a lender or valuation step happens off-site, after contact.
- **Consent:** a required checkbox whose label is `eda_enquiry_consent_text()` plus a link to the WordPress privacy policy page. The exact consent text is stored with each enquiry (`_eda_enquiry_consent_text`).
- **Storage:** private post type `eda_enquiry` (not public, not in REST, can't be created manually), listed under Vehicles → Enquiries with a read-only details box.
  - Meta: `_eda_enquiry_{vehicle_id,type,name,email,phone,message,consent,consent_text,vehicle_label}`.
  - `vehicle_label` is a title + stock ID snapshot, so the enquiry keeps context after the vehicle is deleted.
- **Notification:** to Customizer → Dealer contact → Enquiry email (fallback: admin email), with `Reply-To` set to the customer.
- **Security:** nonce, honeypot field, server-side validation, and no personal data in redirect URLs.
- **Not built yet:** rate limiting / challenge beyond the honeypot (add if spam appears).

### Cache-safe nonce

Vehicle pages may be served from a full-page cache, and a cached page can hold a nonce that has already expired (WordPress nonces live 12–24 h).

- The form carries `data-nonce-url` (`admin-ajax.php?action=eda_enquiry_nonce`).
- `assets/js/enquiry.js` (vanilla, about 30 lines, loaded with `defer` only where the form is rendered) intercepts submit and fetches a fresh nonce (`cache: no-store`; the endpoint sends no-cache headers). It writes the nonce into the hidden field, then submits natively. Double submits are ignored while the request is in flight.
- The endpoint returns only a nonce: no personal data and no state change. It is the only other public handler besides the enquiry handler.
- **The server still verifies the nonce on every submission;** a missing or invalid one returns `?enquiry=error`.
- **Fallback:** without JS, or if the refresh fails, the form submits with the nonce from the HTML. That works whenever the cached page is younger than the nonce lifetime. For heavy caching, keep the page cache TTL under 12 h, or rely on JS (normal for virtually all visitors).
- **What may be cached:** the full vehicle HTML, including the form. **What must not be cached:** `admin-ajax.php` and `admin-post.php` (page caches exclude these by default).

### Retention

- **Default 12 months.** Enquiries whose received date (`post_date_gmt`) is older are permanently deleted.
- **One recurring WP-Cron job:** `eda_purge_expired_enquiries`, daily, scheduled on `init` if missing. It is not one event per enquiry. It deletes in batches of 100 and only touches `eda_enquiry` posts.
- **Configurable:** `add_filter( 'eda_enquiry_retention_months', fn() => 24 );`. Returning `0` disables automatic deletion.
- **Theme switch:** switching away clears the job (`switch_theme`), and enquiries are **not** deleted. While another theme is active, no clean-up runs, and enquiries stay in the database untouched but invisible in admin. Reactivating the theme reschedules the job, and the first run catches up. Moving the enquiry code to a plugin later removes this dependency.
- WP-Cron runs on site traffic. On low-traffic sites, a real system cron calling `wp-cron.php` makes the timing exact.

### Privacy tools (Tools → Export / Erase Personal Data)

Registered as "Vehicle enquiries" with the WordPress exporter and eraser. Lookup is by the stored enquiry email address.

| | Fields |
|---|---|
| **Exported** (per enquiry) | Received date, enquiry type, vehicle reference (title + stock ID snapshot), name, email, phone, message, consent text (if consent was given). Empty fields are omitted |
| **Erased** (anonymised) | Name, email, phone, message, consent flag and consent text are deleted; the name is removed from the admin title ("Test drive – anonymised") |
| **Remains** | Enquiry type, received date, vehicle ID and vehicle reference, and an `_eda_enquiry_anonymised` timestamp. These are non-personal sales statistics (e.g. how many test-drive requests a car received) that can't identify the person once contact details are gone. They are still deleted by the normal retention clean-up |

The eraser reports `items_removed` and never `items_retained`; nothing personal is kept.

## SEO

### Semantic structure (implemented in templates)

- `header.php` provides a skip link and `<main id="main">`. Every template has exactly one H1:

  | Template | H1 |
  |---|---|
  | front page | site name |
  | vehicle | vehicle title |
  | inventory | "Vehicles" / term name |
  | page | page title |
  | `index.php` | blog / archive / search title |
  | 404 | "Page not found" |

- Section headings below the H1 are H2.
- Breadcrumbs: `eda_breadcrumb_trail()` builds one trail, used for the visible `<nav aria-label="Breadcrumb">` (`template-parts/breadcrumbs.php`, current page marked `aria-current="page"`) **and** the BreadcrumbList JSON-LD, so they can't disagree. Vehicle trail: Home › Vehicles › {Make} › {Vehicle}.

### Structured data (`inc/seo.php`)

- **Vehicle pages:** a `Car` built by `eda_vehicle_schema()` from stored data only:
  - name, url, images, description;
  - brand (make), model, `vehicleConfiguration` (variant), body type, fuel, transmission;
  - `itemCondition`: `new` → NewCondition, `used` → UsedCondition, `demo` → UsedCondition (registered and driven). The visible label stays "Demo". No condition stored means none is emitted;
  - model date, first registration, mileage (KMT), colours, doors, seats, VIN, `sku` (stock ID), CO2, Euro norm;
  - engine power (KWT) and displacement (CMQ).
- **Offer:** only when a price exists (EUR). `availability` only when explicitly `available` (InStock) or `sold` (SoldOut); reserved or not-stated emits none.
- **Never emitted:** ratings, reviews, finance terms, invented availability.
- **BreadcrumbList:** on vehicle pages and vehicle listings.
- **Encoding:** `JSON_HEX_TAG` so data can't break out of the script element.

### Inventory headings

Two cases are kept strictly apart (`eda_inventory_heading()`):

| Request | H1 / `<title>` | Breadcrumb | Indexing |
|---|---|---|---|
| Real term archive, e.g. `/vehicles/make/bmw/`, `/vehicles/fuel/electric/` | "{Term} vehicles" (translatable pattern; nl_BE "Aanbod %s", fr_BE "Véhicules : %s") | Home › Vehicles › {Term} | index, self-canonical |
| Query-string filter state, e.g. `/vehicles/?fuel=petrol`, `/vehicles/?make=bmw&price_max=30000`, or a term archive with extra filter params | Neutral "Vehicles" | Home › Vehicles | noindex, follow |

Headings are never built from arbitrary filters. Selected filters will be shown as chips or a result summary. (WordPress reports `is_tax()` for `/vehicles/?fuel=petrol` too; `eda_is_vehicle_term_landing()` makes the distinction.)

### Canonical / robots strategy

| URL | robots | canonical |
|---|---|---|
| `/vehicle/{slug}/` | index | self (WordPress core) |
| `/vehicles/`, `/vehicles/page/N/` | index | self, parameters stripped |
| `/vehicles/make/bmw/` (and other term archives, paged) | index | self, parameters stripped |
| Listing + tracking params only (`?utm_source=…`) | index | clean URL |
| Term archive with no vehicles (e.g. catalogue make `/vehicles/make/abarth/`) | **noindex, follow** | none |
| Listing + any filter/sort param (`make`, `model`, `body`, `fuel`, `transmission`, `condition`, `equipment`, `price_min`, `price_max`, `year_min`, `year_max`, `km_max`, `sort`) | **noindex, follow** | none |
| Search (`?s=`) | noindex (WordPress core) | — |

Reasons:

- Term archives are the intended indexable landing pages ("used BMW").
- Filter combinations are effectively unlimited, so they are kept out of the index while links to vehicles are still followed.
- Filtered views get no canonical because pairing noindex with a canonical to another URL sends mixed signals.
- The filter parameter list is `eda_vehicle_filter_params()`; new filter params must be added there.

If an SEO plugin is installed later, disable the overlapping output in `inc/seo.php` (canonical / JSON-LD) to avoid duplicates.

## Vehicle action bar

`template-parts/vehicle-action-bar.php`, included right after the price on vehicle pages:

- **Semantics:** `<nav aria-label="Vehicle actions">` with real links.
  - Call: `tel:`.
  - WhatsApp: `https://wa.me/{number}?text=…`, with a pre-filled, translatable message naming the vehicle and URL.
  - Enquire: `#enquiry`, the form on the same page.
- **Conditional:** not rendered for sold vehicles. Call and WhatsApp appear only when the numbers are set in Customizer → Dealer contact.
- **Layout:**
  - Below 1024 px, CSS pins it to the bottom of the viewport, with safe-area padding, a 48 px minimum target height, and `scroll-padding-bottom` so focused fields aren't hidden behind it.
  - On larger screens it stays inline.
  - It is early in the source, hence early in keyboard order.
- No JS is involved.

## Multilingual readiness

- **Translation-ready strings.** Every UI/admin string goes through gettext with the `elite-auto-dealer` text domain, including taxonomy labels, enum labels, units (`_x( 'hp', 'unit' )` → NL "pk", FR "ch") and the price format (`_x( '€ %s', 'price format' )` → FR "%s €").
- **Translation files.** `load_theme_textdomain()` loads `languages/`. The template is `languages/elite-auto-dealer.pot`; regenerate it with:

  ```sh
  wp i18n make-pot . languages/elite-auto-dealer.pot --domain=elite-auto-dealer --exclude=tests,docs
  ```

- **Shipped translations:** `languages/nl_BE.po/.mo` (Belgian Dutch) and `languages/fr_BE.po/.mo` (Belgian French) cover all strings, with the formal "u"/"vous" register and Belgian automotive terms:

  | English | nl_BE | fr_BE |
  |---|---|---|
  | Used | Tweedehands | Occasion |
  | Manual / Automatic | Manueel / Automaat | Manuelle / Automatique |
  | Estate | Break | Break |
  | MPV | Monovolume | Monospace |
  | hp | pk | ch |
  | Margin scheme | Margeregeling | Régime de la marge |
  | Trade-in | Inruil | Reprise |
  | Price format | € %s | %s € |

  Brand names, machine values and slugs are not translated.
- **After string changes:** regenerate the POT, update both `.po` files, then run `wp i18n make-mo languages`.
- **Core language packs required for number formats.** Thousands and decimal separators (`24.950`, `77,4`) come from WordPress core's locale data, so the site needs the core `nl_BE` / `fr_BE` language packs (Settings → General → Site Language installs them). Without them, numbers fall back to `24,950` while theme strings are still translated.
- **Existing term names are database content.** Terms seeded while the site language was English (e.g. "Electric") keep that name until edited or translated by a multilingual plugin.
- **Numbers, prices and dates** use `number_format_i18n()` / `date_i18n()`, so they follow the site locale.
- **Seeded terms** have fixed English slugs and translated names (the activation locale decides the names). Code, filters and URLs depend only on slugs.
- **URLs:** the bases `vehicle` / `vehicles` and the taxonomy bases are language-neutral defaults behind `eda_url_bases`. No language is assumed in URLs; the permalink system was not redesigned.
- **Later, multilingual plugin (WPML/Polylang):**
  - vehicles and the 7 taxonomies are registered as translatable content;
  - term **names** are translated per language;
  - decide then whether slugs and bases are translated per language (affects SEO URLs) or stay shared;
  - add `hreflang` via the plugin;
  - enum labels and units come from the theme's `.po` files, not the database.

## Demo inventory

Demo content only, replaceable without code changes. The full vehicle table is in [DEMO-INVENTORY.md](DEMO-INVENTORY.md).

- **Identity:** **Aurelis Motors**, a fictional independent dealership in Brussels: "Premium pre-owned automobiles". It is stored in the `dealership` block of `demo/vehicles.json`. The seeder does **not** change site options (title, tagline, Customizer contact details).
- **Source:** `demo/vehicles.json` is the single source of truth: 15 vehicles plus the make, model and equipment vocabularies.
  - Values are machine values: schema meta keys, enum values, term slugs. Display labels come from the theme and translations.
  - Make, model and equipment names are the stored term names; brand names are not translated.
- **Commands** (from the WordPress root, theme active):

  ```sh
  wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php            # import / update
  wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php validate   # validate the JSON only
  wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php cleanup    # remove the demo (vehicles + images)
  wp eval-file wp-content/themes/elite-auto-dealer/tests/demo-test.php      # verify (leaves the demo seeded)
  ```

- **Validation first:** the JSON is checked against the theme schema and data rules before anything is written; any error aborts with nothing changed. Rules: 15 vehicles, unique stock IDs and VINs, known enum and term values, model ↔ make links, values that survive sanitising unchanged, EV fields only on EV/PHEV, no engine size, Euro norm or CO2 for EVs, kW ↔ hp consistency, year = first registration year, no "new" condition.
- **Featured order:** each featured record has `featured_order` (1–6), which the seeder writes to the vehicle's `menu_order`. Validation requires it on featured vehicles only, and the values must be unique.
- **Identity and idempotency:**
  - Vehicles are matched by **stock ID** (`AUR-26001` … `AUR-26015`) **and** the demo marker `_eda_demo = aurelis-demo`.
  - A match is updated in place; a missing one is created. Running twice gives 15 created, then 0 created / 15 updated.
  - Every schema field except `gallery` is synced, so removing a value from the JSON removes it from the vehicle.
  - Meta is written through `eda_sanitize_vehicle_meta_value()` and the registered sanitize callbacks; taxonomies are set by term ID.
- **Real vehicles are never touched:** if a non-demo vehicle uses a demo stock ID, that record is skipped with a warning.
- **Terms:**
  - Missing makes, models (with their `eda_make` link) and equipment terms are created and marked `_eda_demo`.
  - Existing terms are reused, never renamed.
  - Base vocabularies (fuel, body, transmission, condition) must already exist; they are seeded on theme activation.
- **Cleanup** (`cleanup` mode) is conservative:
  - It deletes only vehicles carrying the demo marker.
  - It deletes make, model and equipment terms only if the seeder created them **and** no vehicle uses them any more.
  - It never deletes base vocabulary terms, terms that existed before seeding, or terms used by real vehicles. Enquiries are not deleted; they follow the retention policy.
- **Images:** 75 approved photos (15 × hero/rear/cockpit/interior/detail, exactly 1536 × 1024, 3:2, sRGB JPEG) are imported by `demo/import-images.php` from the Git-ignored `demo/images/`. The hero becomes the featured image; the other four form `_eda_gallery`. Identity, idempotency, the QA gate and cleanup are in [VEHICLE-IMAGE-ROADMAP.md](VEHICLE-IMAGE-ROADMAP.md) §7; the manifest is `demo/image-roadmap.json` (`php tests/image-roadmap-test.php`). The seeder never touches `_thumbnail_id` or `_eda_gallery`, so reseeding keeps the images.
- **Site identity on the demo site:** the title "Aurelis Motors" and tagline "Premium pre-owned automobiles" were set as normal WordPress options (Settings → General) on the local demo install only. They are never hard-coded in the theme or set by the seeder. Phone, WhatsApp and enquiry email stay empty until safe fictional contact data is approved.
- **Term language:** make, model and equipment term names are English for now. Translating database terms is deferred; the theme UI is NL/FR-ready.
- **Packaging:** `demo/` stays in the development repository. Showcase/demo builds may include the demo tooling. Production/client release packages may exclude `demo/` (seeder and dataset) when a clean install is required; the theme does not depend on it at runtime. Packaging rules are not changed yet.

## Future inventory filtering

Planned approach, not built yet:

1. **One form, GET parameters, on `/vehicles/`.** Taxonomy query vars already work natively: `/vehicles/?make=bmw&fuel=diesel` filters with no extra code. Multiple values: `?fuel=diesel,electric` (OR).
2. **Range filters via `pre_get_posts`** on the main vehicle archive query, reading `price_min`, `price_max`, `year_min`, `year_max`, `km_max`, and turning them into a `meta_query` with `'type' => 'NUMERIC'`. Register them with the `query_vars` filter. Avoid `year` as a parameter name: it is reserved by WordPress.
3. **Sorting** via `?sort=price_asc|price_desc|year_desc|km_asc|newest`, mapped to `meta_key` + `orderby => meta_value_num`.
4. **Sold vehicles**: decide whether they stay listed (social proof) or are excluded with a `meta_query` on `_eda_availability != sold`. The decision belongs in that same `pre_get_posts` hook.
5. **Cascading make → model**: read `eda_make` term meta to build the model dropdown; a small JS file can reload options, or the REST API (`/wp/v2/vehicle_model?...`) can serve them.
6. **Facet counts**: taxonomy term counts are global. For single-dealer inventory sizes, compute available terms from the current result set's IDs (one extra query) rather than an AJAX call per change.
7. Keep filtering server-rendered first (works without JS, crawlable), then enhance with JS (fetch the same URL, swap the results region, keep the URL in sync) if wanted. The canonical/robots rules above already cover these URLs.

## Decisions that affect later work

- **Content model lives in the theme.** Switching themes hides vehicles and enquiries. If clients may change themes, move `inc/vehicle-*.php`, `inc/enquiries.php` and the JSON-LD part of `inc/seo.php` to a plugin (or mu-plugin) unchanged.
- **Taxonomy for filterable attributes, meta for numbers and per-vehicle facts.** Changing a field from one to the other later needs a data migration.
- **`availability` enum instead of separate reserved/sold flags**, so a car can't be both.
- **`vat_regime` enum instead of a VAT boolean**, extensible without a storage change.
- **Prices are whole euros (integers).** Cents are not supported.
- **Finance example**: Belgian consumer-credit rules require a representative example (APR, term, total payable) whenever a monthly amount is advertised. `finance_monthly` is therefore stored but not displayed (see Design-phase requirements).

- **Prefix `eda` / meta prefix `_eda_`.** WPCS flags 3-letter prefixes as collision-prone; kept for brevity and excluded in `phpcs.xml.dist`. Renaming later means migrating stored meta keys, so change it now or never.
- **Classic theme with the block editor for vehicle descriptions.** Vehicle details use a classic meta box, which still works in the block editor.
- **Enquiry form nonce is refreshed on submit** (see "Cache-safe nonce"), so vehicle pages can be fully page-cached.
- **Enquiry retention is 12 months by default** and runs only while this theme is active (see "Retention").

## Design-phase requirements

Approved behaviour the visual design must implement:

1. **Monthly finance amounts are hidden publicly.**
   - `finance_monthly` stays in the data (admin and demo records) but is not output on cards, vehicle pages or in structured data.
   - Public display may only be enabled together with a compliant representative credit example: lender, APR, term, total amount payable and the other legally required information.
2. **Sold vehicles** (implemented in the first visual shell):
   - they stay published and listed, and their URLs keep working; the price stays in the database and in structured data;
   - the price slot shows **"Sold"**, with a smaller **"Last asking price € …"** line underneath (`eda_vehicle_last_asking_price()`), on cards and on the vehicle page;
   - a SOLD badge is shown on the image; the photograph remains full strength with no greyscale or dimming;
   - no action bar, no enquiry form and no purchase CTA; a notice links back to the available collection;
   - a monthly finance amount is never shown.
3. **Inventory order:** listings group vehicles as **available (and not stated) → reserved → sold**, and the visitor's sort (newest, price, mileage, year) applies inside each group (`eda_status_order` + `eda_inventory_status_order()`). A sold car's stored price can therefore never put it first under "price: low to high".
4. **Featured order (homepage):** a fixed, curated merchandising order taken from the vehicle's native **Order** attribute (`menu_order`, editable under the editor's page attributes; lower first). Status never reorders the featured row, but reserved and sold badges still show.
5. **Sold card image:** shown as is, with no greyscale or dimming. The SOLD badge and the "Sold" price treatment communicate the state.
6. **"Clear filters"** appears only when a real filter is active (`eda_request_has_filters()`). A sort on its own is not a filter. Sorted-only views remain `noindex` like any parameter view.
