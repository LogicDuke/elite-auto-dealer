# Demo inventory: Aurelis Motors

Generated from `demo/vehicles.json` (the canonical source). Fictional dealership: **Aurelis Motors**, premium pre-owned automobiles, Brussels. All VINs are fictional (`DEMAUR…`). See [ARCHITECTURE.md](ARCHITECTURE.md#demo-inventory) for seeding and cleanup.

★ = featured. Homepage order: M4 Competition, RS6 Avant, 911 Carrera, i4 M50, e-tron GT, Range Rover Sport (`featured_order` 1–6). Monthly finance figures are stored but not displayed (see "Approved decisions").

| Stock ID | Vehicle | Variant | First reg. | Mileage | Fuel | Power | Gearbox | Battery / EV range (WLTP) | Price | VAT | Availability | Condition |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| AUR-26001 | BMW M4 Competition xDrive | Competition xDrive | 2024-03 | 18.400 km | Petrol | 375 kW / 510 hp | automatic | — | € 84.900 | deductible | available ★ | used |
| AUR-26002 | BMW 330e Touring | 330e Touring M Sport | 2023-05 | 54.800 km | Plug-in hybrid | 215 kW / 292 hp | automatic | 12.0 kWh / 56 km | € 36.950 | deductible | available | used |
| AUR-26003 | BMW i4 M50 | M50 | 2024-02 | 21.300 km | Electric | 400 kW / 544 hp | automatic | 80.7 kWh / 497 km | € 52.900 | deductible | available ★ | used |
| AUR-26004 | Mercedes-Benz GLE 450 4MATIC | 450 4MATIC AMG Line | 2022-04 | 67.200 km | Petrol | 270 kW / 367 hp | automatic | — | € 58.900 | margin | reserved | used |
| AUR-26005 | Mercedes-Benz C300e Estate | C 300 e Estate AMG Line | 2025-06 | 9.400 km | Plug-in hybrid | 230 kW / 313 hp | automatic | 25.4 kWh / 108 km | € 58.950 | deductible | available | demo |
| AUR-26006 | Mercedes-Benz A250e | A 250 e AMG Line | 2022-09 | 41.900 km | Plug-in hybrid | 160 kW / 218 hp | automatic | 15.6 kWh / 68 km | € 27.450 | margin | sold | used |
| AUR-26007 | Audi RS6 Avant | Avant | 2023-01 | 38.700 km | Petrol | 441 kW / 600 hp | automatic | — | € 109.900 | margin | available ★ | used |
| AUR-26008 | Audi Q5 55 TFSI e | 55 TFSI e quattro S line | 2023-03 | 46.300 km | Plug-in hybrid | 270 kW / 367 hp | automatic | 17.9 kWh / 62 km | € 44.900 | deductible | available | used |
| AUR-26009 | Audi e-tron GT quattro | quattro | 2023-07 | 24.800 km | Electric | 350 kW / 476 hp | automatic | 83.7 kWh / 472 km | € 69.900 | deductible | available ★ | used |
| AUR-26010 | Porsche 911 Carrera | Carrera | 2022-05 | 32.100 km | Petrol | 283 kW / 385 hp | automatic | — | € 112.500 | margin | reserved ★ | used |
| AUR-26011 | Porsche Macan T | T | 2024-04 | 17.600 km | Petrol | 195 kW / 265 hp | automatic | — | € 72.900 | deductible | available | used |
| AUR-26012 | Range Rover Sport P440e | P440e Dynamic SE | 2024-01 | 22.900 km | Plug-in hybrid | 324 kW / 440 hp | automatic | 38.2 kWh / 117 km | € 98.500 | deductible | available ★ | used |
| AUR-26013 | Volvo XC60 Recharge | T6 Recharge AWD Plus Dark | 2023-02 | 52.400 km | Plug-in hybrid | 250 kW / 340 hp | automatic | 18.8 kWh / 81 km | € 41.950 | margin | available | used |
| AUR-26014 | Volkswagen Golf GTI | GTI | 2022-03 | 63.500 km | Petrol | 180 kW / 245 hp | manual | — | € 29.950 | margin | available | used |
| AUR-26015 | Tesla Model Y Long Range | Long Range AWD | 2024-06 | 35.800 km | Electric | 378 kW / 514 hp | automatic | 75.0 kWh / 533 km | € 36.900 | deductible | available | used |

## Spread

- **Price:** € 27.450 – € 112.500
- **Mileage:** 9.400 km – 67.200 km
- **Fuel:** electric 3, petrol 6, plug-in-hybrid 6
- **VAT regime:** deductible 9, margin 6
- **Availability:** available 12, reserved 2, sold 1
- **Condition:** demo 1, used 14
- **Transmission:** automatic 14, manual 1
- **Body:** coupe 2, estate 3, hatchback 2, sedan 2, suv 6
- **Equipment vocabulary:** 37 shared terms

## Naming and data notes

- **Model = model family, Variant = trim** (project rule):
  - 330e Touring → model 3 Series;
  - C300e / A250e → C-Class / A-Class;
  - GLE 450 4MATIC → GLE;
  - RS6 → "RS 6" (Audi's spelling).
- **Porsche Macan → Macan T.** A specific combustion variant was chosen within the requested Macan class: 2.0 turbo, 195 kW, PDK.
- **Range Rover Sport → make Land Rover.** Range Rover is a Land Rover model line.
- **Volvo XC60 Recharge = T6 Recharge AWD,** a plug-in hybrid. "Recharge" is Volvo's branding for its plug-in models.
- **Mercedes GLE 450 is a mild hybrid,** classed as petrol. Mild hybrids can't be plugged in or driven electrically; using the "hybrid" fuel term would mislead.
- **Body types:** BMW i4 (Gran Coupé liftback) and Audi e-tron GT (four-door coupé) are classed as sedan, as Belgian portals usually list them.
- **Battery capacity** is the manufacturer's headline figure: gross for plug-in hybrids, usable (net) for EVs, as usually quoted in Belgian listings.
- **Fuel coverage:** the requested list contains no diesel and no full (non-plug-in) hybrid, so those fuel terms have no demo vehicles.
- **Demo marker:** `_eda_demo = aurelis-demo` on every vehicle, and on make, model and equipment terms the seeder created.

## Approved decisions

- **Finance:**
  - `finance_monthly` values are kept in the records but are **not displayed publicly**;
  - display is enabled only once a compliant representative credit example exists (see ARCHITECTURE "Design-phase requirements").
- **Identity:**
  - the local demo site's title is "Aurelis Motors" and its tagline "Premium pre-owned automobiles" (WordPress options, not theme code);
  - no phone, WhatsApp or enquiry contact data is seeded until safe fictional data is approved.
- **Specifications:**
  - values are realistic for these models but are demo data, not verified against a specific vehicle;
  - **Tesla Model Y power (378 kW / 514 hp)** is a representative homologation-style listing value, not a manufacturer-published claim; Tesla does not publish power ratings.
- **Term language:** make, model and equipment terms stay in English for this phase.
- **Sold vehicle:** the A250e keeps its price in the database. The design phase shows SOLD clearly and hides purchase CTAs and finance promotion.
- **Packaging:** `demo/` stays in the repository. Client release packages may exclude it when a clean install is required.
- **Fuel coverage:** the current mix (no diesel, no full hybrid) is accepted. No vehicle will be swapped for coverage.
