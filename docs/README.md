![Static Site Exporter: WordPress to Markdown for Jekyll, Hugo, and more](../.wordpress-org/banner-1544x500.png)
  
# Static Site Exporter: WordPress to Jekyll and Hugo Markdown exporter

One-click WordPress plugin that converts all posts, pages, taxonomies, metadata, and settings to Markdown and YAML which can be dropped into Jekyll (or Hugo or any other Markdown and YAML based site engine).

[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/jekyll-exporter)](https://wordpress.org/plugins/jekyll-exporter/) [![WordPress plugin rating](https://img.shields.io/wordpress/plugin/rating/jekyll-exporter)](https://wordpress.org/support/plugin/jekyll-exporter/reviews/) [![CI](https://github.com/benbalter/wordpress-static-site-exporter/actions/workflows/ci.yml/badge.svg)](https://github.com/benbalter/wordpress-static-site-exporter/actions/workflows/ci.yml) [![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](http://makeapullrequest.com)

View plugin in [the WordPress plugin directory](https://wordpress.org/plugins/jekyll-exporter/).

## Features

* Converts all posts, pages, and settings from WordPress to Markdown and YAML for use in Jekyll (or Hugo or any other Markdown and YAML based site engine)
* Export what your users see, not what the database stores (runs post content through `the_content` filter prior to export, allowing third-party plugins to modify the output)
* Converts all `post_content` to Markdown
* Converts all `post_meta` and fields within the `wp_posts` table to YAML front matter for parsing by Jekyll
* Generates a `_config.yml` with all settings in the `wp_options` table
* Outputs a single zip file with `_config.yml`, pages, and `_posts` folder containing `.md` files for each post in the proper Jekyll naming convention
* **Selective export**: Export only specific categories, tags, or post types using WP-CLI
* No settings. Just a single click.

## Usage

1. In your WordPress dashboard, go to **Plugins → Add New**, search for **Static Site Exporter**, then install and activate it
2. Select `Export to Jekyll` from the `Tools` menu
3. Unzip the download into your static site

For large sites, use [WP-CLI](command-line-usage.md) instead: `wp jekyll-export --output=export.zip`.

## More information

See [the full documentation](https://github.com/benbalter/wordpress-static-site-exporter/tree/main/docs):

* [Frequently asked questions](../docs/faq.md)
* [Changelog](../docs/changelog.md)
* [Command-line-usage](../docs/command-line-usage.md)
* [Selective export by category or tag](../docs/selective-export.md)
* [Custom post types](../docs/custom-post-types.md)
* [Custom fields](../docs/custom-fields.md)
* [Developing locally](../docs/developing-locally.md)
* [Minimum required PHP version](../docs/required-php-version.md)
