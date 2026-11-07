## Command-line Usage

If your web server times out before the export finishes, or you'd rather use the terminal, run the export with [WP-CLI](https://wp-cli.org). It works just like the plugin, but writes the zip file to STDOUT, and it copes better with unusual setups, such as a `wp-content` folder in an unusual location:

```
wp jekyll-export > export.zip
```

To write the zip straight to a file instead of STDOUT, pass `--output`. This keeps a stray PHP notice or warning from ending up inside the archive:

```
wp jekyll-export --output=export.zip
```

To lay the export out for Hugo, Astro, or Eleventy instead of Jekyll, pass `--format` (see [Output formats](output-formats.md)):

```
wp jekyll-export --format=hugo --output=export.zip
```

To leave out drafts and other unpublished content, pass the statuses you want with `--post_status`:

```
wp jekyll-export --post_status=publish > export.zip
```

If your media is served from a CDN, or the uploads folder is too big to zip, pass `--skip-uploads` to export without `wp-content/uploads`:

```
wp jekyll-export --skip-uploads > export.zip
```

To export only some categories, tags, or post types, see [Selective Export](selective-export.md).
