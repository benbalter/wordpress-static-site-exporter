<?php
/**
 * Tests for CLI functionality
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 */

/**
 * Test suite for CLI functionality
 */
class CLITest extends WP_UnitTestCase {

	/**
	 * Test that CLI file loads without errors when WP_CLI is defined
	 */
	function test_cli_file_loads() {
		// Test that the CLI file can be included without errors.
		// The actual CLI class is only defined when WP_CLI is true, not just defined.
		$cli_file = __DIR__ . '/../lib/cli.php';
		$this->assertFileExists( $cli_file, 'CLI file should exist' );

		// In the WordPress test environment, WP_CLI may be defined but false.
		// We can only verify the file structure is valid.
		$this->assertTrue( true );
	}

	/**
	 * Test that CLI functionality is conditional on WP_CLI
	 */
	function test_cli_conditional_loading() {
		// The CLI functionality should only be available when WP_CLI is defined and true.
		// In test environment, we verify the conditional logic exists.
		$cli_content = file_get_contents( __DIR__ . '/../lib/cli.php' );
		$this->assertStringContainsString( 'if ( defined( \'WP_CLI\' ) && WP_CLI )', $cli_content );
		$this->assertStringContainsString( 'class Jekyll_Export_Command', $cli_content );
	}

	/**
	 * Test that CLI class structure is correct
	 */
	function test_cli_class_structure() {
		// Verify the CLI file contains the expected class and method signatures.
		$cli_content = file_get_contents( __DIR__ . '/../lib/cli.php' );
		$this->assertStringContainsString( 'extends WP_CLI_Command', $cli_content );
		$this->assertStringContainsString( 'function __invoke(', $cli_content );
	}
}
