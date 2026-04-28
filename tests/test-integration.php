<?php
/**
 * Integration tests for full export workflow
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Integration test suite for full export workflow
 */
class IntegrationTest extends WP_UnitTestCase {

	/**
	 * ID of sample post for integration tests
	 *
	 * @var int
	 */
	private static $test_post_id = 0;

	/**
	 * ID of test author
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Setup the test class
	 */
	protected static function set_up_before_class() {
		parent::set_up_before_class();

		self::$author_id = wp_insert_user(
			array(
				'user_login'   => rand_str(),
				'user_pass'    => rand_str(),
				'display_name' => 'Integration Tester',
			)
		);

		self::$test_post_id = wp_insert_post(
			array(
				'post_name'    => 'integration-test-post',
				'post_title'   => 'Integration Test Post',
				'post_content' => '<p>This is an <strong>integration</strong> test post with <a href="http://example.com">a link</a>.</p>',
				'post_status'  => 'publish',
				'post_author'  => self::$author_id,
				'post_date'    => '2024-01-01',
				'post_excerpt' => 'Test excerpt',
				'tags_input'   => array( 'integration', 'test' ),
			)
		);

		// Add custom meta.
		add_post_meta( self::$test_post_id, 'custom_field', 'custom_value' );
	}

	/**
	 * Setup each test
	 */
	protected function set_up() {
		parent::set_up();

		global $jekyll_export;
		$jekyll_export->init_temp_dir();
	}

	/**
	 * Tear down each test
	 */
	protected function tear_down() {
		global $jekyll_export;
		if ( isset( $jekyll_export->dir ) && file_exists( $jekyll_export->dir ) ) {
			$jekyll_export->cleanup();
		}
	}

	/**
	 * Test full export workflow produces valid output
	 */
	function test_full_export_workflow() {
		global $jekyll_export;

		// Run the export process (without sending the file).
		$jekyll_export->convert_options();
		$jekyll_export->convert_posts();

		// Verify _config.yml exists and is valid.
		$config_file = $jekyll_export->dir . '/_config.yml';
		$this->assertFileExists( $config_file );

		$config_contents = file_get_contents( $config_file );
		$config          = Yaml::parse( $config_contents );
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'name', $config );

		// Verify post was exported.
		$post_file = $jekyll_export->dir . '/_posts/2024-01-01-integration-test-post.md';
		$this->assertFileExists( $post_file );

		// Verify post content.
		$post_contents = file_get_contents( $post_file );

		// Check YAML front matter.
		$parts = explode( '---', $post_contents );
		$this->assertEquals( 3, count( $parts ), 'Post should have valid YAML front matter' );

		$yaml = Yaml::parse( $parts[1] );
		$this->assertEquals( 'Integration Test Post', $yaml['title'] );
		$this->assertEquals( 'Integration Tester', $yaml['author'] );
		$this->assertArrayHasKey( 'tags', $yaml );
		$this->assertContains( 'integration', $yaml['tags'] );
		$this->assertContains( 'test', $yaml['tags'] );
		$this->assertEquals( 'Test excerpt', $yaml['excerpt'] );
		$this->assertEquals( array( 'custom_value' ), $yaml['custom_field'] );

		// Check content was converted to Markdown.
		$body = trim( $parts[2] );
		$this->assertStringContainsString( '**integration**', $body );
		$this->assertStringContainsString( '[a link](http://example.com)', $body );
	}

	/**
	 * Test that zip creation includes all necessary files
	 */
	function test_zip_includes_all_files() {
		global $jekyll_export;

		// Run export and create zip.
		$jekyll_export->convert_options();
		$jekyll_export->convert_posts();
		$jekyll_export->zip();

		$this->assertFileExists( $jekyll_export->zip );

		// Extract and verify contents.
		$extract_dir = get_temp_dir() . 'jekyll-test-extract-' . time();
		mkdir( $extract_dir );

		$zip = new ZipArchive();
		$zip->open( $jekyll_export->zip );
		$zip->extractTo( $extract_dir );
		$zip->close();

		// Check for required files and directories.
		$this->assertTrue( file_exists( $extract_dir . '/_config.yml' ) );
		$this->assertTrue( is_dir( $extract_dir . '/_posts' ) );
		$this->assertTrue( is_dir( $extract_dir . '/_drafts' ) );
		$this->assertTrue( is_dir( $extract_dir . '/wp-content' ) );

		// Check that post file exists in zip.
		$this->assertTrue( file_exists( $extract_dir . '/_posts/2024-01-01-integration-test-post.md' ) );

		// Cleanup.
		$this->recursive_rmdir( $extract_dir );
	}

	/**
	 * Test export with uploads
	 */
	function test_export_with_uploads() {
		global $jekyll_export;

		// Create a fake upload file.
		$upload_dir = wp_upload_dir();
		if ( ! file_exists( $upload_dir['basedir'] ) ) {
			mkdir( $upload_dir['basedir'], 0777, true );
		}

		file_put_contents( $upload_dir['basedir'] . '/test-upload.txt', 'Upload test content' );

		// Run export.
		$jekyll_export->convert_uploads();

		// Verify upload was copied.
		$upload_in_export = $jekyll_export->dir . '/wp-content/uploads/test-upload.txt';
		$this->assertTrue( file_exists( $upload_in_export ), 'Upload file should be copied to export directory' );
		$this->assertEquals( 'Upload test content', file_get_contents( $upload_in_export ) );
	}

	/**
	 * Test that export handles multiple post types
	 */
	function test_export_multiple_post_types() {
		global $jekyll_export;

		// Create different post types.
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Test Page Export',
				'post_content' => 'Page content',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => self::$author_id,
			)
		);

		$draft_id = wp_insert_post(
			array(
				'post_title'   => 'Test Draft Export',
				'post_content' => 'Draft content',
				'post_status'  => 'draft',
				'post_name'    => 'test-draft-export',
				'post_author'  => self::$author_id,
			)
		);

		// Run export.
		$jekyll_export->convert_posts();

		// Verify page was exported to root.
		$page_file = $jekyll_export->dir . '/' . get_page_uri( $page_id ) . '.md';
		$this->assertTrue( file_exists( $page_file ), 'Page should be exported to root directory' );

		// Verify draft was exported to _drafts.
		$draft_files = glob( $jekyll_export->dir . '/_drafts/*.md' );
		$found_draft = false;
		foreach ( $draft_files as $file ) {
			if ( strpos( $file, 'test-draft-export' ) !== false ) {
				$found_draft = true;
				break;
			}
		}
		$this->assertTrue( $found_draft, 'Draft should be exported to _drafts directory' );

		// Verify original integration test post still exists.
		$post_file = $jekyll_export->dir . '/_posts/2024-01-01-integration-test-post.md';
		$this->assertTrue( file_exists( $post_file ), 'Original post should still exist' );
	}

	/**
	 * Test that export handles posts with special characters in titles
	 */
	function test_export_special_characters() {
		global $jekyll_export;

		$special_post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post with "Quotes" & Special <Chars>',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_name'    => 'test-post-special',
				'post_date'    => '2024-02-01',
				'post_author'  => self::$author_id,
			)
		);

		$jekyll_export->convert_posts();

		// Verify file was created.
		$post_file = $jekyll_export->dir . '/_posts/2024-02-01-test-post-special.md';
		$this->assertTrue( file_exists( $post_file ) );

		// Verify YAML is valid despite special characters.
		$contents = file_get_contents( $post_file );
		$parts    = explode( '---', $contents );
		$yaml     = Yaml::parse( $parts[1] );

		// WordPress's get_the_title() returns the title as stored, matching what was inserted.
		$post = get_post( $special_post_id );
		$this->assertEquals( get_the_title( $post ), $yaml['title'] );
	}

	/**
	 * Test that exported posts have localized image URLs
	 */
	function test_export_localizes_inline_images() {
		global $jekyll_export;

		// Create a post with inline images.
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Post with Images',
				'post_content' => '<p>Image: <img src="http://example.org/wp-content/uploads/test.jpg" alt="Test"></p>',
				'post_status'  => 'publish',
				'post_name'    => 'post-with-images',
				'post_date'    => '2024-03-01',
				'post_author'  => self::$author_id,
			)
		);

		// Run export.
		$jekyll_export->convert_posts();

		// Verify the exported file.
		$post_file = $jekyll_export->dir . '/_posts/2024-03-01-post-with-images.md';
		$this->assertTrue( file_exists( $post_file ), 'Post file should exist' );

		// Check that URLs are localized in the post body (not the YAML front matter).
		$contents = file_get_contents( $post_file );
		
		// Split content into YAML front matter and body.
		$parts = explode( '---', $contents );
		$this->assertCount( 3, $parts, 'Post should have YAML front matter' );
		
		$body = $parts[2]; // The content after the second '---'
		
		// Check that inline image URLs in the body are localized.
		$this->assertStringNotContainsString( 'http://example.org/wp-content/uploads', $body, 'Absolute image URL should be replaced in post body' );
		$this->assertStringContainsString( '/wp-content/uploads/test.jpg', $body, 'Relative URL should be present in post body' );
	}

	/**
	 * Recursively remove directory
	 *
	 * @param string $dir Directory to remove.
	 */
	private function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? $this->recursive_rmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
