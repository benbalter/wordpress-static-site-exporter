## Custom fields

When using custom fields (e.g. with the Advanced Custom fields plugin) you might have to register a filter to convert array style configs to plain values.

### Available Filters

The plugin provides two filters for customizing post metadata:

- **`jekyll_export_meta`**: Filters the metadata for a single post before it's merged with taxonomy terms. Receives `$meta` array and `$post` object as parameters.
- **`jekyll_export_post_meta`**: Filters the complete metadata array (including taxonomy terms) just before it's written to the YAML frontmatter. Receives `$meta` array and `$post` object as parameters. This is the recommended filter for most use cases.

**Note:** As of the latest version, the plugin no longer automatically removes empty or falsy values from the frontmatter. All metadata is preserved by default. If you want to remove certain fields, you can use the `jekyll_export_post_meta` filter to customize this behavior.

By default, the plugin saves custom fields in an array structure that is exported as: 

```php
["my-bool"]=>
    array(1) {
        [0] => string(1) "1"
    }
["location"]=>
    array(1) {
        [0] => string(88) "My address"
    }
```

And this leads to a YAML structure like:

```yaml
my-bool:
- "1"
location:
- 'My address'
```

This is likely not the structure you expect or want to work with. You can convert it using a filter:

```php
add_filter( 'jekyll_export_meta', function($meta) {
    foreach ($meta as $key => $value) {
        if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
            $meta[$key] = $value[0];
        }
    }

    return $meta;
});
```

A more complete solution could look like that:

```php
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
```

### Removing Empty or Falsy Values

If you want to remove empty or falsy values from the frontmatter (similar to the pre-3.0.3 behavior), you can use the `jekyll_export_post_meta` filter:

```php
add_filter( 'jekyll_export_post_meta', function( $meta, $post ) {
    foreach ( $meta as $key => $value ) {
        // Remove falsy values except numeric 0
        if ( ! is_numeric( $value ) && ! $value ) {
            unset( $meta[ $key ] );
        }
    }
    return $meta;
}, 10, 2 );
```

