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

		private $taxonomies_temp = array();

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
			add_action( 'after_setup_theme', array( $this, 'init' ), 5 );

			add_filter( 'wpdlib_menu_validated', array( $this, 'menu_validated' ), 10, 2 );
			add_filter( 'wpptd_post_type_validated', array( $this, 'post_type_validated' ), 10, 2 );
			add_filter( 'wpptd_metabox_validated', array( $this, 'metabox_validated' ), 10, 2 );
			add_filter( 'wpptd_taxonomy_validated', array( $this, 'taxonomy_validated' ), 10, 2 );
			add_filter( 'wpptd_term_metabox_validated', array( $this, 'term_metabox_validated' ), 10, 2 );
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
								} else {
									if ( isset( $post_type_args['metaboxes'] ) && is_array( $post_type_args['metaboxes'] ) ) {
										foreach ( $post_type_args['metaboxes'] as $metabox_slug => $metabox_args ) {
											$metabox = $post_type->add( new Metabox( $metabox_slug, $metabox_args ) );
											if ( is_wp_error( $metabox ) ) {
												self::doing_it_wrong( __METHOD__, $metabox->get_error_message(), '0.5.0' );
											} else {
												if ( isset( $metabox_args['fields'] ) && is_array( $metabox_args['fields'] ) ) {
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
									if ( isset( $post_type_args['taxonomies'] ) && is_array( $post_type_args['taxonomies'] ) ) {
										foreach ( $post_type_args['taxonomies'] as $taxonomy_slug => $taxonomy_args ) {
											if ( is_array( $taxonomy_args ) ) {
												$taxonomy = $post_type->add( new Taxonomy( $taxonomy_slug, $taxonomy_args ) );
												if ( is_wp_error( $taxonomy ) ) {
													self::doing_it_wrong( __METHOD__, $taxonomy->get_error_message(), '0.5.0' );
												} else {
													if ( wpptd_supports_termmeta() ) {
														//TODO: check version numbers here
														if ( isset( $taxonomy_args['metaboxes'] ) && is_array( $taxonomy_args['metaboxes'] ) ) {
															foreach ( $taxonomy_args['metaboxes'] as $metabox_slug => $metabox_args ) {
																$metabox = $taxonomy->add( new TermMetabox( $metabox_slug, $metabox_args ) );
																if ( is_wp_error( $metabox ) ) {
																	self::doing_it_wrong( __METHOD__, $metabox->get_error_message(), '0.5.0' );
																} else {
																	if ( isset( $metabox_args['fields'] ) && is_array( $metabox_args['fields'] ) ) {
																		foreach ( $metabox_args['fields'] as $field_slug => $field_args ) {
																			$field = $metabox->add( new TermField( $field_slug, $field_args ) );
																			if ( is_wp_error( $field ) ) {
																				self::doing_it_wrong( __METHOD__, $field->get_error_message(), '0.5.0' );
																			}
																		}
																	}
																}
															}
														}
													}
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
				$hierarchy = array(
					'WPDLib\Components\Menu'		=> array(
						'WPPTD\Components\PostType'		=> array(
							'WPPTD\Components\Metabox'		=> array(
								'WPPTD\Components\Field'		=> array(),
							),
							'WPPTD\Components\Taxonomy'		=> array(),
						),
					),
				);

				if ( wpptd_supports_termmeta() ) {
					$hierarchy['WPDLib\Components\Menu']['WPPTD\Components\PostType']['WPPTD\Components\Taxonomy'] = array(
						'WPPTD\Components\TermMetabox'	=> array(
							'WPPTD\Components\TermField'	=> array(),
						),
					);
				}

				ComponentManager::register_hierarchy( apply_filters( 'wpptd_class_hierarchy', $hierarchy ) );

				do_action( 'wpptd', $this );

				$this->taxonomies_temp = array();
			} else {
				self::doing_it_wrong( __METHOD__, __( 'This function should never be called manually.', 'post-types-definitely' ), '0.5.0' );
			}
		}

		public function menu_validated( $args, $menu ) {
			if ( isset( $args['post_types'] ) ) {
				unset( $args['post_types'] );
			}
			return $args;
		}

		public function post_type_validated( $args, $post_type ) {
			if ( isset( $args['metaboxes'] ) ) {
				unset( $args['metaboxes'] );
			}
			if ( isset( $args['taxonomies'] ) ) {
				unset( $args['taxonomies'] );
			}
			return $args;
		}

		public function metabox_validated( $args, $metabox ) {
			if ( isset( $args['fields'] ) ) {
				unset( $args['fields'] );
			}
			return $args;
		}

		public function taxonomy_validated( $args, $taxonomy ) {
			if ( isset( $args['metaboxes'] ) ) {
				unset( $args['metaboxes'] );
			}
			return $args;
		}

		public function term_metabox_validated( $args, $term_metabox ) {
			return $this->metabox_validated( $args, $term_metabox );
		}
	}
}
