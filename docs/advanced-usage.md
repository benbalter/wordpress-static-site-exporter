# Advanced usage

Most sites only need the one-click export from the Tools menu. Everything here is optional.

## Export from the command line

Large sites can time out in the browser. [WP-CLI](command-line-usage.md) runs outside the web server, so its time limit doesn't apply, and it writes the zip straight to a file:

```
wp jekyll-export --output=export.zip
```

## Choose what to export

Export one category, tag, post type, or status, from the command line or with PHP filters. See [Selective export](selective-export.md).

## Custom post types and custom fields

Custom post types need one filter to be included, and are exported as Jekyll collections. Custom fields become front matter, and filters let you rename, drop, or add fields. See [Custom post types](custom-post-types.md) and [Custom fields](custom-fields.md).

## Large sites

Skipping uploads, excluding cache folders, and raising PHP's limits all help. See [Performance tips for large sites](performance-tips.md).
