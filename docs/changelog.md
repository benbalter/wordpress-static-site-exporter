## Changelog

### 4.1.0

* **Behavior change:** Post revisions are no longer exported by default. Previously `revision` was included in the default post types, which filled the `_drafts/` folder with duplicate copies of every post. To restore the old behavior, re-add `'revision'` via the `jekyll_export_post_types` filter
* Broadened the `convert_content()` fallback to catch any `Throwable` (not just `InvalidArgumentException`) from the HTML-to-Markdown converter, so an unexpected converter error falls back to the post's raw HTML instead of aborting the entire export
* Emit a `WP_DEBUG`-gated warning when a public custom field shadows a reserved front matter key (e.g. `layout`, `image`, `date`), surfacing silent overrides. The override behavior itself is unchanged
* Internal: de-duplicated the raw-HTML fallback filters in `convert_content()` and the reflection boilerplate in `ColspanTableConverter`

### 4.0.4

* Stream the export zip to the browser in 8 KB chunks instead of loading the entire archive into memory in `send()`, so large exports no longer hit `memory_limit` after a successful build
* `zip_folder()` now throws `RuntimeException` instead of calling `wp_die()` directly, so the existing `export()` try/catch renders a friendly error and runs `cleanup()` on partial temp files
* Added `jekyll_export_html_converter` filter so integrations (and tests) can swap in a custom HTML-to-Markdown converter
* Gated the v4.0.3 fallback `error_log()` call behind `WP_DEBUG`
* Hardened sanitization of `$_GET['type']` in the export callback
* Added regression tests for the v4.0.3 `Invalid HTML was provided` fallback and for the new `zip_folder()` throw behavior

### 4.0.3

* Catch `InvalidArgumentException` from `league/html-to-markdown` in `convert_content()` and fall back to the post's raw HTML for that single post instead of aborting the entire export with "Jekyll Export failed: Invalid HTML was provided" ([#400](https://github.com/benbalter/wordpress-static-site-exporter/issues/400))

### 4.0.2

* Add shutdown handler to surface fatal errors (memory exhaustion, max execution time) during export with actionable error messages instead of a generic WordPress critical error page
* Add proactive `memory_limit` pre-flight check (warns when below 64MB) in `validate_environment()`
* Display admin error notice on Tools → Export when environment validation fails, before the user clicks Export

### 4.0.1

* Security: Use cryptographically secure randomness (`wp_generate_password`) instead of `md5(time())` for the export temp directory name to prevent symlink/TOCTOU attacks on shared hosts (CWE-330/377)
* Security: Reject non-CLI access in deprecated `jekyll-export-cli.php` before bootstrapping WordPress (CWE-665)
* Security: Sanitize each path segment of page filenames as defense-in-depth against path traversal (CWE-22)
* Fix stale `$upload_basedir` cache in `copy_recursive()` on multisite by keying it on the current blog ID

### 4.0.0

* **Breaking:** Minimum PHP version bumped from 7.2.5 to 8.2
* **Breaking:** Minimum WordPress version bumped from 4.4 to 6.4
* Updated `symfony/yaml` from ^5.4 to ^7.0
* Updated PHPUnit from ~8.0 to ~9.6
* Removed `symfony/polyfill-php80` (no longer needed)
* Added PHPStan static analysis at level 5
* Fixed `get_posts()` to return integer IDs instead of strings
* Fixed PHPDoc type annotations throughout codebase
* Deprecated legacy `jekyll-export-cli.php` in favor of `lib/cli.php`
* Improved CI pipeline with PHPStan job and vendor consistency checks

[View Past Releases](https://github.com/benbalter/wordpress-to-jekyll-exporter/releases)
