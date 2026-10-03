# Aurelis Motors website image roadmap

Status: **locked for production; no website images generated yet.**

This roadmap covers non-vehicle website imagery only. The 75 vehicle photographs are a separate, completed production set and must not be regenerated or modified by this phase.

## 1. Approved image set

Exactly **4 website images** are required.

| # | Filename | Slot | Final source size | Ratio | Target weight | Preferred max |
|---|---|---|---:|---:|---:|---:|
| 1 | `aurelis-home-hero-desktop.jpg` | Homepage hero — default / landscape / desktop-tablet | **2560 × 1138 px** | ~2.25:1 | **280–380 KB** | **450 KB** |
| 2 | `aurelis-home-hero-mobile.jpg` | Homepage hero — portrait below 1024 px | **1200 × 1800 px** | 2:3 | **160–250 KB** | **300 KB** |
| 3 | `aurelis-about-showroom.jpg` | About page supporting image after text | **1536 × 1024 px** | 3:2 | **180–350 KB** | **450 KB** |
| 4 | `aurelis-contact-entrance.jpg` | Contact page entrance/facade image | **1200 × 1500 px** | 4:5 | **180–350 KB** | **450 KB** |

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

- supporting image **after the About text**, not a page-banner hero;
- add visual substance and trust without making the page promotional;
- show the same Aurelis Motors visual world as the vehicle set.

Recommended subject:

- premium showroom interior or delivery/showroom environment;
- architecture and material detail more important than another generic car portrait;
- calm, spacious, credible European dealership atmosphere;
- no people required.

It should render responsively at the page's text-column width without art-directed cropping.

## 6. Contact image

File: `aurelis-contact-entrance.jpg`  
Final source: **1200 × 1500 px, 4:5 portrait**.

Purpose:

- show the Aurelis Motors entrance / facade — effectively "where the customer will arrive";
- desktop: occupy the otherwise sparse left column beside the contact form;
- below the desktop two-column breakpoint: move **after the form** so it does not push the form down.

The image should feel useful and location-oriented, not decorative filler.

## 7. Finance

**No image.**

Finance stays text-only until real representative credit examples / lender content are available. Decorative keys, handshake or cockpit photography is intentionally excluded because it would weaken the restrained, compliance-first tone.

## 8. Pages that deliberately stay without photographic banner heroes

- About
- Finance
- Contact
- Vehicle inventory
- Single vehicle pages
- Privacy/legal pages
- Search / 404

About and Contact receive supporting in-content images only.

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

### About

Plain responsive `<img>`.

Recommended sizes:

`(min-width: 46rem) 44rem, 92vw`

### Contact

Plain responsive `<img>`.

Recommended sizes:

`(min-width: 56em) 40vw, 92vw`

No new registered WordPress image sizes are required for this phase unless implementation evidence later proves otherwise.

## 12. Storage and source-of-truth rules

- Approved website-image originals are archived outside Git in Google Drive.
- Local development copies should be Git-ignored, analogous to the vehicle production set.
- Git tracks the roadmap, import/assignment logic, tests and documentation — not the JPEG masters.
- Final production files must be checked for filename, dimensions, JPEG decode, sRGB and weight before import.
- Do not upscale an off-contract file. Reject and reproduce it instead.

## 13. Next production phase

The next step is to generate **exactly these four images**, audit them visually and technically, archive them in Drive, then implement/import them.

Do not generate additional website imagery unless the roadmap is explicitly revised.
