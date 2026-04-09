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
		 * Export WordPress content to Jekyll format.
		 *
		 * ## OPTIONS
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
		 * ## EXAMPLES
		 *
		 *     # Export all content
		 *     $ wp jekyll-export > export.zip
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
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( $args = array(), $assoc_args = array() ) {
			global $jekyll_export;

			// Set up taxonomy filters based on CLI arguments.
			$taxonomy_filters = array();

			if ( ! empty( $assoc_args['category'] ) ) {
				$categories = array_filter( array_map( 'trim', explode( ',', $assoc_args['category'] ) ) );
				if ( ! empty( $categories ) ) {
					$taxonomy_filters['category'] = $categories;
				}
			}

			if ( ! empty( $assoc_args['tag'] ) ) {
				$tags = array_filter( array_map( 'trim', explode( ',', $assoc_args['tag'] ) ) );
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

			$jekyll_export->export();
		}
	}

	WP_CLI::add_command( 'jekyll-export', 'Jekyll_Export_Command' );

}
