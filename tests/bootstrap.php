<?php
/**
 * Bootstrap the local test environment
 *
 * @package Jekyll_Exporter
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Require the Jekyll Export Plugin on load
 */
function _manually_load_plugin() {
	require __DIR__ . '/../jekyll-exporter.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
