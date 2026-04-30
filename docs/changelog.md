## Changelog

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
