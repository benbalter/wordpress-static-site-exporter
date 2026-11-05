## Custom post types

To export custom post types, add a filter (e.g., in your theme's `functions.php`) like this:

```php
add_filter( 'jekyll_export_post_types', function() {
	return array('post', 'page', 'your-custom-post-type');
});
```

Each custom post type is exported as a Jekyll collection: posts of type `portfolio` go in `_portfolios/`, and the exported `_config.yml` declares that collection with `output: true`, so Jekyll builds a page for each one.
