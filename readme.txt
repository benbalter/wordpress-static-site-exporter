=== Static Site Exporter ===
Contributors: benbalter
Tags: jekyll, hugo, markdown, static site, export
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 4.6.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
GitHub Plugin URI: benbalter/wordpress-static-site-exporter
Primary Branch: main

Export WordPress posts, pages, and media to Markdown with YAML front matter for Jekyll, Hugo, and other static site generators.

== Description ==

One-click WordPress plugin that converts all posts, pages, taxonomies, metadata, and settings to Markdown and YAML which can be dropped into Jekyll (or Hugo or any other Markdown and YAML based site engine).

== Features ==

* Converts all posts, pages, and settings from WordPress to Markdown and YAML for use in Jekyll (or Hugo or any other Markdown and YAML based site engine)
* Export what your users see, not what the database stores (runs post content through `the_content` filter prior to export, allowing third-party plugins to modify the output)
* Converts all `post_content` to Markdown
* Converts all `post_meta` and fields within the `wp_posts` table to YAML front matter for parsing by Jekyll
* Generates a `_config.yml` with all settings in the `wp_options` table
* Outputs a single zip file with `_config.yml`, pages, and `_posts` folder containing `.md` files for each post in the proper Jekyll naming convention
* **Selective export**: Export only specific categories, tags, or post types using WP-CLI
* No settings. Just a single click.

== Usage ==

1. In your WordPress dashboard, go to **Plugins → Add New**, search for **Static Site Exporter**, then install and activate it
2. Select `Export to Jekyll` from the `Tools` menu
3. Unzip the download into your static site

For large sites, use [WP-CLI](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/command-line-usage.md) instead: `wp jekyll-export --output=export.zip`.

== More information ==

See [the full documentation](https://github.com/benbalter/wordpress-static-site-exporter/tree/main/docs):

* [Frequently asked questions](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/faq.md)
* [Changelog](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/changelog.md)
* [Command-line-usage](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/command-line-usage.md)
* [Selective export by category or tag](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/selective-export.md)
* [Custom post types](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/custom-post-types.md)
* [Custom fields](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/custom-fields.md)
* [Developing locally](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/developing-locally.md)
* [Minimum required PHP version](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/required-php-version.md)

=== Selective Export by Category or Tag ===

This feature allows you to export only a specific subset of your WordPress content, filtered by category, tag, or post type. This is particularly useful when:

- You have a large WordPress site but only need to convert specific sections
- You want to migrate content by topic or category
- You need to export content incrementally

== Using WP-CLI ==

The easiest way to perform selective exports is via WP-CLI commands.

= Export by Category =

To export posts from a single category, use the category slug:

    wp jekyll-export --category=technology > technology-export.zip

To export from multiple categories (OR logic - posts in any of these categories):

    wp jekyll-export --category=tech,news,updates > export.zip

= Export by Tag =

To export posts with a specific tag:

    wp jekyll-export --tag=featured > featured-export.zip

To export posts with multiple tags (OR logic):

    wp jekyll-export --tag=featured,popular > export.zip

= Export Specific Post Types =

To export only pages:

    wp jekyll-export --post_type=page > pages-export.zip

To export only posts:

    wp jekyll-export --post_type=post > posts-export.zip

To export custom post types:

    wp jekyll-export --post_type=portfolio,testimonial > custom-export.zip

= Combining Filters =

You can combine multiple filters. Posts must match ALL specified filters (AND logic):

    # Export posts that are in "technology" category AND have "featured" tag
    wp jekyll-export --category=technology --tag=featured --post_type=post > export.zip

== Using PHP Filters ==

For more programmatic control, you can use WordPress filters directly in your theme's `functions.php` or a custom plugin.

= Filter by Category =

    add_filter( 'jekyll_export_taxonomy_filters', function() {
        return array(
            'category' => array( 'technology', 'science' ),
        );
    } );

= Filter by Tag =

    add_filter( 'jekyll_export_taxonomy_filters', function() {
        return array(
            'post_tag' => array( 'featured', 'popular' ),
        );
    } );

= Filter by Custom Taxonomy =

    add_filter( 'jekyll_export_taxonomy_filters', function() {
        return array(
            'my_custom_taxonomy' => array( 'term-slug-1', 'term-slug-2' ),
        );
    } );

= Combine Multiple Taxonomies =

    add_filter( 'jekyll_export_taxonomy_filters', function() {
        return array(
            'category' => array( 'technology' ),
            'post_tag' => array( 'featured' ),
            'custom_tax' => array( 'term-1' ),
        );
    } );

= Filter Post Types =

    add_filter( 'jekyll_export_post_types', function() {
        return array( 'post', 'page' ); // Only export posts and pages
    } );

== Finding Category and Tag Slugs ==

If you're not sure what slug to use:

= Via WordPress Admin =

1. Go to **Posts > Categories** or **Posts > Tags**
2. Hover over the category/tag name
3. Look at the browser's status bar or the URL - you'll see something like `tag_ID=123&taxonomy=post_tag&term_slug=featured`
4. The slug is the part after `term_slug=`

= Via WP-CLI =

List all categories with their slugs:

    wp term list category --fields=name,slug

List all tags with their slugs:

    wp term list post_tag --fields=name,slug

== Use Cases ==

= Scenario 1: Export a Single Blog Section =

You have a WordPress site with multiple sections (Tech, Lifestyle, Travel) and want to move just the Tech section to a static site:

    wp jekyll-export --category=tech > tech-blog-export.zip

= Scenario 2: Export Featured Content =

You want to export only posts marked as "featured" for a special showcase site:

    wp jekyll-export --tag=featured > featured-content.zip

= Scenario 3: Export by Year (using custom taxonomy) =

If you've tagged posts by year, you can export by year:

    wp jekyll-export --tag=2024 > 2024-posts.zip

= Scenario 4: Migrate Content Incrementally =

Export different categories separately for incremental migration:

    wp jekyll-export --category=tech > tech.zip
    wp jekyll-export --category=news > news.zip
    wp jekyll-export --category=reviews > reviews.zip

== Technical Details ==

- **Taxonomy Filtering**: Uses WordPress term slugs (not names or IDs)
- **Query Performance**: Filtering is done at the database level for efficiency
- **OR Logic Within Taxonomy**: Multiple terms in the same taxonomy use OR logic (e.g., posts in category A OR B)
- **AND Logic Across Taxonomies**: Multiple taxonomies use AND logic (e.g., posts in category A AND having tag B)
- **Post Type Filtering**: Works independently of taxonomy filtering

== Limitations ==

- Revisions are excluded when using taxonomy filters (as they don't have taxonomy terms)
- Taxonomy filtering uses term slugs, not term IDs or names
- Empty taxonomy filters are ignored (no filtering applied)

== Troubleshooting ==

= No Posts Exported =

If your export is empty:

1. **Check the slug**: Make sure you're using the term slug, not the name
   - Use `wp term list category` to verify the exact slug
2. **Check post status**: Only published, future, and draft posts are exported
3. **Verify taxonomy**: Make sure you're using the correct taxonomy name (`category`, `post_tag`, etc.)

= Wrong Posts Exported =

If you're getting unexpected posts:

1. **Check term associations**: Verify which posts have the category/tag assigned
2. **Review filter logic**: Remember that multiple categories use OR logic
3. **Clear cache**: If testing, use `wp cache flush` between exports

== Custom post types ==

To export custom post types, add a filter (e.g., in your theme's `functions.php`) like this:

    add_filter( 'jekyll_export_post_types', function() {
    	return array('post', 'page', 'your-custom-post-type');
    });

Each custom post type is exported as a Jekyll collection: posts of type `portfolio` go in `_portfolios/`, and the exported `_config.yml` declares that collection with `output: true`, so Jekyll builds a page for each one.

== Custom fields ==

When using custom fields (e.g. with the Advanced Custom fields plugin) you might have to register a filter to convert array style configs to plain values.

= Available Filters =

The plugin provides two filters for customizing post metadata:

- **`jekyll_export_meta`**: Filters the metadata for a single post before it's merged with taxonomy terms. Receives `$meta` array and `$post` object as parameters.
- **`jekyll_export_post_meta`**: Filters the complete metadata array (including taxonomy terms) just before it's written to the YAML frontmatter. Receives `$meta` array and `$post` object as parameters. This is the recommended filter for most use cases.

**Note:** As of the latest version, the plugin no longer automatically removes empty or falsy values from the frontmatter. All metadata is preserved by default. If you want to remove certain fields, you can use the `jekyll_export_post_meta` filter to customize this behavior.

By default, the plugin saves custom fields in an array structure that is exported as: 

    ["my-bool"]=>
        array(1) {
            [0] => string(1) "1"
        }
    ["location"]=>
        array(1) {
            [0] => string(88) "My address"
        }

And this leads to a YAML structure like:

    my-bool:
    - "1"
    location:
    - 'My address'

This is likely not the structure you expect or want to work with. You can convert it using a filter:

    add_filter( 'jekyll_export_meta', function($meta) {
        foreach ($meta as $key => $value) {
            if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
                $meta[$key] = $value[0];
            }
        }

        return $meta;
    });

A more complete solution could look like that:

    add_filter( 'jekyll_export_meta', function($meta) {
        foreach ($meta as $key => $value) {
            // Advanced Custom Fields
            if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
                $value = maybe_unserialize($value[0]);
                // Advanced Custom Fields: NextGEN Gallery Field add-on
                if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
                    $value = $value[0];
                }
            }
            // convert types
            $value = match ($key) {
                // Advanced Custom Fields: "true_false" type
                'my-bool' => (bool) $value,
                default => $value
            };
            $meta[$key] = $value;
        }

        return $meta;
    });

= Removing Empty or Falsy Values =

If you want to remove empty or falsy values from the frontmatter (similar to the pre-3.0.3 behavior), you can use the `jekyll_export_post_meta` filter:

    add_filter( 'jekyll_export_post_meta', function( $meta, $post ) {
        foreach ( $meta as $key => $value ) {
            // Remove falsy values except numeric 0
            if ( ! is_numeric( $value ) && ! $value ) {
                unset( $meta[ $key ] );
            }
        }
        return $meta;
    }, 10, 2 );

== Command-line Usage ==

If your web server times out before the export finishes, or you'd rather use the terminal, run the export with [WP-CLI](https://wp-cli.org). It works just like the plugin, but writes the zip file to STDOUT, and it copes better with unusual setups, such as a `wp-content` folder in an unusual location:

    wp jekyll-export > export.zip

To write the zip straight to a file instead of STDOUT, pass `--output`. This keeps a stray PHP notice or warning from ending up inside the archive:

    wp jekyll-export --output=export.zip

To leave out drafts and other unpublished content, pass the statuses you want with `--post_status`:

    wp jekyll-export --post_status=publish > export.zip

If your media is served from a CDN, or the uploads folder is too big to zip, pass `--skip-uploads` to export without `wp-content/uploads`:

    wp jekyll-export --skip-uploads > export.zip

To export only some categories, tags, or post types, see [Selective Export](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/selective-export.md).

=== Performance Tips for Large Sites ===

If you're running a large WordPress site with thousands of posts or gigabytes of uploads, here are some tips to make the export process faster and more efficient.

== Quick Wins ==

= 1. Use WP-CLI Instead of Browser Export =

Browser-based exports are subject to PHP execution time limits (typically 30-300 seconds). Use WP-CLI for unlimited execution time:

    wp jekyll-export > export.zip

= 2. Skip Uploads if You Don't Need Them =

If your images and files are served from a CDN or you plan to handle them separately, you can skip the uploads directory entirely:

    // Add to your theme's functions.php or a custom plugin
    add_filter( 'jekyll_export_skip_uploads', '__return_true' );

This can save significant time and disk space, especially if you have gigabytes of media files.

= 3. Exclude Cache and Temporary Directories =

Many sites accumulate cache files and temporary uploads that aren't needed in the export:

    add_filter( 'jekyll_export_excluded_upload_dirs', function( $excluded ) {
        return array_merge( $excluded, array(
            '/cache/',
            '/tmp/',
            '/backup/',
            '/wc-logs/',  // WooCommerce logs
            '/wpml/',     // WPML cache
        ) );
    } );

== Still Having Timeout Issues? ==

If exports are still timing out, try these solutions:

= Increase PHP Memory and Time Limits =

Add to your `wp-config.php`:

    define( 'WP_MEMORY_LIMIT', '512M' );
    @ini_set( 'max_execution_time', '600' ); // 10 minutes

= Export Only Specific Post Types =

If you only need posts (not pages or other custom post types):

    add_filter( 'jekyll_export_post_types', function() {
        return array( 'post' ); // Only export posts
    } );

= Run Export During Off-Peak Hours =

Schedule the export using WP-CLI and cron during low-traffic periods:

    # Add to crontab to run at 3 AM
    0 3 * * 0 cd /path/to/wordpress && wp jekyll-export > /path/to/backups/jekyll-$(date +\%Y\%m\%d).zip

== Measuring Performance ==

To see how long your export takes:

= Via WP-CLI with Timing =

    time wp jekyll-export > export.zip

= Via the `jekyll_export_complete` action =

`jekyll_export_complete` fires once after a successful export, after the archive has been sent or saved and temporary files have been cleaned up. It does not fire when the export fails. Output echoed by a callback is discarded.

    add_action( 'jekyll_export', function() {
        update_option( 'jekyll_export_start_time', microtime( true ) );
    } );

    add_action( 'jekyll_export_complete', function() {
        $start = get_option( 'jekyll_export_start_time' );
        if ( $start ) {
            $duration = microtime( true ) - $start;
            error_log( sprintf( 'Jekyll export completed in %.2f seconds', $duration ) );
            delete_option( 'jekyll_export_start_time' );
        }
    } );

== Database Optimization ==

Before exporting, optimize your database:

    wp db optimize

This can improve query performance during the export process.

== Hardware Recommendations ==

For very large sites (10,000+ posts), consider:

- **SSD storage** for faster file I/O
- **At least 2GB RAM** for PHP
- **Modern PHP version** (7.4+ or 8.0+) for better performance

== Troubleshooting Slow Exports ==

If exports are still slow after optimizations:

1. **Check slow query log**: Identify if specific database queries are bottlenecks
2. **Profile plugin conflicts**: Disable other plugins temporarily to isolate issues
3. **Monitor server resources**: Check if CPU/memory/disk I/O is maxed out
4. **Consider hosting**: Shared hosting may have strict resource limits

== Getting Help ==

If exports are still slow, ask in the [support forum](https://wordpress.org/support/plugin/jekyll-exporter/) (see [Support](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/SUPPORT.md)). Include how many posts and pages you have, the size of `wp-content/uploads`, your PHP version and memory limit, how long the export runs before it stops, and any errors from your PHP error log.

== Where to get help or report an issue ==

* For getting started and general documentation, please browse, and feel free to contribute to [the project documentation](https://github.com/benbalter/wordpress-static-site-exporter/tree/master/docs).
* For support questions ("How do I", "I can't seem to", etc.) please search and if not already answered, open a thread in the [Support Forums](http://wordpress.org/support/plugin/jekyll-exporter).
* For technical issues (e.g., to submit a bug or feature request) please search and if not already filed, [open an issue on GitHub](https://github.com/benbalter/wordpress-static-site-exporter/issues).

== Things to check before reporting an issue ==

* Are you using the latest version of WordPress?
* Are you using the latest version of the plugin?
* Does the problem occur even when you deactivate all plugins and use the default theme?
* Have you tried deactivating and reactivating the plugin?
* Has your issue [already been reported](https://github.com/benbalter/wordpress-to-jekyll-exporter/issues)?

== What to include in an issue ==

* What steps can another user take to recreate the issue?
* What is the expected outcome of that action?
* What is the actual outcome of that action?
* Are there any screenshots or screencasts that may be helpful to include?
* Only include one bug per issue. If you have discovered two bugs, please file two issues.

== Frequently Asked Questions ==

= Does it work with Hugo, Eleventy, or other static site generators? =

Yes. Every post becomes a Markdown file with YAML front matter, which most static site generators read. The folders follow Jekyll's layout (`_posts/`, `_drafts/`, `_config.yml`), so for another generator, move the posts into its content folder.

= My export times out, or the zip file is empty. What can I do? =

Large sites can run past PHP's time or memory limits in the browser. Use WP-CLI instead, which has no time limit, and write the zip straight to a file:

    wp jekyll-export --output=export.zip

If your media library is large, add `--skip-uploads` and copy `wp-content/uploads` separately. See [Performance Tips for Large Sites](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/performance-tips.md) for more.

= Can I export only some of my posts? =

Yes. With WP-CLI, filter by category, tag, post type, or status:

    wp jekyll-export --category=technology --post_status=publish > export.zip

See [Selective Export](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/selective-export.md) for every option, including the PHP filters.

= Where do my images and other media go? =

The export copies `wp-content/uploads` into the zip and rewrites links to your media as relative URLs (`/wp-content/uploads/...`), so they keep working on the new site.

= Will my old URLs keep working? =

Each post's front matter includes a `permalink` set to its WordPress URL, so Jekyll publishes it at the same address.

= Are drafts exported? =

Yes, into `_drafts/`. To leave them out, use `wp jekyll-export --post_status=publish`.

= Does it export custom post types and custom fields? =

Custom fields are exported as front matter. Custom post types need one filter to include them, and the export then declares each one as a Jekyll collection. See [Custom post types](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/custom-post-types.md) and [Custom fields](https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/custom-fields.md).

= Does the export include the text after a "More" tag? =

Yes. Each post is exported in full, including everything after a More tag (the "Continue reading" break) and every page of a post split with a Page Break. The More tag stays in the Markdown as `<!--more-->`, which Hugo treats as the end of a post's summary. To use it for excerpts in Jekyll, add `excerpt_separator: <!--more-->` to the front matter of the posts that have one. Setting it in `_config.yml` instead also works, but then any post without the tag uses its whole text as the excerpt.

== Screenshots ==

1. Choose Export to Jekyll from the Tools menu to download your site as a zip file.
2. The zip has your posts, drafts, pages, and settings, laid out for Jekyll.
3. Each post is Markdown with YAML front matter: title, date, author, categories, tags, and its original URL.

== Changelog ==

= 4.6.0 =

This release exports posts in full. Review the note marked **Export change** before re-exporting a site you've exported before.

* **Export change:** Posts with a More tag (the "Continue reading" break) are exported in full again. Since 2.4.2, the export stopped at the tag and put a "(more…)" link in place of the rest of the post, and posts split with Page Break blocks lost every page after the first. The More tag is kept in the Markdown as `<!--more-->` on its own line, which Hugo uses to end a post's summary and Jekyll can use as its excerpt separator ([#509](https://github.com/benbalter/wordpress-static-site-exporter/issues/509), [#510](https://github.com/benbalter/wordpress-static-site-exporter/pull/510)). Thanks to [@shtrom](https://github.com/shtrom) for the report
* The plugin's WordPress.org page now shows all of its documentation. WordPress.org cuts the description off after 2,500 words, and repeated and outdated sections had pushed it past that. The readme build now fails before the description gets too long again ([#511](https://github.com/benbalter/wordpress-static-site-exporter/pull/511))
* The plugin now ships its `composer.json`, so WordPress.org reviewers can see which libraries are bundled in `vendor/` ([#511](https://github.com/benbalter/wordpress-static-site-exporter/pull/511))

= 4.5.0 =

This release fixes a bug in 4.4.0 that exported every post as HTML instead of Markdown, and updates the HTML-to-Markdown converter for two security fixes, which also change some converted Markdown. Review the notes marked **Export change** before re-exporting a site you've exported before.

* **Export change:** Posts are converted to Markdown again. 4.4.0 shipped without several files the bundled converter needs, so every post failed to convert and was exported as its original HTML, without an error. If you exported with 4.4.0, export again
* **Export change:** Updated `league/html-to-markdown` to 5.2.3. It fixes two cases where conversion time grew with the square of a post's size instead of in proportion to it, so a single large or unusual post could stall an export until PHP timed out ([GHSA-h3wg-gqjr-jjmw](https://github.com/thephpleague/html-to-markdown/security/advisories/GHSA-h3wg-gqjr-jjmw), [GHSA-4xjf-2r66-5r28](https://github.com/thephpleague/html-to-markdown/security/advisories/GHSA-4xjf-2r66-5r28)). It also changes some output: non-breaking spaces are kept instead of becoming regular spaces, headings and paragraphs no longer run onto the same line as an image or other inline content before them, code blocks from `<pre>` lose a stray leading blank line, links whose text is their URL become autolinks more often, and a line of just `-` or `+` is escaped instead of becoming an empty list item. See the [upstream changelog](https://github.com/thephpleague/html-to-markdown/blob/5.2.3/CHANGELOG.md) for the full list
* Updated `symfony/yaml` to 7.4.22, which fixes how some values are quoted and how multi-line text is written, so a few front matter values may be written differently
* Code samples on the WordPress.org plugin page now render as code. They were shown as plain text, which turned `--category` and other flags into en dashes, so copied commands failed ([#499](https://github.com/benbalter/wordpress-static-site-exporter/pull/499))
* A test now pins how the converter escapes Markdown, so a future converter update can't change exported output unnoticed ([#465](https://github.com/benbalter/wordpress-static-site-exporter/pull/465)). Thanks to [@theluckystrike](https://github.com/theluckystrike)

= 4.4.0 =

This release updates the HTML-to-Markdown converter for security fixes, which changes some converted Markdown. Review the note marked **Export change** before re-exporting a site you've exported before.

* **Export change:** Updated `league/html-to-markdown` to 5.1.3, a security release. Content inside `<pre>` and `<code>`, attribute values of HTML kept as-is, and the `href`, `src`, `title`, and `alt` of links and images could previously break out of the Markdown they were written into ([GHSA-8m5h-gv5q-jmp5](https://github.com/thephpleague/html-to-markdown/security/advisories/GHSA-8m5h-gv5q-jmp5), [GHSA-vc6h-86x6-wcjg](https://github.com/thephpleague/html-to-markdown/security/advisories/GHSA-vc6h-86x6-wcjg), [GHSA-r2cp-59h5-vgjj](https://github.com/thephpleague/html-to-markdown/security/advisories/GHSA-r2cp-59h5-vgjj)). The fixes change how backticks, pipes, and link and image attributes are escaped, and keep some code as HTML. See the [upstream changelog](https://github.com/thephpleague/html-to-markdown/blob/5.1.3/CHANGELOG.md#513---2026-10-05) for the full list
* The `jekyll_export_meta`, `jekyll_export_terms`, `jekyll_export_markdown`, `jekyll_export_html`, and `jekyll_export_content` filters now receive the post being exported as a second argument, so output can be customized per post. Existing one-argument callbacks are unaffected ([#463](https://github.com/benbalter/wordpress-static-site-exporter/pull/463)). Thanks to [@theluckystrike](https://github.com/theluckystrike)
* The `jekyll_export_complete` action now fires once after a successful export, after the archive is sent or saved. It does not fire if the export fails. The action was documented, but the plugin never fired it ([#459](https://github.com/benbalter/wordpress-static-site-exporter/pull/459)). Thanks to [@Anandb71](https://github.com/Anandb71)
* Updated `symfony/yaml` to 7.4.20 (parser fixes only; the exporter only writes YAML)

= 4.3.0 =

This release changes what some exports contain. Review the notes marked **Export change** before re-exporting a site you've exported before.

* **Export change:** Posts are now selected with `WP_Query` and an explicit status list (`publish`, `future`, `draft`, `pending`, `private`, and `inherit`), so trashed posts and pages and auto-drafts are no longer written to `_drafts/`. Custom statuses registered by other plugins (e.g., PublishPress or Edit Flow's `pitch` and `assigned`) are no longer exported either. The list can be changed with the new `jekyll_export_post_statuses` filter
* **Export change:** Only link and media URLs (`href`, `src`, and each `srcset` candidate) are made relative, and only where the site or home URL is followed by a path, query, or fragment. Absolute site URLs in prose, code samples, and query strings are now kept as written, which fixes exports such as the default privacy policy's "Our website address is: ." Other attributes that pass through as raw HTML, such as the file block's `<object data>`, the video block's `poster`, and inline `background-image` styles, now keep their absolute URLs
* **Export change:** Posts with `[]: ` in a code sample are now converted to Markdown instead of falling back to raw HTML
* Grandchild pages and nested upload directories are now exported. `WP_Filesystem_Direct::mkdir()` isn't recursive, so a page written before its parent (lower post ID) silently failed to write; directories are now created with `wp_mkdir_p()`
* Front-matter `permalink` and `image` values now strip the site and home URLs in both schemes, so featured images on subdirectory or mixed-scheme installs get relative paths
* Fatal errors during an export (memory or time limits) now show an export-specific message and guidance on core's error page. The previous shutdown handler never ran because core's fatal error handler exits first. The public `shutdown_handler()` method has been removed
* `--category` and `--tag` for `wp jekyll-export` now also split on whitespace, as well as commas
* Uses core helpers (`wp_is_writable()`, `mysql2date()`, `cache_users()`) in place of custom code, and the test suite no longer leaves temporary files behind
* The WordPress.org listing now has a short description and updated tags, and is tested up to WordPress 7.1

= 4.2.0 =

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

= 4.1.1 =

* Fixed a zero-byte or unreadable zip download ([#413](https://github.com/benbalter/wordpress-static-site-exporter/issues/413)). `ZipArchive` defers every write to `close()`, so a full disk, an exhausted quota, or an unwritable temp directory produced a missing archive that the exporter happily streamed as an empty response. `zip_folder()` now throws when `close()` fails, and `zip()` verifies the archive exists and is non-empty before it is sent
* `zip_folder()` now detects a failed `ZipArchive::open()`. `open()` returns a non-zero integer error code rather than `false` on failure, so the previous falsy check never fired and every `addFile()` call silently no-op'd
* `send()` opens the archive before emitting any headers and throws if it cannot be read, instead of returning silently after the download headers were already sent
* `send()` disables transparent gzip compression (`zlib.output_compression`) so the `Content-Length` header cannot disagree with the bytes actually written
* `send()` fails with the offending file and line number when a theme or another plugin has already written to the response (a stray blank line or byte order mark), instead of shipping a corrupt archive
* The temporary zip now uses the same random suffix as the temporary export directory, so concurrent or previously crashed exports cannot collide on a fixed `wp-jekyll.zip` in a shared temp directory
* `ob_start()` now wraps the `jekyll_export` action, so output echoed by a third-party hook can no longer be prepended to the archive
* Skip files that vanish mid-export rather than adding a zip entry with an empty name

= 4.1.0 =

* **Behavior change:** Post revisions are no longer exported by default. Previously `revision` was included in the default post types, which filled the `_drafts/` folder with duplicate copies of every post. To restore the old behavior, re-add `'revision'` via the `jekyll_export_post_types` filter
* Broadened the `convert_content()` fallback to catch any `Throwable` (not just `InvalidArgumentException`) from the HTML-to-Markdown converter, so an unexpected converter error falls back to the post's raw HTML instead of aborting the entire export
* Emit a `WP_DEBUG`-gated warning when a public custom field shadows a reserved front matter key (e.g. `layout`, `image`, `date`), surfacing silent overrides. The override behavior itself is unchanged
* Internal: de-duplicated the raw-HTML fallback filters in `convert_content()` and the reflection boilerplate in `ColspanTableConverter`

= 4.0.4 =

* Stream the export zip to the browser in 8 KB chunks instead of loading the entire archive into memory in `send()`, so large exports no longer hit `memory_limit` after a successful build
* `zip_folder()` now throws `RuntimeException` instead of calling `wp_die()` directly, so the existing `export()` try/catch renders a friendly error and runs `cleanup()` on partial temp files
* Added `jekyll_export_html_converter` filter so integrations (and tests) can swap in a custom HTML-to-Markdown converter
* Gated the v4.0.3 fallback `error_log()` call behind `WP_DEBUG`
* Hardened sanitization of `$_GET['type']` in the export callback
* Added regression tests for the v4.0.3 `Invalid HTML was provided` fallback and for the new `zip_folder()` throw behavior

= 4.0.3 =

* Catch `InvalidArgumentException` from `league/html-to-markdown` in `convert_content()` and fall back to the post's raw HTML for that single post instead of aborting the entire export with "Jekyll Export failed: Invalid HTML was provided" ([#400](https://github.com/benbalter/wordpress-static-site-exporter/issues/400))

= 4.0.2 =

* Add shutdown handler to surface fatal errors (memory exhaustion, max execution time) during export with actionable error messages instead of a generic WordPress critical error page
* Add proactive `memory_limit` pre-flight check (warns when below 64MB) in `validate_environment()`
* Display admin error notice on Tools → Export when environment validation fails, before the user clicks Export

= 4.0.1 =

* Security: Use cryptographically secure randomness (`wp_generate_password`) instead of `md5(time())` for the export temp directory name to prevent symlink/TOCTOU attacks on shared hosts (CWE-330/377)
* Security: Reject non-CLI access in deprecated `jekyll-export-cli.php` before bootstrapping WordPress (CWE-665)
* Security: Sanitize each path segment of page filenames as defense-in-depth against path traversal (CWE-22)
* Fix stale `$upload_basedir` cache in `copy_recursive()` on multisite by keying it on the current blog ID

= 4.0.0 =

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

== Upgrade Notice ==

= 4.6.0 =

Posts with a More tag or Page Breaks are exported in full again. Since 2.4.2, the export stopped at the first break. Re-export to get the rest of those posts.

= 4.5.0 =

Security update: fixes two bugs in the bundled HTML-to-Markdown converter that let a single large post stall an export. Some converted Markdown changes; see the changelog.
