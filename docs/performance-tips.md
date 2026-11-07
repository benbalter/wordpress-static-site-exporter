# Performance Tips for Large Sites

If you're running a large WordPress site with thousands of posts or gigabytes of uploads, here are some tips to make the export process faster and more efficient.

## Quick Wins

### 1. Use WP-CLI Instead of Browser Export

Browser-based exports are subject to PHP execution time limits (typically 30-300 seconds). Use WP-CLI for unlimited execution time:

```bash
wp jekyll-export > export.zip
```

### 2. Skip Uploads if You Don't Need Them

If your images and files are served from a CDN or you plan to handle them separately, you can skip the uploads directory entirely:

```php
// Add to your theme's functions.php or a custom plugin
add_filter( 'jekyll_export_skip_uploads', '__return_true' );
```

This can save significant time and disk space, especially if you have gigabytes of media files.

### 3. Exclude Cache and Temporary Directories

Many sites accumulate cache files and temporary uploads that aren't needed in the export:

```php
add_filter( 'jekyll_export_excluded_upload_dirs', function( $excluded ) {
    return array_merge( $excluded, array(
        '/cache/',
        '/tmp/',
        '/backup/',
        '/wc-logs/',  // WooCommerce logs
        '/wpml/',     // WPML cache
    ) );
} );
```

## Still Having Timeout Issues?

If exports are still timing out, try these solutions:

### Increase PHP Memory and Time Limits

Add to your `wp-config.php`:

```php
define( 'WP_MEMORY_LIMIT', '512M' );
@ini_set( 'max_execution_time', '600' ); // 10 minutes
```

### Export Only Specific Post Types

If you only need posts (not pages or other custom post types):

```php
add_filter( 'jekyll_export_post_types', function() {
    return array( 'post' ); // Only export posts
} );
```

### Run Export During Off-Peak Hours

Schedule the export using WP-CLI and cron during low-traffic periods:

```bash
# Add to crontab to run at 3 AM
0 3 * * 0 cd /path/to/wordpress && wp jekyll-export > /path/to/backups/jekyll-$(date +\%Y\%m\%d).zip
```

## Measuring Performance

To see how long your export takes:

### Via WP-CLI with Timing
```bash
time wp jekyll-export > export.zip
```

### Via the `jekyll_export_complete` action

`jekyll_export_complete` fires once after a successful export, after the archive has been sent or saved and temporary files have been cleaned up. It does not fire when the export fails. Output echoed by a callback is discarded.

```php
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
```

## Database Optimization

Before exporting, optimize your database:

```bash
wp db optimize
```

This can improve query performance during the export process.

## Hardware Recommendations

For very large sites (10,000+ posts), consider:

- **SSD storage** for faster file I/O
- **At least 2GB RAM** for PHP
- **Modern PHP version** (7.4+ or 8.0+) for better performance

## Troubleshooting Slow Exports

If exports are still slow after optimizations:

1. **Check slow query log**: Identify if specific database queries are bottlenecks
2. **Profile plugin conflicts**: Disable other plugins temporarily to isolate issues
3. **Monitor server resources**: Check if CPU/memory/disk I/O is maxed out
4. **Consider hosting**: Shared hosting may have strict resource limits

## Getting Help

If exports are still slow, ask in the [support forum](https://wordpress.org/support/plugin/jekyll-exporter/) (see [Support](SUPPORT.md)). Include how many posts and pages you have, the size of `wp-content/uploads`, your PHP version and memory limit, how long the export runs before it stops, and any errors from your PHP error log.
