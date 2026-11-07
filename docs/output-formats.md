## Output formats

Every export is Markdown with YAML front matter, which Jekyll, Hugo, Astro, and Eleventy all read. The format you pick decides where the files go, how drafts are marked, which front matter keys are written, and which config file comes with them. Jekyll is the default.

Pick a format from the dropdown under **Tools → Export to Static Site**, or pass `--format` on the command line:

```
wp jekyll-export --format=hugo --output=export.zip
```

| | Jekyll | Hugo | Astro | Eleventy |
|---|---|---|---|---|
| Posts | `_posts/YYYY-MM-DD-slug.md` | `content/posts/slug.md` | `src/content/posts/slug.md` | `posts/slug.md` |
| Pages | site root, by page path | `content/`, by page path | `src/content/pages/`, by page path | site root, by page path |
| Custom post types | `_<type>s/` | `content/<type>/` | `src/content/<type>/` | `<type>/` |
| Drafts | `_drafts/` | next to posts, `draft: true` | next to posts, `draft: true` | next to posts, `draft: true` and `permalink: false` |
| Original URL | `permalink` | `url` | `permalink` | `permalink` |
| `layout` | kept | left out | left out | kept |
| Uploads | `wp-content/uploads/` | `static/wp-content/uploads/` | `public/wp-content/uploads/` | `wp-content/uploads/` |
| Config | `_config.yml` | `hugo.yaml` | `src/content.config.ts` | `_data/site.json`, `eleventy.config.mjs`, `_includes/` |

In every format, links to your images and other uploads are written as `/wp-content/uploads/...`, and each generator serves the uploads folder from the site root, so the links keep working.

Posts keep the URL WordPress gave them unless your site uses plain permalinks (`/?p=123`). Hugo, Astro, and Eleventy can't publish a page at a URL with a query string, so for them those posts leave out the original URL and get the generator's default instead. Drafts always have plain permalinks, so the same goes for drafts. Scheduled posts get the URL they'll have once they're published.

Jekyll and Hugo hold back posts dated in the future (scheduled posts) unless you ask for them. Eleventy, and Astro with the route below, publish them right away.

A `layout` custom field is always kept, even in formats that leave out the default one.

### Jekyll

Unzip the export into your Jekyll site. Posts go in `_posts/`, drafts in `_drafts/` (run `jekyll serve --drafts` to see them), and pages at the root. `_config.yml` has your site's name, description, and URL, and declares a collection for each custom post type. Each post's `permalink` is its WordPress URL, so Jekyll publishes it at the same address.

### Hugo

Unzip the export into a new Hugo site, or copy `content/`, `static/`, and `hugo.yaml` into an existing one. `hugo.yaml` sets `title`, `baseURL`, and `locale` (Hugo 0.158 and later; on older versions, rename it to `languageCode`), plus your tagline as `params.description`.

Each post's `url` is its WordPress URL, so Hugo publishes it at the same address. Drafts have `draft: true`, so Hugo leaves them out unless you run `hugo --buildDrafts`. Hugo also leaves out posts dated in the future (scheduled posts) unless you pass `--buildFuture`.

Hugo needs a theme or templates to turn the content into pages. Without one, it builds without error but writes no HTML.

### Astro

Copy `src/content/`, `src/content.config.ts`, and `public/wp-content/` into an Astro project (start one with `npm create astro@latest`). `src/content.config.ts` declares a `posts` and a `pages` collection, plus one for each custom post type, with a schema that accepts any front matter, so the first build works without editing it.

Astro doesn't create pages from a content collection on its own. Add a route such as `src/pages/[...path].astro` to publish posts at their WordPress URLs (from `permalink`), pages at their WordPress paths, and to skip drafts:

```astro
---
import { getCollection, render } from 'astro:content';

export async function getStaticPaths() {
  const published = ({ data }) => !data.draft;
  const entries = [
    ...(await getCollection('posts', published)),
    ...(await getCollection('pages', published)),
  ];

  return entries.map((entry) => ({
    // Posts keep their WordPress URL; pages use their folder path (about/team).
    params: { path: (entry.data.permalink ?? entry.id).replace(/^\/|\/$/g, '') },
    props: { entry },
  }));
}

const { entry } = Astro.props;
const { Content } = await render(entry);
---
<h1>{entry.data.title}</h1>
<Content />
```

To publish a custom post type too, add `...(await getCollection('<type>', published))` to the list.

### Eleventy

Unzip the export into a new Eleventy site and run `npx @11ty/eleventy`. Each post's `permalink` is its WordPress URL, so Eleventy publishes it at the same address, and drafts have `permalink: false`, so Eleventy doesn't write them at all. `_data/site.json` has your site's title, description, and URL, available in templates as `site`.

The export also includes what Eleventy needs to build it:

* `eleventy.config.mjs` copies `wp-content/` to the built site, so your uploads are published, and stops Eleventy from running Liquid over your posts, which fails on a post that quotes a Liquid or Nunjucks tag (double curly braces, or a curly brace and percent sign). If you already have an Eleventy config, copy these two settings into it:

  ```js
  eleventyConfig.addPassthroughCopy("wp-content");
  // and in the exported config object:
  markdownTemplateEngine: false,
  ```

* `_includes/post.njk`, `_includes/page.njk`, and one for each custom post type are the layouts the posts' `layout` keys name. Eleventy stops with an error on a layout it can't find, so these start out showing only the content. Replace them with your own.

### Adding a format

The `jekyll_export_formats` filter adds a format or changes a built-in one. Any setting a format leaves out takes Jekyll's value:

```php
add_filter( 'jekyll_export_formats', function ( $formats ) {
    $formats['zola'] = array(
        'label'              => 'Zola',
        'posts_dir'          => 'content/blog/',
        'pages_dir'          => 'content/',
        'post_type_dir'      => 'content/%s/',
        'date_prefix'        => false,
        'drafts_dir'         => '',
        'draft_front_matter' => array( 'draft' => true ),
        'permalink_key'      => 'path',
        'query_permalinks'   => false,
        'layout'             => false,
        'uploads_dir'        => 'static/',
        'config'             => '',
    );
    return $formats;
} );
```

To change the default format without picking it each time, filter `jekyll_export_format`:

```php
add_filter( 'jekyll_export_format', function () {
    return 'hugo';
} );
```
