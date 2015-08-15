<?php
/*
Plugin Name: Post Types Definitely
Plugin URI: http://wordpress.org/plugins/post-types-definitely/
Description: This framework plugin makes adding post types with taxonomies and meta to WordPress very simple, yet flexible. It all works using a single action and an array.
Version: 0.5.0
Author: Felix Arntz
Author URI: http://leaves-and-love.net
License: GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wpptd
Domain Path: /languages/
Tags: wordpress, plugin, framework, library, developer, post-types, taxonomies, meta, admin, backend, ui
*/
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\App' ) && file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

\LaL_WP_Plugin_Loader::load_plugin( array(
	'slug'				=> 'post-types-definitely',
	'name'				=> 'Post Types Definitely',
	'version'			=> '0.5.0',
	'main_file'			=> __FILE__,
	'namespace'			=> 'WPPTD',
	'textdomain'		=> 'wpptd',
), array(
	'phpversion'		=> '5.3.0',
	'wpversion'			=> '4.0',
) );
