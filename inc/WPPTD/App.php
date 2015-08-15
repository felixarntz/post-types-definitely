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
		 * @var boolean Holds the status whether the initialization function has been called yet.
		 */
		private $initialization_triggered = false;

		/**
		 * @since 0.5.0
		 * @var boolean Holds the status whether the app has been initialized yet.
		 */
		private $initialized = false;

		/**
		 * @since 0.5.0
		 * @var array Holds the plugin data.
		 */
		protected static $_args = array();

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		protected function __construct( $args ) {
			parent::__construct( $args );
		}

		/**
		 * The run() method.
		 *
		 * This will initialize the plugin on the 'after_setup_theme' action.
		 * If we are currently in the WordPress admin area, the WPPTD\Admin class will be instantiated.
		 *
		 * @since 0.5.0
		 */
		protected function run() {
			General::instance();
			if ( is_admin() ) {
				Admin::instance();
			}

			// use after_setup_theme action so it is initialized as soon as possible, but also so that both plugins and themes can use the action
			add_action( 'after_setup_theme', array( $this, 'init' ), 1 );
		}

		public function set_scope( $scope ) {
			ComponentManager::set_scope( $scope );
		}

		public function add( $component ) {
			return ComponentManager::add( $component );
		}

		public function add_components( $components, $scope = '' ) {
			$this->set_scope( $scope );

			if ( is_array( $components ) ) {
				foreach ( $components as $menu_slug => $menu_args ) {
					$menu = ComponentManager::add( new Menu( $menu_slug, $menu_args ) );
					if ( is_wp_error( $menu ) ) {
						self::doing_it_wrong( __METHOD__, $menu->get_error_message(), '0.5.0' );
					} else {
						if ( isset( $menu_args['post_types'] ) && is_array( $menu_args['post_types'] ) ) {
							foreach ( $menu_args['post_types'] as $post_type_slug => $post_type_args ) {
								$post_type = $menu->add( new PostType( $post_type_slug, $post_type_args ) );
								if ( is_wp_error( $post_type ) ) {
									self::doing_it_wrong( __METHOD__, $post_type->get_error_message(), '0.5.0' );
								} elseif ( isset( $post_type_args['metaboxes'] ) && is_array( $post_type_args['metaboxes'] ) ) {
									foreach ( $post_type_args['metaboxes'] as $metabox_slug => $metabox_args ) {
										$metabox = $post_type->add( new Metabox( $metabox_slug, $metabox_args ) );
										if ( is_wp_error( $metabox ) ) {
											self::doing_it_wrong( __METHOD__, $metabox->get_error_message(), '0.5.0' );
										} elseif ( isset( $metabox_args['fields'] ) && is_array( $metabox_args['fields'] ) ) {
											foreach ( $metabox_args['fields'] as $field_slug => $field_args ) {
												$field = $metabox->add( new Field( $field_slug, $field_args ) );
												if ( is_wp_error( $field ) ) {
													self::doing_it_wrong( __METHOD__, $field->get_error_message(), '0.5.0' );
												}
											}
										}
									}
								}
							}
						}
						if ( isset( $menu_args['taxonomies'] ) && is_array( $menu_args['taxonomies'] ) ) {
							foreach ( $menu_args['taxonomies'] as $taxonomy_slug => $taxonomy_args ) {
								$taxonomy = $menu->add( new Taxonomy( $taxonomy_slug, $taxonomy_args ) );
								if ( is_wp_error( $taxonomy ) ) {
									self::doing_it_wrong( __METHOD__, $taxonomy->get_error_message(), '0.5.0' );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Initializes the plugin framework.
		 *
		 * This function adds all components to the plugin. It is executed on the 'after_setup_theme' hook with priority 1.
		 * The action 'wpptd' should be used to add all the components.
		 *
		 * @internal
		 * @see WPPTD\App::add_components()
		 * @see WPPTD\App::add()
		 * @since 0.5.0
		 */
		public function init() {
			if ( ! $this->initialization_triggered ) {
				$this->initialization_triggered = true;

				ComponentManager::register_hierarchy( array(
					'WPDLib\Components\Menu'		=> array(
						'WPPTD\Components\PostType'		=> array(
							'WPPTD\Components\Metabox'		=> array(
								'WPPTD\Components\Field'		=> array(),
							),
						),
						'WPPTD\Components\Taxonomy'		=> array(),
					),
				) );

				do_action( 'wpptd', $this );

				$this->initialized = true;
			} else {
				self::doing_it_wrong( __METHOD__, __( 'This function should never be called manually.', 'wpptd' ), '0.5.0' );
			}
		}
	}
}
