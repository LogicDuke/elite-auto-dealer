# Elite Auto Dealer

Lightweight single-dealership used-car WordPress theme by Elite Digital Services.

- Requires WordPress 6.5+, PHP 8.1+
- No page builder, no runtime dependencies
- Repository contains this theme only (no WordPress core, uploads, plugins or database)

## Install (local)

1. Put this folder at `wp-content/themes/elite-auto-dealer`.
2. Activate the theme. Activation registers vehicles, seeds the default taxonomy terms and flushes permalinks.
3. Inventory lives at `/vehicles/`, single vehicles at `/vehicle/{slug}/`.

## Checks

```sh
# PHP syntax
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;

# Data-model smoke test (from the WordPress root, theme active)
wp eval-file wp-content/themes/elite-auto-dealer/tests/smoke-test.php

# Demo inventory (Aurelis Motors): seed, then verify
wp eval-file wp-content/themes/elite-auto-dealer/demo/seed.php
wp eval-file wp-content/themes/elite-auto-dealer/tests/demo-test.php

# Demo images: unpack the approved ZIP (Drive) into demo/images/, then import and verify
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php validate
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-images.php
wp eval-file wp-content/themes/elite-auto-dealer/tests/image-import-test.php

# Website images (homepage hero + inner-page headers): unpack the approved ZIPs into demo/images/site/
wp eval-file wp-content/themes/elite-auto-dealer/demo/import-site-images.php
wp eval-file wp-content/themes/elite-auto-dealer/tests/site-image-test.php

# Make/Model catalogue (theme active)
wp eval-file wp-content/themes/elite-auto-dealer/tests/catalogue-test.php

# Catalogue source: regenerate data/vehicle-catalogue.json, or verify it is current
python bin/build-vehicle-catalogue.py
python bin/build-vehicle-catalogue.py --check

# Vehicle image roadmap / manifest (no WordPress needed, from the theme root)
php tests/image-roadmap-test.php

# Coding standards (requires PHPCS + WordPress Coding Standards installed globally)
phpcs
```

## Docs

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): file layout, vehicle data model, taxonomies, URLs, enquiries, SEO, multilingual readiness, filtering plan.
- [docs/VEHICLE-CATALOGUE.md](docs/VEHICLE-CATALOGUE.md): Make/Model catalogue, generator, versioning, public filter rules.
- [docs/PROJECT-RULES.md](docs/PROJECT-RULES.md): binding responsive, accessibility, performance, privacy and multilingual rules.
- [docs/REFERENCE-AUDIT-POTENZA.md](docs/REFERENCE-AUDIT-POTENZA.md): reference audit (evidence for the rules).

Translation template: regenerate `languages/elite-auto-dealer.pot` with
`wp i18n make-pot . languages/elite-auto-dealer.pot --domain=elite-auto-dealer --exclude=tests,docs`.
