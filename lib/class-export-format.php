<?php
/**
 * Output format profiles: where each static site generator expects content,
 * and how its front matter differs from Jekyll's.
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2026 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-static-site-exporter/
 */

/**
 * One static site generator's layout for an export.
 *
 * The Markdown and YAML front matter the exporter writes already work in
 * every supported generator. What differs is the folders, how drafts are
 * marked, a few front matter keys, where uploads land, and the site config
 * file, so each of those is a property here rather than a branch in the
 * exporter.
 */
class Jekyll_Export_Format {

	/**
	 * The format used when nothing asks for another one.
	 */
	const DEFAULT_SLUG = 'jekyll';

	/**
	 * Format slug, e.g. `hugo`.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Human-readable name shown in the admin, e.g. `Hugo`.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Folder posts are written to, with a trailing slash.
	 *
	 * @var string
	 */
	public $posts_dir;

	/**
	 * Folder pages are written to (nested by page URI), with a trailing
	 * slash, or '' for the site root.
	 *
	 * @var string
	 */
	public $pages_dir;

	/**
	 * Folder for other post types, as a sprintf() pattern given the post type.
	 *
	 * @var string
	 */
	public $post_type_dir;

	/**
	 * Whether post filenames start with the post's date (YYYY-MM-DD-slug.md).
	 *
	 * @var bool
	 */
	public $date_prefix;

	/**
	 * Folder unpublished posts are written to, or '' to keep them next to
	 * their published siblings and mark them with $draft_front_matter.
	 *
	 * @var string
	 */
	public $drafts_dir;

	/**
	 * Front matter added to unpublished posts.
	 *
	 * @var array<string, mixed>
	 */
	public $draft_front_matter;

	/**
	 * Front matter key that holds the post's original WordPress URL, or ''
	 * to leave it out.
	 *
	 * @var string
	 */
	public $permalink_key;

	/**
	 * Whether a permalink with a query string (WordPress's plain `/?p=123`
	 * links) is kept. Generators that turn the URL into an output path can't
	 * write one, so the others fall back to their own URLs.
	 *
	 * @var bool
	 */
	public $query_permalinks;

	/**
	 * Whether the `layout: <post type>` key is kept.
	 *
	 * @var bool
	 */
	public $layout;

	/**
	 * Folder the `wp-content/uploads/` tree is copied into, with a trailing
	 * slash, or '' for the site root.
	 *
	 * @var string
	 */
	public $uploads_dir;

	/**
	 * Which site config file to write: `jekyll`, `hugo`, `astro`, `eleventy`,
	 * or '' for none.
	 *
	 * @var string
	 */
	public $config;

	/**
	 * Build a format from a profile array, as returned by profiles().
	 *
	 * Keys the profile leaves out take Jekyll's values.
	 *
	 * @param string $slug    The format's slug.
	 * @param array  $profile The format's settings.
	 */
	public function __construct( $slug, $profile ) {
		$profiles = self::builtin_profiles();
		$profile  = array_merge( $profiles[ self::DEFAULT_SLUG ], array( 'label' => $slug ), (array) $profile );

		$this->slug               = (string) $slug;
		$this->label              = (string) $profile['label'];
		$this->posts_dir          = (string) $profile['posts_dir'];
		$this->pages_dir          = (string) $profile['pages_dir'];
		$this->post_type_dir      = (string) $profile['post_type_dir'];
		$this->date_prefix        = (bool) $profile['date_prefix'];
		$this->drafts_dir         = (string) $profile['drafts_dir'];
		$this->draft_front_matter = (array) $profile['draft_front_matter'];
		$this->permalink_key      = (string) $profile['permalink_key'];
		$this->query_permalinks   = (bool) $profile['query_permalinks'];
		$this->layout             = (bool) $profile['layout'];
		$this->uploads_dir        = (string) $profile['uploads_dir'];
		$this->config             = (string) $profile['config'];
	}

	/**
	 * The built-in profiles, before the `jekyll_export_formats` filter.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function builtin_profiles() {
		return array(
			'jekyll'   => array(
				'label'              => 'Jekyll',
				'posts_dir'          => '_posts/',
				'pages_dir'          => '',
				'post_type_dir'      => '_%ss/',
				'date_prefix'        => true,
				'drafts_dir'         => '_drafts/',
				'draft_front_matter' => array(),
				'permalink_key'      => 'permalink',
				'query_permalinks'   => true,
				'layout'             => true,
				'uploads_dir'        => '',
				'config'             => 'jekyll',
			),
			'hugo'     => array(
				'label'              => 'Hugo',
				'posts_dir'          => 'content/posts/',
				'pages_dir'          => 'content/',
				'post_type_dir'      => 'content/%s/',
				'date_prefix'        => false,
				'drafts_dir'         => '',
				'draft_front_matter' => array( 'draft' => true ),
				// Hugo publishes a page at the exact path in `url`.
				'permalink_key'      => 'url',
				'query_permalinks'   => false,
				// Hugo picks templates by section, and a `layout` it can't
				// find only adds a warning.
				'layout'             => false,
				'uploads_dir'        => 'static/',
				'config'             => 'hugo',
			),
			'astro'    => array(
				'label'              => 'Astro',
				'posts_dir'          => 'src/content/posts/',
				'pages_dir'          => 'src/content/pages/',
				'post_type_dir'      => 'src/content/%s/',
				'date_prefix'        => false,
				'drafts_dir'         => '',
				'draft_front_matter' => array( 'draft' => true ),
				// Astro routes by file, so `permalink` is data a route can read.
				'permalink_key'      => 'permalink',
				'query_permalinks'   => false,
				'layout'             => false,
				'uploads_dir'        => 'public/',
				'config'             => 'astro',
			),
			'eleventy' => array(
				'label'              => 'Eleventy',
				'posts_dir'          => 'posts/',
				'pages_dir'          => '',
				'post_type_dir'      => '%s/',
				'date_prefix'        => false,
				'drafts_dir'         => '',
				// `permalink: false` stops Eleventy from writing the page.
				'draft_front_matter' => array(
					'draft'     => true,
					'permalink' => false,
				),
				'permalink_key'      => 'permalink',
				'query_permalinks'   => false,
				'layout'             => true,
				'uploads_dir'        => '',
				'config'             => 'eleventy',
			),
		);
	}

	/**
	 * Every registered profile, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function profiles() {
		/**
		 * Filters the available output formats.
		 *
		 * Add a profile to support another generator, or change a built-in
		 * one. Keys a profile leaves out take Jekyll's values.
		 *
		 * @param array<string, array<string, mixed>> $profiles Profiles keyed by slug.
		 */
		$profiles = apply_filters( 'jekyll_export_formats', self::builtin_profiles() );

		return array_filter( (array) $profiles, 'is_array' );
	}

	/**
	 * The registered format slugs.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_map( 'strval', array_keys( self::profiles() ) );
	}

	/**
	 * Look up a format by slug.
	 *
	 * @param string $slug The format's slug.
	 * @return Jekyll_Export_Format|null The format, or null if no format has that slug.
	 */
	public static function get( $slug ) {
		$profiles = self::profiles();
		$slug     = (string) $slug;

		if ( ! isset( $profiles[ $slug ] ) ) {
			return null;
		}

		return new self( $slug, $profiles[ $slug ] );
	}

	/**
	 * Folder a published post of the given type is written to.
	 *
	 * @param string $post_type The post type.
	 * @return string The folder, with a trailing slash ('' for the site root).
	 */
	public function dir_for( $post_type ) {
		if ( 'page' === $post_type ) {
			return $this->pages_dir;
		}

		if ( 'post' === $post_type ) {
			return $this->posts_dir;
		}

		return sprintf( $this->post_type_dir, $post_type );
	}

	/**
	 * Folders for every exported post type that aren't posts or pages, keyed
	 * by post type, for formats whose config has to declare them.
	 *
	 * @param string[] $post_types The exported post types.
	 * @return array<string, string> Folder (without trailing slash) keyed by post type.
	 */
	public function collection_dirs( $post_types ) {
		$dirs = array();
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				continue;
			}
			$dirs[ $post_type ] = untrailingslashit( $this->dir_for( $post_type ) );
		}
		return $dirs;
	}

	/**
	 * Adjust Jekyll's front matter for this format.
	 *
	 * Runs before the `jekyll_export_meta` filter, so callbacks see the final
	 * keys. Jekyll's profile leaves the front matter unchanged.
	 *
	 * @param array   $meta The front matter convert_meta() built.
	 * @param WP_Post $post The post being exported.
	 * @return array The front matter for this format.
	 */
	public function map_front_matter( $meta, $post ) {
		if ( isset( $meta['permalink'] ) && is_string( $meta['permalink'] ) && ! $this->query_permalinks && false !== strpos( $meta['permalink'], '?' ) ) {
			unset( $meta['permalink'] );
		}

		if ( array_key_exists( 'permalink', $meta ) && 'permalink' !== $this->permalink_key ) {
			if ( '' === $this->permalink_key ) {
				unset( $meta['permalink'] );
			} else {
				// Rename in place so the key order (and the YAML) stays stable.
				$keys = array_keys( $meta );
				$keys[ array_search( 'permalink', $keys, true ) ] = $this->permalink_key;
				$meta = array_combine( $keys, $meta );
			}
		}

		// Only the `layout` convert_meta() sets; a `layout` custom field is kept.
		if ( ! $this->layout && isset( $meta['layout'] ) && get_post_type( $post ) === $meta['layout'] ) {
			unset( $meta['layout'] );
		}

		if ( ! empty( $this->draft_front_matter ) && ! self::is_published( $post ) ) {
			$meta = array_merge( $meta, $this->draft_front_matter );
		}

		return $meta;
	}

	/**
	 * Whether a post is published (or scheduled), as opposed to a draft,
	 * pending, or private post.
	 *
	 * @param WP_Post|int $post The post.
	 * @return bool
	 */
	public static function is_published( $post ) {
		return in_array( get_post_status( $post ), array( 'publish', 'future' ), true );
	}
}
