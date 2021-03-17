<?php

/*
Plugin Name: WordPress - Elasticsearch
Plugin URI:  https://github.com/simonbinder/wordpress-elasticsearch
Description: Elasticsearch sync plugin for WordPress.
Version:     1.0
Author:      Simon Binder
Author URI:  https://github.com/simonbinder
License:     GPL V3.
License URI: http://www.gnu.org/licenses/
Text Domain: wordpress-elasticsearch
Domain Path: /languages
 */

// exit.
defined( 'ABSPATH' ) || exit;


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/autoloader-class.php';
\PurpleElastic\Inc\Autoloader::run();

/**
 * Adds the debug function
 *
 * @param mixed $var The value to be printed.
 */
function debug( $var ) {
	error_log( print_r( $var, true ) );
}

$init_connection = new \Elastic\Inc\Init_Connection();

wp_enqueue_script(
	'purple-gutenbergid-script',
	plugins_url( 'inc/purpleId.js', __FILE__ ),
	array(),
	filemtime( plugin_dir_path( __FILE__ ) . 'inc/purpleId.js' ),
	true
);
