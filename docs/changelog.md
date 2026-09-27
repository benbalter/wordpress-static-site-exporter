## Changelog

### 4.2.0

* `send()` now discards every output buffer left open by a theme or another plugin before it streams the archive (web requests only; under WP-CLI the caller owns stdout) ([#413](https://github.com/benbalter/wordpress-static-site-exporter/issues/413)). A buffer with a callback (an HTML minifier, a CDN rewriter) would otherwise rewrite the binary zip on its way out, and a buffer opened without a chunk size would hold the entire archive in memory until the export died mid-stream. Both reach the browser as a truncated or unreadable download rather than an error page
* `send()` now disables `zlib.output_compression` and opens the archive before tearing down those buffers, so a read failure is still reportable as an admin error page, and checks `headers_sent()` afterwards, so content that was merely buffered is no longer mistaken for output already on the wire
* The PHP version check no longer calls `wp_die()` while the plugin loads. On PHP older than 8.2 that took down the whole site, front end included. The plugin now shows an admin notice and stays inactive. `Requires PHP` and `Requires at least` are also declared in the plugin header, so WordPress refuses to activate it on an unsupported version
* `get_posts()` no longer caches the list of post IDs in the object cache. The cache key ignored the `jekyll_export_post_types` and `jekyll_export_taxonomy_filters` filters and never expired, so on a site with a persistent object cache (Redis, Memcached) a filtered export, such as `wp jekyll-export --category=...`, could return a previous run's posts
* `convert_options()` no longer runs `maybe_unserialize()` on values `get_option()` has already unserialized
* URL localization now strips the home URL as well as the site URL, so internal links are made relative when WordPress is installed in a subdirectory
* New `--output=<file>` option for `wp jekyll-export` writes the zip to a file instead of STDOUT, so a PHP notice printed during the export can no longer corrupt the archive
* Posts are now exported in batches of 100. Each batch's posts, custom fields, terms, and featured images are loaded in a few bulk queries instead of several queries per post, and released from memory afterwards, so large sites export faster and memory use no longer grows with the number of posts
* Updated `symfony/yaml` to 7.4.18, which fixes CVE-2026-45304, CVE-2026-45305, and CVE-2026-45133 in its YAML parser. The exporter only writes YAML, so exposure was low. Also updated `league/html-to-markdown` to 5.1.2
* The WordPress.org package no longer includes the test suite, CI configuration, docs, or development scripts, so the download is smaller
* Translations now come exclusively from [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/jekyll-exporter/) language packs. The bundled `.po` files have been removed; none of them contained any translated strings

### 4.1.1

* Fixed a zero-byte or unreadable zip download ([#413](https://github.com/benbalter/wordpress-static-site-exporter/issues/413)). `ZipArchive` defers every write to `close()`, so a full disk, an exhausted quota, or an unwritable temp directory produced a missing archive that the exporter happily streamed as an empty response. `zip_folder()` now throws when `close()` fails, and `zip()` verifies the archive exists and is non-empty before it is sent
* `zip_folder()` now detects a failed `ZipArchive::open()`. `open()` returns a non-zero integer error code rather than `false` on failure, so the previous falsy check never fired and every `addFile()` call silently no-op'd
* `send()` opens the archive before emitting any headers and throws if it cannot be read, instead of returning silently after the download headers were already sent
* `send()` disables transparent gzip compression (`zlib.output_compression`) so the `Content-Length` header cannot disagree with the bytes actually written
* `send()` fails with the offending file and line number when a theme or another plugin has already written to the response (a stray blank line or byte order mark), instead of shipping a corrupt archive
* The temporary zip now uses the same random suffix as the temporary export directory, so concurrent or previously crashed exports cannot collide on a fixed `wp-jekyll.zip` in a shared temp directory
* `ob_start()` now wraps the `jekyll_export` action, so output echoed by a third-party hook can no longer be prepended to the archive
* Skip files that vanish mid-export rather than adding a zip entry with an empty name

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
