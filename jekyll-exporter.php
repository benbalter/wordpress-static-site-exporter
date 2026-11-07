<?php
/**
 * Exports WordPress posts, pages, and options as YAML files parsable by Jekyll
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2026 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 *
 * @wordpress-plugin
 * Plugin Name: Static Site Exporter
 * Plugin URI:  https://github.com/benbalter/wordpress-static-site-exporter
 * Description: One-click plugin that converts all posts, pages, taxonomies, metadata, and settings to Markdown and YAML for Jekyll, Hugo, or other static site generators.
 * Version:     4.5.0
 * Author:      Ben Balter
 * Author URI:  https://ben.balter.com
 * Text Domain: jekyll-exporter
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Copyright 2012-2026 Ben Balter  (email : Ben@Balter.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Bail without loading anything on an unsupported PHP version. wp_die() here would
// take down the entire site (front end included) rather than just this plugin.
// Loaded only by WordPress; refuse direct requests to this file.
defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Static Site Exporter requires PHP 8.2 or later and has been disabled.', 'jekyll-exporter' ) . '</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/lib/class-export-format.php';
require_once __DIR__ . '/lib/cli.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/colspan-table-converter.php';

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Jekyll_Export
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben.balter@github.com>
 * @copyright  2012-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 */
class Jekyll_Export {

	/**
	 * Strings to strip from option keys on export
	 *
	 * @var array
	 */
	public $rename_options = array( 'site', 'blog' );

	/**
	 * Array of wp_options value to convert to _config.yml
	 *
	 * @var array
	 */
	public $options = array(
		'name',
		'description',
		'url',
	);

	/**
	 * Path to the temporary export directory
	 *
	 * @var string
	 */
	public $dir;

	/**
	 * Filenames written so far, keyed by export directory, mapping each
	 * filename to the ID of the post written there. See write().
	 *
	 * @var array<string, array<string, int>>
	 */
	private $written_files = array();

	/**
	 * Path to the temporary zip file
	 *
	 * @var string
	 */
	public $zip;

	/**
	 * Slug of the Tools page that starts an export.
	 */
	const ADMIN_PAGE = 'jekyll-export';

	/**
	 * Hook into WP Core
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'current_screen', array( $this, 'callback' ) );
		add_action( 'admin_notices', array( $this, 'display_environment_notice' ) );
	}

	/**
	 * Listens for page callback, intercepts and runs export
	 */
	public function callback() {
		if ( get_current_screen()->id !== 'export' ) {
			return;
		}

		if ( ! isset( $_GET['type'] ) || 'jekyll' !== sanitize_text_field( wp_unslash( $_GET['type'] ) ) ) {
			return;
		}

		// Both checks used to return silently, which dropped the user on the
		// plain Export screen with no hint why. The menu link carries a nonce
		// minted when the admin page loaded, so a tab left open past the nonce
		// lifetime (12 to 24 hours) is the common way to hit this.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You need the Manage Options capability (usually an Administrator) to export this site.', 'jekyll-exporter' ),
				esc_html__( 'Export not allowed', 'jekyll-exporter' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jekyll_export' ) ) {
			wp_die(
				esc_html__( 'This export link has expired. Reload the page, then choose Export to Static Site from the Tools menu again.', 'jekyll-exporter' ),
				esc_html__( 'Export link expired', 'jekyll-exporter' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		if ( isset( $_GET['format'] ) ) {
			$format = sanitize_key( wp_unslash( $_GET['format'] ) );
			if ( ! $this->set_format( $format ) ) {
				$this->die_unknown_format( $format );
			}
		}

		$this->export();
		exit();
	}


	/**
	 * Add menu option to tools list
	 *
	 * The menu opens a screen where you pick the format, rather than linking
	 * straight to the download, so the nonce is minted when you click Export,
	 * not when some earlier admin page rendered the menu.
	 */
	public function register_menu() {
		add_management_page( __( 'Export to Static Site', 'jekyll-exporter' ), __( 'Export to Static Site', 'jekyll-exporter' ), 'manage_options', self::ADMIN_PAGE, array( $this, 'render_admin_page' ) );
	}

	/**
	 * Render the Tools > Export to Static Site screen: a format picker and an
	 * Export button that submits to callback().
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You need the Manage Options capability (usually an Administrator) to export this site.', 'jekyll-exporter' ),
				esc_html__( 'Export not allowed', 'jekyll-exporter' ),
				array( 'response' => 403 )
			);
		}

		$selected = (string) apply_filters( 'jekyll_export_format', Jekyll_Export_Format::DEFAULT_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export to Static Site', 'jekyll-exporter' ); ?></h1>
			<p><?php esc_html_e( 'Download your posts, pages, and uploads as a zip of Markdown files with YAML front matter, laid out for the static site generator you choose.', 'jekyll-exporter' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'export.php' ) ); ?>">
				<input type="hidden" name="type" value="jekyll" />
				<?php wp_nonce_field( 'jekyll_export', '_wpnonce', false ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jekyll-export-format"><?php esc_html_e( 'Format', 'jekyll-exporter' ); ?></label></th>
						<td>
							<select name="format" id="jekyll-export-format">
								<?php foreach ( Jekyll_Export_Format::slugs() as $slug ) : ?>
									<?php $format = Jekyll_Export_Format::get( $slug ); ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>><?php echo esc_html( $format ? $format->label : $slug ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: %s: link to the output formats documentation */
										esc_html__( 'Jekyll is the default. %s explains where each format puts your content.', 'jekyll-exporter' ),
										'<a href="' . esc_url( 'https://github.com/benbalter/wordpress-static-site-exporter/blob/main/docs/output-formats.md' ) . '">' . esc_html__( 'Output formats', 'jekyll-exporter' ) . '</a>'
									)
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Export', 'jekyll-exporter' ), 'primary', '' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Use the given format for exports in this request.
	 *
	 * Shared by the admin screen and `wp jekyll-export --format`.
	 *
	 * @param string $slug The format's slug.
	 * @return bool False if no format has that slug.
	 */
	public function set_format( $slug ) {
		$slug = (string) $slug;
		if ( null === Jekyll_Export_Format::get( $slug ) ) {
			return false;
		}

		add_filter(
			'jekyll_export_format',
			function () use ( $slug ) {
				return $slug;
			}
		);

		return true;
	}

	/**
	 * The format this export is written in, from the `jekyll_export_format`
	 * filter.
	 *
	 * Looked up on every call rather than cached, since the filter can change
	 * between exports in the same request (WP-CLI scripts, tests).
	 *
	 * @return Jekyll_Export_Format
	 */
	public function get_format() {
		/**
		 * Filters the export's output format.
		 *
		 * @param string $slug A slug registered with `jekyll_export_formats`:
		 *                     jekyll (default), hugo, astro, or eleventy.
		 */
		$slug   = (string) apply_filters( 'jekyll_export_format', Jekyll_Export_Format::DEFAULT_SLUG );
		$format = Jekyll_Export_Format::get( $slug );

		if ( null === $format ) {
			$this->die_unknown_format( $slug );
		}

		return $format;
	}

	/**
	 * Stop with a message naming the formats that do exist, rather than
	 * silently exporting something the user didn't ask for.
	 *
	 * @param string $slug The unknown slug.
	 * @return never
	 */
	protected function die_unknown_format( $slug ) {
		wp_die(
			esc_html(
				sprintf(
					/* translators: 1: requested format, 2: comma-separated list of available formats */
					__( 'Unknown export format "%1$s". Choose one of: %2$s.', 'jekyll-exporter' ),
					$slug,
					implode( ', ', Jekyll_Export_Format::slugs() )
				)
			),
			esc_html__( 'Static Site Exporter', 'jekyll-exporter' ),
			array(
				'response'  => 400,
				'back_link' => true,
			)
		);
	}

	/**
	 * Display an admin notice on the Tools → Export page when the server
	 * environment does not meet the requirements for a Jekyll export.
	 */
	public function display_environment_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'export', 'tools_page_' . self::ADMIN_PAGE ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$validation = $this->validate_environment();
		if ( ! is_wp_error( $validation ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'Static Site Exporter:', 'jekyll-exporter' ) . '</strong> ';
		echo wp_kses_post( implode( '<br>', $validation->get_error_messages() ) );
		echo '</p></div>';
	}


	/**
	 * Get an array of all post and page IDs to export.
	 *
	 * @return int[] post IDs, in ascending order.
	 */
	public function get_posts() {
		// Revisions are excluded by default (they would fill `_drafts/` with duplicate copies
		// of every post). Re-add 'revision' via the `jekyll_export_post_types` filter if needed.
		$post_types = (array) apply_filters( 'jekyll_export_post_types', array( 'post', 'page' ) );

		// Trash and auto-drafts are never exported. 'inherit' only matches
		// revisions and attachments, which are exported only when added via the
		// `jekyll_export_post_types` filter.
		$post_statuses = (array) apply_filters( 'jekyll_export_post_statuses', array( 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ) );

		// Allow filtering by taxonomy terms (e.g., categories, tags).
		$taxonomy_filters = apply_filters( 'jekyll_export_taxonomy_filters', array() );

		// Use AND logic between taxonomies, OR logic within taxonomy.
		$tax_query = array( 'relation' => 'AND' );
		foreach ( (array) $taxonomy_filters as $taxonomy => $terms ) {
			if ( empty( $terms ) ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'slug',
				'terms'            => (array) $terms,
				'include_children' => false,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $post_statuses,
				'tax_query'              => count( $tax_query ) > 1 ? $tax_query : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				// Other plugins' query filters (multilingual, membership, and
				// visibility plugins) would otherwise drop posts from the export
				// without a trace; the export filters below are the supported way
				// to narrow it.
				'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Convert a posts meta data (both post_meta and the fields in wp_posts) to key value pairs for export
	 *
	 * @param WP_Post $post the post.
	 */
	public function convert_meta( $post ) {

		// Cache user data to avoid repeated database queries.
		static $user_cache = array();
		if ( ! isset( $user_cache[ $post->post_author ] ) ) {
			$user_data                        = get_userdata( (int) $post->post_author );
			$user_cache[ $post->post_author ] = $user_data ? $user_data->display_name : '';
		}

		$output = array(
			'id'      => $post->ID,
			'title'   => get_the_title( $post ),
			'date'    => get_the_date( 'c', $post ),
			'author'  => $user_cache[ $post->post_author ],
			'excerpt' => $post->post_excerpt,
			'layout'  => get_post_type( $post ),
			'guid'    => $post->guid,
		);

		// Preserve exact permalink, since Jekyll doesn't support redirection.
		if ( 'page' !== $post->post_type ) {
			$output['permalink'] = $this->make_url_relative( get_permalink( $post ) );

			// WordPress gives a scheduled post a plain /?p=123 link until it
			// publishes. Formats that can't publish at a query string URL get
			// the address the post will have instead; Jekyll's output is
			// left exactly as it was.
			if ( 'future' === $post->post_status && ! $this->get_format()->query_permalinks ) {
				$scheduled              = clone $post;
				$scheduled->post_status = 'publish';
				$output['permalink']    = $this->make_url_relative( get_permalink( $scheduled ) );
			}
		}

		// Reserved front matter keys set above; a public custom field of the same name will
		// override these (an intentional feature for e.g. `layout`/`image`, but worth surfacing).
		$reserved_keys = array_keys( $output );

		// Convert traditional post_meta values, hide hidden values.
		foreach ( get_post_custom( $post->ID ) as $key => $value ) {

			if ( substr( $key, 0, 1 ) === '_' ) {
				continue;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && in_array( $key, $reserved_keys, true ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[jekyll-export] Custom field "%s" on post %d overrides reserved front matter.', $key, (int) $post->ID ) );
			}

			$output[ $key ] = $value;
		}

		$post_thumbnail_id = get_post_thumbnail_id( $post );

		if ( $post_thumbnail_id ) {
			$post_thumbnail_src = wp_get_attachment_image_src( $post_thumbnail_id, 'post-thumbnail' );

			if ( $post_thumbnail_src ) {
				$output['image'] = $this->make_url_relative( $post_thumbnail_src[0] );
			}
		}

		$output = $this->get_format()->map_front_matter( $output, $post );

		$output = apply_filters( 'jekyll_export_meta', $output, $post );
		return $output;
	}


	/**
	 * Convert post taxonomies for export
	 *
	 * @param WP_Post $post the Post object.
	 * @return array an array of converted terms
	 */
	public function convert_terms( $post ) {

		$output = array();

		foreach ( get_object_taxonomies(
			get_post_type( $post )
		) as $tax ) {

			$terms = get_the_terms( $post, $tax );

			// Convert tax name for Jekyll.
			switch ( $tax ) {
				case 'post_tag':
					$tax = 'tags';
					break;
				case 'category':
					$tax = 'categories';
					break;
			}

			if ( 'post_format' === $tax ) {
				$output['format'] = get_post_format( $post );
			} elseif ( is_array( $terms ) ) {
				$output[ $tax ] = wp_list_pluck( $terms, 'name' );
			}
		}

		$output = apply_filters( 'jekyll_export_terms', $output, $post );
		return $output;
	}

	/**
	 * Localize URLs in HTML content to use relative paths instead of absolute URLs.
	 *
	 * Only link and media URLs (`href`, `src`, and each `srcset` candidate) are
	 * rewritten, so http://example.org/wp-content/uploads/image.jpg becomes
	 * /wp-content/uploads/image.jpg while URLs in text, code samples, and query
	 * strings are left alone.
	 *
	 * @param String $content the HTML content to localize.
	 * @return String the content with localized URLs
	 */
	public function localize_urls( $content ) {
		$tags = new WP_HTML_Tag_Processor( (string) $content );

		while ( $tags->next_tag() ) {
			foreach ( array( 'href', 'src' ) as $attribute ) {
				$value = $tags->get_attribute( $attribute );
				if ( ! is_string( $value ) ) {
					continue;
				}

				$relative = $this->make_url_relative( $value );
				if ( $relative !== $value ) {
					$tags->set_attribute( $attribute, $relative );
				}
			}

			$srcset = $tags->get_attribute( 'srcset' );
			if ( is_string( $srcset ) ) {
				// Each candidate is a URL, optionally followed by a descriptor.
				$relative = preg_replace_callback(
					'/(^|,)(\s*)(\S+)/',
					function ( $matches ) {
						return $matches[1] . $matches[2] . $this->make_url_relative( $matches[3] );
					},
					$srcset
				);
				if ( $relative !== $srcset ) {
					$tags->set_attribute( 'srcset', $relative );
				}
			}
		}

		return apply_filters( 'jekyll_export_localized_urls', $tags->get_updated_html() );
	}

	/**
	 * Localize every occurrence of the site's URLs in content that isn't parsed
	 * as HTML (Jetpack Markdown source and the raw-HTML fallback).
	 *
	 * @param String $content the content to localize.
	 * @return String the content with localized URLs
	 */
	protected function localize_urls_in_text( $content ) {
		// Both schemes are stripped, so http://example.org/wp-content/uploads/image.jpg
		// becomes /wp-content/uploads/image.jpg.
		$content = str_replace( $this->get_local_urls(), '', $content );

		return apply_filters( 'jekyll_export_localized_urls', $content );
	}

	/**
	 * The site's own absolute URLs, to be stripped when making URLs relative.
	 *
	 * Includes both the site URL (where WordPress and the uploads live) and the
	 * home URL (what permalinks use), which differ when WordPress is installed in
	 * a subdirectory, each in both the http and https schemes.
	 *
	 * @return string[] URLs without trailing slashes, longest first.
	 */
	protected function get_local_urls() {
		$urls = array();
		foreach ( array( get_site_url(), home_url() ) as $url ) {
			$urls[] = untrailingslashit( set_url_scheme( $url, 'http' ) );
			$urls[] = untrailingslashit( set_url_scheme( $url, 'https' ) );
		}
		$urls = array_values( array_unique( $urls ) );

		// Longest first, so a subdirectory install's site URL is not left
		// half-replaced by the shorter home URL it starts with.
		usort(
			$urls,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		return $urls;
	}

	/**
	 * Make a single URL relative if it points at this site.
	 *
	 * The site or home URL is only stripped when it is followed by a path, query,
	 * or fragment (or nothing), so https://example.org does not match
	 * https://example.org.evil.com or https://example.org/wp does not eat the
	 * start of https://example.org/wp-post/.
	 *
	 * @param string $url the absolute URL.
	 * @return string the site-relative URL, or the URL unchanged if it is not local.
	 */
	public function make_url_relative( $url ) {
		foreach ( $this->get_local_urls() as $local ) {
			if ( 0 !== strpos( $url, $local ) ) {
				continue;
			}

			$rest = (string) substr( $url, strlen( $local ) );
			if ( '' === $rest ) {
				return '/';
			}

			if ( in_array( $rest[0], array( '/', '?', '#' ), true ) ) {
				return $rest;
			}
		}

		return $url;
	}

	/**
	 * Convert the main post content to Markdown.
	 *
	 * @param WP_Post $post the post to Convert.
	 * @return string the converted post content
	 */
	public function convert_content( $post ) {

		// check if jetpack markdown is available.
		if ( class_exists( 'WPCom_Markdown' ) ) {
			$wpcom_markdown_instance = WPCom_Markdown::get_instance();

			if ( $wpcom_markdown_instance && $wpcom_markdown_instance->is_posting_enabled() ) {
				// jetpack markdown is available so just return it.
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's edit_{$field} filter, which Jetpack hooks to turn the stored HTML back into Markdown.
				$content = apply_filters( 'edit_post_content', $post->post_content, $post->ID );
				// Localize URLs in Jetpack markdown content.
				$content = $this->localize_urls_in_text( $content );

				return $content;
			}
		}

		$html = $this->get_full_content( $post );
		// Localize URLs before converting to Markdown.
		$content = $this->localize_urls( $html );

		// Reuse converter instance to avoid recreating it for each post.
		static $default_converter = null;
		if ( null === $default_converter ) {
			$converter_options = apply_filters(
				'jekyll_export_markdown_converter_options',
				array(
					'header_style'      => 'atx',
					// Keep the More tag so Hugo (summary divider) and Jekyll
					// (excerpt_separator) can still split the excerpt there.
					'preserve_comments' => array( 'more' ),
				)
			);
			$default_converter = new HtmlConverter( $converter_options );
			$default_converter->getEnvironment()->addConverter( new ColspanTableConverter() );
		}

		// Allow tests and integrations to swap in a custom converter.  Re-applied
		// on every call (not cached) so per-test filters don't leak between tests.
		$converter = apply_filters( 'jekyll_export_html_converter', $default_converter );

		try {
			$markdown = $converter->convert( $content );
		} catch ( \Throwable $e ) {
			// Converter failed (e.g., empty/unparseable content, or an unexpected error
			// raised inside a converter). Fall back to the raw HTML for this post rather
			// than aborting the entire export.
			// See https://github.com/benbalter/wordpress-static-site-exporter/issues/400.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$post_id = (int) $post->ID;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[jekyll-export] HTML-to-Markdown conversion failed for post %d: %s. Falling back to raw HTML.', $post_id, $e->getMessage() ) );
			}
			return $this->html_fallback( $html, $post );
		}

		$markdown = $this->isolate_more_marker( $markdown, $html );
		$markdown = apply_filters( 'jekyll_export_markdown', $markdown, $post );
		$markdown = apply_filters( 'jekyll_export_content', $markdown, $post );
		return $markdown;
	}

	/**
	 * Get a post's complete HTML content, for every page and past the More tag.
	 *
	 * Don't use get_the_content() here: given a post, it decides whether to show
	 * the full text from the current request, and an export never looks like a
	 * single-post view, so it cuts the post at <!--more--> (adding a "Continue
	 * reading" link) and returns only the first <!--nextpage--> page.
	 *
	 * @see https://github.com/benbalter/wordpress-static-site-exporter/issues/509
	 *
	 * @param WP_Post $post the post being exported.
	 * @return string the post's HTML content
	 */
	private function get_full_content( $post ) {
		// Keep get_the_content()'s behavior for password-protected posts, so
		// their bodies don't end up in a public static site.
		if ( post_password_required( $post ) ) {
			return get_the_password_form( $post );
		}

		// Normalize custom More text (<!--more Keep reading-->) to the bare tag
		// static site generators look for. The Markdown converter keeps it and
		// drops other comments, including <!--nextpage--> and block delimiters.
		return (string) preg_replace( '/<!--more(.*?)?-->/', '<!--more-->', $post->post_content );
	}

	/**
	 * Put the preserved <!--more--> marker on a line of its own.
	 *
	 * The converter emits the comment inline, so the next paragraph starts on the
	 * marker's line (and, after inline text, it escapes the `<` as `\<`).
	 * CommonMark renderers read a line starting with `<!--` as a raw HTML block,
	 * which would swallow that paragraph's Markdown.
	 *
	 * @param string $markdown the converted Markdown.
	 * @param string $html the HTML it was converted from.
	 * @return string the Markdown with the marker separated by blank lines
	 */
	private function isolate_more_marker( $markdown, $html ) {
		if ( false === strpos( $html, '<!--more-->' ) ) {
			return $markdown;
		}

		return trim( (string) preg_replace( '/\s*\\\\?<!--more-->\s*/', "\n\n<!--more-->\n\n", $markdown, 1 ) );
	}

	/**
	 * Apply the raw-HTML fallback filters when Markdown conversion is skipped or fails.
	 *
	 * @param string  $content the raw HTML content, before URL localization.
	 * @param WP_Post $post the post being exported.
	 * @return string the filtered fallback content.
	 */
	private function html_fallback( $content, $post ) {
		$content = $this->localize_urls_in_text( $content );
		$content = apply_filters( 'jekyll_export_html', $content, $post );
		return apply_filters( 'jekyll_export_content', $content, $post );
	}

	/**
	 * Loop through and convert all posts to MD files with YAML headers
	 */
	public function convert_posts() {
		global $post;

		// Work in batches: load each batch's posts, meta, and terms in a few bulk
		// queries rather than several queries per post, then release them from
		// the in-memory object cache so memory use doesn't grow with the size of
		// the site.
		foreach ( array_chunk( $this->get_posts(), 100 ) as $post_ids ) {
			$this->prime_post_caches( $post_ids );

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				setup_postdata( $post );

				$meta = array_merge( $this->convert_meta( $post ), $this->convert_terms( $post ) );

				// Allow users to customize the post metadata before it's written.
				$meta = apply_filters( 'jekyll_export_post_meta', $meta, $post );

				$output  = "---\n";
				$output .= Yaml::dump( $meta );
				$output .= "---\n\n";
				$output .= $this->convert_content( $post );
				$this->write( $output, $post );
			}

			if ( wp_cache_supports( 'flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Load a batch of posts, their meta and terms, their featured images, and
	 * their authors into the object cache in bulk.
	 *
	 * @param int[] $post_ids the post IDs to prime.
	 * @return void
	 */
	protected function prime_post_caches( $post_ids ) {
		_prime_post_caches( $post_ids, true, true );

		$thumbnail_ids = array();
		$author_ids    = array();
		foreach ( $post_ids as $post_id ) {
			$thumbnail_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) {
				$thumbnail_ids[] = $thumbnail_id;
			}

			$batch_post = get_post( $post_id );
			if ( $batch_post && $batch_post->post_author ) {
				$author_ids[] = (int) $batch_post->post_author;
			}
		}

		if ( ! empty( $author_ids ) ) {
			cache_users( array_unique( $author_ids ) );
		}

		if ( ! empty( $thumbnail_ids ) ) {
			_prime_post_caches( array_unique( $thumbnail_ids ), false, true );
		}
	}

	/**
	 * Callback to modify the filesystem filter
	 */
	public function filesystem_method_filter() {
		return 'direct';
	}

	/**
	 * Return the base temporary directory appropriate for the current environment.
	 *
	 * On Azure Web Apps the standard temp dir behaves unexpectedly, so %HOME%\temp is
	 * used instead.  Both init_temp_dir() and validate_environment() call this so they
	 * always agree on which directory to use.
	 *
	 * @return string Temp directory path (may or may not have a trailing slash).
	 */
	protected function get_export_temp_dir() {
		// When on Azure Web App use %HOME%\temp\ to avoid weird default temp folder behavior.
		// For more information see https://github.com/projectkudu/kudu/wiki/Understanding-the-Azure-App-Service-file-system.
		return ( getenv( 'WEBSITE_SITE_NAME' ) !== false ) ? ( getenv( 'HOME' ) . DIRECTORY_SEPARATOR . 'temp' ) : get_temp_dir();
	}

	/**
	 * Initialize the temporary directory
	 */
	public function init_temp_dir() {
		global $wp_filesystem;

		add_filter( 'filesystem_method', array( $this, 'filesystem_method_filter' ) );

		WP_Filesystem();

		$temp_dir = $this->get_export_temp_dir();
		$wp_filesystem->mkdir( $temp_dir );

		// realpath() returns false if the directory doesn't exist, so we need to check.
		$real_temp_dir = realpath( $temp_dir );
		if ( false !== $real_temp_dir ) {
			$temp_dir = $real_temp_dir;
		}
		$temp_dir = trailingslashit( $temp_dir );

		// Use cryptographically secure randomness for the temp dir name to prevent
		// a co-located attacker from pre-creating the path (e.g. as a symlink) to
		// redirect file writes outside the export root.
		// The zip shares the suffix so concurrent or crashed exports can never
		// collide on a fixed filename in a shared temp directory.
		$suffix    = wp_generate_password( 12, false );
		$this->dir = trailingslashit( $temp_dir . 'wp-jekyll-' . $suffix );
		$this->zip = $temp_dir . 'wp-jekyll-' . $suffix . '.zip';

		$format = $this->get_format();

		$wp_filesystem->mkdir( $this->dir );
		wp_mkdir_p( $this->dir . $format->posts_dir );
		if ( '' !== $format->drafts_dir ) {
			wp_mkdir_p( $this->dir . $format->drafts_dir );
		}
		wp_mkdir_p( $this->dir . $format->uploads_dir . 'wp-content/' );
	}

	/**
	 * Validate that the server environment meets the requirements for export.
	 *
	 * @return true|WP_Error True if valid, WP_Error with details if not.
	 */
	public function validate_environment() {
		$errors = new WP_Error();

		if ( ! class_exists( 'ZipArchive' ) ) {
			$errors->add(
				'missing_zip',
				__( 'The ZipArchive PHP extension is required for export but is not installed. Please contact your hosting provider to enable the zip extension.', 'jekyll-exporter' )
			);
		}

		$temp_dir = $this->get_export_temp_dir();
		if ( ! wp_is_writable( $temp_dir ) ) {
			$errors->add(
				'temp_not_writable',
				/* translators: %s: temporary directory path */
				sprintf( __( 'The temporary directory (%s) is not writable. Please check file permissions.', 'jekyll-exporter' ), esc_html( $temp_dir ) )
			);
		}

		$memory_limit = apply_filters( 'jekyll_export_memory_limit', ini_get( 'memory_limit' ) );
		if ( '' !== $memory_limit && -1 !== (int) $memory_limit ) {
			$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
			$min_memory   = 64 * 1024 * 1024; // 64 MB minimum.
			if ( $memory_bytes < $min_memory ) {
				$errors->add(
					'insufficient_memory',
					/* translators: %s: current PHP memory limit value */
					sprintf( __( 'The PHP memory limit (%s) is below the minimum required for export. Static Site Exporter requires at least 64M to continue. Please increase the limit in php.ini or contact your hosting provider.', 'jekyll-exporter' ), esc_html( $memory_limit ) )
				);
			}
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Main function, bootstraps, converts, and cleans up
	 *
	 * @param string|null $destination Optional path to save the archive to. When
	 *                                 omitted, the archive is streamed as a download.
	 */
	public function export( $destination = null ) {
		// Fail on an unknown format before doing any work.
		$this->get_format();

		$validation = $this->validate_environment();
		if ( is_wp_error( $validation ) ) {
			wp_die(
				wp_kses_post( implode( '<br>', $validation->get_error_messages() ) ),
				esc_html__( 'Static Site Exporter', 'jekyll-exporter' ),
				array( 'back_link' => true )
			);
		}

		// Disable PHP time limit to prevent timeout during large exports.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged -- Large exports need more than the default time limit; silenced because set_time_limit may be disabled on some hosts.
		@set_time_limit( 0 );

		// Attempt to increase memory limit for large exports.
		wp_raise_memory_limit( 'jekyll_export' );

		// Fatal errors (e.g. memory exhaustion) bypass the try-catch block and are
		// handled by core's fatal error handler.  While the export runs, filter
		// the page it renders so it says what failed instead of the generic
		// "critical error" message.
		add_filter( 'wp_php_error_message', array( $this, 'fatal_error_message' ), 10, 2 );
		add_filter( 'wp_php_error_args', array( $this, 'fatal_error_args' ), 10, 2 );

		try {
			$ob_level_before = ob_get_level();

			// Buffer before firing the action so anything a third-party hook
			// echoes is discarded rather than prepended to the binary zip.
			ob_start();
			do_action( 'jekyll_export' );
			$this->init_temp_dir();
			$this->convert_options();
			$this->convert_posts();
			$this->convert_uploads();
			$this->zip();
			ob_end_clean();
			if ( null === $destination ) {
				$this->send();
			} else {
				$this->save( $destination );
			}
			$this->cleanup();

			// Buffer before firing the action so anything a third-party hook
			// echoes is discarded rather than appended to the response.
			ob_start();
			do_action( 'jekyll_export_complete' );
			ob_end_clean();
		} catch ( \Throwable $e ) {
			// Clean up only output buffers that export() started, leaving any
			// pre-existing WordPress/admin buffers intact.
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}

			// Attempt cleanup of any partial temp files.
			if ( ! empty( $this->dir ) || ! empty( $this->zip ) ) {
				$this->cleanup();
			}

			wp_die(
				/* translators: %s: error message from the exception */
				wp_kses_post( sprintf( __( 'Export failed: %s', 'jekyll-exporter' ), esc_html( $e->getMessage() ) ) ),
				esc_html__( 'Static Site Exporter', 'jekyll-exporter' ),
				array( 'back_link' => true )
			);
		} finally {
			remove_filter( 'wp_php_error_message', array( $this, 'fatal_error_message' ), 10 );
			remove_filter( 'wp_php_error_args', array( $this, 'fatal_error_args' ), 10 );
		}
	}

	/**
	 * Replace core's fatal error message while an export is running.
	 *
	 * Hooked to `wp_php_error_message` for the duration of export().  PHP fatal
	 * errors (e.g. memory exhaustion, maximum execution time) cannot be caught
	 * by try-catch, so core's fatal error handler renders the error page; this
	 * makes that page say what failed and how to fix it.
	 *
	 * @param string $message HTML error message to display.
	 * @param array  $error   Error information retrieved from error_get_last().
	 * @return string The filtered message.
	 */
	public function fatal_error_message( $message, $error ) {
		$details = isset( $error['message'] ) ? (string) $error['message'] : '';

		// Detect common causes and add guidance.
		if ( stripos( $details, 'Allowed memory size' ) !== false ) {
			$details .= ' ' . __( 'Try increasing the PHP memory_limit in php.ini or contact your hosting provider.', 'jekyll-exporter' );
		} elseif ( stripos( $details, 'Maximum execution time' ) !== false ) {
			$details .= ' ' . __( 'The export took too long. Try exporting fewer posts using WP-CLI with the --category or --post_type flags.', 'jekyll-exporter' );
		}

		/* translators: %s: error message from the fatal error */
		return '<p>' . sprintf( esc_html__( 'Export failed: %s', 'jekyll-exporter' ), esc_html( $details ) ) . '</p>';
	}

	/**
	 * Set the title of core's fatal error page while an export is running.
	 *
	 * Hooked to `wp_php_error_args` for the duration of export().
	 *
	 * @param array $args  Arguments passed to wp_die().
	 * @param array $error Error information retrieved from error_get_last().
	 * @return array The filtered arguments.
	 */
	public function fatal_error_args( $args, $error ) {
		$args['title']     = __( 'Static Site Exporter', 'jekyll-exporter' );
		$args['back_link'] = true;
		$args['response']  = 500;
		return $args;
	}


	/**
	 * Convert site settings to the format's config file (`_config.yml` for
	 * Jekyll)
	 */
	public function convert_options() {
		global $wp_filesystem;

		$options = array();

		// Build the full list of option keys to look up, including prefixed variants.
		$option_keys = $this->options;
		foreach ( $this->rename_options as $prefix ) {
			foreach ( $this->options as $opt ) {
				$option_keys[] = $prefix . $opt;
			}
		}

		// Query only the needed options instead of loading all options.
		// Use a sentinel object to distinguish "missing" from "stored as false".
		$missing_option = new stdClass();
		foreach ( array_unique( $option_keys ) as $key ) {
			$value = get_option( $key, $missing_option );
			if ( $missing_option !== $value ) {
				$options[ $key ] = $value;
			}
		}

		// Strip site and blog prefixes from key names.
		foreach ( array_keys( $options ) as $key ) {
			foreach ( $this->rename_options as $rename ) {
				$len = strlen( $rename );
				if ( substr( $key, 0, $len ) === $rename ) {
					$this->rename_key( $options, $key, substr( $key, $len ) );
				}
			}
		}

		// Keep only the configured option keys.
		foreach ( array_keys( $options ) as $key ) {
			if ( ! in_array( $key, $this->options, true ) ) {
				unset( $options[ $key ] );
			}
		}

		$format     = $this->get_format();
		$post_types = array_map( 'strval', (array) apply_filters( 'jekyll_export_post_types', array( 'post', 'page' ) ) );

		switch ( $format->config ) {
			case 'jekyll':
				$this->write_jekyll_config( $options, $post_types );
				break;
			case 'hugo':
				$hugo = array(
					'baseURL' => isset( $options['url'] ) ? trailingslashit( (string) $options['url'] ) : '/',
					// Hugo 0.158 renamed `languageCode` to `locale`.
					'locale'  => get_bloginfo( 'language' ),
					'title'   => isset( $options['name'] ) ? $options['name'] : '',
				);
				if ( isset( $options['description'] ) && '' !== $options['description'] ) {
					$hugo['params'] = array( 'description' => $options['description'] );
				}
				$wp_filesystem->put_contents( $this->dir . 'hugo.yaml', Yaml::dump( $hugo ) );
				break;
			case 'astro':
				wp_mkdir_p( $this->dir . 'src/' );
				$wp_filesystem->put_contents( $this->dir . 'src/content.config.ts', $this->astro_content_config( $format, $post_types ) );
				break;
			case 'eleventy':
				$site = array(
					'title'       => isset( $options['name'] ) ? $options['name'] : '',
					'description' => isset( $options['description'] ) ? $options['description'] : '',
					'url'         => isset( $options['url'] ) ? $options['url'] : '',
				);
				wp_mkdir_p( $this->dir . '_data/' );
				$wp_filesystem->put_contents( $this->dir . '_data/site.json', wp_json_encode( $site, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
				$this->write_eleventy_scaffold( $post_types );
				break;
		}
	}

	/**
	 * Write the two things an Eleventy build needs before it can build the
	 * export: a config, and a layout for each `layout:` the posts name.
	 *
	 * Eleventy stops with an error on a layout it can't find, doesn't copy
	 * uploads unless told to, and runs Markdown through Liquid, which breaks
	 * on any post that quotes `{{` or `{%`.
	 *
	 * @param string[] $post_types The exported post types.
	 */
	protected function write_eleventy_scaffold( $post_types ) {
		global $wp_filesystem;

		$config = <<<'JS'
// Generated by Static Site Exporter.
export default function (eleventyConfig) {
	// Serve images and other uploads at the same /wp-content/uploads/ URLs.
	eleventyConfig.addPassthroughCopy("wp-content");
}

export const config = {
	// WordPress content isn't a template, so don't run Liquid over it.
	markdownTemplateEngine: false,
};

JS;
		$wp_filesystem->put_contents( $this->dir . 'eleventy.config.mjs', $config );

		wp_mkdir_p( $this->dir . '_includes/' );
		foreach ( array_unique( array_merge( array( 'post', 'page' ), $post_types ) ) as $post_type ) {
			$wp_filesystem->put_contents(
				$this->dir . '_includes/' . sanitize_file_name( $post_type . '.njk' ),
				'{# Generated by Static Site Exporter: the layout for `layout: ' . $post_type . "`. Replace it with your own. #}\n{{ content | safe }}\n"
			);
		}
	}

	/**
	 * Write Jekyll's `_config.yml`.
	 *
	 * @param array    $options    The site settings, keyed by Jekyll's names.
	 * @param string[] $post_types The exported post types.
	 */
	protected function write_jekyll_config( $options, $post_types ) {
		global $wp_filesystem;

		// Posts of other types are written to `_<type>s/` (see write()), which
		// Jekyll ignores unless it's declared as a collection, so declare them.
		$collections = array();
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				continue;
			}
			$collections[ $post_type . 's' ] = array( 'output' => true );
		}
		if ( ! empty( $collections ) ) {
			$options['collections'] = $collections;
		}

		$output = Yaml::dump( $options );

		$wp_filesystem->put_contents( $this->dir . '_config.yml', $output );
	}

	/**
	 * Astro's `src/content.config.ts`, declaring a content collection for
	 * posts, pages, and each other exported post type.
	 *
	 * The schema only types the keys the exporter always writes and lets
	 * everything else (custom fields, taxonomies) through, so the first build
	 * works without editing it.
	 *
	 * @param Jekyll_Export_Format $format     The Astro format.
	 * @param string[]             $post_types The exported post types.
	 * @return string The file's contents.
	 */
	protected function astro_content_config( $format, $post_types ) {
		$collections = array_merge(
			array(
				'posts' => untrailingslashit( $format->posts_dir ),
				'pages' => untrailingslashit( $format->pages_dir ),
			),
			$format->collection_dirs( $post_types )
		);

		$lines = array();
		foreach ( $collections as $name => $dir ) {
			$lines[] = sprintf(
				"\t%s: defineCollection({ loader: glob({ pattern: '**/*.md', base: %s }), schema }),",
				wp_json_encode( (string) $name, JSON_UNESCAPED_SLASHES ),
				wp_json_encode( './' . $dir, JSON_UNESCAPED_SLASHES )
			);
		}

		return <<<'TS'
// Generated by Static Site Exporter. Declares a collection for each folder
// under src/content/, with a schema that accepts any front matter.
import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const schema = z
	.object({
		title: z.string().optional(),
		date: z.coerce.date().optional(),
		draft: z.boolean().optional(),
		permalink: z.string().optional(),
	})
	.passthrough();

export const collections = {

TS
		. implode( "\n", $lines ) . "\n};\n";
	}


	/**
	 * Write file to temp dir
	 *
	 * @param String  $output the post content.
	 * @param WP_Post $post the Post object.
	 */
	public function write( $output, $post ) {

		global $wp_filesystem;

		$format    = $this->get_format();
		$post_type = (string) get_post_type( $post );

		if ( ! Jekyll_Export_Format::is_published( $post ) ) {
			// Drafts often have no slug and sometimes no title; join whichever exist.
			$name = implode(
				'-',
				array_filter(
					array( $this->filename_slug( get_page_uri( $post->ID ) ), get_the_title( $post->ID ) ),
					function ( $part ) {
						return '' !== $part;
					}
				)
			);
			// Formats without a drafts folder keep drafts next to their
			// published siblings and mark them in the front matter instead.
			$dir      = '' !== $format->drafts_dir ? $format->drafts_dir : $format->dir_for( $post_type );
			$filename = $dir . sanitize_file_name( ( '' === $name ? 'draft-' . $post->ID : $name ) . '.md' );
		} elseif ( 'page' === $post_type ) {
			// Sanitize each path segment independently so the directory hierarchy is
			// preserved while individual segments cannot contain traversal sequences.
			$segments = array_map(
				function ( $segment ) {
					return sanitize_file_name( $this->filename_slug( $segment ) );
				},
				explode( '/', get_page_uri( $post->ID ) )
			);
			$filename = $format->pages_dir . implode( '/', $segments ) . '.md';
		} else {
			$slug     = sanitize_file_name( $this->filename_slug( $post->post_name ) );
			$prefix   = $format->date_prefix ? mysql2date( 'Y-m-d', $post->post_date, false ) . '-' : '';
			$filename = $format->dir_for( $post_type ) . $prefix . ( '' === $slug ? (string) $post->ID : $slug ) . '.md';
		}

		// Two posts can map to the same file (the same slug on the same day, or
		// drafts with the same title). put_contents() would silently keep only
		// the last one, so give later ones the post ID instead. Tracked per
		// export directory, so rewriting the same post still overwrites it.
		$claimed = isset( $this->written_files[ $this->dir ][ $filename ] ) ? $this->written_files[ $this->dir ][ $filename ] : null;
		if ( null !== $claimed && $claimed !== $post->ID ) {
			$filename = substr( $filename, 0, -3 ) . '-' . $post->ID . '.md';
		}
		$this->written_files[ $this->dir ][ $filename ] = $post->ID;

		// A nested page can be written before its parent, so create every missing
		// directory in the path, not just the last one.
		wp_mkdir_p( $this->dir . dirname( $filename ) );
		$wp_filesystem->put_contents( $this->dir . $filename, $output );
	}

	/**
	 * Turn a post slug into the readable form used in a filename.
	 *
	 * WordPress stores non-ASCII slugs percent-encoded (日本 is %e6%97%a5%e6%9c%ac).
	 * sanitize_file_name() strips the % signs, which left filenames of hex
	 * digits, so decode first.
	 *
	 * @param string $slug A post slug or page URI segment.
	 * @return string The decoded slug.
	 */
	public function filename_slug( $slug ) {
		return rawurldecode( (string) $slug );
	}

	/**
	 * Creates a zip archive of the given folder
	 *
	 * @param String $source the source directory to zip.
	 * @param String $destination the path to output the zip.
	 *
	 * @throws \RuntimeException If the source directory does not exist or the zip file cannot be opened.
	 */
	public function zip_folder( $source, $destination ) {

		if ( ! file_exists( $source ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( sprintf( 'file does not exist: %s', $source ) );
		}

		$source = realpath( $source );

		$zip = new ZipArchive();

		// ZipArchive::open() returns true on success or a non-zero integer error
		// code on failure, so a falsy check never catches an error.
		$opened = $zip->open( $destination, ZipArchive::CREATE | ZIPARCHIVE::OVERWRITE );
		if ( true !== $opened ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( sprintf( 'Cannot open zip archive %s (error code %d)', $destination, (int) $opened ) );
		}

		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $files as $file ) {
			$path = (string) $file;

			// realpath() returns false for a file that vanished mid-export (or a
			// broken symlink), which would otherwise produce an empty entry name.
			$real_file = realpath( $path );
			if ( false === $real_file ) {
				continue;
			}

			$local_name = substr( $real_file, strlen( $source ) + 1 );
			if ( '' === $local_name ) {
				continue;
			}

			if ( is_dir( $real_file ) === true ) {
				$zip->addEmptyDir( $local_name );
			} elseif ( is_file( $real_file ) === true ) {
				$zip->addFile( $real_file, $local_name );
			}
		}

		// Nothing is written to disk until close(), so this is where a full disk,
		// an exhausted quota, or an unwritable temp directory actually surfaces.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- close() warns on failure; the error is reported as an exception below.
		$status = @$zip->close();
		if ( ! $status ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- getStatusString() can warn on an archive that failed to close.
			$reason = (string) @$zip->getStatusString();
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( sprintf( 'Cannot write zip archive %s: %s', $destination, $reason ) );
		}

		return $status;
	}

	/**
	 * Zip temp dir
	 *
	 * @throws \RuntimeException If the archive was not written or is empty.
	 */
	public function zip() {
		$this->zip_folder( $this->dir, $this->zip );

		// The archive only exists once close() has flushed it, and a stale stat
		// cache would otherwise report the pre-export state of the path.
		clearstatcache( true, $this->zip );
		if ( ! file_exists( $this->zip ) || filesize( $this->zip ) < 1 ) {
			$message = sprintf(
				'The export archive (%s) is missing or empty. This usually means the server ran out of disk space or the temporary directory quota was exhausted.',
				$this->zip
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( $message );
		}
	}

	/**
	 * Whether the archive stream needs the output buffers torn down first.
	 *
	 * Web requests do; under WP-CLI the caller owns stdout and any buffer
	 * around it, the same carve-out the headers_sent() check in send() makes.
	 * Split out from send() so tests can drive the web-request path.
	 *
	 * @return bool
	 */
	protected function should_discard_output_buffers() {
		return ! $this->is_cli();
	}

	/**
	 * Whether the export is running from the command line (WP-CLI or php-cli).
	 *
	 * @return bool
	 */
	protected function is_cli() {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === PHP_SAPI;
	}

	/**
	 * Discard every output buffer that is still open.
	 *
	 * Called immediately before the archive is streamed.  A buffer left open by
	 * a theme or another plugin is a problem in two ways: a buffer with a
	 * callback (an HTML minifier, a CDN rewriter) runs the binary archive
	 * through that callback and corrupts it, and a buffer opened without a
	 * chunk size never auto-flushes, so it holds the entire archive in memory
	 * until the export dies mid-stream -- which reaches the browser as a
	 * truncated download rather than an error page.
	 *
	 * Buffers are discarded rather than flushed: whatever they hold is admin
	 * markup that has no business being inside a zip file.
	 *
	 * Only called for web requests.  Under WP-CLI the caller owns stdout and
	 * its own buffers, the same reason the headers_sent() check in send() is
	 * skipped there.
	 *
	 * @return void
	 */
	public function discard_output_buffers() {
		$level = ob_get_level();

		while ( $level > 0 ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A non-removable handler emits a notice; the loop exits on the level check below.
			@ob_end_clean();

			$remaining = ob_get_level();

			// Some handlers (notably zlib output compression) refuse to be
			// removed.  Stop rather than spin forever.
			if ( $remaining >= $level ) {
				break;
			}

			$level = $remaining;
		}
	}

	/**
	 * Send headers and zip file to user
	 *
	 * @throws \RuntimeException If output has already been sent or the archive cannot be read.
	 */
	public function send() {
		// Transparent gzip compression would make Content-Length disagree with the
		// bytes actually written, which browsers report as a truncated download.
		// Disabled first, so the buffer it installs is gone before the teardown
		// below counts what is still open.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.IniSet.Risky,Squiz.PHP.DiscouragedFunctions.Discouraged -- Deliberate: transparent compression corrupts the download, and ini_set may be disabled on some hosts.
		@ini_set( 'zlib.output_compression', 'Off' );

		// Open the file before sending any headers so a read failure can still be
		// reported as an error page rather than a zero byte download.  This runs
		// before the buffers are torn down so the failure can still be rendered
		// as an admin error page.
		// Stream the zip in chunks: WP_Filesystem has no streaming read, and
		// get_contents() would load a multi-gigabyte archive into memory.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Failure is handled explicitly below; see above.
		$handle = @fopen( $this->zip, 'rb' );
		if ( false === $handle ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( sprintf( 'The export archive (%s) could not be opened for reading.', $this->zip ) );
		}

		// Nothing below this point may be buffered or rewritten.  Skipped under
		// WP-CLI, where the caller owns stdout and any buffer around it.
		if ( $this->should_discard_output_buffers() ) {
			$this->discard_output_buffers();
		}

		// If something already wrote to the response, the download would arrive
		// corrupt no matter what we do; fail loudly with the culprit instead.
		// Checked after the teardown above, so content that was merely buffered
		// (and has now been discarded) is not mistaken for output already on the
		// wire.
		$sent_file = '';
		$sent_line = 0;
		if ( ! $this->is_cli() && headers_sent( $sent_file, $sent_line ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the stream opened above.

			$message = sprintf(
				'Cannot send the export because output was already sent by %1$s on line %2$d. A theme or another plugin is printing content (often a stray blank line or byte order mark) before the download starts.',
				$sent_file,
				$sent_line
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( $message );
		}

		clearstatcache( true, $this->zip );
		$size = filesize( $this->zip );

		// Send headers.
		@header( 'Content-Type: application/zip' );
		@header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $this->get_format()->slug . '-export.zip' ) );
		if ( false !== $size ) {
			@header( 'Content-Length: ' . $size );
		}

		// Stream the zip in chunks to avoid loading the entire file into
		// memory (which would defeat the point of having a memory_limit
		// pre-flight check for large exports).
		flush();
		while ( ! feof( $handle ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Binary zip content streamed in chunks; escaping would corrupt it.
			echo fread( $handle, 8192 );
			flush();
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the stream opened above.
	}


	/**
	 * Copy the archive to a file instead of streaming it.
	 *
	 * @param string $destination the path to write the archive to.
	 * @throws \RuntimeException If the archive cannot be written.
	 */
	public function save( $destination ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem->copy( $this->zip, $destination, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by export() and rendered through wp_die() which escapes it.
			throw new \RuntimeException( sprintf( 'The export archive could not be written to %s.', $destination ) );
		}
	}

	/**
	 * Clear temp files
	 */
	public function cleanup() {
		global $wp_filesystem;

		$wp_filesystem->delete( $this->dir, true );
		$wp_filesystem->delete( $this->zip );
	}


	/**
	 * Rename an assoc. array's key without changing the order
	 *
	 * @param Array  $array the Array.
	 * @param String $from the original key.
	 * @param String $to the resulting key.
	 * @return void
	 */
	public function rename_key( &$array, $from, $to ) {

		$keys  = array_keys( $array );
		$index = array_search( $from, $keys, true );

		if ( false === $index ) {
			return;
		}

		$keys[ $index ] = $to;
		$array          = array_combine( $keys, $array );
	}

	/**
	 * Convert uploads to static files in the resulting site
	 */
	public function convert_uploads() {
		$upload_dir = wp_upload_dir();
		$source     = $upload_dir['basedir'];

		// Allow sites to skip uploads export for very large installations.
		if ( apply_filters( 'jekyll_export_skip_uploads', false ) ) {
			return;
		}

		$site_url = trailingslashit( set_url_scheme( get_site_url(), 'http' ) );
		$base_url = set_url_scheme( $upload_dir['baseurl'], 'http' );
		$dest     = $this->dir . $this->get_format()->uploads_dir . str_replace( $site_url, '', $base_url );
		$this->copy_recursive( $source, $dest );
	}

	/**
	 * Copy a file, or recursively copy a folder and its contents
	 *
	 * @author      Aidan Lister <aidan@php.net>
	 * @version     1.0.2
	 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
	 * @param       string $source    Source path.
	 * @param       string $dest      Destination path.
	 * @return      bool     Returns TRUE on success, false on failure
	 */
	public function copy_recursive( $source, $dest ) {

		global $wp_filesystem;

		// Check for symlinks and resolve them instead of creating new symlinks.
		// This prevents cleanup from following symlinks and deleting the original files.
		if ( is_link( $source ) ) {
			$resolved_source = realpath( $source );
			if ( false === $resolved_source ) {
				return false;
			}

			// Security: Validate that the resolved path is within ABSPATH or the uploads directory.
			// This prevents symlinks from being used to access files outside the WordPress installation.
			// Cache upload_dir per-blog to avoid repeated filesystem operations while remaining
			// correct across switch_to_blog() boundaries on multisite.
			static $upload_basedir_cache = array();
			$blog_id                     = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
			if ( ! isset( $upload_basedir_cache[ $blog_id ] ) ) {
				$upload_dir = wp_upload_dir();
				// Validate that basedir key exists and is not empty.
				if ( empty( $upload_dir['basedir'] ) ) {
					return false;
				}
				$upload_basedir_cache[ $blog_id ] = $upload_dir['basedir'];
			}
			$upload_basedir = $upload_basedir_cache[ $blog_id ];

			$allowed_bases = array(
				ABSPATH,
				$upload_basedir,
			);

			// Allow tests to add additional allowed paths.
			$allowed_bases = apply_filters( 'jekyll_export_allowed_symlink_bases', $allowed_bases );

			// Normalize all paths for comparison.
			$is_allowed = false;
			foreach ( $allowed_bases as $base ) {
				$base_normalized = realpath( $base );
				if ( false === $base_normalized ) {
					continue;
				}

				// Check for exact path match first.
				if ( rtrim( $resolved_source, '/' ) === rtrim( $base_normalized, '/' ) ) {
					$is_allowed = true;
					break;
				}

				// Check if the resolved path is within the allowed base (prefix match).
				// Ensure we're checking at directory boundaries to prevent /var/www2 matching /var/www.
				$base_with_sep     = trailingslashit( $base_normalized );
				$resolved_with_sep = trailingslashit( $resolved_source );
				if ( 0 === strpos( $resolved_with_sep, $base_with_sep ) ) {
					$is_allowed = true;
					break;
				}
			}

			if ( ! $is_allowed ) {
				// Symlink points outside allowed directories, skip it.
				return false;
			}

			$source = $resolved_source;
		}

		// Simple copy for a file.
		if ( is_file( $source ) ) {
			return $wp_filesystem->copy( $source, $dest );
		}

		// Avoid copying the output of this plugin and causing infinite recursion.
		if ( strpos( $source, '/wp-jekyll-' ) !== false ) {
			return true;
		}

		// Allow filtering specific directories to skip (e.g., cache directories).
		$excluded_dirs = apply_filters( 'jekyll_export_excluded_upload_dirs', array() );
		foreach ( $excluded_dirs as $excluded ) {
			if ( false !== strpos( $source, $excluded ) ) {
				return true;
			}
		}

		// Make destination directory.
		if ( ! is_dir( $dest ) ) {
			wp_mkdir_p( $dest );
		}

		// Use scandir instead of dir() for better performance.
		$entries = @scandir( $source );
		if ( false === $entries ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			// Skip pointers.
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			// Deep copy directories.
			$this->copy_recursive( "$source/$entry", "$dest/$entry" );
		}

		return true;
	}
}

global $jekyll_export;
$jekyll_export = new Jekyll_Export();
