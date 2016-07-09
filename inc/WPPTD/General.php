<?php
/**
 * WPPTD\General class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPPTD;

use WPDLib\Components\Manager as ComponentManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\General' ) ) {
	/**
	 * This class register post types and taxonomies and connects them appropriately.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class General {

		/**
		 * @since 0.5.0
		 * @var WPPTD\General|null Holds the instance of this class.
		 */
		private static $instance = null;

		/**
		 * Gets the instance of this class. If it does not exist, it will be created.
		 *
		 * @since 0.5.0
		 * @return WPPTD\General
		 */
		public static function instance() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		private function __construct() {
			add_action( 'after_setup_theme', array( $this, 'add_hooks' ) );
		}

		/**
		 * Hooks in all the necessary actions.
		 *
		 * This function should be executed after the plugin has been initialized.
		 *
		 * @since 0.5.0
		 */
		public function add_hooks() {
			add_action( 'init', array( $this, 'register_post_types' ), 20 );
			add_action( 'init', array( $this, 'register_taxonomies' ), 30 );
			add_action( 'init', array( $this, 'register_connections' ), 40 );
			if ( version_compare( get_bloginfo( 'version' ), '4.6', '>=' ) ) {
				add_action( 'init', array( $this, 'register_meta' ), 50 );
			}
		}

		/**
		 * Registers all post types added with the plugin.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 */
		public function register_post_types() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$post_type->register();
			}
		}

		/**
		 * Registers all taxonomies added with the plugin.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 */
		public function register_taxonomies() {
			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy->register();
			}
		}

		/**
		 * Connects post types with their assigned taxonomies.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 */
		public function register_connections() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$taxonomies = $post_type->get_children( 'WPPTD\Components\Taxonomy' );
				foreach ( $taxonomies as $taxonomy ) {
					$status = register_taxonomy_for_object_type( $taxonomy->slug, $post_type->slug );
				}
			}
		}

		/**
		 * Registers meta for all post types and taxonomies.
		 *
		 * Registering metadata with details was introduced in WordPress 4.6. Therefore this
		 * method is not used in versions below that.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.6.5
		 */
		public function register_meta() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$post_type->register_meta();
			}

			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy->register_meta();
			}
		}

	}
}
