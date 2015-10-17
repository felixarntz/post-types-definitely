<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

use WPPTD\General as General;
use WPPTD\Admin as Admin;
use WPPTD\Components\PostType as PostType;
use WPPTD\Components\Metabox as Metabox;
use WPPTD\Components\Field as Field;
use WPPTD\Components\Taxonomy as Taxonomy;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Menu as Menu;
use LaL_WP_Plugin as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\App' ) ) {
	/**
	 * This class initializes the plugin.
	 *
	 * It also triggers the action and filter to hook into and contains all API functions of the plugin.
	 *
	 * @since 0.5.0
	 */
	class App extends Plugin {

		/**
		 * @since 0.5.0
		 * @var array Holds the plugin data.
		 */
		protected static $_args = array();

		/**
		 * @since 0.5.0
		 * @var array helper variable to temporarily hold taxonomies and their post type names
		 */
		private $taxonomies_temp = array();

		/**
		 * Class constructor.
		 *
		 * @internal
		 * @since 0.5.0
		 */
		protected function __construct( $args ) {
			parent::__construct( $args );
		}

		/**
		 * The run() method.
		 *
		 * This will initialize the plugin on the 'after_setup_theme' action.
		 *
		 * @internal
		 * @since 0.5.0
		 */
		protected function run() {
			General::instance();
			if ( is_admin() ) {
				Admin::instance();
			}

			// use after_setup_theme action so it is initialized as soon as possible, but also so that both plugins and themes can use the action
			add_action( 'after_setup_theme', array( $this, 'init' ), 5 );

			add_filter( 'wpdlib_menu_validated', array( $this, 'menu_validated' ), 10, 2 );
			add_filter( 'wpptd_post_type_validated', array( $this, 'post_type_validated' ), 10, 2 );
			add_filter( 'wpptd_metabox_validated', array( $this, 'metabox_validated' ), 10, 2 );
		}

		/**
		 * Sets the current scope.
		 *
		 * The scope is an internal identifier. When adding a component, it will be added to the currently active scope.
		 * Therefore every plugin or theme should define its own unique scope to prevent conflicts.
		 *
		 * @since 0.5.0
		 * @param string $scope the current scope to set
		 */
		public function set_scope( $scope ) {
			ComponentManager::set_scope( $scope );
		}

		/**
		 * Adds a toplevel component.
		 *
		 * This function should be utilized when using the plugin manually.
		 * Every component has an `add()` method to add subcomponents to it, however if you want to add toplevel components, use this function.
		 *
		 * @since 0.5.0
		 * @param WPDLib\Component $component the component object to add
		 * @return WPDLib\Component|WP_Error either the component added or a WP_Error object if an error occurred
		 */
		public function add( $component ) {
			return ComponentManager::add( $component );
		}

		/**
		 * Takes an array of hierarchically nested components and adds them.
		 *
		 * This function is the general function to add an array of components.
		 * You should call it from your plugin or theme within the 'wpptd' action.
		 *
		 * @since 0.5.0
		 * @param array $components the components to add
		 * @param string $scope the scope to add the components to
		 */
		public function add_components( $components, $scope = '' ) {
			$this->set_scope( $scope );

			if ( is_array( $components ) ) {
				$this->add_menus( $components );
			}
		}

		/**
		 * Initializes the plugin framework.
		 *
		 * This function adds all components to the plugin. It is executed on the 'after_setup_theme' hook with priority 5.
		 * The action 'wpptd' should be used to add all the components.
		 *
		 * @internal
		 * @see WPPTD\App::add_components()
		 * @see WPPTD\App::add()
		 * @since 0.5.0
		 */
		public function init() {
			if ( ! did_action( 'wpptd' ) ) {
				ComponentManager::register_hierarchy( apply_filters( 'wpptd_class_hierarchy', array(
					'WPDLib\Components\Menu'		=> array(
						'WPPTD\Components\PostType'		=> array(
							'WPPTD\Components\Metabox'		=> array(
								'WPPTD\Components\Field'		=> array(),
							),
							'WPPTD\Components\Taxonomy'		=> array(),
						),
					),
				) ) );

				do_action( 'wpptd', $this );

				$this->taxonomies_temp = array();
			} else {
				self::doing_it_wrong( __METHOD__, __( 'This function should never be called manually.', 'post-types-definitely' ), '0.5.0' );
			}
		}

		/**
		 * Callback function run after a menu has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the menu arguments
		 * @param WPDLib\Menu $menu the current menu object
		 * @return array the adjusted menu arguments
		 */
		public function menu_validated( $args, $menu ) {
			if ( isset( $args['post_types'] ) ) {
				unset( $args['post_types'] );
			}
			return $args;
		}

		/**
		 * Callback function run after a post type has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the post type arguments
		 * @param WPPTD\PostType $menu the current post type object
		 * @return array the adjusted post type arguments
		 */
		public function post_type_validated( $args, $post_type ) {
			if ( isset( $args['metaboxes'] ) ) {
				unset( $args['metaboxes'] );
			}
			if ( isset( $args['taxonomies'] ) ) {
				unset( $args['taxonomies'] );
			}
			return $args;
		}

		/**
		 * Callback function run after a metabox has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the metabox arguments
		 * @param WPPTD\Metabox $menu the current metabox object
		 * @return array the adjusted metabox arguments
		 */
		public function metabox_validated( $args, $metabox ) {
			if ( isset( $args['fields'] ) ) {
				unset( $args['fields'] );
			}
			return $args;
		}

		/**
		 * Adds menus and their subcomponents.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $menus the menus to add as $menu_slug => $menu_args
		 */
		protected function add_menus( $menus ) {
			foreach ( $menus as $menu_slug => $menu_args ) {
				$menu = $this->add( new Menu( $menu_slug, $menu_args ) );
				if ( is_wp_error( $menu ) ) {
					self::doing_it_wrong( __METHOD__, $menu->get_error_message(), '0.5.0' );
				} elseif ( isset( $menu_args['post_types'] ) && is_array( $menu_args['post_types'] ) ) {
					$this->add_post_types( $menu_args['post_types'], $menu );
				}
			}
		}

		/**
		 * Adds post types and their subcomponents.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $post_types the post types to add as $post_type_slug => $post_type_args
		 * @param WPDLib\Menu $menu the menu to add the post types to
		 */
		protected function add_post_types( $post_types, $menu ) {
			foreach ( $post_types as $post_type_slug => $post_type_args ) {
				$post_type = $menu->add( new PostType( $post_type_slug, $post_type_args ) );
				if ( is_wp_error( $post_type ) ) {
					self::doing_it_wrong( __METHOD__, $post_type->get_error_message(), '0.5.0' );
				} else {
					if ( isset( $post_type_args['taxonomies'] ) && is_array( $post_type_args['taxonomies'] ) ) {
						$this->add_taxonomies( $post_type_args['taxonomies'], $post_type );
					}
					if ( isset( $post_type_args['metaboxes'] ) && is_array( $post_type_args['metaboxes'] ) ) {
						$this->add_metaboxes( $post_type_args['metaboxes'], $post_type );
					}
				}
			}
		}

		/**
		 * Adds taxonomies and their subcomponents.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $taxonomies the taxonomies to add as $taxonomy_slug => $taxonomy_args
		 * @param WPPTD\PostType $post_type the post type to add the taxonomies to
		 */
		protected function add_taxonomies( $taxonomies, $post_type ) {
			foreach ( $taxonomies as $taxonomy_slug => $taxonomy_args ) {
				if ( is_array( $taxonomy_args ) ) {
					$taxonomy = $post_type->add( new Taxonomy( $taxonomy_slug, $taxonomy_args ) );
					if ( is_wp_error( $taxonomy ) ) {
						self::doing_it_wrong( __METHOD__, $taxonomy->get_error_message(), '0.5.0' );
					} else {
						if ( isset( $this->taxonomies_temp[ $taxonomy_slug ] ) && is_array( $this->taxonomies_temp[ $taxonomy_slug ] ) ) {
							foreach ( $this->taxonomies_temp[ $taxonomy_slug ] as $_post_type ) {
								$_post_type->add( $taxonomy );
							}
						}
						$this->taxonomies_temp[ $taxonomy_slug ] = $taxonomy;
					}
				} else {
					if ( isset( $this->taxonomies_temp[ $taxonomy_slug ] ) && is_object( $this->taxonomies_temp[ $taxonomy_slug ] ) ) {
						$taxonomy = $post_type->add( $this->taxonomies_temp[ $taxonomy_slug ] );
					} else {
						if ( ! isset( $this->taxonomies_temp[ $taxonomy_slug ] ) ) {
							$this->taxonomies_temp[ $taxonomy_slug ] = array();
						}
						$this->taxonomies_temp[ $taxonomy_slug ][] = $post_type;
					}
				}
			}
		}

		/**
		 * Adds metaboxes and their subcomponents.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $metaboxes the metaboxes to add as $metabox_slug => $metabox_args
		 * @param WPPTD\PostType $post_type the post type to add the metaboxes to
		 */
		protected function add_metaboxes( $metaboxes, $post_type ) {
			foreach ( $metaboxes as $metabox_slug => $metabox_args ) {
				$metabox = $post_type->add( new Metabox( $metabox_slug, $metabox_args ) );
				if ( is_wp_error( $metabox ) ) {
					self::doing_it_wrong( __METHOD__, $metabox->get_error_message(), '0.5.0' );
				} elseif ( isset( $metabox_args['fields'] ) && is_array( $metabox_args['fields'] ) ) {
					$this->add_fields( $metabox_args['fields'], $metabox );
				}
			}
		}

		/**
		 * Adds fields.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $fields the fields to add as $field_slug => $field_args
		 * @param WPPTD\Metabox $metabox the metabox to add the fields to
		 */
		protected function add_fields( $fields, $metabox ) {
			foreach ( $fields as $field_slug => $field_args ) {
				$field = $metabox->add( new Field( $field_slug, $field_args ) );
				if ( is_wp_error( $field ) ) {
					self::doing_it_wrong( __METHOD__, $field->get_error_message(), '0.5.0' );
				}
			}
		}
	}
}
