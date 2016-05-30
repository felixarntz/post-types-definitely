<?php
/**
 * WPPTD\App class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPPTD;

use WPPTD\General as General;
use WPPTD\Admin as Admin;
use WPPTD\Components\PostType as PostType;
use WPPTD\Components\Metabox as Metabox;
use WPPTD\Components\Field as Field;
use WPPTD\Components\Taxonomy as Taxonomy;
use WPPTD\Components\TermMetabox as TermMetabox;
use WPPTD\Components\TermField as TermField;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Menu as Menu;
use WPDLib\FieldTypes\Manager as FieldManager;
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
		protected $taxonomies_temp = array();

		/**
		 * Class constructor.
		 *
		 * This is protected on purpose since it is called by the parent class' singleton.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args array of class arguments (passed by the plugin utility class)
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
			FieldManager::init();

			General::instance();
			if ( is_admin() ) {
				Admin::instance();
			}

			// use after_setup_theme action so it is initialized as soon as possible, but also so that both plugins and themes can use the action
			add_action( 'after_setup_theme', array( $this, 'init' ), 5 );

			add_filter( 'wpdlib_menu_validated', array( $this, 'menu_validated' ), 10, 2 );
			add_filter( 'wpptd_post_type_validated', array( $this, 'post_type_validated' ), 10, 2 );
			add_filter( 'wpptd_post_metabox_validated', array( $this, 'metabox_validated' ), 10, 2 );
			add_filter( 'wpptd_taxonomy_validated', array( $this, 'taxonomy_validated' ), 10, 2 );
			add_filter( 'wpptd_term_metabox_validated', array( $this, 'term_metabox_validated' ), 10, 2 );
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
		 * @param WPDLib\Components\Base $component the component object to add
		 * @return WPDLib\Components\Base|WP_Error either the component added or a WP_Error object if an error occurred
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

				/**
				 * This filter can be used to alter the component hierarchy of the plugin.
				 * It must only be used to add more components to the hierarchy, never to change or remove something existing.
				 *
				 * @since 0.5.0
				 * @param array the nested array of component class names
				 */
				ComponentManager::register_hierarchy( apply_filters( 'wpptd_class_hierarchy', $hierarchy ) );

				/**
				 * The main API action of the plugin.
				 *
				 * Every developer must hook into this action to register components.
				 *
				 * @since 0.5.0
				 * @param WPPTD\App instance of the main plugin class
				 */
				do_action( 'wpptd', $this );

				$this->taxonomies_temp = array();
			} else {
				self::doing_it_wrong( __METHOD__, __( 'This function should never be called manually.', 'post-types-definitely' ), '0.5.0' );
			}
		}

		/**
		 * Callback function to run after a menu has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the menu arguments
		 * @param WPDLib\Components\Menu $menu the current menu object
		 * @return array the adjusted menu arguments
		 */
		public function menu_validated( $args, $menu ) {
			if ( isset( $args['post_types'] ) ) {
				unset( $args['post_types'] );
			}
			return $args;
		}

		/**
		 * Callback function to run after a post type has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the post type arguments
		 * @param WPPTD\Components\PostType $post_type the current post type object
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
		 * Callback function to run after a metabox has been validated.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args the metabox arguments
		 * @param WPPTD\Components\Metabox $metabox the current metabox object
		 * @return array the adjusted metabox arguments
		 */
		public function metabox_validated( $args, $metabox ) {
			if ( isset( $args['fields'] ) ) {
				unset( $args['fields'] );
			}
			return $args;
		}

		/**
		 * Callback function to run after a taxonomy has been validated.
		 *
		 * @internal
		 * @since 0.6.0
		 * @param array $args the taxonomy arguments
		 * @param WPPTD\Components\Taxonomy $taxonomy the current taxonomy object
		 * @return array the adjusted taxonomy arguments
		 */
		public function taxonomy_validated( $args, $taxonomy ) {
			if ( isset( $args['metaboxes'] ) ) {
				unset( $args['metaboxes'] );
			}
			return $args;
		}

		/**
		 * Callback function to run after a term metabox has been validated.
		 *
		 * @internal
		 * @since 0.6.0
		 * @param array $args the term metabox arguments
		 * @param WPPTD\Components\TermMetabox $term_metabox the current term metabox object
		 * @return array the adjusted term metabox arguments
		 */
		public function term_metabox_validated( $args, $term_metabox ) {
			return $this->metabox_validated( $args, $term_metabox );
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
		 * @param WPDLib\Components\Menu $menu the menu to add the post types to
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
		 * Adds taxonomies.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $taxonomies the taxonomies to add as $taxonomy_slug => $taxonomy_args
		 * @param WPPTD\Components\PostType $post_type the post type to add the taxonomies to
		 */
		protected function add_taxonomies( $taxonomies, $post_type ) {
			foreach ( $taxonomies as $taxonomy_slug => $taxonomy_args ) {
				if ( is_array( $taxonomy_args ) ) {
					$taxonomy = $post_type->add( new Taxonomy( $taxonomy_slug, $taxonomy_args ) );
					if ( is_wp_error( $taxonomy ) ) {
						self::doing_it_wrong( __METHOD__, $taxonomy->get_error_message(), '0.5.0' );
					} else {
						if ( wpptd_supports_termmeta() ) {
							if ( isset( $taxonomy_args['metaboxes'] ) && is_array( $taxonomy_args['metaboxes'] ) ) {
								$this->add_term_metaboxes( $taxonomy_args['metaboxes'], $taxonomy );
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

		/**
		 * Adds metaboxes and their subcomponents.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $metaboxes the metaboxes to add as $metabox_slug => $metabox_args
		 * @param WPPTD\Components\PostType $post_type the post type to add the metaboxes to
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
		 * @param WPPTD\Components\Metabox $metabox the metabox to add the fields to
		 */
		protected function add_fields( $fields, $metabox ) {
			foreach ( $fields as $field_slug => $field_args ) {
				$field = $metabox->add( new Field( $field_slug, $field_args ) );
				if ( is_wp_error( $field ) ) {
					self::doing_it_wrong( __METHOD__, $field->get_error_message(), '0.5.0' );
				}
			}
		}

		/**
		 * Adds term metaboxes and their subcomponents.
		 *
		 * @internal
		 * @since 0.6.0
		 * @param array $term_metaboxes the term metaboxes to add as $term_metabox_slug => $term_metabox_args
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy to add the term metaboxes to
		 */
		protected function add_term_metaboxes( $term_metaboxes, $taxonomy ) {
			foreach ( $term_metaboxes as $term_metabox_slug => $term_metabox_args ) {
				$term_metabox = $taxonomy->add( new TermMetabox( $term_metabox_slug, $term_metabox_args ) );
				if ( is_wp_error( $term_metabox ) ) {
					self::doing_it_wrong( __METHOD__, $term_metabox->get_error_message(), '0.6.0' );
				} else {
					if ( isset( $term_metabox_args['fields'] ) && is_array( $term_metabox_args['fields'] ) ) {
						$this->add_term_fields( $term_metabox_args['fields'], $term_metabox );
					}
				}
			}
		}

		/**
		 * Adds term fields.
		 *
		 * @internal
		 * @since 0.6.0
		 * @param array $term_fields the term fields to add as $term_field_slug => $term_field_args
		 * @param WPPTD\Components\TermMetabox $term_metabox the term metabox to add the term fields to
		 */
		protected function add_term_fields( $term_fields, $term_metabox ) {
			foreach ( $term_fields as $term_field_slug => $term_field_args ) {
				$term_field = $term_metabox->add( new TermField( $term_field_slug, $term_field_args ) );
				if ( is_wp_error( $term_field ) ) {
					self::doing_it_wrong( __METHOD__, $term_field->get_error_message(), '0.6.0' );
				}
			}
		}

		/**
		 * Adds a link to the framework guide to the plugins table.
		 *
		 * @internal
		 * @since 0.6.1
		 * @param array $links the original links
		 * @return array the modified links
		 */
		public static function filter_plugin_links( $links = array() ) {
			$custom_links = array(
				'<a href="' . 'https://github.com/felixarntz/post-types-definitely/wiki' . '">' . __( 'Guide', 'post-types-definitely' ) . '</a>',
			);

			return array_merge( $custom_links, $links );
		}

		/**
		 * Adds a link to the framework guide to the network plugins table.
		 *
		 * @internal
		 * @since 0.6.1
		 * @param array $links the original links
		 * @return array the modified links
		 */
		public static function filter_network_plugin_links( $links = array() ) {
			return self::filter_plugin_links( $links );
		}

		/**
		 * Renders a plugin information message.
		 *
		 * @internal
		 * @since 0.6.1
		 * @param string $status either 'activated' or 'active'
		 * @param string $context either 'site' or 'network'
		 */
		public static function render_status_message( $status, $context = 'site' ) {
			?>
			<p>
				<?php if ( 'activated' === $status ) : ?>
					<?php printf( __( 'You have just activated %s.', 'post-types-definitely' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php elseif ( 'network' === $context ) : ?>
					<?php printf( __( 'You are running the plugin %s on your network.', 'post-types-definitely' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php else : ?>
					<?php printf( __( 'You are running the plugin %s on your site.', 'post-types-definitely' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php endif; ?>
				<?php _e( 'This plugin is a framework that developers can leverage to quickly add extended post types and taxonomies with specific meta boxes and fields.', 'post-types-definitely' ); ?>
			</p>
			<p>
				<?php printf( __( 'For a guide on how to use the framework please read the <a href="%s">Wiki</a>.', 'post-types-definitely' ), 'https://github.com/felixarntz/post-types-definitely/wiki' ); ?>
			</p>
			<?php
		}

		/**
		 * Renders a network plugin information message.
		 *
		 * @internal
		 * @since 0.6.1
		 * @param string $status either 'activated' or 'active'
		 * @param string $context either 'site' or 'network'
		 */
		public static function render_network_status_message( $status, $context = 'network' ) {
			self::render_status_message( $status, $context );
		}
	}
}
