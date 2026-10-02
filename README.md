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

# Coding standards (requires PHPCS + WordPress Coding Standards installed globally)
phpcs
```

## Docs

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): file layout, vehicle data model, taxonomies, URLs, enquiries, SEO, multilingual readiness, filtering plan.
- [docs/PROJECT-RULES.md](docs/PROJECT-RULES.md): binding responsive, accessibility, performance, privacy and multilingual rules.
- [docs/REFERENCE-AUDIT-POTENZA.md](docs/REFERENCE-AUDIT-POTENZA.md): reference audit (evidence for the rules).

Translation template: regenerate `languages/elite-auto-dealer.pot` with
`wp i18n make-pot . languages/elite-auto-dealer.pot --domain=elite-auto-dealer --exclude=tests,docs`.
