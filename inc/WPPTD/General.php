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
			$taxonomies = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy->register();
			}
		}

		public function register_connections() {
			$relationships = array();

			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			$post_type_keys = array();
			foreach ( $post_types as $key => $post_type ) {
				$post_type_keys[ $post_type->slug ] = $key;
				$taxonomy_slugs = $post_type->taxonomy_slugs;
				foreach ( $taxonomy_slugs as $taxonomy_slug ) {
					if ( ! isset( $relationships[ $taxonomy_slug ] ) ) {
						$relationships[ $taxonomy_slug ] = array();
					}
					if ( ! in_array( $post_type->slug, $relationships[ $taxonomy_slug ] ) ) {
						$relationships[ $taxonomy_slug ][] = $post_type->slug;
					}
				}
				$post_type->taxonomy_slugs = array();
			}

			$taxonomies = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\Taxonomy' );
			$taxonomy_keys = array();
			foreach ( $taxonomies as $key => $taxonomy ) {
				$taxonomy_keys[ $taxonomy->slug ] = $key;
				$post_type_slugs = $taxonomy->post_type_slugs;
				if ( count( $post_type_slugs ) > 0 ) {
					if ( ! isset( $relationships[ $taxonomy->slug ] ) ) {
						$relationships[ $taxonomy->slug ] = array();
					}
					foreach ( $post_type_slugs as $post_type_slug ) {
						if ( ! in_array( $post_type_slug, $relationships[ $taxonomy->slug ] ) ) {
							$relationships[ $taxonomy->slug ][] = $post_type_slug;
						}
					}
				}
				$taxonomy->post_type_slugs = array();
			}

			foreach ( $relationships as $taxonomy_slug => $post_type_slugs ) {
				foreach ( $post_type_slugs as $post_type_slug ) {
					$status = register_taxonomy_for_object_type( $taxonomy_slug, $post_type_slug );
					if ( $status ) {
						$post_types[ $post_type_keys[ $post_type_slug ] ]->taxonomy_slugs[] = $taxonomy_slug;
						$taxonomies[ $taxonomy_keys[ $taxonomy_slug ] ]->post_type_slugs[] = $post_type_slug;
					}
				}
			}
		}

	}
}
