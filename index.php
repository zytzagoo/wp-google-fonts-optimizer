<?php
// @codingStandardsIgnoreFile
/**
 * Plugin Name:  WP Google Fonts Optimizer
 * Plugin URI:   https://wordpress.org/plugins/wp-google-fonts-optimizer/
 * Description:  Automatically detect and combine multiple Google Web Fonts requests into a single one.
 * Author:       zytzagoo
 * Author URI:   https://zytzagoo.net
 * Version:      0.0.1
 * Requires PHP: 5.4
 *
 * @package ZWF\GoogleFontsOptimizer
 */

namespace ZWF;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin.
 *
 * @wp-hook plugins_loaded
 *
 * @return void
 */
function bootstrap() {
    if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
        // Composer-generated autoload file.
        include_once __DIR__ . '/vendor/autoload.php';
    }

    $combiner = new GoogleFontsOptimizer();
    add_action( 'wp', [ $combiner, 'run' ] );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 0 );
