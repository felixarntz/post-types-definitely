<?php
/*
Plugin Name: Post Types Definitely
Plugin URI: https://wordpress.org/plugins/post-types-definitely/
Description: This framework plugin makes adding post types with taxonomies and meta to WordPress very simple, yet flexible.
Version: 0.6.2
Author: Felix Arntz
Author URI: http://leaves-and-love.net
License: GNU General Public License v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: post-types-definitely
Tags: wordpress, plugin, definitely, framework, library, developer, admin, backend, structured data, ui, api, cms, post-types, posts, custom-post-type, list table, post filters, row actions, bulk actions, taxonomies, terms, meta, post meta, postmeta, term meta, termmeta, meta boxes, metaboxes, repeatable, fields, custom fields, help tabs
*/
/**
 * @package WPPTD
 * @version 0.6.2
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( version_compare( phpversion(), '5.3.0' ) >= 0 && ! class_exists( 'WPPTD\App' ) ) {
	if ( file_exists( dirname( __FILE__ ) . '/post-types-definitely/vendor/autoload.php' ) ) {
		require_once dirname( __FILE__ ) . '/post-types-definitely/vendor/autoload.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
	}
} elseif ( ! class_exists( 'LaL_WP_Plugin_Loader' ) ) {
	if ( file_exists( dirname( __FILE__ ) . '/post-types-definitely/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php' ) ) {
		require_once dirname( __FILE__ ) . '/post-types-definitely/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php';
	}
}

LaL_WP_Plugin_Loader::load_plugin( array(
	'slug'					=> 'post-types-definitely',
	'name'					=> 'Post Types Definitely',
	'version'				=> '0.6.2',
	'main_file'				=> __FILE__,
	'namespace'				=> 'WPPTD',
	'textdomain'			=> 'post-types-definitely',
	'use_language_packs'	=> true,
	'is_library'			=> true,
), array(
	'phpversion'			=> '5.3.0',
	'wpversion'				=> '4.0',
) );
