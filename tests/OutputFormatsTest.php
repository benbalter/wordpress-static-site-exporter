<?php
/**
 * Tests for the Jekyll, Hugo, Astro, and Eleventy output formats
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2026 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-static-site-exporter/
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Test suite for output formats
 */
class OutputFormatsTest extends WP_UnitTestCase {

	/**
	 * IDs of the posts each test exports, keyed by role.
	 *
	 * @var array<string, int>
	 */
	private $ids = array();

	/**
	 * Register a custom post type, seed one of everything the exporter treats
	 * differently, and switch to pretty permalinks.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		register_post_type( 'recipe', array( 'public' => true ) );

		add_filter(
			'jekyll_export_post_types',
			function () {
				return array( 'post', 'page', 'recipe' );
			}
		);

		$author = self::factory()->user->create( array( 'display_name' => 'Tester' ) );

		$this->ids['post'] = self::factory()->post->create(
			array(
				'post_name'    => 'test-post',
				'post_title'   => 'Test Post',
				'post_content' => 'This is a test <strong>post</strong>.',
				'post_excerpt' => 'A short summary.',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_date'    => '2014-01-01 00:00:00',
				'tags_input'   => array( 'tag1' ),
			)
		);
		add_post_meta( $this->ids['post'], 'subtitle', 'A custom field' );

		$this->ids['draft'] = self::factory()->post->create(
			array(
				'post_name'    => 'test-draft',
				'post_title'   => 'Test Draft',
				'post_content' => 'Not done yet.',
				'post_excerpt' => '',
				'post_status'  => 'draft',
				'post_author'  => $author,
				'post_date'    => '2014-02-01 00:00:00',
			)
		);

		$this->ids['page'] = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'about',
				'post_title'   => 'About',
				'post_content' => 'About this site.',
				'post_excerpt' => '',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_date'    => '2014-03-01 00:00:00',
			)
		);

		$this->ids['child'] = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'team',
				'post_title'   => 'Team',
				'post_content' => 'Who we are.',
				'post_excerpt' => '',
				'post_status'  => 'publish',
				'post_parent'  => $this->ids['page'],
				'post_author'  => $author,
				'post_date'    => '2014-03-02 00:00:00',
			)
		);

		$this->ids['recipe'] = self::factory()->post->create(
			array(
				'post_type'    => 'recipe',
				'post_name'    => 'pancakes',
				'post_title'   => 'Pancakes',
				'post_content' => 'Mix and fry.',
				'post_excerpt' => '',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_date'    => '2014-04-01 00:00:00',
			)
		);
	}

	/**
	 * Remove the export directory and the custom post type.
	 */
	public function tear_down() {
		global $jekyll_export;

		if ( ! empty( $jekyll_export->dir ) ) {
			$jekyll_export->cleanup();
		}

		$upload_dir = wp_upload_dir();
		foreach ( (array) glob( $upload_dir['basedir'] . '/*' ) as $path ) {
			$GLOBALS['wp_filesystem']->delete( $path, true );
		}

		unregister_post_type( 'recipe' );
		parent::tear_down();
	}

	/**
	 * Run every export step except zipping, in the given format.
	 *
	 * @param string|null $format The format slug, or null for the default.
	 * @return string The export directory.
	 */
	private function export_to_dir( $format = null ) {
		global $jekyll_export;

		if ( null !== $format ) {
			$this->assertTrue( $jekyll_export->set_format( $format ) );
		}

		// Start from an uploads folder with only this test's file in it.
		$upload_dir = wp_upload_dir();
		foreach ( (array) glob( $upload_dir['basedir'] . '/*' ) as $path ) {
			$GLOBALS['wp_filesystem']->delete( $path, true );
		}
		file_put_contents( $upload_dir['basedir'] . '/photo.jpg', 'jpg' );

		$jekyll_export->init_temp_dir();
		$jekyll_export->convert_options();
		$jekyll_export->convert_posts();
		$jekyll_export->convert_uploads();

		return $jekyll_export->dir;
	}

	/**
	 * Every file and folder under a directory, relative to it, sorted.
	 *
	 * @param string $dir The directory.
	 * @return string[]
	 */
	private function tree( $dir ) {
		$paths = array();
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $files as $file ) {
			$path = substr( (string) $file, strlen( $dir ) );
			// wp_upload_dir() creates this month's folder as a side effect.
			if ( preg_match( '#wp-content/uploads/\d{4}(/|$)#', $path ) ) {
				continue;
			}
			$paths[] = $file->isDir() ? $path . '/' : $path;
		}
		sort( $paths );
		return $paths;
	}

	/**
	 * A file's front matter, parsed.
	 *
	 * @param string $path The Markdown file.
	 * @return array
	 */
	private function front_matter( $path ) {
		$this->assertFileExists( $path );
		$parts = explode( "---\n", file_get_contents( $path ), 3 );
		return Yaml::parse( $parts[1] );
	}

	/**
	 * Fill in the values that change from run to run (post IDs and GUIDs) in
	 * an expected file.
	 *
	 * @param string $contents The expected contents, with placeholders.
	 * @return string
	 */
	public function fill_placeholders( $contents ) {
		$replace = array();
		foreach ( $this->ids as $role => $id ) {
			$replace[ '{' . $role . '_id}' ]   = (string) $id;
			$replace[ '{' . $role . '_guid}' ] = get_post( $id )->guid;
		}
		return strtr( $contents, $replace );
	}

	/**
	 * The default export is still Jekyll's, byte for byte: the same files, in
	 * the same folders, with the same front matter in the same order.
	 */
	function test_default_export_matches_jekyll_layout_exactly() {
		$dir = $this->export_to_dir();

		$this->assertSame(
			array(
				'_config.yml',
				'_drafts/',
				'_drafts/test-draft-Test-Draft.md',
				'_posts/',
				'_posts/2014-01-01-test-post.md',
				'_recipes/',
				'_recipes/2014-04-01-pancakes.md',
				'about.md',
				'about/',
				'about/team.md',
				'wp-content/',
				'wp-content/uploads/',
				'wp-content/uploads/photo.jpg',
			),
			$this->tree( $dir )
		);

		$expected = array(
			'_config.yml'                      => "url: 'http://example.org'\nname: 'Test Blog'\ndescription: ''\ncollections:\n    recipes: { output: true }\n",
			'_posts/2014-01-01-test-post.md'   => "---\nid: {post_id}\ntitle: 'Test Post'\ndate: '2014-01-01T00:00:00+00:00'\nauthor: Tester\nexcerpt: 'A short summary.'\nlayout: post\nguid: '{post_guid}'\npermalink: /2014/01/01/test-post/\nsubtitle:\n    - 'A custom field'\ncategories:\n    - Uncategorized\ntags:\n    - tag1\nformat: false\n---\n\nThis is a test **post**.",
			'_drafts/test-draft-Test-Draft.md' => "---\nid: {draft_id}\ntitle: 'Test Draft'\ndate: '2014-02-01T00:00:00+00:00'\nauthor: Tester\nexcerpt: ''\nlayout: post\nguid: '{draft_guid}'\npermalink: '/?p={draft_id}'\ncategories:\n    - Uncategorized\nformat: false\n---\n\nNot done yet.",
			'about.md'                         => "---\nid: {page_id}\ntitle: About\ndate: '2014-03-01T00:00:00+00:00'\nauthor: Tester\nexcerpt: ''\nlayout: page\nguid: '{page_guid}'\n---\n\nAbout this site.",
			'about/team.md'                    => "---\nid: {child_id}\ntitle: Team\ndate: '2014-03-02T00:00:00+00:00'\nauthor: Tester\nexcerpt: ''\nlayout: page\nguid: '{child_guid}'\n---\n\nWho we are.",
			'_recipes/2014-04-01-pancakes.md'  => "---\nid: {recipe_id}\ntitle: Pancakes\ndate: '2014-04-01T00:00:00+00:00'\nauthor: Tester\nexcerpt: ''\nlayout: recipe\nguid: '{recipe_guid}'\npermalink: /recipe/pancakes/\n---\n\nMix and fry.",
		);

		$actual = array();
		foreach ( array_keys( $expected ) as $path ) {
			$actual[ $path ] = file_get_contents( $dir . $path );
		}
		$this->assertSame( array_map( array( $this, 'fill_placeholders' ), $expected ), $actual );
	}

	/**
	 * Where each format writes each kind of post, and its config file.
	 *
	 * @return array<string, array>
	 */
	public function format_provider() {
		return array(
			'jekyll'   => array(
				'jekyll',
				array(
					'post'   => '_posts/2014-01-01-test-post.md',
					'draft'  => '_drafts/test-draft-Test-Draft.md',
					'page'   => 'about.md',
					'child'  => 'about/team.md',
					'recipe' => '_recipes/2014-04-01-pancakes.md',
				),
				'_config.yml',
				'wp-content/uploads/photo.jpg',
			),
			'hugo'     => array(
				'hugo',
				array(
					'post'   => 'content/posts/test-post.md',
					'draft'  => 'content/posts/test-draft-Test-Draft.md',
					'page'   => 'content/about.md',
					'child'  => 'content/about/team.md',
					'recipe' => 'content/recipe/pancakes.md',
				),
				'hugo.yaml',
				'static/wp-content/uploads/photo.jpg',
			),
			'astro'    => array(
				'astro',
				array(
					'post'   => 'src/content/posts/test-post.md',
					'draft'  => 'src/content/posts/test-draft-Test-Draft.md',
					'page'   => 'src/content/pages/about.md',
					'child'  => 'src/content/pages/about/team.md',
					'recipe' => 'src/content/recipe/pancakes.md',
				),
				'src/content.config.ts',
				'public/wp-content/uploads/photo.jpg',
			),
			'eleventy' => array(
				'eleventy',
				array(
					'post'   => 'posts/test-post.md',
					'draft'  => 'posts/test-draft-Test-Draft.md',
					'page'   => 'about.md',
					'child'  => 'about/team.md',
					'recipe' => 'recipe/pancakes.md',
				),
				'_data/site.json',
				'wp-content/uploads/photo.jpg',
			),
		);
	}

	/**
	 * Each format writes posts, drafts, pages, and custom post types where
	 * its generator looks for them, with the front matter it reads.
	 *
	 * @dataProvider format_provider
	 *
	 * @param string $format  The format slug.
	 * @param array  $paths   Expected path of each post, keyed by role.
	 * @param string $config  Expected config file.
	 * @param string $uploads Expected path of the uploaded file.
	 */
	function test_format_layout( $format, $paths, $config, $uploads ) {
		$dir = $this->export_to_dir( $format );

		foreach ( $paths as $path ) {
			$this->assertFileExists( $dir . $path );
		}
		$this->assertFileExists( $dir . $config );
		$this->assertFileExists( $dir . $uploads );

		$post  = $this->front_matter( $dir . $paths['post'] );
		$draft = $this->front_matter( $dir . $paths['draft'] );
		$page  = $this->front_matter( $dir . $paths['page'] );

		// Every format keeps the shared keys.
		$this->assertSame( 'Test Post', $post['title'] );
		$this->assertSame( '2014-01-01T00:00:00+00:00', $post['date'] );
		$this->assertSame( array( 'tag1' ), $post['tags'] );
		$this->assertSame( array( 'A custom field' ), $post['subtitle'] );
		$this->assertArrayNotHasKey( 'draft', $post );
		$this->assertArrayNotHasKey( 'permalink', $page );
		$this->assertArrayNotHasKey( 'url', $page );

		switch ( $format ) {
			case 'jekyll':
				$this->assertSame( '/2014/01/01/test-post/', $post['permalink'] );
				$this->assertSame( 'post', $post['layout'] );
				$this->assertArrayNotHasKey( 'draft', $draft );
				break;

			case 'hugo':
				$this->assertSame( '/2014/01/01/test-post/', $post['url'] );
				$this->assertArrayNotHasKey( 'permalink', $post );
				$this->assertArrayNotHasKey( 'layout', $post );
				$this->assertArrayNotHasKey( 'layout', $page );
				$this->assertTrue( $draft['draft'] );
				// A draft's link is WordPress's plain /?p=123, which isn't a path.
				$this->assertArrayNotHasKey( 'url', $draft );
				break;

			case 'astro':
				$this->assertSame( '/2014/01/01/test-post/', $post['permalink'] );
				$this->assertArrayNotHasKey( 'layout', $post );
				$this->assertArrayNotHasKey( 'layout', $page );
				$this->assertTrue( $draft['draft'] );
				$this->assertArrayNotHasKey( 'permalink', $draft );
				break;

			case 'eleventy':
				$this->assertSame( '/2014/01/01/test-post/', $post['permalink'] );
				$this->assertSame( 'post', $post['layout'] );
				$this->assertTrue( $draft['draft'] );
				$this->assertFalse( $draft['permalink'] );
				break;
		}

		// Folders only the Jekyll layout uses aren't left behind empty.
		if ( 'jekyll' !== $format ) {
			$this->assertDirectoryDoesNotExist( $dir . '_posts' );
			$this->assertDirectoryDoesNotExist( $dir . '_drafts' );
			$this->assertFileDoesNotExist( $dir . '_config.yml' );
		}
	}

	/**
	 * Hugo's config names the site and its URL.
	 */
	function test_hugo_config() {
		update_option( 'blogdescription', 'Just another site' );
		$dir    = $this->export_to_dir( 'hugo' );
		$config = Yaml::parse( file_get_contents( $dir . 'hugo.yaml' ) );

		$this->assertSame( 'http://example.org/', $config['baseURL'] );
		$this->assertSame( 'Test Blog', $config['title'] );
		$this->assertSame( get_bloginfo( 'language' ), $config['locale'] );
		$this->assertSame( 'Just another site', $config['params']['description'] );
	}

	/**
	 * Astro's content config declares a collection for every folder the
	 * export writes to.
	 */
	function test_astro_config_declares_collections() {
		$dir    = $this->export_to_dir( 'astro' );
		$config = file_get_contents( $dir . 'src/content.config.ts' );

		$this->assertStringContainsString( "\"posts\": defineCollection({ loader: glob({ pattern: '**/*.md', base: \"./src/content/posts\" }), schema }),", $config );
		$this->assertStringContainsString( "\"pages\": defineCollection({ loader: glob({ pattern: '**/*.md', base: \"./src/content/pages\" }), schema }),", $config );
		$this->assertStringContainsString( "\"recipe\": defineCollection({ loader: glob({ pattern: '**/*.md', base: \"./src/content/recipe\" }), schema }),", $config );
		$this->assertStringContainsString( 'export const collections = {', $config );
	}

	/**
	 * Eleventy's global data file names the site.
	 */
	function test_eleventy_site_data() {
		$dir  = $this->export_to_dir( 'eleventy' );
		$site = json_decode( file_get_contents( $dir . '_data/site.json' ), true );

		$this->assertSame(
			array(
				'title'       => 'Test Blog',
				'description' => get_option( 'blogdescription' ),
				'url'         => 'http://example.org',
			),
			$site
		);
	}

	/**
	 * Eleventy gets a config that copies uploads and skips Liquid, and a stub
	 * layout for each `layout:` the posts name, since Eleventy fails the
	 * build on a missing layout.
	 */
	function test_eleventy_scaffold() {
		$dir = $this->export_to_dir( 'eleventy' );

		$config = file_get_contents( $dir . 'eleventy.config.mjs' );
		$this->assertStringContainsString( 'addPassthroughCopy("wp-content")', $config );
		$this->assertStringContainsString( 'markdownTemplateEngine: false', $config );

		foreach ( array( 'post', 'page', 'recipe' ) as $layout ) {
			$this->assertStringContainsString( '{{ content | safe }}', file_get_contents( $dir . '_includes/' . $layout . '.njk' ) );
		}
	}

	/**
	 * A scheduled post keeps the URL it will publish at, rather than the
	 * plain /?p=123 link WordPress gives it until then, which Hugo can't use.
	 */
	function test_scheduled_post_keeps_its_future_url() {
		global $jekyll_export;

		$future = self::factory()->post->create(
			array(
				'post_name'   => 'coming-soon',
				'post_status' => 'future',
				'post_date'   => '3014-01-01 00:00:00',
			)
		);

		$this->assertSame( '/?p=' . $future, $jekyll_export->convert_meta( get_post( $future ) )['permalink'], 'Jekyll output is unchanged' );

		$jekyll_export->set_format( 'hugo' );
		$this->assertSame( '/3014/01/01/coming-soon/', $jekyll_export->convert_meta( get_post( $future ) )['url'] );
	}

	/**
	 * A `layout` custom field survives formats that drop the default one.
	 */
	function test_custom_layout_field_is_kept() {
		global $jekyll_export;

		add_post_meta( $this->ids['post'], 'layout', 'wide' );
		$jekyll_export->set_format( 'hugo' );

		$meta = $jekyll_export->convert_meta( get_post( $this->ids['post'] ) );
		$this->assertSame( array( 'wide' ), $meta['layout'] );
	}

	/**
	 * The `jekyll_export_meta` filter sees the format's keys, not Jekyll's.
	 */
	function test_meta_filter_sees_format_keys() {
		global $jekyll_export;

		$seen = null;
		add_filter(
			'jekyll_export_meta',
			function ( $meta ) use ( &$seen ) {
				$seen = $meta;
				return $meta;
			}
		);
		$jekyll_export->set_format( 'hugo' );
		$jekyll_export->convert_meta( get_post( $this->ids['post'] ) );

		$this->assertArrayHasKey( 'url', $seen );
		$this->assertArrayNotHasKey( 'permalink', $seen );
	}

	/**
	 * A plugin can add a format through `jekyll_export_formats`; keys it
	 * leaves out take Jekyll's values.
	 */
	function test_formats_filter_adds_a_format() {
		global $jekyll_export;

		add_filter(
			'jekyll_export_formats',
			function ( $formats ) {
				$formats['zola'] = array(
					'label'       => 'Zola',
					'posts_dir'   => 'content/blog/',
					'date_prefix' => false,
				);
				return $formats;
			}
		);

		$this->assertContains( 'zola', Jekyll_Export_Format::slugs() );
		$this->assertTrue( $jekyll_export->set_format( 'zola' ) );

		$format = $jekyll_export->get_format();
		$this->assertSame( 'Zola', $format->label );
		$this->assertSame( 'content/blog/', $format->dir_for( 'post' ) );
		$this->assertSame( '_drafts/', $format->drafts_dir );
	}

	/**
	 * An unknown format stops the export with a message listing the real
	 * ones, rather than quietly exporting Jekyll.
	 */
	function test_unknown_format_fails() {
		global $jekyll_export;

		$this->assertFalse( $jekyll_export->set_format( 'gatsby' ) );

		add_filter(
			'jekyll_export_format',
			function () {
				return 'gatsby';
			}
		);

		try {
			$jekyll_export->export( get_temp_dir() . 'never-written.zip' );
			$this->fail( 'export() should wp_die() for an unknown format' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'Unknown export format', $e->getMessage() );
			$this->assertStringContainsString( 'gatsby', $e->getMessage() );
			$this->assertStringContainsString( 'jekyll, hugo, astro, eleventy', $e->getMessage() );
		}
		$this->assertFileDoesNotExist( get_temp_dir() . 'never-written.zip' );
	}

	/**
	 * The admin form's `format` param picks the format, and an unknown one
	 * is refused before anything is exported.
	 */
	function test_callback_rejects_unknown_format_param() {
		global $jekyll_export;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		$_GET['type']     = 'jekyll';
		$_GET['_wpnonce'] = wp_create_nonce( 'jekyll_export' );
		$_GET['format']   = 'gatsby';

		try {
			set_current_screen( 'export' );
			$jekyll_export->callback();
			$this->fail( 'callback() should wp_die() for an unknown format' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'Unknown export format', $e->getMessage() );
		} finally {
			unset( $_GET['type'], $_GET['_wpnonce'], $_GET['format'] );
		}

		$this->assertSame( 'jekyll', $jekyll_export->get_format()->slug );
	}

	/**
	 * The admin screen submits the chosen format, with a fresh nonce, to the
	 * URL callback() listens on.
	 */
	function test_admin_page_form() {
		global $jekyll_export;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		ob_start();
		$jekyll_export->render_admin_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'method="get"', $html );
		$this->assertStringContainsString( 'action="' . esc_url( admin_url( 'export.php' ) ) . '"', $html );
		$this->assertStringContainsString( 'name="type" value="jekyll"', $html );
		$this->assertStringContainsString( 'name="_wpnonce" value="' . wp_create_nonce( 'jekyll_export' ) . '"', $html );
		$this->assertStringNotContainsString( '_wp_http_referer', $html );
		$this->assertStringContainsString( 'name="format"', $html );
		foreach ( array( 'jekyll', 'hugo', 'astro', 'eleventy' ) as $slug ) {
			$this->assertStringContainsString( 'value="' . $slug . '"', $html );
		}
		$this->assertMatchesRegularExpression( '/value="jekyll"\s+selected/', $html );
	}

	/**
	 * The admin screen honors the `jekyll_export_format` filter as its default.
	 */
	function test_admin_page_preselects_filtered_format() {
		global $jekyll_export;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
		$jekyll_export->set_format( 'eleventy' );

		ob_start();
		$jekyll_export->render_admin_page();
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="eleventy"\s+selected/', $html );
	}

	/**
	 * The admin screen is for administrators only.
	 */
	function test_admin_page_blocks_non_admin() {
		global $jekyll_export;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( WPDieException::class );
		$jekyll_export->render_admin_page();
	}

	/**
	 * `wp jekyll-export --format` goes through set_format(), and an unknown
	 * format is a CLI error. Jekyll_Export_Command only exists under WP-CLI,
	 * so this checks the wiring; set_format() itself is tested above.
	 */
	function test_cli_format_flag() {
		$cli = file_get_contents( __DIR__ . '/../lib/cli.php' );

		$this->assertStringContainsString( '[--format=<format>]', $cli );
		$this->assertStringContainsString( "\$jekyll_export->set_format( \$assoc_args['format'] )", $cli );
		$this->assertStringContainsString( 'WP_CLI::error(', $cli );
	}
}
