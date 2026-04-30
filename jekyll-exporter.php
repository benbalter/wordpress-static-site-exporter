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
 * Plugin URI:  https://github.com/benbalter/wordpress-to-jekyll-exporter/
 * Description: One-click plugin that converts all posts, pages, taxonomies, metadata, and settings to Markdown and YAML for Jekyll, Hugo, or other static site generators.
 * Version:     4.0.1
 * Author:      Ben Balter
 * Author URI:  https://ben.balter.com
 * Text Domain: jekyll-exporter
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

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	wp_die( 'Jekyll Export requires PHP 8.2 or later' );
}

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
	 * Path to the temporary zip file
	 *
	 * @var string
	 */
	public $zip;

	/**
	 * Whether an export is currently in progress (used by the shutdown handler).
	 *
	 * @var bool
	 */
	private $exporting = false;

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

		if ( ! isset( $_GET['type'] ) || 'jekyll' !== $_GET['type'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jekyll_export' ) ) {
			return;
		}

		$this->export();
		exit();
	}


	/**
	 * Add menu option to tools list
	 */
	public function register_menu() {
		add_management_page( __( 'Export to Jekyll', 'jekyll-exporter' ), __( 'Export to Jekyll', 'jekyll-exporter' ), 'manage_options', 'export.php?type=jekyll&_wpnonce=' . wp_create_nonce( 'jekyll_export' ) );
	}

	/**
	 * Display an admin notice on the Tools → Export page when the server
	 * environment does not meet the requirements for a Jekyll export.
	 */
	public function display_environment_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'export' !== $screen->id ) {
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
		echo '<strong>' . esc_html__( 'Jekyll Export:', 'jekyll-exporter' ) . '</strong> ';
		echo wp_kses_post( implode( '<br>', $validation->get_error_messages() ) );
		echo '</p></div>';
	}


	/**
	 * Get an array of all post and page IDs
	 * Note: We don't use core's get_posts as it doesn't scale as well on large sites
	 */
	public function get_posts() {
		global $wpdb;

		$posts = wp_cache_get( 'jekyll_export_posts' );
		if ( $posts ) {
			return $posts;
		}

		$post_types = apply_filters( 'jekyll_export_post_types', array( 'post', 'page', 'revision' ) );

		// Allow filtering by taxonomy terms (e.g., categories, tags).
		$taxonomy_filters = apply_filters( 'jekyll_export_taxonomy_filters', array() );

		// Use a single query with IN clause for better performance.
		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$query        = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders)";

		// Add taxonomy filtering if specified.
		if ( ! empty( $taxonomy_filters ) ) {
			$tax_conditions = array();
			$tax_params     = $post_types;

			foreach ( $taxonomy_filters as $taxonomy => $terms ) {
				if ( empty( $terms ) ) {
					continue;
				}

				$terms             = (array) $terms;
				$term_placeholders = implode( ', ', array_fill( 0, count( $terms ), '%s' ) );

				// Use AND logic between taxonomies, OR logic within taxonomy.
				$tax_conditions[] = "ID IN (
					SELECT object_id FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
					WHERE tt.taxonomy = %s AND t.slug IN ($term_placeholders)
				)";

				$tax_params[] = $taxonomy;
				$tax_params   = array_merge( $tax_params, $terms );
			}

			if ( ! empty( $tax_conditions ) ) {
				$query .= ' AND (' . implode( ' AND ', $tax_conditions ) . ')';
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query uses placeholders and is passed to $wpdb->prepare().
			$posts = $wpdb->get_col( $wpdb->prepare( $query, ...$tax_params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query uses placeholders generated by array_fill and is passed to $wpdb->prepare().
			$posts = $wpdb->get_col( $wpdb->prepare( $query, ...$post_types ) );
		}

		$posts = array_map( 'intval', $posts );

		wp_cache_set( 'jekyll_export_posts', $posts );
		return $posts;
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
			$output['permalink'] = str_replace( home_url(), '', get_permalink( $post ) );
		}

		// Convert traditional post_meta values, hide hidden values.
		foreach ( get_post_custom( $post->ID ) as $key => $value ) {

			if ( substr( $key, 0, 1 ) === '_' ) {
				continue;
			}

			$output[ $key ] = $value;
		}

		$post_thumbnail_id = get_post_thumbnail_id( $post );

		if ( $post_thumbnail_id ) {
			$post_thumbnail_src = wp_get_attachment_image_src( $post_thumbnail_id, 'post-thumbnail' );

			if ( $post_thumbnail_src ) {
				$output['image'] = str_replace( home_url(), '', $post_thumbnail_src[0] );
			}
		}

		$output = apply_filters( 'jekyll_export_meta', $output );
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

		$output = apply_filters( 'jekyll_export_terms', $output );
		return $output;
	}

	/**
	 * Localize URLs in content to use relative paths instead of absolute URLs.
	 *
	 * @param String $content the content to localize.
	 * @return String the content with localized URLs
	 */
	public function localize_urls( $content ) {
		// Get the site URL with both http and https versions.
		$site_url_http  = set_url_scheme( get_site_url(), 'http' );
		$site_url_https = set_url_scheme( get_site_url(), 'https' );

		// Replace absolute URLs with relative paths for both http and https.
		// This handles URLs like: http://example.org/wp-content/uploads/image.jpg
		// Result: /wp-content/uploads/image.jpg
		// Process the longer URL first to avoid partial replacements.
		if ( strlen( $site_url_https ) >= strlen( $site_url_http ) ) {
			$content = str_replace( $site_url_https, '', $content );
			$content = str_replace( $site_url_http, '', $content );
		} else {
			$content = str_replace( $site_url_http, '', $content );
			$content = str_replace( $site_url_https, '', $content );
		}

		return apply_filters( 'jekyll_export_localized_urls', $content );
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
				$content = apply_filters( 'edit_post_content', $post->post_content, $post->ID );
				// Localize URLs in Jetpack markdown content.
				$content = $this->localize_urls( $content );

				return $content;
			}
		}

		$content = get_the_content( null, false, $post );
		// Localize URLs before converting to Markdown.
		$content = $this->localize_urls( $content );

		// Reuse converter instance to avoid recreating it for each post.
		static $converter = null;
		if ( null === $converter ) {
			$converter_options = apply_filters( 'jekyll_export_markdown_converter_options', array( 'header_style' => 'atx' ) );
			$converter         = new HtmlConverter( $converter_options );
			$converter->getEnvironment()->addConverter( new ColspanTableConverter() );
		}

		$markdown = $converter->convert( $content );

		if ( strpos( $markdown, '[]: ' ) !== false ) {
			// faulty links; return plain HTML.
			$content = apply_filters( 'jekyll_export_html', $content );
			$content = apply_filters( 'jekyll_export_content', $content );
			return $content;
		}

		$markdown = apply_filters( 'jekyll_export_markdown', $markdown );
		$markdown = apply_filters( 'jekyll_export_content', $markdown );
		return $markdown;
	}

	/**
	 * Loop through and convert all posts to MD files with YAML headers
	 */
	public function convert_posts() {
		global $post;

		foreach ( $this->get_posts() as $post_id ) {
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
		$this->dir = trailingslashit( $temp_dir . 'wp-jekyll-' . wp_generate_password( 12, false ) );
		$this->zip = $temp_dir . 'wp-jekyll.zip';

		$wp_filesystem->mkdir( $this->dir );
		$wp_filesystem->mkdir( $this->dir . '_posts/' );
		$wp_filesystem->mkdir( $this->dir . '_drafts/' );
		$wp_filesystem->mkdir( $this->dir . 'wp-content/' );
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking temp dir writability before WP_Filesystem is initialized.
		if ( ! is_writable( $temp_dir ) ) {
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
	 */
	public function export() {
		$validation = $this->validate_environment();
		if ( is_wp_error( $validation ) ) {
			wp_die(
				wp_kses_post( implode( '<br>', $validation->get_error_messages() ) ),
				esc_html__( 'Jekyll Export Error', 'jekyll-exporter' ),
				array( 'back_link' => true )
			);
		}

		// Disable PHP time limit to prevent timeout during large exports.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Silenced because set_time_limit may be disabled on some hosts.
		@set_time_limit( 0 );

		// Attempt to increase memory limit for large exports.
		wp_raise_memory_limit( 'jekyll_export' );

		// Register a shutdown handler to catch fatal errors (e.g. memory
		// exhaustion) that bypass the try-catch block, turning a generic 500
		// Internal Server Error into a descriptive error page.
		$this->exporting = true;
		register_shutdown_function( array( $this, 'shutdown_handler' ) );

		try {
			$ob_level_before = ob_get_level();
			do_action( 'jekyll_export' );
			ob_start();
			$this->init_temp_dir();
			$this->convert_options();
			$this->convert_posts();
			$this->convert_uploads();
			$this->zip();
			ob_end_clean();
			$this->send();
			$this->cleanup();
			$this->exporting = false;
		} catch ( \Throwable $e ) {
			$this->exporting = false;

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
				wp_kses_post( sprintf( __( 'Jekyll Export failed: %s', 'jekyll-exporter' ), esc_html( $e->getMessage() ) ) ),
				esc_html__( 'Jekyll Export Error', 'jekyll-exporter' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Shutdown handler to catch fatal errors during export.
	 *
	 * PHP fatal errors (e.g. memory exhaustion, maximum execution time) cannot
	 * be caught by try-catch.  This handler inspects the last error and, when
	 * a fatal error occurred while an export was in progress, outputs a
	 * descriptive error page instead of the generic "Internal Server Error".
	 */
	public function shutdown_handler() {
		if ( ! $this->exporting ) {
			return;
		}

		$error = error_get_last();
		if ( null === $error ) {
			return;
		}

		// Only handle fatal error types.
		$fatal_types = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
		if ( ! ( $error['type'] & $fatal_types ) ) {
			return;
		}

		// Clean any partial output.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Build a user-friendly message.
		$message = $error['message'];

		// Detect common causes and add guidance.
		if ( stripos( $message, 'Allowed memory size' ) !== false ) {
			$message .= ' ' . __( 'Try increasing the PHP memory_limit in php.ini or contact your hosting provider.', 'jekyll-exporter' );
		} elseif ( stripos( $message, 'Maximum execution time' ) !== false ) {
			$message .= ' ' . __( 'The export took too long. Try exporting fewer posts using WP-CLI with the --category or --post_type flags.', 'jekyll-exporter' );
		}

		wp_die(
			/* translators: %s: error message from the fatal error */
			wp_kses_post( sprintf( __( 'Jekyll Export failed: %s', 'jekyll-exporter' ), esc_html( $message ) ) ),
			esc_html__( 'Jekyll Export Error', 'jekyll-exporter' ),
			array(
				'back_link' => true,
				'response'  => 500,
			)
		);
	}


	/**
	 * Convert options table to _config.yml file
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
				$options[ $key ] = maybe_unserialize( $value );
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

		$output = Yaml::dump( $options );

		$wp_filesystem->put_contents( $this->dir . '_config.yml', $output );
	}


	/**
	 * Write file to temp dir
	 *
	 * @param String  $output the post content.
	 * @param WP_Post $post the Post object.
	 */
	public function write( $output, $post ) {

		global $wp_filesystem;

		if ( ! in_array( get_post_status( $post ), array( 'publish', 'future' ), true ) ) {
			$filename = '_drafts/' . sanitize_file_name( get_page_uri( $post->ID ) . '-' . ( get_the_title( $post->ID ) ) . '.md' );
		} elseif ( get_post_type( $post ) === 'page' ) {
			// Sanitize each path segment independently so the directory hierarchy is
			// preserved while individual segments cannot contain traversal sequences.
			$segments = array_map( 'sanitize_file_name', explode( '/', get_page_uri( $post->ID ) ) );
			$filename = implode( '/', $segments ) . '.md';
		} else {
			$filename = '_' . get_post_type( $post ) . 's/' . gmdate( 'Y-m-d', strtotime( $post->post_date ) ) . '-' . sanitize_file_name( $post->post_name ) . '.md';
		}

		$wp_filesystem->mkdir( $this->dir . dirname( $filename ) );
		$wp_filesystem->put_contents( $this->dir . $filename, $output );
	}

	/**
	 * Creates a zip archive of the given folder
	 *
	 * @param String $source the source directory to zip.
	 * @param String $destination the path to output the zip.
	 */
	public function zip_folder( $source, $destination ) {

		if ( ! file_exists( $source ) ) {
			wp_die( 'file does not exist: ' . esc_html( $source ) );
		}

		$source = realpath( $source );

		$zip = new ZipArchive();
		if ( ! $zip->open( $destination, ZipArchive::CREATE | ZIPARCHIVE::OVERWRITE ) ) {
			wp_die( 'Cannot open zip archive: ' . esc_html( $destination ) );
		}

		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $files as $file ) {
			// Ignore "." and ".." folders.
			if ( in_array( substr( $file, strrpos( $file, DIRECTORY_SEPARATOR ) + 1 ), array( '.', '..' ), true ) ) {
				continue;
			}

			if ( is_dir( $file ) === true ) {
				$zip->addEmptyDir( substr( realpath( $file ), strlen( $source ) + 1 ) );
			} elseif ( is_file( $file ) === true ) {
				$zip->addFile( $file, substr( realpath( $file ), strlen( $source ) + 1 ) );
			}
		}

		return $zip->close();
	}

	/**
	 * Zip temp dir
	 */
	public function zip() {
		$this->zip_folder( $this->dir, $this->zip );
	}

	/**
	 * Send headers and zip file to user
	 */
	public function send() {
		// Send headers.
		@header( 'Content-Type: application/zip' );
		@header( 'Content-Disposition: attachment; filename=jekyll-export.zip' );
		@header( 'Content-Length: ' . filesize( $this->zip ) );

		// Read file.
		flush();
		readfile( $this->zip );
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
		$dest     = $this->dir . str_replace( $site_url, '', $base_url );
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
			$wp_filesystem->mkdir( $dest );
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
