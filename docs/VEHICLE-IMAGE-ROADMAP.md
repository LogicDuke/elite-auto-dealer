# Vehicle image roadmap: Aurelis Motors demo inventory

Production specification for **75 images: 15 vehicles × 5 roles**. Machine-readable twin: `demo/image-roadmap.json`, generated from the same spec and validated by `tests/image-roadmap-test.php`. Vehicle identity comes from `demo/vehicles.json`; if this document and the inventory ever disagree, the inventory wins.

Status: **produced and imported.** The approved set (75/75) is imported on the local demo site by `demo/import-images.php` (section 7).

## 1. Global style

All 75 images must look like the work of one dealership photographer at one location.

- **Look:** photorealistic premium European dealership photography with real materials and restrained, slightly cool-neutral colour grading. Natural contrast with no exaggerated HDR, no CGI or render sheen, no lens flares and no heavy vignettes.
- **Not allowed:** people, hands, watermarks, random or generated text, or third-party logos. The only branding is the vehicle's own badges. No showroom clutter and no other dealership signage.
- **Location (exteriors):** the Aurelis Motors forecourt in Brussels. Contemporary architecture in dark charcoal and warm grey stone, large clean glazing, slim brushed-metal details, understated landscaping and clean light paving. The light is soft Belgian overcast or bright-overcast daylight, with no harsh midday shadows and no public street chaos.
- **Forecourt zones** (used to vary the hero framing):

| Zone | Setting |
|---|---|
| A | In front of the full-height glazed showroom facade (dark charcoal mullions, warm interior glow kept subtle) |
| B | Against the warm grey natural-stone wall with slim brushed-metal vertical fins |
| C | At the open edge of the forecourt, low ornamental grasses and young birch trees behind, architecture partly in frame |
| D | Under the covered delivery canopy (charcoal soffit, soft even overhead light), glazed facade in the background |

- **Interiors:** photographed inside the real vehicle in soft daylight or soft dealership light, with the same grade as the exteriors. Materials must match the record exactly. **Left-hand drive in every vehicle.**
- **Screens:** switched on to a calm, dark state (map, energy flow or home screen) with **no legible text**. If clean text isn't achievable, show a dark screen.
- **Number plates (decided):** blank white Belgian-format plate with thin red border and **no characters** (front and rear). No real-looking Belgian registration, no fictional registration, and no generated plate text.

## 2. Camera language

- **Exteriors:** full-frame equivalent 35–50 mm. Camera slightly below eye level, natural perspective, verticals kept straight, no extreme wide-angle distortion. The whole car is in frame with **safe margins of about 8 % on every side**, so the 640×427 and 960×640 derivatives and the full 1536×1024 file never clip bumpers, mirrors or wheels.
- **Interiors:** 24–35 mm equivalent. No fisheye and no stretched cabins; realistic proportions.
- **Varied per vehicle:** hero angle (front-left or front-right three-quarter), forecourt zone, focal length within the range, and the architecture framing. Every vehicle has its own row in the table below.

## 3. File contract

| Item | Value |
|---|---|
| Images per vehicle | exactly 5 (`hero`, `rear`, `cockpit`, `interior`, `detail`) |
| Total | 75 |
| Delivered file | **exactly 1536 × 1024 px**, **3:2 landscape**, high-quality JPEG (`.jpg`), sRGB. This is the final web master: produced directly at this size. No larger master, no PNG, no later bulk-resize phase |
| Naming | `{stock-id-lowercase}-{role}.jpg`, e.g. `aur-26001-hero.jpg` |
| Folder | `demo/images/{stock-id-lowercase}/` (5 approved files each) |
| Featured image | `hero`; the other four form the gallery, in order rear → cockpit → interior → detail |
| Alt text | `{Make} {Model/variant} — {view}`, e.g. "BMW M4 Competition xDrive — cockpit". No "image of" / "photo of" |
| Weight | target **180–350 KB** per image; preferred maximum **450 KB**. Applies to every role, the `hero` included. Measured per file at import QA (section 7); the theme ships no compression code |
| Smaller or off-contract files | **rejected, never upscaled.** Anything smaller than 1536 × 1024, not exactly 3:2 or not sRGB JPEG goes back to production for re-export at the contract; WordPress is never relied on to crop or enlarge it |
| Scope | vehicle photographs only. The **homepage hero** is a separate art-directed asset with its own brief; its dimensions are not defined yet |
| Source | **original generated demo imagery** from factual descriptions. No manufacturer website images, no other dealers' photography, no unlicensed stock |

## 4. Continuity contract (per vehicle)

All five images of one vehicle must show the identical car:

- same body colour;
- same wheel design and caliper colour;
- same interior colour and trim;
- same generation;
- same exterior package;
- same plate treatment;
- same session light.

Any drift is a reject. Across vehicles, the colours stored in the data guarantee variety; no two cars share an exterior colour.

## 5. Overview

| Stock ID | Vehicle | Exterior / interior | Hero | Zone | Lens | Detail shot |
|---|---|---|---|---|---|---|
| AUR-26001 | BMW M4 Competition xDrive | Isle of Man Green metallic / Black Merino leather | front-left | B | 45 mm | front wheel and blue brake caliper |
| AUR-26002 | BMW 330e Touring | Mineral Grey metallic / Black Sensatec | front-right | A | 40 mm | luggage area with tailgate open |
| AUR-26003 | BMW i4 M50 | Brooklyn Grey metallic / Black Vernasca leather | front-left | C | 50 mm | liftback boot opening |
| AUR-26004 | Mercedes-Benz GLE 450 4MATIC | Obsidian Black metallic / Macchiato Beige leather | front-right | D | 40 mm | rear seats and panoramic roof |
| AUR-26005 | Mercedes-Benz C300e Estate | High-tech Silver metallic / Black Artico man-made leather | front-left | B | 40 mm | charging socket |
| AUR-26006 | Mercedes-Benz A250e | Polar White / Black Artico and Microcut | front-right | C | 35 mm | rear seats |
| AUR-26007 | Audi RS6 Avant | Nardo Grey / Black Valcona leather | front-left | A | 45 mm | oval tailpipes and rear diffuser |
| AUR-26008 | Audi Q5 55 TFSI e | Manhattan Grey metallic / Black leather and Alcantara | front-left | D | 45 mm | Matrix LED headlight |
| AUR-26009 | Audi e-tron GT quattro | Tactical Green metallic / Black leather | front-right | C | 50 mm | charging port |
| AUR-26010 | Porsche 911 Carrera | GT Silver metallic / Black leather | front-right | A | 50 mm | rear light bar and engine grille |
| AUR-26011 | Porsche Macan T | Gentian Blue metallic / Black leather with Sport-Tex | front-right | B | 45 mm | Sport-Tex seat detail |
| AUR-26012 | Range Rover Sport P440e | Varesine Blue metallic / Ebony Windsor leather | front-left | D | 45 mm | luggage area with tailgate open |
| AUR-26013 | Volvo XC60 Recharge | Denim Blue metallic / Charcoal leather | front-left | A | 40 mm | Bowers & Wilkins door speaker |
| AUR-26014 | Volkswagen Golf GTI | Moonstone Grey / Scalepaper tartan cloth | front-left | C | 35 mm | manual gear lever |
| AUR-26015 | Tesla Model Y Long Range | Pearl White Multi-Coat / Black synthetic leather | front-left | B | 40 mm | front trunk |

## 6. Vehicle specifications

### AUR-26001 · BMW M4 Competition xDrive

- **Identity:** BMW M4 Competition xDrive (Competition xDrive), 2024, G82 BMW M4 Coupé, pre-LCI (2021–2024 front lights), with the BMW Curved Display interior; coupé, 2 doors, 4 seats, petrol, automatic gearbox
- **Exterior:** Isle of Man Green metallic · **Interior:** Black Merino leather · **Body:** coupé
- **Wheels:** staggered 19/20-inch M forged double-spoke wheels in Jet Black, blue M Compound brake calipers
- **Trim/package:** M Competition exterior with carbon-fibre roof and black high-gloss Shadowline details
- **Model-specific notes:** Two-door coupé with long doors and frameless windows; large vertical kidney grille with horizontal slats; quad tailpipes; carbon roof visible in rear shot.
- **Must NOT get wrong:** four doors or M3 saloon body; small horizontal F82 kidneys; Gran Coupé roofline; red brake calipers; right-hand drive; fake "Competition" lettering on the grille.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26001-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 45 mm equivalent, eye-level slightly low camera, parked against the warm grey natural-stone wall with slim brushed-metal vertical fins. Soft Belgian overcast daylight, restrained grade. Featured image. | BMW M4 Competition xDrive — front three-quarter view |
| 2 | `aur-26001-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (B), 45 mm equivalent. | BMW M4 Competition xDrive — rear three-quarter view |
| 3 | `aur-26001-cockpit.jpg` | cockpit | From the rear seat area, slightly right of centre: thick-rimmed M leather steering wheel with red M buttons on the LEFT, BMW Curved Display (instrument and central screen in one curved panel) showing a dark navigation map without legible text, M carbon-look trim. No hands, no people, 24–35 mm equivalent, no fisheye. | BMW M4 Competition xDrive — cockpit |
| 4 | `aur-26001-interior.jpg` | interior | From the open passenger door: black Merino leather M sport seats with M-tricolour stitching, two rear seats visible (4-seat coupé). No people, 24–35 mm equivalent, natural proportions. | BMW M4 Competition xDrive — front seats |
| 5 | `aur-26001-detail.jpg` | detail | Low close-up of the left front forged wheel with the blue M Compound brake caliper and the green front wing edge; tyre sidewall without legible branding. | BMW M4 Competition xDrive — front wheel and blue brake caliper |

### AUR-26002 · BMW 330e Touring

- **Identity:** BMW 330e Touring (330e Touring M Sport), 2023, G21 BMW 3 Series Touring LCI (2022+) with slim headlights and the Curved Display; estate, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** Mineral Grey metallic · **Interior:** Black Sensatec · **Body:** estate
- **Wheels:** 18-inch M double-spoke bicolour alloy wheels, blue M Sport brake calipers
- **Trim/package:** M Sport bumpers and Shadowline trim, charging flap on the front-left wing
- **Model-specific notes:** Estate (Touring) body with roof rails; LCI headlights and taillights; plug-in hybrid has charging flap on front-left wing above the wheel.
- **Must NOT get wrong:** saloon body; pre-LCI dashboard with separate screens; X3 proportions; exhaust-free rear (it has tailpipes); right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26002-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 40 mm equivalent, eye-level slightly low camera, parked in front of the full-height glazed showroom facade (dark charcoal mullions, warm interior glow kept subtle). Soft Belgian overcast daylight, restrained grade. Featured image. | BMW 330e Touring — front three-quarter view |
| 2 | `aur-26002-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (A), 40 mm equivalent. | BMW 330e Touring — rear three-quarter view |
| 3 | `aur-26002-cockpit.jpg` | cockpit | From behind the front seats, centred slightly right: M leather steering wheel on the LEFT, Curved Display with a dark home screen and no legible text, short gear selector. No hands, no people, 24–35 mm equivalent, no fisheye. | BMW 330e Touring — cockpit |
| 4 | `aur-26002-interior.jpg` | interior | From the open driver door looking across: black Sensatec sport seats with contrast stitching, panoramic sense of space toward the rear bench. No people, 24–35 mm equivalent, natural proportions. | BMW 330e Touring — front seats |
| 5 | `aur-26002-detail.jpg` | detail | Rear three-quarter close view with the tailgate open: flat load floor, roller blind, rear seat backs upright, tow-bar socket visible in the bumper. | BMW 330e Touring — luggage area with tailgate open |

### AUR-26003 · BMW i4 M50

- **Identity:** BMW i4 M50 (M50), 2024, G26 BMW i4 M50 Gran Coupé, pre-LCI, frameless doors, Curved Display; four/five-door saloon, 5 doors, 5 seats, battery-electric, automatic gearbox
- **Exterior:** Brooklyn Grey metallic · **Interior:** Black Vernasca leather · **Body:** four/five-door saloon
- **Wheels:** 19-inch M aerodynamic bicolour wheels, blue M Sport brake calipers
- **Trim/package:** M50 exterior with closed kidney grille panel and Cerium Grey accents
- **Model-specific notes:** Five-door liftback Gran Coupé with frameless windows; closed (non-functional) kidney grille panel; no tailpipes; charging flap on the rear right quarter (closed).
- **Must NOT get wrong:** exhaust tailpipes; boot-lid-only saloon opening; iX or i7 front; open grille slats; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26003-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 50 mm equivalent, eye-level slightly low camera, parked at the open edge of the forecourt, low ornamental grasses and young birch trees behind, architecture partly in frame. Soft Belgian overcast daylight, restrained grade. Featured image. | BMW i4 M50 — front three-quarter view |
| 2 | `aur-26003-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (C), 50 mm equivalent. | BMW i4 M50 — rear three-quarter view |
| 3 | `aur-26003-cockpit.jpg` | cockpit | From the rear seat, slightly right of centre: M steering wheel on the LEFT, Curved Display showing a neutral range/energy screen without legible text, small BMW gear selector toggle. No hands, no people, 24–35 mm equivalent, no fisheye. | BMW i4 M50 — cockpit |
| 4 | `aur-26003-interior.jpg` | interior | From the open rear door: black Vernasca leather sport seats, frameless door edge visible. No people, 24–35 mm equivalent, natural proportions. | BMW i4 M50 — front seats |
| 5 | `aur-26003-detail.jpg` | detail | Rear view with the large liftback tailgate fully open showing the luggage compartment depth; no exhaust tailpipes, blue diffuser accents. | BMW i4 M50 — liftback boot opening |

### AUR-26004 · Mercedes-Benz GLE 450 4MATIC

- **Identity:** Mercedes-Benz GLE 450 4MATIC (450 4MATIC AMG Line), 2022, V167 Mercedes-Benz GLE, pre-facelift (2019–2023), five-seat configuration; SUV, 5 doors, 5 seats, petrol, automatic gearbox
- **Exterior:** Obsidian Black metallic · **Interior:** Macchiato Beige leather · **Body:** SUV
- **Wheels:** 21-inch AMG multi-spoke alloy wheels
- **Trim/package:** AMG Line exterior, panoramic sliding sunroof
- **Model-specific notes:** Large upright SUV; AMG Line front with the large star in the grille; twin widescreen dashboard; light beige interior contrasts with black exterior.
- **Must NOT get wrong:** GLE Coupé sloping roof; AMG Panamericana grille; third seat row; 2023 facelift lights; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26004-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 40 mm equivalent, eye-level slightly low camera, parked under the covered delivery canopy (charcoal soffit, soft even overhead light), glazed facade in the background. Soft Belgian overcast daylight, restrained grade. Featured image. | Mercedes-Benz GLE 450 4MATIC — front three-quarter view |
| 2 | `aur-26004-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (D), 40 mm equivalent. | Mercedes-Benz GLE 450 4MATIC — rear three-quarter view |
| 3 | `aur-26004-cockpit.jpg` | cockpit | From the rear seat centre: black dashboard with open-pore wood trim, MBUX twin 12.3-inch widescreen displays under one glass panel showing a dark map without legible text, steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Mercedes-Benz GLE 450 4MATIC — cockpit |
| 4 | `aur-26004-interior.jpg` | interior | From the open passenger door: Macchiato Beige leather front seats with black dashboard top, memory-seat controls on the door. No people, 24–35 mm equivalent, natural proportions. | Mercedes-Benz GLE 450 4MATIC — front seats |
| 5 | `aur-26004-detail.jpg` | detail | From the open rear door, slightly upward: Macchiato Beige rear bench and the open panoramic roof blind showing soft overcast sky. | Mercedes-Benz GLE 450 4MATIC — rear seats and panoramic roof |

### AUR-26005 · Mercedes-Benz C300e Estate

- **Identity:** Mercedes-Benz C300e Estate (C 300 e Estate AMG Line), 2025, S206 Mercedes-Benz C-Class Estate (2021+), plug-in hybrid; estate, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** High-tech Silver metallic · **Interior:** Black Artico man-made leather · **Body:** estate
- **Wheels:** 18-inch AMG multi-spoke alloy wheels
- **Trim/package:** AMG Line exterior with star-pattern grille, portrait central display
- **Model-specific notes:** Estate body; ex-demonstrator in near-new condition; AMG Line star grille; charging socket in the rear bumper on the right.
- **Must NOT get wrong:** C-Class saloon or E-Class Estate proportions; landscape central screen; charging flap on a front wing; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26005-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 40 mm equivalent, eye-level slightly low camera, parked against the warm grey natural-stone wall with slim brushed-metal vertical fins. Soft Belgian overcast daylight, restrained grade. Featured image. | Mercedes-Benz C300e Estate — front three-quarter view |
| 2 | `aur-26005-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (B), 40 mm equivalent. | Mercedes-Benz C300e Estate — rear three-quarter view |
| 3 | `aur-26005-cockpit.jpg` | cockpit | From the rear seat, slightly right of centre: free-standing driver display and portrait 11.9-inch central touchscreen (dark map, no legible text) AMG Line steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Mercedes-Benz C300e Estate — cockpit |
| 4 | `aur-26005-interior.jpg` | interior | From the open driver door: black Artico man-made leather seats, ambient lighting off or subtle. No people, 24–35 mm equivalent, natural proportions. | Mercedes-Benz C300e Estate — front seats |
| 5 | `aur-26005-detail.jpg` | detail | Close-up of the open charging flap in the rear bumper, right-hand side, showing the AC/DC charging socket; no cable, no visible third-party branding. | Mercedes-Benz C300e Estate — charging socket |

### AUR-26006 · Mercedes-Benz A250e

- **Identity:** Mercedes-Benz A250e (A 250 e AMG Line), 2022, W177 Mercedes-Benz A-Class hatchback, pre-facelift (2018–2022); hatchback, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** Polar White · **Interior:** Black Artico and Microcut · **Body:** hatchback
- **Wheels:** 18-inch AMG five-twin-spoke alloy wheels
- **Trim/package:** AMG Line exterior with diamond grille
- **Model-specific notes:** Compact five-door hatchback; this record is SOLD but still needs a full image set for sold-state design.
- **Must NOT get wrong:** A-Class saloon or CLA; facelift (2023) lights and grille; three-door body; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26006-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 35 mm equivalent, eye-level slightly low camera, parked at the open edge of the forecourt, low ornamental grasses and young birch trees behind, architecture partly in frame. Soft Belgian overcast daylight, restrained grade. Featured image. | Mercedes-Benz A250e — front three-quarter view |
| 2 | `aur-26006-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (C), 35 mm equivalent. | Mercedes-Benz A250e — rear three-quarter view |
| 3 | `aur-26006-cockpit.jpg` | cockpit | From the rear seat centre: twin 10.25-inch widescreen displays in one panel (dark home screen, no legible text), three round turbine vents, steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Mercedes-Benz A250e — cockpit |
| 4 | `aur-26006-interior.jpg` | interior | From the open passenger door: black Artico/Microcut sport seats with red contrast stitching. No people, 24–35 mm equivalent, natural proportions. | Mercedes-Benz A250e — front seats |
| 5 | `aur-26006-detail.jpg` | detail | From the open rear door: rear bench and rear legroom, showing the compact cabin is usable for adults; seat belts neat. | Mercedes-Benz A250e — rear seats |

### AUR-26007 · Audi RS6 Avant

- **Identity:** Audi RS6 Avant (Avant), 2023, C8 Audi RS 6 Avant (2020+), pre-2024 performance update; estate, 5 doors, 5 seats, petrol, automatic gearbox
- **Exterior:** Nardo Grey · **Interior:** Black Valcona leather · **Body:** estate
- **Wheels:** 22-inch RS five-V-spoke wheels in gloss anthracite, grey brake calipers
- **Trim/package:** wide RS body (flared arches), gloss-black honeycomb singleframe grille, black optics package
- **Model-specific notes:** Estate with very wide arches (only front doors, roof and tailgate shared with A6); oval twin tailpipes; flat matt-like Nardo Grey solid paint (not metallic).
- **Must NOT get wrong:** narrow A6 Avant body; RS 7 Sportback roofline; round tailpipes; chrome grille frame; metallic sparkle in the grey; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26007-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 45 mm equivalent, eye-level slightly low camera, parked in front of the full-height glazed showroom facade (dark charcoal mullions, warm interior glow kept subtle). Soft Belgian overcast daylight, restrained grade. Featured image. | Audi RS6 Avant — front three-quarter view |
| 2 | `aur-26007-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (A), 45 mm equivalent. | Audi RS6 Avant — rear three-quarter view |
| 3 | `aur-26007-cockpit.jpg` | cockpit | From the rear seat, slightly right of centre: flat-bottomed RS steering wheel on the LEFT, Audi virtual cockpit (dark, no legible text), twin MMI touch screens (dark), carbon trim. No hands, no people, 24–35 mm equivalent, no fisheye. | Audi RS6 Avant — cockpit |
| 4 | `aur-26007-interior.jpg` | interior | From the open driver door: black Valcona leather RS sport seats with honeycomb stitching. No people, 24–35 mm equivalent, natural proportions. | Audi RS6 Avant — front seats |
| 5 | `aur-26007-detail.jpg` | detail | Low rear close-up of the two large oval RS tailpipes and gloss-black diffuser, Nardo Grey bumper edge. | Audi RS6 Avant — oval tailpipes and rear diffuser |

### AUR-26008 · Audi Q5 55 TFSI e

- **Identity:** Audi Q5 55 TFSI e (55 TFSI e quattro S line), 2023, FY Audi Q5 facelift (2021+), standard SUV roof; SUV, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** Manhattan Grey metallic · **Interior:** Black leather and Alcantara · **Body:** SUV
- **Wheels:** 20-inch five-V-spoke S line alloy wheels
- **Trim/package:** S line exterior, Matrix LED headlights, black roof rails
- **Model-specific notes:** Standard Q5 SUV body (not Sportback); facelift octagonal grille; plug-in hybrid charging flap on the rear left quarter (closed).
- **Must NOT get wrong:** Q5 Sportback coupé roof; pre-2021 headlights; SQ5 quad tailpipes; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26008-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 45 mm equivalent, eye-level slightly low camera, parked under the covered delivery canopy (charcoal soffit, soft even overhead light), glazed facade in the background. Soft Belgian overcast daylight, restrained grade. Featured image. | Audi Q5 55 TFSI e — front three-quarter view |
| 2 | `aur-26008-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (D), 45 mm equivalent. | Audi Q5 55 TFSI e — rear three-quarter view |
| 3 | `aur-26008-cockpit.jpg` | cockpit | From the rear seat centre: S line steering wheel on the LEFT, virtual cockpit and 10.1-inch MMI touchscreen (dark map, no legible text). No hands, no people, 24–35 mm equivalent, no fisheye. | Audi Q5 55 TFSI e — cockpit |
| 4 | `aur-26008-interior.jpg` | interior | From the open passenger door: black leather and Alcantara S line sport seats with S embossing. No people, 24–35 mm equivalent, natural proportions. | Audi Q5 55 TFSI e — front seats |
| 5 | `aur-26008-detail.jpg` | detail | Close-up of the left Matrix LED headlight with daytime running light lit, Manhattan Grey bonnet edge and grille corner. | Audi Q5 55 TFSI e — Matrix LED headlight |

### AUR-26009 · Audi e-tron GT quattro

- **Identity:** Audi e-tron GT quattro (quattro), 2023, Audi e-tron GT quattro, pre-facelift (2021–2024); four/five-door saloon, 4 doors, 4 seats, battery-electric, automatic gearbox
- **Exterior:** Tactical Green metallic · **Interior:** Black leather · **Body:** four/five-door saloon
- **Wheels:** 20-inch aero five-twin-arm wheels in dark grey
- **Trim/package:** fixed panoramic glass roof, black styling package
- **Model-specific notes:** Low four-door grand tourer with fastback; masked dark singleframe grille; full-width rear light bar; glass roof (not carbon).
- **Must NOT get wrong:** Porsche Taycan front lights or badge; RS e-tron GT carbon roof; exhaust tailpipes; five-door hatch opening; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26009-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 50 mm equivalent, eye-level slightly low camera, parked at the open edge of the forecourt, low ornamental grasses and young birch trees behind, architecture partly in frame. Soft Belgian overcast daylight, restrained grade. Featured image. | Audi e-tron GT quattro — front three-quarter view |
| 2 | `aur-26009-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (C), 50 mm equivalent. | Audi e-tron GT quattro — rear three-quarter view |
| 3 | `aur-26009-cockpit.jpg` | cockpit | From the rear seat, slightly right of centre: e-tron GT steering wheel on the LEFT, virtual cockpit and central MMI screen showing a neutral energy flow graphic without legible text. No hands, no people, 24–35 mm equivalent, no fisheye. | Audi e-tron GT quattro — cockpit |
| 4 | `aur-26009-interior.jpg` | interior | From the open driver door: black leather sport seats, low seating position, glass roof visible. No people, 24–35 mm equivalent, natural proportions. | Audi e-tron GT quattro — front seats |
| 5 | `aur-26009-detail.jpg` | detail | Close-up of the open charging flap on the front left wing behind the wheel, showing the charging socket; no cable. | Audi e-tron GT quattro — charging port |

### AUR-26010 · Porsche 911 Carrera

- **Identity:** Porsche 911 Carrera (Carrera), 2022, 992.1 Porsche 911 Carrera (2019–2024) coupé; coupé, 2 doors, 4 seats, petrol, automatic gearbox
- **Exterior:** GT Silver metallic · **Interior:** Black leather · **Body:** coupé
- **Wheels:** staggered 19/20-inch Carrera wheels in silver, black brake calipers
- **Trim/package:** 992 wide rear body (shared by all 992 Carrera models), sport exhaust tailpipes, retractable rear spoiler lowered
- **Model-specific notes:** Rear-engine coupé, two doors; flush pop-out door handles; full-width rear light bar; all 992 Carreras share the wide rear body.
- **Must NOT get wrong:** 991-generation dashboard or lights; fixed rear wing (GT3); Targa roll bar; Turbo side intakes; centre-lock wheels; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26010-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 50 mm equivalent, eye-level slightly low camera, parked in front of the full-height glazed showroom facade (dark charcoal mullions, warm interior glow kept subtle). Soft Belgian overcast daylight, restrained grade. Featured image. | Porsche 911 Carrera — front three-quarter view |
| 2 | `aur-26010-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (A), 50 mm equivalent. | Porsche 911 Carrera — rear three-quarter view |
| 3 | `aur-26010-cockpit.jpg` | cockpit | From the passenger seat area: central analogue rev counter flanked by two digital displays, short PDK toggle selector, 10.9-inch central screen dark with no legible text, steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Porsche 911 Carrera — cockpit |
| 4 | `aur-26010-interior.jpg` | interior | From the open passenger door: black leather sport seats and the two small rear seats (2+2). No people, 24–35 mm equivalent, natural proportions. | Porsche 911 Carrera — front seats |
| 5 | `aur-26010-detail.jpg` | detail | Rear close-up of the full-width LED light strip and the vertical engine-lid slats with the integrated third brake light. | Porsche 911 Carrera — rear light bar and engine grille |

### AUR-26011 · Porsche Macan T

- **Identity:** Porsche Macan T (T), 2024, Porsche Macan T, 95B.3 facelift (2022–2024), combustion model; SUV, 5 doors, 5 seats, petrol, automatic gearbox
- **Exterior:** Gentian Blue metallic · **Interior:** Black leather with Sport-Tex · **Body:** SUV
- **Wheels:** 20-inch Macan Design wheels in Titanium Grey
- **Trim/package:** Macan T details: Agate Grey mirror caps, side blades and lettering, dark tailpipes
- **Model-specific notes:** Compact SUV with full-width rear light strip; Agate Grey accents are part of the T identity and must appear in every exterior shot.
- **Must NOT get wrong:** electric Macan (2024 EV with split headlights and no grille); Cayenne proportions; GTS red accents; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26011-hero.jpg` | hero (featured) | Front three-quarter (front-right), whole car in frame with safe margins, 45 mm equivalent, eye-level slightly low camera, parked against the warm grey natural-stone wall with slim brushed-metal vertical fins. Soft Belgian overcast daylight, restrained grade. Featured image. | Porsche Macan T — front three-quarter view |
| 2 | `aur-26011-rear.jpg` | rear | Rear three-quarter (rear-left), whole car in frame with safe margins, same session and forecourt zone as the hero (B), 45 mm equivalent. | Porsche Macan T — rear three-quarter view |
| 3 | `aur-26011-cockpit.jpg` | cockpit | From the rear seat centre: GT sport steering wheel on the LEFT, analogue central rev counter, 10.9-inch central screen dark without legible text, touch centre console. No hands, no people, 24–35 mm equivalent, no fisheye. | Porsche Macan T — cockpit |
| 4 | `aur-26011-interior.jpg` | interior | From the open driver door: black leather sport seats with Sport-Tex (grid-pattern) centre panels. No people, 24–35 mm equivalent, natural proportions. | Porsche Macan T — front seats |
| 5 | `aur-26011-detail.jpg` | detail | Close-up of the driver seat showing the Sport-Tex centre section, stitching and side bolster. | Porsche Macan T — Sport-Tex seat detail |

### AUR-26012 · Range Rover Sport P440e

- **Identity:** Range Rover Sport P440e (P440e Dynamic SE), 2024, L461 Range Rover Sport (2022+), plug-in hybrid P440e; SUV, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** Varesine Blue metallic · **Interior:** Ebony Windsor leather · **Body:** SUV
- **Wheels:** 22-inch split-spoke alloy wheels in Gloss Dark Grey
- **Trim/package:** Dynamic SE exterior, flush deployable door handles, contrast roof not used (body-colour roof)
- **Model-specific notes:** Minimalist slab-sided SUV, very slim headlights and hidden taillights; flush door handles; body-colour roof.
- **Must NOT get wrong:** full-size Range Rover (L460) vertical taillights; Velar or older L494 Sport; contrast black roof; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26012-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 45 mm equivalent, eye-level slightly low camera, parked under the covered delivery canopy (charcoal soffit, soft even overhead light), glazed facade in the background. Soft Belgian overcast daylight, restrained grade. Featured image. | Range Rover Sport P440e — front three-quarter view |
| 2 | `aur-26012-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (D), 45 mm equivalent. | Range Rover Sport P440e — rear three-quarter view |
| 3 | `aur-26012-cockpit.jpg` | cockpit | From the rear seat centre: Pivi Pro 13.1-inch curved floating touchscreen (dark map, no legible text), interactive driver display, steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Range Rover Sport P440e — cockpit |
| 4 | `aur-26012-interior.jpg` | interior | From the open passenger door: Ebony Windsor leather seats with perforation (heated/ventilated), panoramic roof above. No people, 24–35 mm equivalent, natural proportions. | Range Rover Sport P440e — front seats |
| 5 | `aur-26012-detail.jpg` | detail | Rear view with the powered tailgate open, flat load floor, tow bar visible, suspension at loading height. | Range Rover Sport P440e — luggage area with tailgate open |

### AUR-26013 · Volvo XC60 Recharge

- **Identity:** Volvo XC60 Recharge (T6 Recharge AWD Plus Dark), 2023, Volvo XC60 second generation, 2022+ update with Google-based infotainment; SUV, 5 doors, 5 seats, plug-in hybrid, automatic gearbox
- **Exterior:** Denim Blue metallic · **Interior:** Charcoal leather · **Body:** SUV
- **Wheels:** 20-inch five-double-spoke black diamond-cut wheels
- **Trim/package:** Plus Dark exterior (gloss-black grille and window trim)
- **Model-specific notes:** "Thor's hammer" headlights, Plus Dark black trim instead of chrome; plug-in hybrid charge flap on the front-left wing (closed).
- **Must NOT get wrong:** XC90 (three-row) or XC40 proportions; chrome window trim; landscape screen; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26013-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 40 mm equivalent, eye-level slightly low camera, parked in front of the full-height glazed showroom facade (dark charcoal mullions, warm interior glow kept subtle). Soft Belgian overcast daylight, restrained grade. Featured image. | Volvo XC60 Recharge — front three-quarter view |
| 2 | `aur-26013-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (A), 40 mm equivalent. | Volvo XC60 Recharge — rear three-quarter view |
| 3 | `aur-26013-cockpit.jpg` | cockpit | From the rear seat centre: portrait 9-inch centre display (dark map, no legible text), crystal gear selector, steering wheel on the LEFT. No hands, no people, 24–35 mm equivalent, no fisheye. | Volvo XC60 Recharge — cockpit |
| 4 | `aur-26013-interior.jpg` | interior | From the open passenger door: Charcoal leather seats, light-coloured headliner, metal mesh aluminium décor. No people, 24–35 mm equivalent, natural proportions. | Volvo XC60 Recharge — front seats |
| 5 | `aur-26013-detail.jpg` | detail | Close-up of the front door panel with the Bowers & Wilkins metal speaker grille and Charcoal leather armrest. | Volvo XC60 Recharge — Bowers & Wilkins door speaker |

### AUR-26014 · Volkswagen Golf GTI

- **Identity:** Volkswagen Golf GTI (GTI), 2022, Mk8 Volkswagen Golf GTI (2020–2023), pre-facelift, five-door; hatchback, 5 doors, 5 seats, petrol, manual gearbox
- **Exterior:** Moonstone Grey · **Interior:** Scalepaper tartan cloth · **Body:** hatchback
- **Wheels:** 18-inch Richmond diamond-cut alloy wheels, red brake calipers
- **Trim/package:** GTI front with red line across the grille and LED light bar, X-pattern honeycomb fog lights in the bumper
- **Model-specific notes:** Five-door hatchback; manual gearbox (no DSG paddles or shift-by-wire selector); twin tailpipes, one each side.
- **Must NOT get wrong:** DSG gear selector; Mk7 dashboard; 2024 facelift illuminated VW logo; three-door body; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26014-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 35 mm equivalent, eye-level slightly low camera, parked at the open edge of the forecourt, low ornamental grasses and young birch trees behind, architecture partly in frame. Soft Belgian overcast daylight, restrained grade. Featured image. | Volkswagen Golf GTI — front three-quarter view |
| 2 | `aur-26014-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (C), 35 mm equivalent. | Volkswagen Golf GTI — rear three-quarter view |
| 3 | `aur-26014-cockpit.jpg` | cockpit | From the rear seat centre: GTI steering wheel with red stitching on the LEFT, digital cockpit and 10-inch central screen (dark, no legible text), SIX-SPEED MANUAL gear lever with golf-ball knob. No hands, no people, 24–35 mm equivalent, no fisheye. | Volkswagen Golf GTI — cockpit |
| 4 | `aur-26014-interior.jpg` | interior | From the open driver door: Scalepaper tartan cloth GTI sport seats with red accents. No people, 24–35 mm equivalent, natural proportions. | Volkswagen Golf GTI — front seats |
| 5 | `aur-26014-detail.jpg` | detail | Close-up of the six-speed manual gear lever with the dimpled golf-ball knob, gaiter with red stitching and the centre console. | Volkswagen Golf GTI — manual gear lever |

### AUR-26015 · Tesla Model Y Long Range

- **Identity:** Tesla Model Y Long Range (Long Range AWD), 2024, Tesla Model Y Long Range AWD, pre-refresh (2020–2024), Berlin-built; SUV, 5 doors, 5 seats, battery-electric, automatic gearbox
- **Exterior:** Pearl White Multi-Coat · **Interior:** Black synthetic leather · **Body:** SUV
- **Wheels:** 19-inch Gemini wheels with aero covers
- **Trim/package:** black exterior window trim, fixed full glass roof
- **Model-specific notes:** Smooth grille-less nose; pre-refresh separate taillights (no full-width light bar); no charging cable visible.
- **Must NOT get wrong:** 2025 "Juniper" refresh light bar; Model 3 saloon body; instrument cluster behind the wheel; Apple CarPlay/Android Auto UI; right-hand drive.

| # | File | Role | Shot | Alt text |
|---|---|---|---|---|
| 1 | `aur-26015-hero.jpg` | hero (featured) | Front three-quarter (front-left), whole car in frame with safe margins, 40 mm equivalent, eye-level slightly low camera, parked against the warm grey natural-stone wall with slim brushed-metal vertical fins. Soft Belgian overcast daylight, restrained grade. Featured image. | Tesla Model Y Long Range — front three-quarter view |
| 2 | `aur-26015-rear.jpg` | rear | Rear three-quarter (rear-right), whole car in frame with safe margins, same session and forecourt zone as the hero (B), 40 mm equivalent. | Tesla Model Y Long Range — rear three-quarter view |
| 3 | `aur-26015-cockpit.jpg` | cockpit | From the rear seat centre: single 15-inch landscape centre touchscreen (dark map, no legible text), NO driver instrument cluster, round steering wheel on the LEFT with two scroll wheels. No hands, no people, 24–35 mm equivalent, no fisheye. | Tesla Model Y Long Range — cockpit |
| 4 | `aur-26015-interior.jpg` | interior | From the open passenger door: black synthetic leather seats, minimalist full-width dashboard trim strip, glass roof above. No people, 24–35 mm equivalent, natural proportions. | Tesla Model Y Long Range — front seats |
| 5 | `aur-26015-detail.jpg` | detail | Front view with the bonnet open showing the empty front luggage compartment (frunk). | Tesla Model Y Long Range — front trunk |

## 7. Import (implemented)

**Commands** (from the WordPress root, theme active, after `demo/seed.php`):

```sh
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php            # import / update
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php validate   # QA gate only, writes nothing
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php cleanup    # remove the demo images only
wp eval-file wp-content/themes/elite-auto-dealer/tests/image-import-test.php        # verify (leaves the 75 images imported)
```

CLI only: no admin screen, REST route or upload endpoint.

1. **Source:** the approved files live in `demo/images/{stock-id-lowercase}/` with the exact filenames above. They are local development assets: `/demo/images/` is Git-ignored, and the approved set is archived in Google Drive (`aurelis-motors-75-approved-images.zip`). To set up a machine, unpack that ZIP so each file lands at its manifest `path`.
2. The importer reads `demo/image-roadmap.json` and processes only slots whose file exists. It never fetches remote URLs.
   **QA gate before any attachment is created** (`validate` runs it alone):
   - the file is readable, has a `.jpg` extension and fully decodes as a JPEG (`image/jpeg`);
   - it is exactly the `image_contract` 1536 × 1024 px (3:2);
   - it is sRGB (an embedded ICC profile must be sRGB; without one, a 3-channel JPEG counts as sRGB);
   - it weighs at most the preferred 450 KB (460,800 bytes). 180–350 KB is the target, and 351–450 KB still passes.

   A failing file is reported and skipped, never resized, upscaled or recompressed.
3. **Vehicle match:** the vehicle is the single post with that stock ID **and** `_eda_demo = aurelis-demo`. If a real (non-demo) or duplicated vehicle holds the stock ID, its images are skipped with a warning.
4. **Attachment identity:** post meta, never titles or file names:
   - `_eda_demo` = dataset id;
   - `_eda_demo_image` = canonical filename;
   - `_eda_demo_image_checksum` = SHA-256 of the source.
5. **Idempotency:**
   - A rerun with identical files creates nothing: 75 reused, same IDs and relationships.
   - A changed source (checksum differs) replaces the file and its derivatives **in place**: same attachment ID and path, so the relationships stay and no orphan is created.
   - Two attachments with one identity are reported and left alone. Untagged media is never touched.
6. **Relationships:** `hero` → featured image (`_thumbnail_id`). `rear`, `cockpit`, `interior`, `detail` → `_eda_gallery` in that order (the hero is not repeated), written through the theme's schema sanitiser. They are rebuilt from the tagged attachments on every run, so a partial run never drops existing links.
7. **Attachment data:**
   - alt text from the manifest goes to `_wp_attachment_image_alt`;
   - the title is the alt text, and the caption and description are empty;
   - the MIME type is `image/jpeg`, and the parent is the vehicle.
8. **Cleanup** (`import-images.php cleanup`, also run first by `seed.php cleanup`):
   - it deletes only attachments carrying **both** `_eda_demo` and `_eda_demo_image`, together with their files;
   - featured-image references go with the attachment, and gallery references are stripped, so no broken IDs remain;
   - untagged or half-tagged uploads survive, even with a demo-like file name.

   On Windows hosts whose uploads path uses `C:/`, core leaves derivative files behind (`path_is_absolute()` expects `C:\`). The cleanup therefore also removes each attachment's own listed sizes, confined to its folder.
9. **Local site (3 October 2026):** 75 tagged attachments; 15 vehicles with the hero as featured image and a 4-image gallery; 525 files in uploads (75 originals + 6 sizes each, no `eda-vehicle-large` copy).
10. WordPress generates the `eda-vehicle-card` (640×427) and `eda-vehicle-medium` (960×640) derivatives. `eda-vehicle-large` (1536×1024) equals the source, so WordPress keeps the original for it, and core sizes at or above the source width (`1536x1536`, `2048x2048`) are skipped too. WordPress never upscales, and no derivative is larger than the source. No custom resizing.

## 8. Open decisions

- **Plate treatment (decided):** blank white Belgian-format plate with thin red border and no characters on every exterior image. Do not generate "AURELIS" or any other plate text.
- **Generation tool and model,** and whether image-to-image reference passes are used to hold continuity within a vehicle.
- **Who approves each image** against this contract, and the reject criteria for brand-detail accuracy (grille, lights, dashboard family).
- **Storage (decided):** the approved 1536 × 1024 JPEGs are archived outside Git in Google Drive (`04_Vehicle_Images/Approved_Web_Images`). Git keeps the contract, manifest, importer and tests; `demo/images/` is Git-ignored. At 75 × 180–450 KB the full set is roughly 14–34 MB, so whether a copy ships inside a demo package is a packaging decision.
- **Alt-text language:** English for now (consistent with English term names), with nl/fr alt text deferred.
- **Sold vehicle** (AUR-26006): it gets a full set here; whether sold vehicles keep their full gallery publicly is a design-phase decision.

