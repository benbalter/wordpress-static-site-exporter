# Copilot Instructions for Static Site Exporter

## Project Overview

This is a WordPress plugin that converts WordPress posts, pages, taxonomies, metadata, and settings to Markdown and YAML format for use with Jekyll, Hugo, or other static site generators.

**Key Features:**
- Converts WordPress content to Markdown using League HTML-to-Markdown
- Exports post metadata as YAML front matter
- Generates Jekyll-compatible file structure
- Provides both WordPress admin UI and CLI (WP-CLI) interfaces

## Technology Stack

- **Language**: PHP 7.2.5+ (tested up to PHP 8.4)
- **WordPress**: Compatible with WordPress 6.7, 6.8, and latest
- **Key Dependencies**:
  - `league/html-to-markdown` ^5.0 - HTML to Markdown conversion
  - `symfony/yaml` ^5.4 - YAML parsing and generation
- **Dev Dependencies**:
  - PHPUnit ~8.0 - Unit testing
  - WordPress Coding Standards (WPCS) ^3.0 - Code style enforcement
  - WP-CLI ~2.4 - Command-line interface

## Project Structure

```
/
├── jekyll-exporter.php       # Main plugin file
├── jekyll-export-cli.php     # WP-CLI command definition
├── lib/                      # Library files
│   └── cli.php              # CLI-related functionality
├── tests/                    # PHPUnit tests
│   ├── bootstrap.php        # Test bootstrap
│   └── test-wordpress-to-jekyll-exporter.php
├── script/                   # Build and CI scripts
│   ├── cibuild              # Main CI build script
│   ├── cibuild-phpunit      # Run PHPUnit tests
│   ├── cibuild-phpcs        # Run PHP CodeSniffer
│   ├── fmt                  # Auto-format code
│   └── setup                # Set up test environment
├── docs/                     # Documentation
└── vendor/                   # Composer dependencies
```

## Development Workflow

### Setting Up the Development Environment

1. **Install Dependencies**:
   ```bash
   composer install --dev
   ```

2. **Set Up WordPress Test Environment** (for running tests):
   ```bash
   script/setup
   ```
   This requires MySQL to be running and accessible.

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

**Auto-Format Code**:
```bash
script/fmt
```

### Testing Requirements

- All code changes must include appropriate PHPUnit tests
- Tests are located in `tests/` directory
- Test files must be prefixed with `test-` and have `.php` extension
- Tests require a WordPress test installation (set up via `script/setup`)
- The project supports both single-site and multisite WordPress installations

### Code Style and Standards

- **Follow WordPress Coding Standards** (WPCS)
- Configuration is in `phpcs.ruleset.xml`
- Run `script/cibuild-phpcs` to check compliance
- Run `script/fmt` to automatically fix style issues
- All PHP files must be compatible with PHP 7.2.5+

### Important Coding Conventions

1. **WordPress Compatibility**:
   - Use WordPress functions and filters appropriately
   - Ensure compatibility with WordPress 6.7+ and latest versions
   - Support both single-site and multisite installations

2. **Internationalization**:
   - Text domain: `jekyll-export`
   - Use WordPress i18n functions: `__()`, `_e()`, `esc_html__()`, etc.
   - Translation files are in `languages/` directory

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
- **jekyll-export-cli.php**: WP-CLI command registration
- **lib/cli.php**: CLI-specific functionality and hooks
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
6. If modifying user-visible strings, update translations

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
4. Update minimum PHP version in plugin header if needed

### Vendor Management

This plugin ships production Composer dependencies (in `vendor/`) as part of the repository so that end users don't need to run `composer install`. The `.gitignore` selectively includes only production packages (`league/html-to-markdown`, `symfony/*`) and the Composer autoloader.

**Critical**: The committed `vendor/composer/autoload_*.php` files must be generated with `--no-dev` so they only reference shipped packages. If they are generated with dev dependencies, the plugin will fatal error on load for end users.

- **Always run `script/vendor`** after changing `composer.json` or updating dependencies. This script runs `composer install --no-dev` to regenerate the autoload files correctly.
- **Never run `composer install` or `composer update` without `--no-dev`** when preparing vendor files for commit.
- CI will verify that committed vendor autoload files match the output of `script/vendor`.

## CI/CD Pipeline

The project uses GitHub Actions with the following jobs:

1. **phpunit**: Runs PHPUnit tests on PHP 8.4 across multiple WordPress versions (latest, 6.7, 6.8)
2. **phpcs**: Runs PHP CodeSniffer for code style compliance on PHP 8.4

Tests run against MySQL 5.7 and both single-site and multisite WordPress installations.

## Important Notes

- **Minimum PHP Version**: 7.2.5 (configured in `composer.json`)
- **Tested PHP Version**: 8.4
- **WordPress Compatibility**: 6.7, 6.8, and latest
- **License**: GPLv3 or later
- **Main Author**: Ben Balter

## Useful Commands

```bash
# Install dependencies
composer install --dev

# Run all CI checks
script/cibuild

# Run unit tests
script/cibuild-phpunit

# Check code style
script/cibuild-phpcs

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
