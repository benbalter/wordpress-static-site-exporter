## Frequently Asked Questions

### Does it work with Hugo, Eleventy, or other static site generators?

Yes. Pick Hugo, Astro, or Eleventy from the format dropdown under **Tools → Export to Static Site**, or pass `--format` on the command line, and the export is laid out for that generator, with its folders, draft markers, and config file:

    wp jekyll-export --format=hugo --output=export.zip

Jekyll is the default. Every format is Markdown with YAML front matter, which most other generators read too. See [Output formats](output-formats.md) for what each one includes.

### My export times out, or the zip file is empty. What can I do?

Large sites can run past PHP's time or memory limits in the browser. Use WP-CLI instead, which has no time limit, and write the zip straight to a file:

    wp jekyll-export --output=export.zip

If your media library is large, add `--skip-uploads` and copy `wp-content/uploads` separately. See [Performance Tips for Large Sites](performance-tips.md) for more.

### Can I export only some of my posts?

Yes. With WP-CLI, filter by category, tag, post type, or status:

    wp jekyll-export --category=technology --post_status=publish > export.zip

See [Selective Export](selective-export.md) for every option, including the PHP filters.

### Where do my images and other media go?

The export copies `wp-content/uploads` into the zip and rewrites links to your media as relative URLs (`/wp-content/uploads/...`), so they keep working on the new site.

### Will my old URLs keep working?

Each post's front matter includes its WordPress URL (`permalink`, or `url` for Hugo), so Jekyll, Hugo, and Eleventy publish it at the same address. For Astro, [Output formats](output-formats.md) has a route that does the same.

### Are drafts exported?

Yes, into `_drafts/` for Jekyll. Hugo, Astro, and Eleventy keep drafts next to your posts, marked `draft: true`. To leave them out, use `wp jekyll-export --post_status=publish`.

### Does it export custom post types and custom fields?

Custom fields are exported as front matter. Custom post types need one filter to include them, and the export then declares each one as a Jekyll collection. See [Custom post types](custom-post-types.md) and [Custom fields](custom-fields.md).

### Does the export include the text after a "More" tag?

Yes. Each post is exported in full, including everything after a More tag (the "Continue reading" break) and every page of a post split with a Page Break. The More tag stays in the Markdown as `<!--more-->`, which Hugo treats as the end of a post's summary. To use it for excerpts in Jekyll, add `excerpt_separator: <!--more-->` to the front matter of the posts that have one. Setting it in `_config.yml` instead also works, but then any post without the tag uses its whole text as the excerpt.
