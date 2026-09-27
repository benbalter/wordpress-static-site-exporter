## Command-line Usage

If you're having trouble with your web server timing out before the export is complete, or if you just like terminal better, you may enjoy the command-line tool.

It works just like the plugin, but produces the zipfile on STDOUT:

```
php jekyll-export-cli.php > jekyll-export.zip
```

If using this method, you must run first `cd` into the wordpress-to-jekyll-exporter directory.

Alternatively, if you have [WP-CLI](http://wp-cli.org) installed, you can run:

```
wp jekyll-export > export.zip
```

The WP-CLI version will provide greater compatibility for alternate WordPress environments, such as when `wp-content` isn't in the usual location.

To write the zip straight to a file instead of STDOUT, pass `--output`. This keeps a stray PHP notice or warning from ending up inside the archive:

```
wp jekyll-export --output=export.zip
```

## Filtering by Category or Tag

You can export only specific categories or tags using the WP-CLI command. This is useful when you want to convert just one section of your WordPress site instead of the entire corpus.

### Export posts from a specific category:

```bash
wp jekyll-export --category=technology > export.zip
```

### Export posts from multiple categories:

```bash
wp jekyll-export --category=tech,news,updates > export.zip
```

### Export posts with a specific tag:

```bash
wp jekyll-export --tag=featured > export.zip
```

### Export only pages (or specific post types):

```bash
wp jekyll-export --post_type=page > export.zip
```

### Combine filters:

```bash
wp jekyll-export --category=technology --tag=featured --post_type=post > export.zip
```

## Using Filters in PHP

If you're using the plugin via PHP code or want more control, you can use the `jekyll_export_taxonomy_filters` filter:

```php
add_filter( 'jekyll_export_taxonomy_filters', function() {
    return array(
        'category' => array( 'technology', 'science' ),
        'post_tag' => array( 'featured' ),
    );
} );

// Then trigger the export
global $jekyll_export;
$jekyll_export->export();
```
