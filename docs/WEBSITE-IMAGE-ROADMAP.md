# Aurelis Motors website image roadmap

Status: **9/9 website images produced and imported.** The approved sets (`aurelis-motors-website-images.zip`, `aurelis-motors-inner-page-header-images.zip`, `aurelis-motors-page-header-images.zip`) are live on the local demo site; see section 14.

**Revision (inner-page headers):** all four inner pages use one full-width image hero (section 8). The About, Finance and Contact body images stay in the content; their headers get three new, separate images. This supersedes the split header and the earlier "Finance: no image" rule.

This roadmap covers non-vehicle website imagery only. The 75 vehicle photographs are a separate, completed production set and must not be regenerated or modified by this phase.

## 1. Approved image set

Exactly **9 website images** are required: the homepage hero (2), the Vehicles hero (1), the About / Finance / Contact body images (3) and their header heroes (3).

| # | Filename | Slot | Final source size | Ratio | Target weight | Preferred max |
|---|---|---|---:|---:|---:|---:|
| 1 | `aurelis-home-hero-desktop.jpg` | Homepage hero — default / landscape / desktop-tablet | **2560 × 1138 px** | ~2.25:1 | **280–380 KB** | **450 KB** |
| 2 | `aurelis-home-hero-mobile.jpg` | Homepage hero — portrait below 1024 px | **1200 × 1800 px** | 2:3 | **160–250 KB** | **300 KB** |
| 3 | `aurelis-vehicles-forecourt.jpg` | Vehicles page hero (full width) | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 4 | `aurelis-about-showroom.jpg` | About body image (after the text) | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 5 | `aurelis-finance-consultation.jpg` | Finance body image (after the text) | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 6 | `aurelis-contact-entrance.jpg` | Contact body image (beside / after the form) | **1200 × 1500 px** | 4:5 | **180–350 KB** | **450 KB** |
| 7 | `aurelis-about-header.jpg` | About header hero | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 8 | `aurelis-finance-header.jpg` | Finance header hero | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 9 | `aurelis-contact-header.jpg` | Contact header hero | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |

All files are final web masters: JPEG (`.jpg`), sRGB, generated directly at the specified dimensions. No oversized master and no later bulk-resize phase.

## 2. Why two homepage hero sources are required

The existing homepage hero changes shape substantially across responsive layouts. Measured rendered ratios range from very wide desktop/landscape to tall portrait phone. A single source would either lose most of its width on phones or most of its height on wide screens.

Use an art-directed `<picture>`:

- **Mobile source:** portrait orientation below 1024 px.
- **Desktop/default source:** desktop, landscape phones and landscape/tablet layouts.
- The hero image is above the fold: it must be eager and high priority, never lazy-loaded.

The approved page layout, hero height, headline, CTAs and search-panel overlap remain unchanged.

## 3. Homepage desktop hero composition

File: `aurelis-home-hero-desktop.jpg`  
Final source: **2560 × 1138 px**.

Composition contract:

- Aurelis Motors dealership environment consistent with the vehicle photography.
- Main vehicle / dealership visual interest sits to the **right**.
- Keep approximately the **left 55%** calm and low-detail for the headline and CTAs.
- Main subject should sit approximately within **x 58–88%, y 22–76%**.
- Bottom band should be paving / non-essential detail because the search panel overlaps the hero.
- No essential subject in the top band because wide screens may crop vertically.
- Vehicle should not sit under the headline.
- Natural automotive perspective; no advertising-style distortion.

The desktop hero keeps the existing left-to-right darkening concept so HTML text remains readable.

## 4. Homepage mobile hero composition

File: `aurelis-home-hero-mobile.jpg`  
Final source: **1200 × 1800 px**.

This is a separate art-directed composition, not a crop of the desktop image.

Composition zones:

- **y 10–32%:** primary subject band — vehicle front three-quarter and/or key dealership architecture.
- **y 35–83%:** calm, darker visual surface behind the headline and CTAs.
- **y ≥ 85%:** paving / non-essential detail.
- **y 0–10%:** sky / architecture only; safe to crop.
- Keep central and lower portions calm because mobile text covers most of the width.

Implementation should use approximately `object-position: 50% 30%`.

For portrait layouts, the overlay should become predominantly top-to-bottom, keeping the subject band readable while darkening the text region strongly enough for WCAG text contrast.

## 5. About image

File: `aurelis-about-showroom.jpg`  
Final source: **1536 × 1024 px, 3:2**.

Purpose:

- the About **body** image, after the text (the header uses `aurelis-about-header.jpg`, section 8);
- add visual substance and trust without making the page promotional;
- show the same Aurelis Motors visual world as the vehicle set.

Recommended subject:

- premium showroom interior or delivery/showroom environment;
- architecture and material detail more important than another generic car portrait;
- calm, spacious, credible European dealership atmosphere;
- no people required.

It renders at the text-column width (704 px), uncropped (`sizes="(min-width: 46rem) 44rem, 92vw"`).

## 6. Contact image

File: `aurelis-contact-entrance.jpg`  
Final source: **1200 × 1500 px, 4:5 portrait**.

Purpose:

- show the Aurelis Motors entrance / facade — effectively "where the customer will arrive";
- the Contact **body** image (the header uses `aurelis-contact-header.jpg`, section 8);
- desktop: in the left column under the intro text, beside the form; below 56em: after the form, so it never pushes the form down.

The image should feel useful and location-oriented, not decorative filler.

## 7. Finance

File: `aurelis-finance-consultation.jpg` (1536 × 1024 px, 3:2): the Finance **body** image, after the text (the header uses `aurelis-finance-header.jpg`, section 8).

A quiet, empty consultation area in the same showroom. No people, handshake, cash, keys, paperwork or legible screens, which keeps the restrained, compliance-first tone. The Finance copy is unchanged: no calculators, claims or representative examples until real lender content exists.

## 8. Inner-page header heroes (Vehicles, About, Finance, Contact)

One partial for all four pages (`template-parts/page-intro.php`): global header, then a full-width image hero (shallower than the homepage's), then the page content. Each page keeps its **body image** in the content, and the header uses a **separate** image:

| Page | Header hero image | Stored in | Body image (unchanged position) |
|---|---|---|---|
| Vehicles | `aurelis-vehicles-forecourt.jpg` | theme mod `eda_inventory_image` | none: filters, then the vehicle grid |
| About | `aurelis-about-header.jpg` | page meta `_eda_header_image` | `aurelis-about-showroom.jpg` after the text |
| Finance | `aurelis-finance-header.jpg` | page meta `_eda_header_image` | `aurelis-finance-consultation.jpg` after the text |
| Contact | `aurelis-contact-header.jpg` | page meta `_eda_header_image` | `aurelis-contact-entrance.jpg` beside the form (after it on narrow screens) |

Without a header image a page shows the plain text header on the cream background. Editors set it in the **Header image** box on the page edit screen; the featured image stays the body image.

| Width | Hero height |
|---|---|
| < 40em | **260 px** (`min-height: 16.25rem`) |
| 40–64em | **320 px** (`20rem`) |
| ≥ 64em | **400 px**, growing on wide screens (`clamp(25rem, 22vw, 32rem)`: 422 px at 1920, 512 px at 2560) |

- The image fills the hero (`object-fit: cover`) at `object-position: 50% 45%`, 50% 40% from 100em, and 50% 42% from 140em.
- Breadcrumb top-left; light eyebrow, white H1 and subtitle bottom-left in the content container.
- Overlay: a left-weighted darkening (0.78 → 0.45 at 40% → 0.08) plus a bottom lift; stronger from the bottom below 40em.
- Vehicles, measured from the image pixels: every text element is ≥ 5.4:1 at p10 from 320 to 2560 px (at 2560 px the 512 px hero keeps the cars whole: roofs at y 11, wheels at y 503).
- `sizes="100vw"`, eager, the only `fetchpriority="high"` image on the page.

### Production brief (as used for the three page header images)

Same contract as the other website images: **1536 × 1024 px**, 3:2, JPEG, embedded sRGB, 180–350 KB (max 450 KB), no text, signage, branding or plate characters. Photorealistic, the same Aurelis world as section 9.

The hero shows a horizontal band of the image, so the composition must survive these crops (measured on the Vehicles hero):

- **Desktop 1440 × 400:** only rows **26–68%** of the image are visible, full width. At 1920 × 422, rows 30–64%.
- **Mobile 390 × 260:** the whole 3:2 image is visible, but the text covers most of it.
- **Text zone:** the left **0–45%** of the width, from about **55% down** to the bottom of the visible band, is covered by the title block and darkened. Keep it calm: floor, wall, soft shadow. Put no key subject or face-on vehicle there.
- **Subject zone:** the main subject sits at **x 45–95%, y 30–62%**. No essential detail above 25% or below 70%.

| File | Scene |
|---|---|
| `aurelis-about-header.jpg` | Wide editorial view of the showroom hall: stone, glazing and warm linear light, with one or two premium cars set back on the right. Different from the lounge in `aurelis-about-showroom.jpg`. |
| `aurelis-finance-header.jpg` | Wide, calm view of a refined consultation area or lounge: seating group and stone wall, a car softly out of focus behind glass on the right. No people, paperwork, cash, keys or screens. Different viewpoint from `aurelis-finance-consultation.jpg`. |
| `aurelis-contact-header.jpg` | Arrival moment: a wide exterior of the entrance canopy and forecourt at dusk (warm interior glow, paving, birches), doors on the right half. Different from the frontal door shot in `aurelis-contact-entrance.jpg`. |

Header images are decorative (`alt=""`): the H1 carries the meaning and the body image the description.

### Hero motion

A slow, clearly visible **camera push-in**: CSS only, `@keyframes eda-hero-motion` (scale only, no drift), `infinite alternate`.

- **Easing:** `cubic-bezier(0.3, 0.1, 0.7, 0.9)`. It keeps moving through most of the cycle and only softens at the turns.
- **No panning:** zoom origins are centred horizontally, so nothing slides sideways (measured image-centre shift: 0 px at every width).
- **What moves:** only `.hero__image` and `.page-intro__backdrop-image`. The wrappers clip (`overflow: hidden`); overlays, text, buttons, filters, forms and body images stay still. Transform only, so it runs on the compositor with no layout shift.

| Hero | Scale | One direction | Zoom origin |
|---|---|---|---|
| Home (desktop) | 1.08 | 14 s | 50% 50% |
| Home (portrait mobile source) | 1.04 | 14 s | 50% 30% (subject in the upper third) |
| Vehicles | 1.06 | 13.5 s | 50% 44% (line of cars) |
| Vehicles ≥ 100em (1600 px) | 1.045 | 13.5 s | 50% 95%: ground level, wheels stay planted |
| Vehicles ≥ 140em (2240 px) | 1.015 | 13.5 s | 50% 97% (cars almost fill the 512 px hero) |
| About | 1.07 | 14 s | 50% 50% |
| Finance | 1.06 | 15 s | 50% 50% (the calmest) |
| Contact | 1.07 | 14 s | 50% 50% |
| Any inner hero < 40em | 1.035 | as above | as above |

- **Edges and cars:** the scale always overscans the frame, so no edge is exposed; verified at full zoom from 320 to 2560 px. The Vehicles cars stay whole at full zoom:

  | Width | Wheels from the bottom | Roofs from the top |
  |---|---|---|
  | 1440 | 79 px | 29 px |
  | 1920 | 12 px | 26 px |
  | 2560 | 8 px | 4 px |

- **Reduced motion** (`prefers-reduced-motion: reduce`): `animation: none; transform: none`, so the image stays static with the same crop.
- **Sharpness:** inner heroes stay at about native resolution at 1440 / 1× at full zoom. At 1920 / 1× they are enlarged about 1.3× at full zoom.

## 9. Aurelis visual continuity

All four future images must look like the same dealership and visual world as the approved 75 vehicle photographs:

- contemporary premium European dealership;
- Brussels / Belgian atmosphere;
- dark charcoal architecture;
- warm grey natural stone;
- large clean glazing;
- slim brushed-metal details;
- clean light paving;
- restrained landscaping;
- ornamental grasses and young birch trees;
- subtle warm showroom interior light;
- soft Belgian overcast / bright-overcast daylight;
- restrained cool-neutral colour grade;
- photorealistic materials and natural reflections.

Do not introduce a different dealership architecture.

## 10. Content restrictions

Do not generate:

- captions or headline text inside the image;
- random signage;
- fake dealership branding;
- watermarks;
- random registration characters;
- crowds, hands or distracting people;
- CGI/render sheen;
- exaggerated HDR;
- lens flares or heavy vignettes.

If a vehicle appears with a plate, use the same blank Belgian-format treatment as the vehicle set: white plate, thin red border, no characters.

## 11. Responsive strategy

### Homepage hero

Use `<picture>` with a portrait-mobile source:

`(orientation: portrait) and (max-width: 63.99em)`

Desktop/default `<img>` uses the desktop hero.

Requirements:

- `sizes="100vw"`;
- eager loading;
- `fetchpriority="high"`;
- no lazy loading;
- decorative alt (`alt=""`) because the visible H1 supplies the meaning.

### Inner-page header heroes

Plain responsive `<img>` behind the text: `sizes="100vw"`, `loading="eager"`, `fetchpriority="high"` (the only high-priority image on the page), standard WordPress srcset.

### Body images

About / Finance: `(min-width: 46rem) 44rem, 92vw`. Contact: `(min-width: 56em) 40vw, 92vw`, never high priority.

No new registered WordPress image sizes are required for this phase unless implementation evidence later proves otherwise.

## 12. Storage and source-of-truth rules

- Approved website-image originals are archived outside Git in Google Drive.
- Local development copies should be Git-ignored, analogous to the vehicle production set.
- Git tracks the roadmap, import/assignment logic, tests and documentation — not the JPEG masters.
- Final production files must be checked for filename, dimensions, JPEG decode, sRGB and weight before import.
- Do not upscale an off-contract file. Reject and reproduce it instead.

## 13. Production

Done: all 9 images produced, approved and archived in Drive (images 7–9, the About, Finance and Contact header heroes, to the brief in section 8). Do not generate additional website imagery beyond these unless the roadmap is explicitly revised.

## 14. Implementation (done)

**Commands** (from the WordPress root, theme active):

```sh
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php validate   # QA gate only, writes nothing
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php            # import / update + assign
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php cleanup    # remove the website images
wp eval-file wp-content/themes/elite-auto-dealer/tests/site-image-test.php               # verify (leaves them imported and assigned)
```

CLI only: no admin screen, REST route or upload endpoint. The frontend never depends on demo tooling.

- **Source:** `demo/images/site/{filename}`, local only (covered by the `/demo/images/` Git ignore rule). To set up a machine, unpack `aurelis-motors-website-images.zip` and `aurelis-motors-inner-page-header-images.zip` from Drive into that folder.
- **QA gate:** the same gate as the vehicle photos (`eda_images_check_file()`), run against a per-file contract in `eda_site_images()`:
  - exact dimensions;
  - `.jpg`, `image/jpeg`, full decode;
  - embedded sRGB;
  - weight at most the preferred maximum (450 KB; 300 KB for the mobile hero).

  A failing file is reported and skipped, never resized or recompressed.
- **Identity:** attachment meta `_eda_demo` = `aurelis-demo`, `_eda_demo_site_image` = filename, `_eda_demo_site_image_checksum` = SHA-256. Titles and file names are never used as identity.
- **Idempotency:**
  - a rerun with identical files creates nothing (all reused); missing header files are reported, not faked;
  - a changed source (new checksum) is replaced in place under the same ID and path, so assignments survive and no orphan is created;
  - this is the same shared `eda_images_upsert()` as the vehicle importer.
- **Assignment** (normal WordPress storage, no hard-coded IDs or URLs):

  | Image | Stored in |
  |---|---|
  | Desktop hero | theme mod `eda_hero_image` (Customizer → Header images) |
  | Mobile hero | theme mod `eda_hero_image_mobile` (same section) |
  | Vehicles hero | theme mod `eda_inventory_image` (same section; the archive has no page object) |
  | About / Finance / Contact body image | that page's featured image |
  | About / Finance / Contact header hero | page meta `_eda_header_image` ("Header image" box on the page edit screen) |

  A slot that already holds the dealer's own (untagged) image is left unchanged and reported.
- **Homepage hero** (`eda_home_hero_image()`):
  - markup: `<picture>` with `<source media="(orientation: portrait) and (max-width: 63.99em)">` (mobile file, portrait-only srcset 200–1200w) and the desktop `<img>` (srcset 300–2560w);
  - attributes: `sizes="100vw"`, `alt=""`, `loading="eager"`, `fetchpriority="high"`, `decoding="async"`;
  - styling: `object-fit: cover`. Desktop stays centred under the left-to-right overlay. Portrait screens use `object-position: 50% 30%` and a top-to-bottom overlay measured from the bottom edge: dark through the bottom-aligned text block (about 400 px), light over the subject band. A narrow-phone variant applies below 360 px, where the headline wraps to three lines.
  - fallback: the placeholder when no desktop image is set.
- **Inner-page headers:** `archive-vehicle.php`, `page.php` and `page-templates/contact.php` all call `template-parts/page-intro.php` with title, eyebrow, lead and header image ID (section 8; pages use `eda_page_header_image()`). Body images render in the content as before: `page.php` after the text, Contact beside or after the form.
- **Derivatives:**
  - WordPress's core sizes give same-ratio candidates, so no new image sizes were registered. The 2560 px desktop file is at the core 2560 big-image threshold, so it is kept as is (no `-scaled` copy).
  - The theme's three 3:2 vehicle crops are also generated for these uploads. They are unused and never in a srcset, since their ratio differs.
- **Cleanup:** `import-site-images.php cleanup` (also part of `seed.php cleanup`):
  - removes only attachments carrying both `_eda_demo` and `_eda_demo_site_image`, with all their files;
  - clears the hero and inventory theme mods and page header meta; featured images go with the attachment;
  - leaves vehicle photos and client uploads untouched.
- **Measured (local, 3 October 2026):**
  - 84 Aurelis demo attachments (75 vehicle + 9 website);
  - About, Finance and Contact heroes at 1440 × 900: header first (eager, the only high-priority image, 1536 original 318–335 KB at 1×), the body image second at normal priority (768 px copy); worst text contrast ≥ 8.4:1 at p10 from 320 to 1920 px, with the right side keeping 72–80% of its brightness on desktop;
  - the Vehicles hero at 1440 × 900 is 1425 × 400: the filters start at y 513 and the first card row shows 194 px above the fold;
  - at 1440 × 900 the hero loads the 1536 px copy (180 KB) as the second request and is the LCP;
  - phones load only the mobile file and landscape phones only the desktop file;
  - every hero text element measured ≥ 4.5:1 contrast (heading ≥ 8.5:1) from 320 to 1440 px.
