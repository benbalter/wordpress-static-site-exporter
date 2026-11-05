<?php
/**
 * Run the exporter from the command line and spit the zipfile to STDOUT.
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben.balter@github.com>
 * @copyright  2013-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Extends WP CLI to add our export command
	 */
	class Jekyll_Export_Command extends WP_CLI_Command {

		/**
		 * Export WordPress content as Markdown for a static site generator.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Lay the export out for this static site generator: jekyll (the
		 * default), hugo, astro, or eleventy.
		 *
		 * [--category=<category>]
		 * : Export only posts from this category (slug or comma-separated slugs).
		 *
		 * [--tag=<tag>]
		 * : Export only posts with this tag (slug or comma-separated slugs).
		 *
		 * [--post_type=<post_type>]
		 * : Export only specific post types (comma-separated).
		 *
		 * [--post_status=<post_status>]
		 * : Export only posts with these statuses (comma-separated), e.g. `publish`
		 * to leave out drafts. Defaults to publish, future, draft, pending,
		 * private, and inherit.
		 *
		 * [--skip-uploads]
		 * : Don't copy wp-content/uploads into the export. Useful when media is
		 * served from a CDN, or the uploads folder is too big to zip.
		 *
		 * [--output=<file>]
		 * : Write the zip to this file instead of STDOUT.
		 *
		 * ## EXAMPLES
		 *
		 *     # Export all content
		 *     $ wp jekyll-export > export.zip
		 *
		 *     # Export for Hugo instead of Jekyll
		 *     $ wp jekyll-export --format=hugo --output=export.zip
		 *
		 *     # Export only posts in "technology" category
		 *     $ wp jekyll-export --category=technology > export.zip
		 *
		 *     # Export posts from multiple categories
		 *     $ wp jekyll-export --category=tech,news > export.zip
		 *
		 *     # Export posts with specific tag
		 *     $ wp jekyll-export --tag=featured > export.zip
		 *
		 *     # Export only pages
		 *     $ wp jekyll-export --post_type=page > export.zip
		 *
		 *     # Export only published content, without media
		 *     $ wp jekyll-export --post_status=publish --skip-uploads > export.zip
		 *
		 *     # Write the zip to a file
		 *     $ wp jekyll-export --output=export.zip
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( $args = array(), $assoc_args = array() ) {
			global $jekyll_export;

			// Check the format first, so a typo fails before any work starts.
			if ( ! empty( $assoc_args['format'] ) && ! $jekyll_export->set_format( $assoc_args['format'] ) ) {
				WP_CLI::error(
					sprintf(
						/* translators: 1: requested format, 2: comma-separated list of available formats */
						__( 'Unknown export format "%1$s". Choose one of: %2$s.', 'jekyll-exporter' ),
						$assoc_args['format'],
						implode( ', ', Jekyll_Export_Format::slugs() )
					)
				);
			}

			// Set up taxonomy filters based on CLI arguments.
			$taxonomy_filters = array();

			if ( ! empty( $assoc_args['category'] ) ) {
				$categories = wp_parse_list( $assoc_args['category'] );
				if ( ! empty( $categories ) ) {
					$taxonomy_filters['category'] = $categories;
				}
			}

			if ( ! empty( $assoc_args['tag'] ) ) {
				$tags = wp_parse_list( $assoc_args['tag'] );
				if ( ! empty( $tags ) ) {
					$taxonomy_filters['post_tag'] = $tags;
				}
			}

			// Apply taxonomy filters.
			if ( ! empty( $taxonomy_filters ) ) {
				add_filter(
					'jekyll_export_taxonomy_filters',
					function () use ( $taxonomy_filters ) {
						return $taxonomy_filters;
					}
				);
			}

			// Set up post type filter if specified.
			if ( ! empty( $assoc_args['post_type'] ) ) {
				$post_types = array_filter( array_map( 'trim', explode( ',', $assoc_args['post_type'] ) ) );
				if ( ! empty( $post_types ) ) {
					add_filter(
						'jekyll_export_post_types',
						function () use ( $post_types ) {
							return $post_types;
						}
					);
				}
			}

			// Set up post status filter if specified.
			if ( ! empty( $assoc_args['post_status'] ) ) {
				$post_statuses = wp_parse_list( $assoc_args['post_status'] );
				if ( ! empty( $post_statuses ) ) {
					add_filter(
						'jekyll_export_post_statuses',
						function () use ( $post_statuses ) {
							return $post_statuses;
						}
					);
				}
			}
			if ( ! empty( $assoc_args['skip-uploads'] ) ) {
				add_filter( 'jekyll_export_skip_uploads', '__return_true' );
			}
			if ( empty( $assoc_args['output'] ) ) {
				$jekyll_export->export();
				return;
			}

			$jekyll_export->export( $assoc_args['output'] );
			/* translators: %s: path of the zip file the export was written to */
			WP_CLI::success( sprintf( __( 'Exported to %s', 'jekyll-exporter' ), $assoc_args['output'] ) );
		}
	}

	WP_CLI::add_command( 'jekyll-export', 'Jekyll_Export_Command' );

}
