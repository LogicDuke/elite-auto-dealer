# Reference audit: Potenza "Car Dealer – Classic (Elementor)" demo

Reference: https://cardealer.potenzaglobalsolutions.com/classic-el/
Audited: 2026-10-02, Chrome DevTools (DOM/CSS inspection, Network, console, Lighthouse).
Scope: functional and UX reference only. No source code, assets, copy or styling was copied; observations below are structural and measured.

**Pages analysed**

- Homepage `/classic-el/`
- Inventory `/classic-el/cars/` (unfiltered, filtered via home search, filtered via sidebar, empty result)
- Single vehicle (an Audi A7 demo listing)

**Viewports tested**

| Width | Mode |
|---|---|
| 1440 | Desktop |
| 1024 | Desktop, narrow |
| 768 | Touch tablet, desktop UA |
| 390 | Phone; desktop UA and iPhone UA |
| 412 | Lighthouse mobile run |

Sizes quoted for page weight are **decoded (uncompressed)** sizes from the Resource Timing API; transfer sizes were not reliably measurable (cached responses report 0 bytes).

---

## 1. Executive summary

The reference is a feature-rich, demo-everything theme: Elementor, Slider Revolution, WooCommerce, jQuery UI, select2, two carousel libraries, two lightbox libraries, inline modals for five different lead forms, Google Maps on the homepage, and marketplace features (front-end "Add car", dealer login, cart).

**What it gets right** (worth matching):

- The inventory URL model: plain GET params, crawlable `/page/N/` pagination, shareable filtered URLs.
- Faceted dropdowns that narrow to valid combinations.
- A clear spec table on the vehicle page.
- A capable lightbox (keyboard, fullscreen, zoom, counter).
- Active-filter chips with a result count.

**Where it is weak** (our opportunity):

- **Weight.** About 113–137 requests per page, 47–51 JS files, 32–34 CSS files, 36 render-blocking resources, about 5.3–6.6 MB decoded.
- **Breakpoint logic.** The mobile header markup is chosen server-side by user agent, so a desktop-UA tablet at 768 px gets **no navigation at all**. Sticky behaviour is keyed to `screen.width`, not the viewport.
- **Accessibility.** Hover-only dropdowns, a `<div>` hamburger, unlabeled selects, 43 unnamed links, no `<main>`, no skip link, no focus outlines.
- **SEO.** The homepage has no H1 and no meta description. There is no vehicle JSON-LD. Filtered URLs have no canonical.
- **Mobile UX.** A 109 px topbar wall, a hero scaled down to 7–8 px text, an 837 px-tall stacked search form, no sticky CTA on the vehicle page, and soft card images.
- **EU fit.** US units and data (mpg, `$`, US-style credit application asking date of birth). Facebook Pixel, GTM and GA fire with **no consent banner**. Google Fonts are loaded from Google's CDN.

**Conclusion.** Our foundation's data model and URL plan are already closer to "right" than the reference. The audit mostly confirms the architecture and adds a short list of gaps (see §19–20).

---

## 2. Homepage architecture

Top-to-bottom section order at 1440 px (heights approximate):

| # | Section | Height |
|---|---|---|
| 1 | Header (absolute, transparent, over hero) | 137 |
| 2 | Hero slider, 2 slides | 909 |
| 3 | Inventory search panel, overlapping the hero bottom by ~100 px | 236 |
| 4 | Intro / welcome block + 4 USP icons | 498 |
| 5 | Phone-number call-out band | 330 |
| 6 | Featured vehicles carousel (4 visible, autoplay) | 687 |
| 7 | Single-model promo band | 390 |
| 8 | Latest news (blog) | 587 |
| 9 | Promo video band + 4 counters (stock, reviews, customers, awards) | 444 + 398 |
| 10 | Logo/brand strip | 184 |
| 11 | Testimonials carousel | 460 |
| 12 | Footer: social bar, 4 columns (about/contact, links, recent posts, newsletter), 2 CTA boxes (buy / sell), legal row | — |

Built entirely from Elementor sections. Document height is about 5,900 px at 1440 and about 7,800 px at 390.

## 3. Header / navigation

**Desktop (1440)**

- **Two tiers.** A topbar (43 px) holds hours, email, phone, 8 social icons and a login link. The main bar (~94 px) holds the logo (160×28 px) and the menu. Total height is 137 px.
- **Position.** The header is `position:absolute` over the hero, transparent with light text.
- **Menu items.** 7 text items with dropdown chevrons, then cart (with count badge), search icon and a solid primary-colour CTA button at the far right.
- **Link style.** Uppercase, 13 px, horizontal padding about 13 px, colour transition 0.5 s ease-out.
- **Dropdowns.** 200 px wide and multi-level (one has 52 entries). They are revealed by `visibility`/`opacity` fade on **hover only**.
  - Keyboard focus does not open them: after focus the submenu stays `visibility:hidden`.
  - There is no `aria-haspopup` / `aria-expanded`.
  - Hover state is a light grey row background plus the accent colour.
- **Sticky behaviour.** After about 250 px of scroll, JS adds a class that makes the main bar `position:fixed`. It turns white, 68 px tall, and the logo swaps to a dark variant. The topbar scrolls away.
  - The scroll listener compares against `screen.width` (the physical screen), not the viewport.

**1024**

- The topbar wraps to two lines (65 px), so the header is 159 px.
- The full desktop menu is still shown and is visibly cramped. There is no intermediate pattern.

**768 (touch tablet, desktop UA)**

- The desktop menu is hidden by CSS, but **no mobile trigger exists in the markup**, so navigation is unreachable.
- With a mobile UA the server sends a different header containing the trigger. This is server-side UA detection, which:
  - fails for iPadOS Safari (desktop UA);
  - fails for narrow desktop windows;
  - conflicts with full-page caching.

**390 (iPhone UA)**

- The topbar stacks to **109 px** (hours, email, phone, 8 icons, login). The header totals about 171–189 px, about 20 % of the first screen.
- The bar shows logo, search icon, cart icon and hamburger.
- **Hamburger.** A 35×50 px `<div>`, `tabindex=-1`, no ARIA. It is not keyboard operable.
- **Menu.** An inline **push-down panel**, not an off-canvas drawer.
  - `max-height: 400px` with internal scroll.
  - 8 rows, each about 39 px tall, plus a full-width CTA button.
  - Submenus open as accordions via chevrons.
  - No body scroll lock, no overlay, and no focus management.

## 4. Hero

- **Technology.** Slider Revolution 6.7: 2 slides, arrows, no video, no dots.
- **Composition.** A centred two-line headline (small kicker plus large display line) over a composited road scene with cut-out cars. A dark gradient overlay keeps the text readable.
- **Heights.**

| Viewport | Hero height |
|---|---|
| 1440 | 909 px (taller than the 900 px viewport) |
| 1024 | 643 px |
| 768 | 489 px |
| 390 | 248 px |

- **Scaling.** The slider scales its whole canvas proportionally rather than reflowing. At 390 the hero text renders at **7–8 px** (kicker) and 17 px (headline), which is illegible. There is no art-directed mobile crop.
- **Search overlap.** At desktop the search panel overlaps the bottom of the hero (a common, effective pattern). On mobile it sits below with a gap.
- **Calls to action.** The only hero CTA is a small text link. The real primary action (search) sits below the fold on mobile.

## 5. Vehicle search (homepage widget)

- **Fields.** Year, Make, Model, Mileage (bucketed "≤ 10 000", "≤ 20 000"…), Transmission and Condition, plus a price range slider (jQuery UI, two handles) and a Search button.
- **Layout.**
  - 1440 / 1024 / 768: a 3 × 2 grid of selects plus a price/button column.
  - 390: a single column, **837 px tall**.
- **Markup.** Not a `<form>`.
  - The selects have no `name` and no associated `<label>` (the visual labels are plain text).
  - The submit is `<a href="javascript:void(0)">`.
  - Enter does not submit, and there is no no-JS fallback.
  - The selects are replaced with select2.
- **Dependencies.** Changing **any** field fires one admin-ajax request (~750 ms, with a spinner overlay). It narrows **every other** dropdown to combinations that exist:
  - choosing a make cut Model from 44 options to 8;
  - it cut Year from 15 to 3.
  
  This is true faceting, not just Make→Model.
- **Vocabulary problems** (visible in the options):
  - Model mixes families and trims ("A4", "A4 Prestige", "A7 3.0T Prestige quattro").
  - Transmission is free text ("6-Speed Automatic with Auto-Shift", "8-Speed Automatic"…), which makes it nearly useless as a filter.
- **Submit.** A full page navigation to the inventory with GET params, e.g. `?car_make=…&min_price=…&max_price=…`. Price bounds are always appended even when untouched. Search does not run in the background.
- **Reset.** Not available on the homepage widget.

## 6. Inventory page

**Layout at 1440**

- A 290 px banner with the H1 and a breadcrumb.
- Left sidebar (3/12):
  - keyword search with autocomplete;
  - a price slider with its own Filter button;
  - a filter box with result count, removable active-filter chips, 11 dropdown filters (year, make, model, body, condition, mileage, transmission, drivetrain, engine, fuel economy, colour) and Reset;
  - a loan calculator widget.
- Toolbar:
  - per-page select (12–60);
  - sort (default / name / price / date / year) with a direction toggle;
  - three view modes (grid, masonry, list).

**Behaviour**

- **Filter updates.** Sidebar changes fire admin-ajax (~840 ms) and replace the result list in place.
- **URL state.** The URL is rewritten with the full state: filters, per-page, order, layout, plus two internal tracking params. Results are shareable and survive reload.
- **Pagination.** Numbered links with crawlable `/cars/page/N/` URLs. No load-more or infinite scroll.
- **Grid.**
  - 1440: 3 columns (cards about 263 px wide).
  - 1024: 3 columns.
  - 390: 1 column (360 px).
- **Mobile filters.**
  - The sidebar becomes a 320 px left off-canvas panel (0.3 s ease-out), opened by a "show sidebar" toggle above the results.
  - The toggle is a `<div>` (not keyboard operable, no ARIA).
  - The first card starts at y ≈ 579 px, below a long header and banner.
- **Empty state.** A single sentence and "0" in the count. No suggestions, no "clear filters" shortcut beside the message, and no alternative vehicles.
- **Card density.** Low: 12 per page, 3 per row, large image, little data (see §7).

## 7. Vehicle cards

- **Image.** The demo serves a 265×190 crop (≈ 1.39:1) with **no srcset**.
  - On a 3× phone a 430 px source fills a 360 CSS-px slot, so images look soft.
  - `alt=""` on every card.
  - The first card image carries `fetchpriority="high"`.
- **Height.** Fixed card heights are set inline by JS (equal-height script).
- **Overlay on the image bottom.** A dark strip with year, transmission (truncated to "6-Spe…") and fuel economy (a bare number with no unit).
- **Hover.** A fade-in overlay (0.5 s ease-out) with 3 icon-only links: view, compare, quick-gallery. None has an accessible name.
- **Body.** Centred title link, decorative separator, old price struck through plus current price, an optional tax badge, and NEW/USED corner ribbons.
- **Missing:** mileage, fuel type as a word, first registration, power, monthly price, reserved/sold state and a CTA button.

## 8. Single vehicle page

Layout at 1440 is two columns (8/4).

**Left column**

- **Gallery.** Slick carousel, main image 750×458 (source 876×535, ≈ 1.64:1), 5 visible thumbnails (142×103) in a synced nav carousel, and a "vehicle video" pill over the image.
- **Action row.** Four outline buttons open **modal forms**: make an offer, schedule test drive, email to a friend, finance application.
- **Secondary row.** Add to compare, print, share (Facebook, X, LinkedIn, Pinterest, WhatsApp).
- **Tabs** (`role="tab"`):
  - Overview (free text);
  - Features & Options (checklist);
  - Technical Specifications (free-form rich text; on the demo listing it describes a different car, which shows the risk of unstructured specs);
  - General Information;
  - Location (embedded map).

**Right column**

- H1 title.
- Meta line (year • make • model).
- Short excerpt.
- Price: current price large in the accent colour, old price struck through.
- "Request more info" button.
- Spec table: year, make, model, body, mileage, transmission, drivetrain, engine, fuel, fuel economy, trim, colours, stock no., VIN.
- A city/highway fuel-economy box.

**Below the columns**

- Related vehicles carousel: 6 items, 4 visible.

**Not present**

- Sticky sidebar or sticky price/CTA (desktop or mobile).
- Breadcrumb.
- Finance example on the page itself.
- Dealer card or opening hours.
- Call button near the price.

**Mobile (390)**

- Order: gallery (360×220) → H1 (y 514) → price (y 702, 14 px base) → "request more info" (y 775, just above the fold) → description → the remaining CTAs at y ≈ 1650–1810.
- The tabs stack into 5 full-width rows.
- No fixed or sticky CTA bar. A WhatsApp link exists only inside share.

**Forms**

- All 7 modals are rendered into the HTML on every vehicle page (2,334 DOM nodes).
- The finance application has **61 inputs**, US-style (full address, state, ZIP, date of birth…).
- Several forms have a consent checkbox. None uses CAPTCHA.

## 9. Responsive findings

| | 1440 | 1024 | 768 | 390 |
|---|---|---|---|---|
| Container | 1170 | ~980 | full width − 30 | full width − 30 (15 px padding) |
| Header height | 137 | 159 | 127 (no nav, desktop UA) | 171–189 |
| Nav pattern | full menu | full menu (cramped) | **none** (desktop UA) / panel (mobile UA) | push-down panel |
| Hero height | 909 | 643 | 489 | 248 |
| Search panel | 3×2 + price column, 236 px | same | same, fields 131 px wide | 1 column, 837 px |
| Featured carousel | 4 × 270 px | 3 | 3 × 223 px | 1 × 330 px |
| Inventory grid | 3 col | 3 col | — | 1 col |
| H2 size | 40 px | 35 px | 35 px | 30 px |
| Body text | 14 px | 14 px | 14 px | 14 px |

Other observations:

- **Breakpoint decisions are split across three systems:** CSS media queries (Bootstrap 3 grid, `col-xs-6` etc.), JS checks against `screen.width` (992 px), and server-side mobile UA detection for the header markup. That split causes the 768 px dead zone.
- **Body text stays 14 px at all widths.** That is small for a premium site on desktop, and only the headings scale.
- **No horizontal overflow** was detected at any tested width.
- **Hidden or reordered on mobile:** the desktop menu is hidden, the sidebar moves off-canvas, and the topbar is *not* hidden (it gets taller).

## 10. Motion findings

- **Typical transition timings** (counted across loaded stylesheets): `0.5s ease-in-out` (42 rules), `0.3s ease-out` (24), `0.5s ease-out` (23) and `0.25s` variants. Expressive, but slow for hover feedback.
- **Hover feedback.** Nav links change colour over 0.5 s, card overlays fade over 0.5 s, and image transitions are `all`.
- **Carousels.** Owl Carousel, featured cars and testimonials: autoplay every 5 s, 250 ms slide, looping. The gallery uses Slick (500 ms ease, swipe on, lazy-load on demand).
- **Hero.** Slider Revolution, 2 auto-rotating slides with layered entrance animations.
- **Other.** A page preloader (156 KB GIF), an animated "back to top" car graphic, and 39 `@keyframes` definitions in the loaded CSS.
- **Drawers.** Inventory off-canvas 0.3 s ease-out. The mobile menu expands instantly.
- **Reduced motion.** Only 4 `prefers-reduced-motion` rules were found, all from third-party CSS (WooCommerce, icon font, Elementor). None comes from the theme. The carousel configs autoplay unconditionally; the code doesn't check reduced motion (read from the config, not runtime-tested under emulation).

## 11. Performance findings

| | Homepage | Single vehicle |
|---|---|---|
| Requests | 113 | 137 |
| JS files | 47 | 51 |
| CSS files | 34 | 32 |
| Render-blocking resources | 36 | — |
| Decoded weight | ~5.3 MB | ~6.6 MB |
| HTML (decoded) | 208 KB | 240 KB |
| DOM nodes | 1,782 | 2,334 |
| Inline CSS / JS | 64 KB / 14 KB | — |

- **Libraries on every page:**
  - jQuery 3.7.1 + jQuery Migrate, Bootstrap JS
  - select2; jQuery UI (core, mouse, sortable, slider, autocomplete) + touch-punch
  - Owl Carousel **and** Slick; Magnific Popup **and** PhotoSwipe
  - Slider Revolution; Elementor runtime + handlers
  - WooCommerce cart/session scripts and order attribution; Mailchimp
  - Google Maps API, loaded synchronously and **without a valid key** (console warnings), on the homepage where no map is visible above the fold
- **Third-party hosts:** Google Fonts (CSS + font files), Maps, Tag Manager, Analytics, Facebook (pixel).
- **Fonts.** 5 font files (~309 KB decoded), including two icon fonts.
- **Largest images.** Section backgrounds of 244 / 143 / 139 / 129 KB, plus the 156 KB loader GIF.
  - On the homepage, 43 of 51 images are lazy-loaded (good) but only 16 have `srcset`.
  - Inventory card images have no `srcset`.
- **Main weaknesses:** library duplication; render-blocking CSS sprawl; marketplace/shop code on a dealer site; a map API on pages without maps; a preloader that hides content; and inline modals bloating every vehicle page.

## 12. SEO findings

- **Homepage.**
  - **No H1.** Headings start at H2 and jump to H6 for small labels.
  - No meta description, no OpenGraph.
  - Lighthouse SEO (mobile): **77**. Failures: meta description, 25 non-crawlable anchors (`javascript:void(0)`), 3 non-descriptive link texts.
- **Inventory.**
  - `<title>` is just "Inventory"; filtered views become "Make – Site".
  - Single H1, breadcrumb present, crawlable pagination.
  - No meta description, OG, canonical or JSON-LD.
  - **Filtered parameter URLs carry no canonical or noindex**, so every filter combination is an indexable duplicate.
- **Single vehicle.**
  - Good: one H1, canonical present, OG present (type/title/url/description/image), title pattern "Vehicle – Site".
  - Missing: **no JSON-LD** (no `Car`/`Vehicle`, no `Offer`, no `BreadcrumbList`); only a generic `WebPage` microdata wrapper.
- **No `hreflang`** and `lang="en-US"` only, so there is no multilingual setup.

## 13. Accessibility findings

Lighthouse accessibility (mobile homepage): **81**. Automated failures:

- 43 links without a discernible name (icon-only social links, card overlay icons, carousel arrows)
- 6 buttons without a name
- 32 colour-contrast failures (light grey text on white or light grey)
- no `<main>` landmark
- heading order
- 6 undersized touch targets
- `role=presentation` conflicts

Manual findings:

- **Keyboard and focus.**
  - Desktop dropdowns are hover-only; keyboard users cannot reach submenu items.
  - Nav links set `outline: none`, so there is no visible focus.
  - Only 2 `:focus-visible` rules exist sitewide.
  - No skip link.
- **Controls that are `<div>`s.** The mobile hamburger (`tabindex=-1`, no `aria-expanded`) and the inventory filter toggle.
- **Search form.** Selects have no names or labels, the submit is an anchor, and it is not a form.
- **Images.** 36 of 51 homepage images have empty `alt`, including all vehicle card images.
- **Carousels.** They autoplay without a pause control.
- **Positive:** vehicle tabs use `role="tab"`; the gallery lightbox has labelled controls (close, fullscreen, zoom, prev/next with key hints) and supports arrow keys and Esc.

## 14. Functional feature inventory

Everything observed on the demo, in the order it appears to a visitor:

1. Topbar: hours, email, phone, social icons, login.
2. Sticky header with logo swap; mega/multi-level dropdowns; header search with autocomplete; cart.
3. Hero slider.
4. Homepage faceted search (year, make, model, mileage buckets, transmission, condition, price range).
5. USP icons, phone call-out, featured-vehicle carousel, single-model promo, blog teaser, promo video, stat counters, brand logos, testimonials, newsletter signup, footer buy/sell CTAs.
6. Inventory:
   - keyword search with autocomplete;
   - price slider;
   - 11 dropdown facets with live narrowing, active chips, result count and reset;
   - per-page and sort with a direction toggle;
   - grid / masonry / list views;
   - URL state; crawlable pagination;
   - mobile off-canvas filters;
   - loan calculator widget.
7. Cards: compare, quick gallery, old/new price, tax badge, NEW/USED ribbons.
8. Single vehicle:
   - gallery with thumbs, lightbox, fullscreen and zoom; video link;
   - spec table; features checklist; technical tab; location map; fuel-economy box;
   - related vehicles; print; share (incl. WhatsApp); compare.
9. Single-vehicle lead forms: request info, make an offer, schedule test drive, email to a friend, finance application.
10. Front-end vehicle submission ("Add car"), dealer login, wishlist script, WooCommerce shop.

## 15. V1 feature recommendations (essential, single dealer)

1. CSS-only responsive header:
   - a slim info line on desktop only;
   - a sticky compact bar via `position: sticky`;
   - one accessible drawer for mobile and tablet (real `<button>`, `aria-expanded`, focus trap, Esc, scroll lock);
   - dropdowns that open on hover **and** focus/click.
2. A real `<form method="get" action="/vehicles/">` quick search: make, model (narrowed by make), max price, fuel, plus a "show N vehicles" button. It works without JS.
3. Inventory:
   - **desktop:** sidebar filters (make, model, body, fuel, transmission, price range, year range, max km, Euro norm), result count, active chips, clear all;
   - sort: newest, price ↑/↓, km ↑, year ↓;
   - numbered pagination; the URL is the state.
4. **Mobile:** a filter drawer opened by a sticky "Filters (n)" button.
5. A useful empty state: explain, offer "clear last filter" / "clear all", and show the newest stock.
6. Card:
   - 3:2 image with `srcset` and real `alt`;
   - title (make model variant);
   - key facts: first registration, km, fuel, transmission, kW/hp;
   - price + optional monthly example;
   - reserved/sold badge.
7. Single vehicle:
   - gallery with thumbs, swipe and an accessible lightbox;
   - a sticky price/CTA panel on desktop and a **sticky bottom bar on mobile** (call · WhatsApp · enquire);
   - structured spec list grouped by section;
   - equipment list grouped;
   - Car-Pass, warranty, VAT/margin and Euro norm shown prominently;
   - similar vehicles; dealer block (address, hours, map link, not an embedded map).
8. One enquiry form with a topic choice (information / test drive / trade-in / finance), loaded on demand. Minimal fields, privacy notice, consent, spam protection without third-party tracking.
9. JSON-LD (`Car` + `Offer` + `BreadcrumbList`), canonical tags, meta descriptions, OG tags, one H1 per page.
10. Consent-gated analytics, self-hosted fonts, no preloader.

## 16. Future feature recommendations

- Progressive enhancement of filters: fetch results without a reload, keep the URL in sync with `history.replaceState`, and show live counts per facet.
- Compare (2–3 vehicles) and favourites, stored in `localStorage` with no login.
- Monthly payment calculator tied to a lender's representative example (legal text configurable).
- Trade-in valuation request with photo upload (private enquiry to the dealership, never a public listing).
- Vehicle video / 360° embed field.
- "Price lowered" indicator (only with a compliant reference-price history).
- Feeds and imports for the dealership's own stock (export to AutoScout24, 2dehands/2ememain; CSV import by staff), and XML sitemap extensions.
- Multilingual NL/FR/EN (+DE) with `hreflang`.
- Lead storage and lead export in admin.
- Saved searches / email alerts.

## 17. Features we should avoid

- **Marketplace features:** front-end "Add car" submission, dealer accounts and login, multi-dealer listings, user dashboards, WooCommerce cart/shop.
- **Slider Revolution-style hero carousels** and autoplay carousels in general. Use one strong art-directed hero image with a search entry point instead.
- **Page preloaders.**
- **Masonry and alternate layout modes.** One excellent grid plus an optional list view is enough.
- **Bucketed mileage dropdowns** ("≤ 10 000"). Use ranges.
- **Free-text transmission or technical vocabularies** used as filters.
- **Five separate lead modals rendered inline on every vehicle page.** Also drop "email to a friend" (native share covers it) and US-style credit applications collecting date of birth and full identity on a public page.
- **Server-side user-agent detection** for layout.
- **Embedded Google Maps** on load. Use a static map image or a link, loaded on interaction.
- **US units:** mpg and city/highway economy.
- **A wall of social icons** in the header.

## 18. Opportunities to outperform the reference

| Area | Reference | Elite Auto Dealer target |
|---|---|---|
| Weight | ~113–137 requests, ~5–6.6 MB decoded | < 25 requests, < 1 MB on inventory, 1 CSS + small deferred JS, no jQuery on the front end |
| Navigation | UA-sniffed, 768 px dead zone, hover-only | CSS breakpoints, one accessible drawer, keyboard-complete |
| Mobile hero | Scaled-down canvas, 7 px text | Art-directed `<picture>` crop, readable type, search reachable within the first screen |
| Search | Not a form, JS-only, admin-ajax per change | Native GET form; enhancement optional |
| Data | Free-text specs, mixed model/trim vocabulary | Structured schema (already in place), controlled vocabularies, model family + variant |
| Cards | Image + title + price | Decision data (date, km, fuel, gearbox, power, status) at a glance |
| Single vehicle | No sticky CTA, CTAs below the fold on mobile | Sticky mobile action bar, price and trust facts (Car-Pass, warranty, VAT) above the fold |
| SEO | No H1 home, no schema, duplicate filtered URLs | Vehicle JSON-LD, canonical/noindex strategy for filters, `hreflang` |
| Privacy | Trackers without consent, Google-hosted fonts | Consent-first, self-hosted fonts, minimal form data |
| A11y | Lighthouse 81, many unnamed controls | WCAG 2.1 AA target, Lighthouse ≥ 95 |

## 19. Belgian / EU-specific requirements

Status against the current foundation (`inc/vehicle-meta.php`, `inc/vehicle-taxonomies.php`):

| Requirement | Status | Note |
|---|---|---|
| First registration | ✅ `first_registration` (date) | Show as month/year on cards |
| Mileage (km) | ✅ `mileage` | Range filter, not buckets |
| Car-Pass | ✅ `carpass` enum | Mandatory document for Belgian used-car sales; worth a visible trust badge. A Car-Pass reference/link field could come later |
| VAT deductible / margin vehicle | ⚠️ partial: `vat_deductible` is a boolean | Belgium needs "VAT deductible" vs "margin scheme" (and possibly "VAT not applicable") as an **enum**, plus optional display of the excl.-VAT price for business buyers. Consumer prices must be shown VAT-inclusive |
| CO2 (WLTP g/km) | ✅ `co2` | Relevant to Belgian registration/road tax and company-car deductibility |
| Euro emissions standard | ✅ `euro_norm` | Key for Brussels/Antwerp/Ghent LEZ. Do not hard-code LEZ eligibility rules; they change by date and region |
| kW and hp | ✅ `power_kw`, `power_hp` | kW first (Belgian convention), hp secondary |
| Warranty | ✅ `warranty_months` | Consider a warranty type later (dealer / manufacturer / brand programme). The B2C legal guarantee applies regardless |
| Fuel | ✅ taxonomy | Includes Plug-in hybrid, Electric, CNG, LPG |
| Transmission | ✅ taxonomy (Manual/Automatic) | Keep it controlled; gearbox detail as text if needed |
| EV battery capacity (kWh) | ❌ missing | Needs a decimal field; the sanitiser has no `number` case yet |
| EV range (WLTP km) | ❌ missing | Integer field |
| Stock ID | ✅ `stock_id` | |
| Reserved / sold | ✅ `availability` enum | |
| Finance enquiry | ❌ not built | Advertising a monthly amount triggers consumer-credit disclosure rules (Book VII of the Belgian Code of Economic Law: representative example with APR, term, total amount). Treat `finance_monthly` as unpublishable until that text exists |
| Trade-in enquiry | ❌ not built | Part of the single enquiry form |

Other EU/BE obligations the reference ignores:

- **GDPR / ePrivacy.** No analytics or marketing pixels before consent. Keep form fields to what the enquiry needs, add a privacy notice, and set a retention policy for stored leads.
- **Fonts.** Self-host them. Google-hosted fonts transfer visitor IP addresses to a third party.
- **Language.** NL and FR are both required for Brussels and national reach; EN is optional.
  - Taxonomy term names and slugs, and option labels (Car-Pass, availability, Euro norm), must be translatable.
  - Number and price formats follow the locale (`€ 24.950`).
- **Price-reduction claims.** The Omnibus price-indication rules require a prior-price reference. Confirm with counsel before showing struck-through prices.
- **Accessibility.** Aim for WCAG 2.1 AA. Whether the European Accessibility Act applies depends on the dealer's size and services, but the standard is the right target regardless.

## 20. Concrete implications for the current theme architecture

The foundation's core decisions are **confirmed**, not changed, by this audit:

- taxonomies for finite filterable attributes;
- numeric meta for ranges;
- GET-parameter filtering on `/vehicles/` with crawlable pagination;
- a single schema driving admin, REST and front end.

The reference's mixed vocabularies and free-text specs show exactly what that structure prevents.

**Recommended follow-ups (not implemented in this task; each needs approval):**

1. **`vat_deductible` boolean → `vat_regime` enum** (`vat_deductible`, `margin`, …) *before* real vehicles are entered. This is cheap now and a data migration later.
2. **Add a `number` (decimal) type** to `eda_sanitize_vehicle_meta_value()` and the meta box. Then add `battery_kwh` (decimal) and `ev_range_km` (integer); later perhaps WLTP consumption (decimal).
3. **Model = family, plus a `variant` text meta** (e.g. "3.0 TFSI quattro S line"). This keeps the model filter clean, avoids the reference's "A4" / "A4 Prestige" split, and lets the card title read `Make Model Variant`.
4. **Image sizes.** Our cropped 3:2 sizes (640, 1600) produce a two-step `srcset`. Add an intermediate 3:2 size (~960×640) so phones and tablets get a sharp but not oversized image. Keep 3:2 for both cards and the gallery; the reference mixes 1.39:1 and 1.64:1.
5. **Header/navigation:** CSS breakpoints only. Never branch markup on `wp_is_mobile()`, which is unsafe for caching and is the reference's 768 px bug.
6. **Filtering implementation** (per `docs/ARCHITECTURE.md`):
   - keep the server-rendered GET form;
   - add range params via `pre_get_posts`;
   - add `rel=canonical` to the clean archive (or `noindex,follow`) for multi-parameter combinations, while term landing pages (`/vehicles/make/bmw/`) stay indexable;
   - optional enhancement fetches the same URL and swaps the results region, so no admin-ajax endpoint is needed.
7. **Facet narrowing.** For single-dealer inventory sizes, compute available terms from the current result set's IDs (one extra query) rather than one AJAX call per change.
8. **Leads.**
   - One enquiry template part with a topic param.
   - Rendered on demand, not 5–7 modals in every page.
   - Submission stored as a non-public `eda_lead` post type plus email notification.
   - This needs a privacy/consent design.
9. **Structured data.** Add a `Car` + `Offer` JSON-LD emitter in `inc/` driven by the same meta schema (price, mileage → `mileageFromOdometer`, fuel, transmission, VIN, `dateVehicleFirstRegistered`, availability → `InStock`/`SoldOut`).
10. **Front-end JS budget.** No jQuery. Small vanilla modules (drawer, gallery/lightbox, filter enhancement) loaded with `defer`. No autoplaying motion, and every animation wrapped in `prefers-reduced-motion`.
11. **Multilingual decision before content entry.** Choose the translation approach (plugin vs. theme-level) because it affects term slugs, the seeded term names and URL bases (`/vehicles/`, `/vehicle/`).
12. **Analytics hook point.** Provide a consent-aware place to load tracking (or defer to a consent plugin); never hard-code trackers into templates.
