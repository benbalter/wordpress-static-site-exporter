<?php
/**
 * Exports WordPress posts, pages, and options as YAML files parsable by Jekyll
 *
 * @package    JekyllExporter
 * @author     Ben Balter <ben@balter.com>
 * @copyright  2013-2025 Ben Balter
 * @license    GPLv3
 * @link       https://github.com/benbalter/wordpress-to-jekyll-exporter/
 *
 * @deprecated Use WP-CLI command "wp jekyll-export" instead.
 */

/**
 * Usage:
 *
 *     $ php jekyll-export-cli.php > my-jekyll-files.zip
 *
 * Must be run in the wordpress-to-jekyll-exporter/ directory.
 *
 * @deprecated Use WP-CLI command "wp jekyll-export" instead.
 */
// Reject web/SAPI access before bootstrapping WordPress. Loading wp-load.php is
// expensive and confirms install/plugin paths; refuse early if not invoked from CLI.
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( 'Jekyll export must be run via the command line or administrative dashboard.' );
}

require '../../../wp-load.php';
require_once 'jekyll-exporter.php'; // Ensure plugin is "activated".

// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- Intentional deprecation notice.
trigger_error( 'jekyll-export-cli.php is deprecated. Use "wp jekyll-export" (WP-CLI) instead.', E_USER_DEPRECATED );

$jekyll_export = new Jekyll_Export();
$jekyll_export->export();
