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

		public static function parse_meta_value( $meta_value, $field, $single = null, $formatted = false ) {
			$_meta_value = $meta_value;
			$meta_value = null;

			$type_hint = $field->validate_meta_value( null, true );
			if ( is_array( $type_hint ) ) {
				$meta_value = $field->_field->parse( $_meta_value, $formatted );
				if ( $single !== null && $single ) {
					if ( count( $meta_value > 0 ) ) {
						$meta_value = $meta_value[0];
					} else {
						$meta_value = null;
					}
				}
			} else {
				if ( count( $_meta_value ) > 0 ) {
					$meta_value = $field->_field->parse( $_meta_value[0], $formatted );
				} else {
					$meta_value = $field->_field->parse( $field->default, $formatted );
				}
				if ( $single !== null && ! $single ) {
					$meta_value = array( $meta_value );
				}
			}

			return $meta_value;
		}

		public static function get_all_meta_values( $meta_key ) {
			global $wpdb;

			$query = "SELECT DISTINCT meta_value FROM " . $wpdb->postmeta . " AS m JOIN " . $wpdb->posts . " as p ON ( p.ID = m.post_id )";
			$query .= " WHERE m.meta_key = %s AND m.meta_value != '' AND p.post_type = %s ORDER BY m.meta_value ASC;";

			return $wpdb->get_col( $wpdb->prepare( $query, $meta_key, $this->slug ) );
		}

		public static function validate_post_type_and_taxonomy_titles( $args, $slug ) {
			if ( empty( $args['title'] ) && isset( $args['label'] ) ) {
				$args['title'] = $args['label'];
				unset( $args['label'] );
			}
			if ( empty( $args['title'] ) ) {
				if ( empty( $args['singular_title'] ) ) {
					$args['singular_title'] = ucwords( str_replace( '_', '', $slug ) );
				}
				$args['title'] = $args['singular_title'] . 's';
			} elseif ( empty( $args['singular_title'] ) ) {
				$args['singular_title'] = $args['title'];
			}

			return $args;
		}

		public static function render_help( $screen, $data ) {
			foreach ( $data['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $data['sidebar'] ) ) {
				$screen->set_help_sidebar( $data['sidebar'] );
			}
		}

		public static function validate_help_args( $args, $key ) {
			if( ! is_array( $args[ $key ] ) ) {
				$args[ $key ] = array();
			}
			if ( ! isset( $args[ $key ]['tabs'] ) || ! is_array( $args[ $key ]['tabs'] ) ) {
				$args[ $key ]['tabs'] = array();
			}
			if ( ! isset( $args[ $key ]['sidebar'] ) ) {
				$args[ $key ]['sidebar'] = '';
			}
			foreach ( $args[ $key ]['tabs'] as &$tab ) {
				$tab = wp_parse_args( $tab, array(
					'title'			=> __( 'Help tab title', 'post-types-definitely' ),
					'content'		=> '',
					'callback'		=> false,
				) );
			}

			return $args;
		}

	}
}
