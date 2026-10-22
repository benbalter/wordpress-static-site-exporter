# Third-party classes and ignore patterns

When PHPStan reports legitimate classes as missing (e.g. because WordPress or a plugin is not installed in the analysis environment), prefer fixing discovery first and only then add targeted ignores.

## Before adding `ignoreErrors`

- Confirm the dependency is real (installed/required in this environment).
- Prefer stubs/extensions already used by the repo.
- Prefer a narrow ignore for the vendor prefix over a broad ignore.

## Recommended stub packages

Stubs are useful when the analysis environment does not include WordPress (or a plugin API) but you still want real type checking (instead of blanket ignores).

Common packages:

```bash
composer require --dev szepeviktor/phpstan-wordpress
composer require --dev php-stubs/wordpress-stubs
composer require --dev php-stubs/woocommerce-stubs
composer require --dev php-stubs/acf-pro-stubs
```

When stubs are useful (and sometimes necessary):

- Running PHPStan in a plugin/theme repo without a full WordPress checkout.
- PHPStan reports unknown WordPress core functions (e.g. `add_action()`, `get_option()`).
- Integrations with optional plugins (WooCommerce, ACF Pro) that are not installed during analysis.
- You want method/property existence checks and accurate return types instead of `ignoreErrors`.

Notes:

- Prefer stubs that match the runtime versions; mismatches can cause false positives.
- Adding Composer dependencies changes the repo; confirm it is acceptable for the task.

## Ensure stubs are loaded

Installing stubs is not enough if PHPStan does not scan them. Add stub paths in `phpstan.neon`.

```neon
parameters:
    bootstrapFiles:
        - %rootDir%/../../php-stubs/woocommerce-stubs/woocommerce-stubs.php
    scanFiles:
        - %rootDir%/../../php-stubs/wordpress-stubs/wordpress-stubs.php
        - %rootDir%/../../php-stubs/acf-pro-stubs/acf-pro-stubs.php
        - %rootDir%/../../woocommerce/action-scheduler/functions.php
```

## Targeted ignore patterns (examples)

```neon
parameters:
    ignoreErrors:
        # Admin Columns Pro
        - '#.*(unknown class|invalid type|call to method .* on an unknown class) AC\\ListScreen.*#'

        # Elementor
        - '#.*(unknown class|invalid type|call to method .* on an unknown class) Elementor\\.*#'

        # Yoast SEO
        - '#.*(unknown class|invalid type|call to method .* on an unknown class) WPSEO_.*#'
```

Pattern creation rules:

- Cover error variations: `unknown class`, `invalid type`, `call to method .* on an unknown class`.
- Keep patterns specific enough to target only intended classes.
- Add a short comment naming the plugin/theme.
- Group related patterns for the same dependency.

When to add exceptions:

- Only for legitimate third-party dependencies your code integrates with.
- Document each pattern with a comment.
- Re-run PHPStan to ensure the ignore does not hide unrelated issues.
