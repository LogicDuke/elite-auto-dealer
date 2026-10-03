# Elite Auto Dealer: project rules

Binding rules for everyone building this theme. Each rule exists because the reference demo we audited failed on it; the evidence is in [REFERENCE-AUDIT-POTENZA.md](REFERENCE-AUDIT-POTENZA.md). Architecture details live in [ARCHITECTURE.md](ARCHITECTURE.md).

A change that breaks a rule needs an explicit, documented exception in this file.

## 0. Single-dealer product scope (permanent)

**Elite Auto Dealer is built for one dealership and one dealership inventory. Multi-dealer functionality is outside the scope of this product.** This rule has no exceptions.

1. **The product is not a marketplace,** classifieds site, multi-dealer platform or paid-listing platform.
2. **Only dealership staff manage inventory.** Staff are logged-in WordPress users with an editing role, working in WordPress admin. Inventory management covers creating, editing, publishing, photos, price, specifications, availability, featured, reserved and sold.
3. **Never build:**
   - front-end vehicle submission;
   - seller or dealer accounts, registration flows or public seller profiles;
   - listing fees or payments (WooCommerce or otherwise);
   - any public endpoint (form handler, AJAX action, REST route) that creates or edits `vehicle` posts or vehicle terms.
4. **Visitors may only:** browse, filter and search inventory, view vehicles, call, WhatsApp, and send enquiries (general, test drive, finance, trade-in valuation).
5. **Trade-in is a private enquiry only.** Details of the customer's own car go to the dealership as an `eda_enquiry` and never become a `vehicle` post, a listing or any public content.
6. **Public endpoints are limited to two:**
   - the enquiry handler (`admin_post_nopriv_eda_enquiry`), which may only create private `eda_enquiry` posts;
   - the read-only nonce refresh (`wp_ajax_nopriv_eda_enquiry_nonce`).

   Any new public endpoint needs review against this section; the smoke test enforces the list.

## 1. Responsive and navigation

1. **No user-agent detection for layout or navigation.** Never branch markup on `wp_is_mobile()` or the UA string. Responsive behaviour is CSS media/container queries on the *viewport*. (The reference sent no menu to a desktop-UA tablet at 768 px.)
2. **Never use `screen.width`** (the physical screen) for layout decisions. If JS must know the layout, use `matchMedia()` with the same breakpoints as the CSS.
3. **Never hide navigation without a replacement** at the same breakpoint. Every viewport width from 320 px up must have a reachable menu. Test 320, 390, 768, 1024 and 1440.
4. **Basic navigation works without JS.** Menu links are present in the HTML; JS only enhances (drawer behaviour). If JS fails, the menu is still reachable.
5. **Sticky elements use CSS `position: sticky/fixed`**, not scroll listeners.
6. **Important actions stay near the top on mobile.** On vehicle pages: price and the action bar (call / WhatsApp / enquire) come before long content.

## 2. Accessibility (target: WCAG 2.1 AA)

1. **No `<div>`/`<span>` as buttons or links.** Actions are `<button type="button">`; navigation is `<a href>`. No `href="javascript:void(0)"`.
2. **No hover-only interaction.** Every dropdown and overlay also opens on keyboard focus/activation and touch. Disclosure buttons carry `aria-expanded` and `aria-controls`.
3. **Never remove visible focus.** No `outline: none` without an equal or better `:focus-visible` replacement. A global `:focus-visible` style ships in `main.css`.
4. **Every form control has a `<label>`.** Placeholders are not labels. Use `autocomplete` on personal-data fields, `required` on required ones, and announce status with `role="status"`/`role="alert"`.
5. **Every icon-only link or button has an accessible name** (visible text, `aria-label`, or screen-reader text).
6. **Landmarks and headings:** a skip link, one `<main>`, exactly one H1 per page, no skipped heading levels, and breadcrumbs as `<nav aria-label>` with `aria-current="page"`.
7. **Images:** meaningful `alt` (vehicle photos: make, model, view). Decorative images use `alt=""`.
8. **Touch targets** are at least 44 × 44 px (48 px for the action bar). Fixed bars must not cover focused elements (`scroll-padding`).
9. **Drawers and modals:** a focus trap while open, Esc closes them, focus returns to the trigger, and the background is inert or scroll-locked.
10. **Motion:** nothing autoplays. Every animation or transition beyond simple colour/opacity feedback is wrapped in `@media (prefers-reduced-motion: no-preference)`.
11. **Colour contrast** is at least 4.5:1 for text (3:1 for large text and UI boundaries). Check light-grey text in particular.

## 3. Performance principles

The reference made 113 requests (homepage) and 137 (vehicle page), loading 47–51 JS and 32–34 CSS files, 36 render-blocking, with duplicate libraries. We aim to be an order of magnitude lighter. Concrete budgets are set when the first real templates exist and can be measured; until then these principles apply:

1. **No page builder dependency** (no Elementor or similar).
2. **No Slider Revolution** or any hero slider; use one art-directed `<picture>` hero.
3. **No WooCommerce** unless a real commerce requirement justifies it.
4. **One library per job, at most.** Never two carousel or two lightbox libraries. Prefer none: native `<dialog>`, CSS scroll-snap, `<details>`.
5. **No jQuery on the front end.** Admin may use what WordPress already loads.
6. **Native browser and WordPress features first:** `srcset`/`sizes`, `loading="lazy"`, `decoding="async"`, `fetchpriority`, `<dialog>`, `<details>`, CSS `position: sticky`, `:has()`, `scroll-snap`.
7. **JS loaded only where needed.** Enqueue per template (gallery JS only on vehicle pages, filter JS only on listings), always with `defer`. No inline HTML for features that aren't shown (e.g. no hidden modal forms).
8. **One main stylesheet**, small and cacheable. No per-widget CSS files. Inline only small critical CSS if measurement justifies it.
9. **Responsive images everywhere** using the 3:2 `eda-vehicle-*` sizes; never serve a 1536 px image into a 360 px slot or a 265 px image into a 3× display.
10. **Maps and other heavy embeds load only on demand** (click or visibility), never on pages that don't show them.
11. **Local/static assets:** self-hosted fonts (WOFF2, subset, `font-display: swap`, at most 2 families) and SVG icons inline or as a sprite instead of icon fonts.
12. **No preloaders/splash screens.**
13. **Measure before adding.** Any new dependency needs a reason in its PR and a before/after request and weight comparison.

## 4. Privacy (EU / GDPR / ePrivacy)

1. **No analytics or marketing scripts before the required consent.** Templates never hard-code tracking snippets. Tracking is loaded through the (later) EDS consent layer only. The cookie/consent system is out of scope for this theme phase.
2. **No external font CDNs.** Fonts are self-hosted (avoids transferring visitor IPs to third parties).
3. **Maps and third-party embeds** (Google Maps, YouTube, Vimeo, WhatsApp widgets) are not loaded on page view. Use a static placeholder or link, and load the embed only after user action and, where the embed sets cookies or tracks, after consent.
4. **Forms collect only what the purpose needs.** The enquiry form collects name, email, optional phone and message, and consent. No date of birth, address, income, ID numbers or credit history. Finance applications happen with the lender, not on the dealer site.
5. **Consent is tied to the submission.** The consent checkbox is required and links to the privacy policy, and the exact consent text is stored with each enquiry.
6. **No personal data in URLs** (redirects, query strings, analytics events).
7. **Enquiries are private:** not public, not in the REST API, and visible only to editors and above.
8. **Enquiries are kept 12 months by default** (`eda_enquiry_retention_months`) and are covered by the WordPress personal-data exporter and eraser. Changing the retention period must match the dealer's privacy policy.

## 5. Multilingual (Belgium: NL + FR, EN ready)

1. **All user-facing and admin strings use gettext** with the `elite-auto-dealer` text domain, including units, enum labels and formats. No hard-coded English in templates or reusable logic.
2. **Use `_x()` with context** where a word is ambiguous (units, price format).
3. **Locale-aware formatting:** `number_format_i18n()`, `date_i18n()`/`wp_date()`, and a translatable price pattern. Never concatenate translated fragments into sentences; use `sprintf` with translator comments.
4. **Stored values are language-neutral machine keys** (enum values, term slugs). Labels are translated at display time.
5. **No language assumed in URLs or code paths.** URL bases go through `eda_url_bases`.
6. **No translation plugin in the foundation phase.** When one is added, vehicles and the vehicle taxonomies are registered as translatable (see ARCHITECTURE "Multilingual readiness").
7. **Regenerate `languages/elite-auto-dealer.pot`** whenever strings change.

## 6. SEO

1. One H1 per page, `<main>`, breadcrumbs, logical H2/H3 structure.
2. Structured data uses only stored data: no ratings, no invented availability or finance.
3. Filtered and sorted listing URLs are `noindex, follow`. Clean listings and term archives are indexable with a self-canonical. New filter params must be added to `eda_vehicle_filter_params()`.
4. Internal links are real, crawlable `<a href>` links.

## 7. Code

1. WordPress Coding Standards (`phpcs.xml.dist`) stay clean.
2. `tests/smoke-test.php` passes before any commit. Extend it when adding logic.
3. Escape on output, sanitise on input, nonces on state-changing requests.
4. Prefixes: `eda_` / `EDA_` / `_eda_` / `eda-`.

## Open items needing a decision

- **Spam protection beyond the honeypot** (rate limiting or a privacy-friendly challenge) if spam appears.
- **Who maintains the `nl_BE` / `fr_BE` translations** when strings change, and native-speaker review before launch.
- **Installing WordPress core `nl_BE` / `fr_BE` language packs** on each client site (needed for Belgian number formatting).
