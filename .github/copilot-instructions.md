# Copilot Instructions for Static Site Exporter

## Project Overview

This is a WordPress plugin that converts WordPress posts, pages, taxonomies, metadata, and settings to Markdown and YAML format for use with Jekyll, Hugo, or other static site generators.

**Key Features:**
- Converts WordPress content to Markdown using League HTML-to-Markdown
- Exports post metadata as YAML front matter
- Generates Jekyll-compatible file structure
- Provides both WordPress admin UI and CLI (WP-CLI) interfaces

## Technology Stack

- **Language**: PHP 8.2+ (tested up to PHP 8.4)
- **WordPress**: 6.4+ (CI tests 6.4, 6.8, 6.9, and latest)
- **Key Dependencies**:
  - `league/html-to-markdown` ^5.0 - HTML to Markdown conversion
  - `symfony/yaml` ^7.0 - YAML parsing and generation
- **Dev Dependencies**:
  - PHPUnit ~9.6 - Unit testing
  - WordPress Coding Standards (WPCS) ^3.0 - Code style enforcement
  - PHPStan ^2.0 with `szepeviktor/phpstan-wordpress` - Static analysis
  - WP-CLI ~2.4 - Command-line interface

## Project Structure

```
/
├── jekyll-exporter.php       # Main plugin file
├── jekyll-export-cli.php     # Deprecated standalone CLI script (use `wp jekyll-export`)
├── lib/                      # Library files
│   ├── cli.php              # WP-CLI command (`wp jekyll-export`)
│   └── colspan-table-converter.php
├── tests/                    # PHPUnit tests
│   ├── bootstrap.php        # Test bootstrap
│   └── *Test.php            # e.g. WordPressToJekyllExporterTest.php
├── script/                   # Build and CI scripts
│   ├── cibuild              # Main CI build script (phpunit + phpcs + phpstan)
│   ├── cibuild-phpunit      # Run PHPUnit tests
│   ├── cibuild-phpcs        # Run PHP CodeSniffer
│   ├── cibuild-phpstan      # Run PHPStan
│   ├── build-readme         # Generate readme.txt from docs/
│   ├── vendor               # Regenerate shipped vendor/ with --no-dev
│   ├── bootstrap            # Install dev dependencies
│   ├── fmt                  # Auto-format code
│   ├── setup                # Set up test environment
│   └── install-wp-tests     # Download WordPress and the test suite (called by setup)
├── docs/                     # Documentation
└── vendor/                   # Composer dependencies
```

## Development Workflow

### Setting Up the Development Environment

1. **Install Dependencies**:
   ```bash
   script/bootstrap
   ```

2. **Set Up WordPress Test Environment** (for running tests):
   ```bash
   script/setup
   ```
   This requires MySQL to be running and accessible at `127.0.0.1` with user `root` / password `root`. Set `WP_VERSION` to test a specific WordPress release (default: latest).

### Building and Testing

**Run All Tests**:
```bash
script/cibuild
```

**Run Unit Tests Only**:
```bash
script/cibuild-phpunit
```

**Run Code Style Checks**:
```bash
script/cibuild-phpcs
```

**Run Static Analysis**:
```bash
script/cibuild-phpstan
```

**Auto-Format Code**:
```bash
script/fmt
```

### Testing Requirements

- All code changes must include appropriate PHPUnit tests
- Tests are located in `tests/` directory
- Test files are named `*Test.php` (the suffix `phpunit.xml` collects)
- Tests require a WordPress test installation (set up via `script/setup`)
- The project supports both single-site and multisite WordPress installations

### Code Style and Standards

- **Follow WordPress Coding Standards** (WPCS)
- Configuration is in `phpcs.ruleset.xml`
- Run `script/cibuild-phpcs` to check compliance
- Run `script/fmt` to automatically fix style issues
- All PHP files must be compatible with PHP 8.2+

### Important Coding Conventions

1. **WordPress Compatibility**:
   - Use WordPress functions and filters appropriately
   - Ensure compatibility with WordPress 6.4+ and latest versions
   - Support both single-site and multisite installations

2. **Internationalization**:
   - Text domain: `jekyll-exporter` (matches the WordPress.org slug)
   - Use WordPress i18n functions: `__()`, `_e()`, `esc_html__()`, etc.
   - Translations come from language packs on translate.wordpress.org; none are bundled

3. **Security**:
   - Sanitize all user inputs
   - Escape all outputs
   - Follow WordPress security best practices
   - Use WordPress nonces for form submissions

4. **Documentation**:
   - Use PHPDoc blocks for all classes, methods, and functions
   - Include `@param`, `@return`, and `@throws` tags where appropriate
   - Documentation files are in `docs/` directory
   - `readme.txt` is auto-generated from docs via `script/build-readme`

## Key Files to Understand

- **jekyll-exporter.php**: Main plugin class (`Jekyll_Export`) with core export logic
- **lib/cli.php**: WP-CLI command (`Jekyll_Export_Command`, registered as `wp jekyll-export`)
- **jekyll-export-cli.php**: Deprecated standalone CLI script
- **phpcs.ruleset.xml**: PHP CodeSniffer configuration
- **phpunit.xml**: PHPUnit configuration
- **composer.json**: Dependency management

## Common Tasks

### Adding New Functionality

1. Understand the export flow in `Jekyll_Export` class
2. Add new methods or filters as needed
3. Write PHPUnit tests for new functionality
4. Update documentation in `docs/` if user-facing
5. Run tests and code style checks
6. If modifying user-visible strings, use the `jekyll-exporter` text domain

### Fixing Bugs

1. Write a failing test that reproduces the bug
2. Fix the bug
3. Ensure the test passes
4. Run full test suite to prevent regressions
5. Run code style checks

### Updating Dependencies

1. Update version in `composer.json`
2. Run `composer update`
3. Test thoroughly, especially with different PHP and WordPress versions
4. If the minimum PHP version changes, update it everywhere it's declared: `require.php` and `config.platform.php` in `composer.json`, the `Requires PHP` header and `version_compare()` check in `jekyll-exporter.php`, and `docs/` (then run `script/build-readme`)

### Vendor Management

This plugin ships production Composer dependencies (in `vendor/`) as part of the repository so that end users don't need to run `composer install`. The `.gitignore` selectively includes only production packages (`league/html-to-markdown`, `symfony/*`) and the Composer autoloader.

**Critical**: The committed `vendor/composer/autoload_*.php` files must be generated with `--no-dev` so they only reference shipped packages. If they are generated with dev dependencies, the plugin will fatal error on load for end users.

- **Always run `script/vendor`** after changing `composer.json` or updating dependencies. This script runs `composer install --no-dev` to regenerate the autoload files correctly. Run `script/bootstrap` afterwards to restore the dev dependencies.
- **Never run `composer install` or `composer update` without `--no-dev`** when preparing vendor files for commit.
- CI fails if the committed `vendor/composer/autoload_*.php` files reference any dev package.

## CI/CD Pipeline

The project uses GitHub Actions with the following jobs:

1. **phpunit**: PHPUnit on PHP 8.4 against WordPress latest, 6.8, and 6.9, plus PHP 8.3 multisite on latest and the minimum pair (PHP 8.2 / WordPress 6.4). Tests run against a `mysql:9.6` service container.
2. **phpcs**: PHP CodeSniffer on PHP 8.3
3. **phpstan**: PHPStan static analysis on PHP 8.4 (configured in `phpstan.neon`)
4. **vendor-autoload**: fails if the committed autoload files reference dev dependencies
5. **readme**: regenerates `readme.txt` with `script/build-readme` and fails if it differs from the committed copy

A separate `integration.yml` workflow runs a full export against a Docker Compose WordPress install (`wordpress:latest` with MariaDB).

## Important Notes

- **Minimum PHP Version**: 8.2 (`composer.json` and the `Requires PHP` plugin header)
- **Tested PHP Versions**: 8.2, 8.3, and 8.4
- **WordPress Compatibility**: 6.4+ (`Requires at least` plugin header); `Tested up to` lives in `docs/header.md`
- **License**: GPLv3 or later
- **Main Author**: Ben Balter

## Useful Commands

```bash
# Install dependencies
script/bootstrap

# Run all CI checks
script/cibuild

# Run unit tests
script/cibuild-phpunit

# Check code style
script/cibuild-phpcs

# Run static analysis
script/cibuild-phpstan

# Auto-fix code style issues
script/fmt

# Set up WordPress test environment
script/setup
```

## When Making Changes

1. Always run `script/cibuild` before committing
2. Ensure all tests pass
3. Verify code style compliance
4. Update tests to cover new functionality
5. Update documentation if making user-facing changes
6. Do not modify `readme.txt` directly - edit files in `docs/` and run `script/build-readme`
7. If changing `composer.json` or dependencies, run `script/vendor` and commit the updated `vendor/` files
