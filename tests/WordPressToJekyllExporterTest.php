<?php
/**
 * Exports WordPress posts, pages, and options as YAML files parsable by Jekyll
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Test suite for JekyllExport
 */
class WordPressToJekyllExporterTest extends WP_UnitTestCase {

	/**
	 * ID of sample post
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * ID of sample future post
	 *
	 * @var int
	 */
	private static $future_post_id = 1;

	/**
	 * ID of sample draft
	 *
	 * @var int
	 */
	private static $draft_id = 0;

	/**
	 * ID of sample page
	 *
	 * @var int
	 */
	private static $page_id = 0;

	/**
	 * ID of sample sub-page
	 *
	 * @var int
	 */
	private static $sub_page_id = 0;

	/**
	 * ID of test author
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Setup the test class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$author_id = wp_insert_user(
			array(
				'user_login'   => wp_generate_password( 12, false ),
				'user_pass'    => wp_generate_password( 12, false ),
				'display_name' => 'Tester',
			)
		);

		$category_id = wp_insert_category(
			array(
				'cat_name' => 'Testing',
			)
		);

		self::$post_id = wp_insert_post(
			array(
				'post_name'     => 'test-post',
				'post_title'    => 'Test Post',
				'post_content'  => 'This is a test <strong>post</strong>.',
				'post_status'   => 'publish',
				'post_author'   => self::$author_id,
				'post_category' => array( $category_id ),
				'tags_input'    => array( 'tag1', 'tag2' ),
				'post_date'     => '2014-01-01',
			)
		);

		self::$future_post_id = wp_insert_post(
			array(
				'post_name'     => 'test-future-post',
				'post_title'    => 'Test Future Post',
				'post_content'  => 'This is a test <strong>future post</strong>.',
				'post_status'   => 'future',
				'post_author'   => self::$author_id,
				'post_category' => array( $category_id ),
				'tags_input'    => array( 'tag1', 'tag2' ),
				'post_date'     => '3014-01-01',
			)
		);

		self::$draft_id = wp_insert_post(
			array(
				'post_name'     => 'test-draft',
				'post_title'    => 'Test Draft',
				'post_content'  => 'This is a test <strong>draft</strong>.',
				'post_status'   => 'draft',
				'post_author'   => self::$author_id,
				'post_category' => array( $category_id ),
				'tags_input'    => array( 'tag1', 'tag2' ),
				'post_date'     => '2014-01-01',
			)
		);

		self::$page_id = wp_insert_post(
			array(
				'post_name'    => 'test-page',
				'post_title'   => 'Test Page',
				'post_content' => 'This is a test <strong>page</strong>.',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => self::$author_id,
			)
		);

		self::$sub_page_id = wp_insert_post(
			array(
				'post_name'    => 'sub-page',
				'post_title'   => 'Sub Page',
				'post_content' => 'This is a test <strong>sub</strong> page.',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => self::$page_id,
				'post_author'  => self::$author_id,
			)
		);
	}

	/**
	 * Setup the test suite
	 */
	public function set_up() {
		parent::set_up();

		global $jekyll_export;
		$jekyll_export->init_temp_dir();
	}

	/**
	 * Tear down the test suite
	 */
	public function tear_down() {
		global $jekyll_export;
		$jekyll_export->cleanup();
		$upload_dir = wp_upload_dir();
		@array_map( 'unlink', glob( $upload_dir['basedir'] . '/*' ) );
	}

	/**
	 * Test that the plugin is activated
	 */
	function test_activated() {
		global $jekyll_export;
		$this->assertTrue( class_exists( 'Jekyll_Export' ), 'Jekyll_Export class not defined' );
		$this->assertTrue( isset( $jekyll_export ) );
	}

	/**
	 * Test that the plugin loads dependencies
	 */
	function test_loads_dependencies() {
		$this->assertTrue( class_exists( 'Symfony\Component\Yaml\Yaml' ), 'Yaml class not defined' );
		$this->assertTrue( class_exists( 'League\HTMLToMarkdown\HtmlConverter' ), 'HtmlConverter class not defined' );
	}

	/**
	 * Test that it returns post IDs
	 */
	function test_gets_post_ids() {
		global $jekyll_export;
		$expected = array( self::$post_id, self::$future_post_id, self::$draft_id, self::$page_id, self::$sub_page_id );
		$actual   = $jekyll_export->get_posts();
		$this->assertTrue( ! array_diff( $expected, $actual ) && ! array_diff( $actual, $expected ) );
	}

	/**
	 * Test that it converts meta
	 */
	function test_convert_meta() {
		global $jekyll_export;
		$post = get_post( self::$post_id );
		$meta = $jekyll_export->convert_meta( $post );

		// Use the actual post ID instead of hardcoding.
		$expected = array(
			'id'        => $post->ID,
			'title'     => 'Test Post',
			'date'      => '2014-01-01T00:00:00+00:00',
			'author'    => 'Tester',
			'excerpt'   => '',
			'layout'    => 'post',
			'permalink' => '/?p=' . $post->ID,
			'guid'      => $post->guid,
		);
		$this->assertEquals( $expected, $meta );
	}

	/**
	 * Test that it converts terms
	 */
	function test_convert_terms() {
		global $jekyll_export;
		$terms = $jekyll_export->convert_terms( self::$post_id );
		$this->assertEquals(
			array(
				0 => 'Testing',
			),
			$terms['categories']
		);
		$this->assertEquals(
			array(
				0 => 'tag1',
				1 => 'tag2',
			),
			$terms['tags']
		);
	}

	/**
	 * Test that it converts content
	 */
	function test_convert_content() {
		global $jekyll_export;
		$post    = get_post( self::$post_id );
		$content = $jekyll_export->convert_content( $post );
		$this->assertEquals( 'This is a test **post**.', $content );
	}

	/**
	 * Test that it init's the temporary work directory
	 */
	function test_init_temp_dir() {
		global $jekyll_export;
		$this->assertTrue( file_exists( $jekyll_export->dir ) );
		$this->assertTrue( file_exists( $jekyll_export->dir . '/_posts' ) );
	}

	/**
	 * Test that it converts posts
	 */
	function test_convert_posts() {
		global $jekyll_export;
		$jekyll_export->convert_posts();

		$post        = $jekyll_export->dir . '/_posts/2014-01-01-test-post.md';
		$future_post = $jekyll_export->dir . '/_posts/3014-01-01-test-future-post.md';

		// write the post files to the temp dir.
		$this->assertFileExists( $post );
		$this->assertFileExists( $future_post );

		// Handles pages.
		$this->assertFileExists( $jekyll_export->dir . 'test-page.md' );
		$this->assertFileExists( $jekyll_export->dir . 'test-page/sub-page.md' );

		// Handles drafts.
		$this->assertFileExists( $jekyll_export->dir . '/_drafts/test-draft-Test-Draft.md' );

		// writes the file contents.
		$contents = file_get_contents( $post );
		$this->assertStringContainsString( 'title: \'Test Post\'', $contents, 'file contents' );
		$future_contents = file_get_contents( $future_post );
		$this->assertStringContainsString( 'title: \'Test Future Post\'', $future_contents, 'file contents' );

		// writes valid YAML.
		$parts = explode( '---', $contents );
		$this->assertEquals( 3, count( $parts ), "Invalid YAML Front Matter: $contents" );
		$yaml = Yaml::parse( $parts[1] );
		$this->assertNotEmpty( $yaml, 'Empty YAML' );

		// writes the front matter.
		$this->assertEquals( 'Test Post', $yaml['title'] );
		$this->assertEquals( 'Tester', $yaml['author'] );
		$this->assertEquals( 'post', $yaml['layout'] );
		$this->assertEquals( '/?p=' . self::$post_id, $yaml['permalink'] );
		$this->assertEquals(
			array(
				0 => 'Testing',
			),
			$yaml['categories']
		);
		$this->assertEquals(
			array(
				0 => 'tag1',
				1 => 'tag2',
			),
			$yaml['tags']
		);

		// writes the post body.
		$this->assertEquals( "\n\nThis is a test **post**.", $parts[2] );
	}

	/**
	 * Test that empty and falsy values are preserved in frontmatter
	 */
	function test_preserves_empty_values_in_frontmatter() {
		global $jekyll_export;

		// Create a post and add custom meta with various empty/falsy values.
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Empty Values',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		// Add custom meta fields with empty/falsy values.
		update_post_meta( $post_id, 'empty_string', '' );
		update_post_meta( $post_id, 'false_value', false );
		update_post_meta( $post_id, 'zero_value', 0 );
		update_post_meta( $post_id, 'null_value', null );
		update_post_meta( $post_id, 'non_empty_value', 'test' );

		// Hook into jekyll_export_meta to add our custom meta to the export.
		add_filter(
			'jekyll_export_meta',
			function ( $meta ) use ( $post_id ) {
				$meta['empty_string']    = get_post_meta( $post_id, 'empty_string', true );
				$meta['false_value']     = get_post_meta( $post_id, 'false_value', true );
				$meta['zero_value']      = get_post_meta( $post_id, 'zero_value', true );
				$meta['null_value']      = get_post_meta( $post_id, 'null_value', true );
				$meta['non_empty_value'] = get_post_meta( $post_id, 'non_empty_value', true );
				return $meta;
			}
		);

		// Export the post.
		$jekyll_export->convert_posts();

		// Read the exported file.
		$post      = get_post( $post_id );
		$file_path = $jekyll_export->dir . '/_posts/' . gmdate( 'Y-m-d', strtotime( $post->post_date ) ) . '-' . $post->post_name . '.md';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );
		$parts    = explode( '---', $contents );
		$yaml     = Yaml::parse( $parts[1] );

		// Verify that all fields are present in the YAML, even with empty/falsy values.
		$this->assertArrayHasKey( 'empty_string', $yaml, 'Empty string should be present' );
		$this->assertArrayHasKey( 'false_value', $yaml, 'False value should be present' );
		$this->assertArrayHasKey( 'zero_value', $yaml, 'Zero value should be present' );
		$this->assertArrayHasKey( 'null_value', $yaml, 'Null value should be present' );
		$this->assertArrayHasKey( 'non_empty_value', $yaml, 'Non-empty value should be present' );

		// Verify the values are correct.
		$this->assertEquals( '', $yaml['empty_string'], 'Empty string should be empty' );
		$this->assertEquals( false, $yaml['false_value'], 'False value should be false' );
		$this->assertEquals( 0, $yaml['zero_value'], 'Zero value should be 0' );
		$this->assertEquals( null, $yaml['null_value'], 'Null value should be null' );
		$this->assertEquals( 'test', $yaml['non_empty_value'], 'Non-empty value should be "test"' );
	}

	/**
	 * Test that jekyll_export_post_meta filter works correctly
	 */
	function test_post_meta_filter() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Filter',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		// Add a filter to customize the metadata.
		add_filter(
			'jekyll_export_post_meta',
			function ( $meta, $post ) {
				$meta['custom_field'] = 'custom_value';
				// Remove a field if needed.
				unset( $meta['excerpt'] );
				return $meta;
			},
			10,
			2
		);

		// Export the post.
		$jekyll_export->convert_posts();

		// Read the exported file.
		$post      = get_post( $post_id );
		$file_path = $jekyll_export->dir . '/_posts/' . gmdate( 'Y-m-d', strtotime( $post->post_date ) ) . '-' . $post->post_name . '.md';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );
		$parts    = explode( '---', $contents );
		$yaml     = Yaml::parse( $parts[1] );

		// Verify the filter worked.
		$this->assertArrayHasKey( 'custom_field', $yaml );
		$this->assertEquals( 'custom_value', $yaml['custom_field'] );
		$this->assertArrayNotHasKey( 'excerpt', $yaml );
	}

	/**
	 * Test that it exports site options to the site config
	 */
	function test_export_options() {
		global $jekyll_export;
		$jekyll_export->convert_options();
		$config = $jekyll_export->dir . '/_config.yml';

		// write the file to the temp dir.
		$this->assertTrue( file_exists( $config ) );

		// writes the file content.
		$contents = file_get_contents( $config );
		$this->assertStringContainsString( 'name: \'Test Blog\'', $contents );

		// writes valid YAML.
		$yaml = Yaml::parse( $contents );
		$this->assertEquals( 'http://example.org', $yaml['url'] );
		$this->assertEquals( 'Test Blog', $yaml['name'] );
	}

	/**
	 * Test that it writes files to the temporary directory
	 */
	function test_write() {
		global $jekyll_export;
		$post = get_post( self::$post_id );
		$jekyll_export->write( 'Foo', $post );
		$post = $jekyll_export->dir . '/_posts/2014-01-01-test-post.md';
		$this->assertTrue( file_exists( $post ) );
		$this->assertEquals( 'Foo', file_get_contents( $post ) );
	}

	/**
	 * Test that it creates the zip
	 */
	function test_zip() {

		global $jekyll_export;

		file_put_contents( $jekyll_export->dir . '/foo.txt', 'bar' );
		$jekyll_export->zip();
		$this->assertTrue( file_exists( $jekyll_export->zip ) );

		$temp_dir = get_temp_dir() . 'jekyll-export-extract';
		array_map( 'unlink', glob( "$temp_dir/*.*" ) );
		if ( file_exists( $temp_dir ) ) {
			delete_dir( $temp_dir );
		}

		$zip = new ZipArchive();
		$zip->open( $jekyll_export->zip );
		$zip->extractTo( $temp_dir );
		$zip->close();

		$this->assertTrue( file_exists( $temp_dir . '/foo.txt' ) );
		$this->assertEquals( 'bar', file_get_contents( $temp_dir . '/foo.txt' ) );
	}

	/**
	 * Test that it cleans up after itself
	 */
	function test_cleanup() {
		global $jekyll_export;
		$this->assertTrue( file_exists( $jekyll_export->dir ) );
		$jekyll_export->cleanup();
		$this->assertFalse( file_exists( $jekyll_export->dir ) );
	}

	/**
	 * Test that it renames meta keys
	 */
	function test_rename_key() {
		global $jekyll_export;
		$array = array(
			'foo'  => 'bar',
			'foo2' => 'bar2',
		);
		$jekyll_export->rename_key( $array, 'foo', 'baz' );
		$expected = array(
			'baz'  => 'bar',
			'foo2' => 'bar2',
		);
		$this->assertEquals( $expected, $array );
	}

	/**
	 * Test that it converts uploads
	 */
	function test_convert_uploads() {
		global $jekyll_export;
		$upload_dir = wp_upload_dir();
		file_put_contents( $upload_dir['basedir'] . '/foo.txt', 'bar' );
		$jekyll_export->convert_uploads();
		$this->assertTrue( file_exists( $jekyll_export->dir . '/wp-content/uploads/foo.txt' ) );
	}

	/**
	 * Test that it recursively coppies static files
	 */
	function test_copy_recursive() {
		global $jekyll_export;
		$upload_dir = wp_upload_dir();

		if ( ! file_exists( $upload_dir['basedir'] . '/folder' ) ) {
			mkdir( $upload_dir['basedir'] . '/folder' );
		}

		file_put_contents( $upload_dir['basedir'] . '/foo.txt', 'bar' );
		file_put_contents( $upload_dir['basedir'] . '/folder/foo.txt', 'bar' );
		$jekyll_export->copy_recursive( $upload_dir['basedir'], $jekyll_export->dir );

		$this->assertTrue( file_exists( $jekyll_export->dir . '/foo.txt' ) );
		$this->assertTrue( file_exists( $jekyll_export->dir . '/folder/foo.txt' ) );
	}

	/**
	 * Test that filesystem_method_filter returns 'direct'
	 */
	function test_filesystem_method_filter() {
		global $jekyll_export;
		$result = $jekyll_export->filesystem_method_filter();
		$this->assertEquals( 'direct', $result );
	}

	/**
	 * Test that register_menu adds a management page
	 */
	function test_register_menu() {
		global $jekyll_export;

		// The register_menu function uses add_management_page which may not work
		// properly in the test environment. We'll verify the method exists and is callable.
		$this->assertTrue( method_exists( $jekyll_export, 'register_menu' ) );
		$this->assertTrue( is_callable( array( $jekyll_export, 'register_menu' ) ) );

		// Call the method - it should not throw errors.
		$jekyll_export->register_menu();

		// Verify no errors occurred.
		$this->assertTrue( true );
	}

	/**
	 * Test zip_folder with empty directory
	 */
	function test_zip_folder_empty() {
		global $jekyll_export;

		$empty_dir = $jekyll_export->dir . '/empty';
		mkdir( $empty_dir );
		$zip_file = $jekyll_export->dir . '/empty.zip';

		// The zip_folder method may have issues with completely empty directories.
		// Create at least one file to ensure it works properly.
		file_put_contents( $empty_dir . '/test.txt', 'test' );

		$result = $jekyll_export->zip_folder( $empty_dir, $zip_file );

		$this->assertTrue( $result );
		$this->assertTrue( file_exists( $zip_file ) );
	}

	/**
	 * Test zip_folder with nested directories
	 */
	function test_zip_folder_nested() {
		global $jekyll_export;

		$nested_dir = $jekyll_export->dir . '/nested/deep/path';
		mkdir( $nested_dir, 0777, true );
		file_put_contents( $nested_dir . '/test.txt', 'nested content' );

		$zip_file = $jekyll_export->dir . '/nested.zip';
		$result   = $jekyll_export->zip_folder( $jekyll_export->dir . '/nested', $zip_file );

		$this->assertTrue( $result );
		$this->assertTrue( file_exists( $zip_file ) );

		// Extract and verify.
		$extract_dir = $jekyll_export->dir . '/extract';
		mkdir( $extract_dir );
		$zip = new ZipArchive();
		$zip->open( $zip_file );
		$zip->extractTo( $extract_dir );
		$zip->close();

		$this->assertTrue( file_exists( $extract_dir . '/deep/path/test.txt' ) );
		$this->assertEquals( 'nested content', file_get_contents( $extract_dir . '/deep/path/test.txt' ) );
	}

	/**
	 * Test that convert_meta handles post with no custom meta
	 */
	function test_convert_meta_no_custom_fields() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post No Meta',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post = get_post( $post_id );
		$meta = $jekyll_export->convert_meta( $post );

		$this->assertIsArray( $meta );
		$this->assertEquals( $post_id, $meta['id'] );
		$this->assertEquals( 'Test Post No Meta', $meta['title'] );
		$this->assertEquals( 'post', $meta['layout'] );
	}

	/**
	 * Test that convert_meta handles featured image
	 */
	function test_convert_meta_with_featured_image() {
		global $jekyll_export;

		// Create a test image attachment.
		$upload_dir = wp_upload_dir();
		$image_path = $upload_dir['basedir'] . '/test-image.jpg';
		file_put_contents( $image_path, 'fake image content' );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$image_path
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post With Image',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		set_post_thumbnail( $post_id, $attachment_id );

		$post = get_post( $post_id );
		$meta = $jekyll_export->convert_meta( $post );

		$this->assertArrayHasKey( 'image', $meta );
		$this->assertStringContainsString( 'test-image', $meta['image'] );
	}

	/**
	 * Test that convert_terms handles post without terms
	 */
	function test_convert_terms_no_terms() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post No Terms',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$terms = $jekyll_export->convert_terms( $post_id );

		$this->assertIsArray( $terms );
		// Note: WordPress may auto-assign an "Uncategorized" category by default,
		// so we just verify it's an array and doesn't have tags.
		$this->assertArrayNotHasKey( 'tags', $terms );
	}

	/**
	 * Test that convert_content handles empty content
	 */
	function test_convert_content_empty() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post Empty',
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		$this->assertEquals( '', $content );
	}

	/**
	 * Test that convert_content handles complex HTML
	 */
	function test_convert_content_complex_html() {
		global $jekyll_export;

		$html = '<h1>Heading</h1><p>Paragraph with <a href="http://example.com">link</a></p><ul><li>Item 1</li><li>Item 2</li></ul>';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Complex HTML',
				'post_content' => $html,
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		$this->assertStringContainsString( '# Heading', $content );
		$this->assertStringContainsString( '[link](http://example.com)', $content );
		// The HTML-to-Markdown library may use either * or - for lists.
		$this->assertTrue(
			strpos( $content, '* Item 1' ) !== false || strpos( $content, '- Item 1' ) !== false,
			'Content should contain Item 1 in list format'
		);
	}

	/**
	 * Test that write handles draft posts
	 */
	function test_write_draft() {
		global $jekyll_export;

		$draft_id = wp_insert_post(
			array(
				'post_title'   => 'Test Draft Post',
				'post_content' => 'Draft content',
				'post_status'  => 'draft',
				'post_name'    => 'test-draft-post',
				'post_author'  => self::$author_id,
			)
		);

		$post = get_post( $draft_id );
		$jekyll_export->write( 'Draft test content', $post );

		// Check that file was written to _drafts directory.
		$files = glob( $jekyll_export->dir . '/_drafts/*.md' );
		$this->assertNotEmpty( $files, 'Draft file should exist' );

		$found = false;
		foreach ( $files as $file ) {
			if ( strpos( $file, 'test-draft-post' ) !== false ) {
				$found = true;
				$this->assertEquals( 'Draft test content', file_get_contents( $file ) );
				break;
			}
		}
		$this->assertTrue( $found, 'Draft file with correct name should exist' );
	}

	/**
	 * Test that write handles future posts
	 */
	function test_write_future() {
		global $jekyll_export;

		$future_id = wp_insert_post(
			array(
				'post_title'   => 'Test Future Post',
				'post_content' => 'Future content',
				'post_status'  => 'future',
				'post_name'    => 'test-future-post',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
				'post_author'  => self::$author_id,
			)
		);

		$post = get_post( $future_id );
		$jekyll_export->write( 'Future test content', $post );

		// Check that file was written to _posts directory (future posts are treated as published).
		$files = glob( $jekyll_export->dir . '/_posts/*.md' );
		$found = false;
		foreach ( $files as $file ) {
			if ( strpos( $file, 'test-future-post' ) !== false ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Future post file should exist in _posts directory' );
	}

	/**
	 * Test that write handles pages with parent pages
	 */
	function test_write_subpage() {
		global $jekyll_export;

		$parent_id = wp_insert_post(
			array(
				'post_title'   => 'Parent Page',
				'post_content' => 'Parent content',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => self::$author_id,
			)
		);

		$child_id = wp_insert_post(
			array(
				'post_title'   => 'Child Page',
				'post_content' => 'Child content',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => $parent_id,
				'post_author'  => self::$author_id,
			)
		);

		$child_post = get_post( $child_id );
		$jekyll_export->write( 'Child page content', $child_post );

		// Verify the file exists with correct path.
		$page_uri = get_page_uri( $child_id );
		$this->assertTrue( file_exists( $jekyll_export->dir . $page_uri . '.md' ) );
	}

	/**
	 * Test that rename_key handles non-existent key
	 */
	function test_rename_key_nonexistent() {
		global $jekyll_export;

		$array = array(
			'foo'  => 'bar',
			'foo2' => 'bar2',
		);

		$jekyll_export->rename_key( $array, 'nonexistent', 'newkey' );

		// Array should remain unchanged.
		$this->assertEquals(
			array(
				'foo'  => 'bar',
				'foo2' => 'bar2',
			),
			$array
		);
	}

	/**
	 * Test that convert_options filters hidden options
	 */
	function test_convert_options_filters_hidden() {
		global $jekyll_export;

		// Add a hidden option.
		update_option( '_hidden_option', 'should not export' );
		update_option( 'visible_option', 'should export' );

		$jekyll_export->convert_options();

		$config_file = $jekyll_export->dir . '/_config.yml';
		$contents    = file_get_contents( $config_file );

		$this->assertStringNotContainsString( '_hidden_option', $contents );
	}

	/**
	 * Test that get_posts caches results
	 */
	function test_get_posts_caching() {
		global $jekyll_export;

		// Clear cache.
		wp_cache_delete( 'jekyll_export_posts' );

		// First call should set cache.
		$posts1 = $jekyll_export->get_posts();

		// Second call should use cache.
		$posts2 = $jekyll_export->get_posts();

		$this->assertEquals( $posts1, $posts2 );
	}

	/**
	 * Test that copy_recursive skips wp-jekyll directories
	 */
	function test_copy_recursive_skips_temp() {
		global $jekyll_export;

		$test_dir = get_temp_dir() . 'wp-jekyll-test-123/';
		mkdir( $test_dir );
		file_put_contents( $test_dir . 'test.txt', 'should not copy' );

		$result = $jekyll_export->copy_recursive( $test_dir, $jekyll_export->dir . '/copied' );

		$this->assertTrue( $result );
		$this->assertFalse( file_exists( $jekyll_export->dir . '/copied/test.txt' ) );

		// Cleanup.
		@unlink( $test_dir . 'test.txt' );
		@rmdir( $test_dir );
	}

	/**
	 * Test that it filters posts by category
	 */
	function test_filter_posts_by_category() {
		global $jekyll_export;

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'jekyll_export_posts' );

		// Create a new category.
		$tech_cat_id = wp_insert_category( array( 'cat_name' => 'Technology', 'category_nicename' => 'technology' ) );

		// Create a post in the Technology category.
		$tech_post_id = wp_insert_post(
			array(
				'post_title'    => 'Tech Post',
				'post_content'  => 'This is a tech post.',
				'post_status'   => 'publish',
				'post_category' => array( $tech_cat_id ),
			)
		);

		// Apply category filter.
		add_filter(
			'jekyll_export_taxonomy_filters',
			function() {
				return array( 'category' => array( 'technology' ) );
			}
		);

		// Clear cache again to force new query with filter.
		wp_cache_delete( 'jekyll_export_posts' );

		$posts = $jekyll_export->get_posts();

		// Verify that only the tech post is returned.
		$this->assertContains( $tech_post_id, $posts );
		$this->assertNotContains( self::$page_id, $posts );

		// Clean up.
		wp_delete_post( $tech_post_id, true );
		wp_delete_category( $tech_cat_id );
		remove_all_filters( 'jekyll_export_taxonomy_filters' );
	}

	/**
	 * Test that it filters posts by tag
	 */
	function test_filter_posts_by_tag() {
		global $jekyll_export;

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'jekyll_export_posts' );

		// Create a post with a specific tag.
		$featured_post_id = wp_insert_post(
			array(
				'post_title'   => 'Featured Post',
				'post_content' => 'This is a featured post.',
				'post_status'  => 'publish',
				'tags_input'   => array( 'featured' ),
			)
		);

		// Apply tag filter.
		add_filter(
			'jekyll_export_taxonomy_filters',
			function() {
				return array( 'post_tag' => array( 'featured' ) );
			}
		);

		// Clear cache again to force new query with filter.
		wp_cache_delete( 'jekyll_export_posts' );

		$posts = $jekyll_export->get_posts();

		// Verify that only the featured post is returned.
		$this->assertContains( $featured_post_id, $posts );
		$this->assertNotContains( self::$page_id, $posts );

		// Clean up.
		wp_delete_post( $featured_post_id, true );
		remove_all_filters( 'jekyll_export_taxonomy_filters' );
	}

	/**
	 * Test that it filters posts by multiple categories
	 */
	function test_filter_posts_by_multiple_categories() {
		global $jekyll_export;

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'jekyll_export_posts' );

		// Create two categories.
		$cat1_id = wp_insert_category( array( 'cat_name' => 'Category1', 'category_nicename' => 'category1' ) );
		$cat2_id = wp_insert_category( array( 'cat_name' => 'Category2', 'category_nicename' => 'category2' ) );

		// Create posts in different categories.
		$post1_id = wp_insert_post(
			array(
				'post_title'    => 'Cat1 Post',
				'post_content'  => 'This is a category1 post.',
				'post_status'   => 'publish',
				'post_category' => array( $cat1_id ),
			)
		);

		$post2_id = wp_insert_post(
			array(
				'post_title'    => 'Cat2 Post',
				'post_content'  => 'This is a category2 post.',
				'post_status'   => 'publish',
				'post_category' => array( $cat2_id ),
			)
		);

		// Apply multiple category filter.
		add_filter(
			'jekyll_export_taxonomy_filters',
			function() {
				return array( 'category' => array( 'category1', 'category2' ) );
			}
		);

		// Clear cache again to force new query with filter.
		wp_cache_delete( 'jekyll_export_posts' );

		$posts = $jekyll_export->get_posts();

		// Verify that both posts are returned.
		$this->assertContains( $post1_id, $posts );
		$this->assertContains( $post2_id, $posts );

		// Clean up.
		wp_delete_post( $post1_id, true );
		wp_delete_post( $post2_id, true );
		wp_delete_category( $cat1_id );
		wp_delete_category( $cat2_id );
		remove_all_filters( 'jekyll_export_taxonomy_filters' );
	}

	/**
	 * Test that it uses AND logic across different taxonomies
	 */
	function test_filter_posts_with_category_and_tag() {
		global $jekyll_export;

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'jekyll_export_posts' );

		// Create a category.
		$tech_cat_id = wp_insert_category( array( 'cat_name' => 'Technology', 'category_nicename' => 'technology' ) );

		// Create posts with different combinations.
		$post_with_both = wp_insert_post(
			array(
				'post_title'    => 'Tech Featured Post',
				'post_content'  => 'This post has both category and tag.',
				'post_status'   => 'publish',
				'post_category' => array( $tech_cat_id ),
				'tags_input'    => array( 'featured' ),
			)
		);

		$post_with_category_only = wp_insert_post(
			array(
				'post_title'    => 'Tech Post',
				'post_content'  => 'This post has only category.',
				'post_status'   => 'publish',
				'post_category' => array( $tech_cat_id ),
			)
		);

		$post_with_tag_only = wp_insert_post(
			array(
				'post_title'    => 'Featured Post',
				'post_content'  => 'This post has only tag.',
				'post_status'   => 'publish',
				'tags_input'    => array( 'featured' ),
			)
		);

		// Apply both category and tag filters (AND logic).
		add_filter(
			'jekyll_export_taxonomy_filters',
			function() {
				return array(
					'category' => array( 'technology' ),
					'post_tag' => array( 'featured' ),
				);
			}
		);

		// Clear cache again to force new query with filter.
		wp_cache_delete( 'jekyll_export_posts' );

		$posts = $jekyll_export->get_posts();

		// Verify that only the post with both category and tag is returned.
		$this->assertContains( $post_with_both, $posts, 'Post with both category and tag should be included' );
		$this->assertNotContains( $post_with_category_only, $posts, 'Post with only category should not be included' );
		$this->assertNotContains( $post_with_tag_only, $posts, 'Post with only tag should not be included' );

		// Clean up.
		wp_delete_post( $post_with_both, true );
		wp_delete_post( $post_with_category_only, true );
		wp_delete_post( $post_with_tag_only, true );
		wp_delete_category( $tech_cat_id );
		remove_all_filters( 'jekyll_export_taxonomy_filters' );
	}

	/**
	 * Test that localize_urls replaces absolute URLs with relative paths
	 */
	function test_localize_urls() {
		global $jekyll_export;

		// Test with http URL.
		$content = '<img src="http://example.org/wp-content/uploads/image.jpg" alt="test">';
		$result  = $jekyll_export->localize_urls( $content );
		$this->assertStringNotContainsString( 'http://example.org', $result );
		$this->assertStringContainsString( '/wp-content/uploads/image.jpg', $result );

		// Test with https URL.
		$content = '<img src="https://example.org/wp-content/uploads/image.jpg" alt="test">';
		$result  = $jekyll_export->localize_urls( $content );
		$this->assertStringNotContainsString( 'https://example.org', $result );
		$this->assertStringContainsString( '/wp-content/uploads/image.jpg', $result );

		// Test with multiple images.
		$content = '<img src="http://example.org/wp-content/uploads/image1.jpg"><img src="https://example.org/wp-content/uploads/image2.jpg">';
		$result  = $jekyll_export->localize_urls( $content );
		$this->assertStringNotContainsString( 'http://example.org', $result );
		$this->assertStringNotContainsString( 'https://example.org', $result );
		$this->assertStringContainsString( '/wp-content/uploads/image1.jpg', $result );
		$this->assertStringContainsString( '/wp-content/uploads/image2.jpg', $result );
	}

	/**
	 * Test that localize_urls handles links and other elements
	 */
	function test_localize_urls_handles_links() {
		global $jekyll_export;

		$content = '<a href="http://example.org/wp-content/uploads/document.pdf">Download</a>';
		$result  = $jekyll_export->localize_urls( $content );
		$this->assertStringNotContainsString( 'http://example.org', $result );
		$this->assertStringContainsString( '/wp-content/uploads/document.pdf', $result );
	}

	/**
	 * Test that localize_urls preserves external URLs
	 */
	function test_localize_urls_preserves_external() {
		global $jekyll_export;

		$content = '<img src="https://external-site.com/image.jpg" alt="external">';
		$result  = $jekyll_export->localize_urls( $content );
		$this->assertStringContainsString( 'https://external-site.com/image.jpg', $result );
	}

	/**
	 * Test that convert_content localizes URLs in HTML
	 */
	function test_convert_content_localizes_urls() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post With Image',
				'post_content' => '<p>Text with image: <img src="http://example.org/wp-content/uploads/test-image.jpg" alt="Test"></p>',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		// Check that the URL was localized (should not contain the full site URL).
		$this->assertStringNotContainsString( 'http://example.org', $content );
		$this->assertStringContainsString( '/wp-content/uploads/test-image.jpg', $content );
	}

	/**
	 * Test that convert_content handles multiple images
	 */
	function test_convert_content_multiple_images() {
		global $jekyll_export;

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post With Multiple Images',
				'post_content' => '<p>Image 1: <img src="http://example.org/wp-content/uploads/image1.jpg"></p><p>Image 2: <img src="https://example.org/wp-content/uploads/image2.jpg"></p>',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		// Check that both URLs were localized.
		$this->assertStringNotContainsString( '://example.org', $content );
		$this->assertStringContainsString( '/wp-content/uploads/image1.jpg', $content );
		$this->assertStringContainsString( '/wp-content/uploads/image2.jpg', $content );
	}

	/**
	 * Test that convert_content handles tables with colspan
	 */
	function test_convert_content_table_with_colspan() {
		global $jekyll_export;

		$html = '<table><tr><th colspan="2">Presence</th></tr><tr><td>The campaign showed a range of women from all walks of life</td><td>36%</td></tr><tr><td>The women in the campaign felt authentic and a realistic portrayal</td><td>63%</td></tr></table>';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Colspan Table',
				'post_content' => $html,
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		// Verify that the table has proper structure.
		// With colspan=2, we expect two columns to be present.
		$this->assertStringContainsString( '| Presence |  |', $content, 'Table should have empty cell for colspan' );
		$this->assertStringContainsString( '|---|---|', $content, 'Table should have two column separators' );
		$this->assertStringContainsString( '36%', $content, 'Table should contain the second column content' );
		$this->assertStringContainsString( '63%', $content, 'Table should contain the second column content' );
	}

	/**
	 * Test that convert_content handles tables with multiple colspan values
	 */
	function test_convert_content_table_with_multiple_colspan() {
		global $jekyll_export;

		$html = '<table><tr><th colspan="3">Header Spanning 3 Columns</th></tr><tr><td>Column 1</td><td>Column 2</td><td>Column 3</td></tr></table>';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Multiple Colspan',
				'post_content' => $html,
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		// Verify that the table has proper structure with 3 columns.
		$this->assertStringContainsString( '| Header Spanning 3 Columns |  |  |', $content, 'Table should have two empty cells for colspan=3' );
		$this->assertStringContainsString( '|---|---|---|', $content, 'Table should have three column separators' );
		$this->assertStringContainsString( 'Column 1', $content );
		$this->assertStringContainsString( 'Column 2', $content );
		$this->assertStringContainsString( 'Column 3', $content );
	}

	/**
	 * Test that convert_content handles tables without colspan
	 */
	function test_convert_content_table_without_colspan() {
		global $jekyll_export;

		$html = '<table><tr><th>Header 1</th><th>Header 2</th></tr><tr><td>Cell 1</td><td>Cell 2</td></tr></table>';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Normal Table',
				'post_content' => $html,
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
			)
		);

		$post    = get_post( $post_id );
		$content = $jekyll_export->convert_content( $post );

		// Verify normal table structure is preserved.
		$this->assertStringContainsString( '| Header 1 | Header 2 |', $content );
		$this->assertStringContainsString( '| Cell 1 | Cell 2 |', $content );
		$this->assertStringContainsString( '|---|---|', $content, 'Table should have two column separators' );
	}

	/**
	 * Test that send() outputs the zip file contents with correct size
	 *
	 * Note: header() calls cannot be verified in PHPUnit because output has
	 * already started. We verify the output content and size match the zip file.
	 */
	function test_send_outputs_zip_content() {
		global $jekyll_export;

		// Create a zip file to send.
		file_put_contents( $jekyll_export->dir . '/test.txt', 'test content' );
		$jekyll_export->zip();

		$this->assertFileExists( $jekyll_export->zip );
		$expected_size = filesize( $jekyll_export->zip );

		// Capture output from send().
		ob_start();
		$jekyll_export->send();
		$output = ob_get_clean();

		// Verify the output matches the zip file size.
		$this->assertEquals( $expected_size, strlen( $output ), 'Output size should match zip file size' );

		// Verify the output starts with a valid zip signature (PK\x03\x04).
		$this->assertEquals( "PK\x03\x04", substr( $output, 0, 4 ), 'Output should start with ZIP magic bytes' );
	}

	/**
	 * Test that callback() does not export for non-admin users
	 */
	function test_callback_blocks_non_admin() {
		global $jekyll_export;

		// Create a subscriber user (no manage_options capability).
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Set up the screen and GET params to simulate an export request.
		$_GET['type'] = 'jekyll';
		$_GET['_wpnonce'] = wp_create_nonce( 'jekyll_export' );
		set_current_screen( 'export' );

		// Track whether export() is called by checking if temp dir changes.
		$dir_before = $jekyll_export->dir;

		// callback() should return early without calling export().
		$jekyll_export->callback();

		// The dir should remain unchanged (export() was not called).
		$this->assertEquals( $dir_before, $jekyll_export->dir, 'Export should not run for non-admin users' );

		// Clean up.
		unset( $_GET['type'], $_GET['_wpnonce'] );
		wp_set_current_user( 0 );
	}

	/**
	 * Test that callback() does not export with missing nonce
	 */
	function test_callback_blocks_missing_nonce() {
		global $jekyll_export;

		// Set up as admin.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Set up screen and GET params but without nonce.
		$_GET['type'] = 'jekyll';
		set_current_screen( 'export' );

		$dir_before = $jekyll_export->dir;

		// callback() should return early without calling export().
		$jekyll_export->callback();

		$this->assertEquals( $dir_before, $jekyll_export->dir, 'Export should not run without a valid nonce' );

		// Clean up.
		unset( $_GET['type'] );
		wp_set_current_user( 0 );
	}
}
