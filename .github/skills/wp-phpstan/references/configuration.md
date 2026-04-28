# PHPStan configuration (WordPress)

This reference documents a minimal, WordPress-friendly PHPStan setup and baseline workflow.

## Minimal `phpstan.neon` template

Use the repo’s existing layout. The example below is intentionally conservative and should be adapted to the project’s actual directories.

```neon
# Include the baseline only if the file exists.
includes:
    - phpstan-baseline.neon

parameters:
    level: 5
    paths:
        - src/
        - includes/

    excludePaths:
        - vendor/
        - vendor-prefixed/
        - node_modules/
        - tests/

    ignoreErrors:
        # Add targeted exceptions only when necessary.
```

Guidelines:

- Prefer analyzing first-party code only.
- Exclude anything generated or vendored.
- Keep `ignoreErrors` patterns narrow and grouped by dependency.

## Baseline workflow

Baselines help you adopt PHPStan in legacy code without accepting new regressions.

```bash
# Generate a baseline (explicit filename)
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon

# Update an existing baseline (defaults)
vendor/bin/phpstan analyse --generate-baseline
```

Best practices:

- Avoid adding new errors to the baseline; fix the new code instead.
- Treat baseline changes like code changes: review in PRs.
- Chip away at the baseline gradually (remove entries as you fix root causes).
