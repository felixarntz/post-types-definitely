<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

use WPDLib\Components\Manager as ComponentManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\General' ) ) {
	/**
	 * This class register post types and taxonomies
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
		 * This will hook functions into the 'init' action.
		 *
		 * @since 0.5.0
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_post_types' ), 20 );
			add_action( 'init', array( $this, 'register_taxonomies' ), 30 );
			add_action( 'init', array( $this, 'register_connections' ), 40 );
		}

		public function register_post_types() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$post_type->register();
			}
		}

		public function register_taxonomies() {
			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy->register();
			}
		}

		public function register_connections() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$taxonomies = $post_type->get_children( 'WPPTD\Components\Taxonomy' );
				foreach ( $taxonomies as $taxonomy ) {
					$status = register_taxonomy_for_object_type( $taxonomy->slug, $post_type->slug );
				}
			}
		}

	}
}
