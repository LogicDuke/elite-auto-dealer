# Vehicle Make / Model catalogue

The theme ships a default **Make → Model family** catalogue for the Belgian/European used-car market, so a dealership can enter stock from day one without building taxonomies by hand. It provides **defaults, not a closed list**: staff can always add a make or model the catalogue lacks.

- **Source:** `bin/build-vehicle-catalogue.py`, the editable list, which generates `data/vehicle-catalogue.json`. Currently 49 makes and 428 model families.
- **Code:** `inc/vehicle-catalogue.php` (seeding and versioning), `inc/vehicle-taxonomies.php` (Models screen rules), `inc/vehicle-meta.php` (admin selector), `inc/inventory-query.php` (shared make/model data, public search state).
- **Test:** `wp eval-file wp-content/themes/elite-auto-dealer/tests/catalogue-test.php`.

## Data model (unchanged)

| Level | Storage | Example |
|---|---|---|
| Make | `vehicle_make` taxonomy | BMW |
| Model family | `vehicle_model` taxonomy; term meta `eda_make` = make term ID | M4 |
| Variant / trim | `variant` vehicle meta (text) | Competition xDrive |

The catalogue only contains model **families**. Trims, engines and packages ("M4 Competition xDrive", "40 TFSI", "AMG Line") never go into Model; the test rejects names that look like trims.

## Editing the catalogue (generator)

The catalogue is maintained in `bin/build-vehicle-catalogue.py`, not by hand-editing the JSON.

```sh
python bin/build-vehicle-catalogue.py           # regenerate data/vehicle-catalogue.json
python bin/build-vehicle-catalogue.py --check   # exit 1 if the committed JSON differs from the generator
```

- **Runtime:** Python 3, standard library only, no network access. Run it from the theme root.
- **Deterministic:** makes are sorted by slug and models keep the order they are listed in, so regenerating an unchanged list is byte-identical.
- **Validation:** duplicate makes or models, empty names, unknown or non-normalised explicit slugs, and model-slug collisions that survive prefixing all fail the build.
- **Slugs:**
  - names shared by two makes are make-prefixed automatically;
  - `EXPLICIT_SLUGS` gives readable slugs to bare numbers and symbols (`polestar-2`, `smart-1`, `renault-5`, `honda-e`).
- **Check:** `tests/catalogue-test.php` runs `--check`, so a hand-edited or stale JSON fails the test suite.
- **Packaging:** `bin/` is a development tool, excluded from `git archive` release zips.

### Version bump procedure

1. Edit `CATALOGUE` (and `EXPLICIT_SLUGS` if needed) in `bin/build-vehicle-catalogue.py`.
2. Raise `VERSION` in the generator **and** `EDA_CATALOGUE_VERSION` in `inc/vehicle-catalogue.php` to the same number.
3. Run `python bin/build-vehicle-catalogue.py`, then `wp eval-file wp-content/themes/elite-auto-dealer/tests/catalogue-test.php`.
4. Commit the generator, the JSON and the constant together. Existing installs seed the new entries the next time staff open wp-admin.

## Catalogue file format

```json
{ "version": 1, "makes": [ { "name": "Audi", "slug": "audi", "models": [ "A1", "RS 6", { "name": "500", "slug": "fiat-500" } ] } ] }
```

- A model is a **string** (its slug is WordPress `sanitize_title()` of the name) or an **object** with an explicit slug.
- **Model slugs must be unique across the whole catalogue** (WordPress requires unique slugs per taxonomy).
  - Names shared by two makes get make-prefixed slugs (`abarth-500` / `fiat-500`, `cupra-leon` / `seat-leon`).
  - Names that would slugify badly get readable slugs (`smart-1`, `polestar-2`, `honda-e`).
- The test checks:
  - required manufacturers present, version match;
  - unique make slugs and names; no duplicate model within a make; globally unique model slugs; no trim-like names.

## Seeding (`eda_seed_catalogue()`)

The seeder is idempotent and conservative:

1. **Makes:** matched by slug, then by name (accent- and case-insensitive: "Skoda" = "Škoda"). Missing makes are created.
2. **Models:** matched by name within the same make, then by slug.
   - A slug match that is already linked to this make → reused.
   - A slug match with **no** make and the same name → linked to this make (repairs orphans).
   - A slug used by a different make or a differently named term → **left alone** and reported as a conflict.
   - Otherwise the model is created and linked with `eda_make`.
3. **Never:** renames terms, changes an existing make link, deletes terms, or touches vehicle posts.
4. **Marker:** catalogue terms get term meta `_eda_catalogue = 1`. This is not the demo marker. Demo cleanup never deletes catalogue terms, even when the demo created them first.

On the demo site the first run created 41 makes and 413 models, and adopted the 8 makes and 15 models the demo inventory already used. The second run created nothing.

## Versioning: how additions reach existing installs

- `EDA_CATALOGUE_VERSION` (PHP constant) must equal `version` in the JSON; the test enforces this.
- The installed version is stored in the autoloaded option `eda_catalogue_version`.
- `eda_maybe_seed_catalogue()` runs the seed only when the installed version is lower, then stores the new version. A transient lock prevents parallel runs.
- **When it is checked:**
  - on theme activation (`after_switch_theme`);
  - on `admin_init` for logged-in staff (`edit_vehicles`), excluding AJAX.

  It is never checked on front-end requests or anonymous `admin-post` requests. The cost per admin page is one autoloaded option read; the seed itself runs once per version.
- **To ship catalogue additions:** add entries to the JSON, raise `version` **and** `EDA_CATALOGUE_VERSION`, run the catalogue test. Existing sites seed the new entries the next time staff open wp-admin. Existing terms and dealer links are untouched.
- **Force a re-seed** (e.g. after restoring a database): delete the option (`wp option delete eda_catalogue_version`), then load any wp-admin page.

## Admin workflow

**Adding or editing a vehicle** (Vehicles → Add new / Edit, "Vehicle details" box):

1. **Make:** a select with the full catalogue plus any dealer-created makes.
2. **Model:** stays empty until a make is chosen, then lists only that make's models (`assets/js/make-model.js`). Without JavaScript, the server renders the models of the saved make.
3. **Variant / trim:** a separate free-text field, e.g. "Competition xDrive".
4. **On save** the pair is validated: a model is stored only if it belongs to the chosen make; otherwise the model is cleared, never mis-linked. No make clears both.

The free-text Make/Model tag boxes are hidden (block editor sidebar, classic meta box and Quick Edit), so the selector is the single entry path.

**Missing make or model:** the selector shows two links.

- **Add a make:** Vehicles → Makes → add the name.
- **Add a model:** Vehicles → Models → add the model family name **and choose its make**, which is required.
  - A model without a make is refused with "Choose the make this model belongs to."
  - Editing a model never wipes its make link with an empty choice.
  - The Models list has a **Make** column; models without a make show **No make**.

Back in the vehicle editor, the new make or model appears straight away.

## Public search vs. admin catalogue

| | Makes | Models |
|---|---|---|
| **Admin selector** | Full catalogue + dealer-created (`hide_empty = false`) | All models of the chosen make |
| **Public search** (homepage + inventory) | Only makes with **available or reserved** vehicles | Only models with **available or reserved** vehicles, and only those of the selected make |

**Sold vehicles do not keep a filter choice alive.** Sold cars stay published (their pages remain reachable, and listing visibility is a separate design decision), but a make or model whose only vehicles are sold disappears from the public Make/Model filters. Term counts (`hide_empty`) can't express this because sold cars still count as posts, so the rule is explicit:

- `eda_browseable_vehicle_ids()` runs one meta query for published vehicles with `_eda_availability` in `eda_filter_stock_statuses()` (`available`, `reserved`). It's cached in the object cache until posts, post meta or term relationships change.
- The filter terms are then `get_terms( array( 'object_ids' => $ids ) )`: only makes and models attached to that stock.
- Vehicles without a stated availability don't count either. Set availability on every vehicle.
- The demo shows the effect: A-Class is hidden (its only car, the A250e, is sold); Mercedes-Benz stays (GLE reserved, C-Class available).
- The admin selector is unaffected and always offers the full catalogue.

Both use one data function, `eda_make_model_data( $inventory_only, $field )`, one option renderer, `eda_model_options()`, and one script, `make-model.js`. The homepage and inventory share the same template part (`template-parts/vehicle-search.php`).

Catalogue term archives without stock (e.g. `/vehicles/make/abarth/`) are `noindex, follow` with no canonical, so the catalogue does not create hundreds of thin indexable pages.

## Public cascading behaviour

- **Server first (works without JS):** `eda_vehicle_search_state()` validates the request.
  - An unknown make is ignored.
  - A model that doesn't belong to the selected make is reset.
  - `/vehicles/?make=bmw` renders a Model select with only BMW stock models (3 Series, i4, M4).
- **Query:** `?make=bmw&model=rs-6` drops the conflicting model and shows the BMWs instead of an empty page. A make that doesn't exist at all returns WordPress's normal 404.
- **JavaScript enhancement:** changing Make rebuilds the Model options instantly, with no reload and no network request.
  - The page carries the stock model list once (`data-options` JSON).
  - The selected model is kept only if it belongs to the new make; otherwise it resets to "All models".
  - "All makes" restores all stock models, grouped by make.
- The search stays a plain GET form; empty fields are dropped from the URL (`/vehicles/?make=porsche&model=macan`).
