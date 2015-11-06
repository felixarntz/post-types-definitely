<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPPTD\Utility as Utility;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\Taxonomy' ) ) {
	/**
	 * Class for a taxonomy component.
	 *
	 * This denotes a taxonomy within WordPress.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Taxonomy extends Base {

		/**
		 * @since 0.5.0
		 * @var bool Stores whether this taxonomy has already been registered.
		 */
		protected $registered = false;

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param string $slug the taxonomy slug
		 * @param array $args array of taxonomy properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_taxonomy_validated';
		}

		/**
		 * Checks whether this taxonomy already exists in WordPress.
		 *
		 * @since 0.5.0
		 * @return bool true if the taxonomy exists, otherwise false
		 */
		public function is_already_added() {
			return taxonomy_exists( $this->slug );
		}

		/**
		 * Registers the taxonomy.
		 *
		 * If the taxonomy already exists, some of the arguments will be merged into the existing taxonomy object.
		 *
		 * @since 0.5.0
		 */
		public function register() {
			if ( $this->registered ) {
				return;
			}

			if ( ! $this->is_already_added() ) {
				$_taxonomy_args = $this->args;

				unset( $_taxonomy_args['title'] );
				unset( $_taxonomy_args['singular_title'] );
				unset( $_taxonomy_args['messages'] );
				unset( $_taxonomy_args['help'] );

				$_taxonomy_args['label'] = $this->args['title'];

				$taxonomy_args = array();
				foreach ( $_taxonomy_args as $key => $value ) {
					if ( null !== $value ) {
						$taxonomy_args[ $key ] = $value;
					}
				}

				register_taxonomy( $this->slug, null, $taxonomy_args );
			} else {
				// merge several properties into existing taxonomy
				global $wp_taxonomies;

				if ( $this->args['labels'] ) {
					// merge the slug as $name into the arguments (required for `get_taxonomy_labels()`)
					$wp_taxonomies[ $this->slug ]->labels = get_taxonomy_labels( (object) array_merge( $this->args, array( 'name' => $this->slug ) ) );
					$wp_taxonomies[ $this->slug ]->label = $wp_taxonomies[ $this->slug ]->labels->name;
				}
			}

			$this->registered = true;
		}

		/**
		 * Renders the help tabs and sidebar on the term editing screen of the taxonomy.
		 *
		 * @since 0.5.0
		 */
		public function render_help() {
			Utility::render_help( get_current_screen(), $this->args['help'] );
		}

		/**
		 * Renders the help tabs and sidebar on the terms list screen of the taxonomy.
		 *
		 * @since 0.5.0
		 */
		public function render_list_help() {
			Utility::render_help( get_current_screen(), $this->args['list_help'] );
		}

		/**
		 * Returns the custom term updated messages for this taxonomy.
		 *
		 * @since 0.5.0
		 * @return array the custom messages
		 */
		public function get_updated_messages() {
			return $this->args['messages'];
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $parent the parent component
		 * @return bool|WPDLib\Util\Error an error object if an error occurred during validation, true if it was validated, false if it did not need to be validated
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {

				if ( in_array( $this->slug, array( 'post_format', 'link_category', 'nav_menu' ) ) ) {
					return new UtilError( 'no_valid_taxonomy', sprintf( __( 'The taxonomy slug %s is forbidden since it would interfere with WordPress Core functionality.', 'post-types-definitely' ), $this->slug ), '', ComponentManager::get_scope() );
				}

				// show notice if slug contains dashes
				if ( strpos( $this->slug, '-' ) !== false ) {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The taxonomy slug %s contains dashes which is discouraged. It will still work for the most part, but we recommend to adjust the slug if possible.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
				}

				// generate titles if not provided
				$this->args = Utility::validate_post_type_and_taxonomy_titles( $this->args, $this->slug );

				// generate taxonomy labels
				$this->args = Utility::validate_labels( $this->args, $this->get_default_labels(), 'labels' );

				// generate taxonomy updated messages
				$this->args = Utility::validate_labels( $this->args, $this->get_default_messages(), 'messages' );

				// set some defaults
				if ( null === $this->args['rewrite'] ) {
					if ( $this->args['public'] ) {
						$this->args['rewrite'] = array(
							'slug'			=> str_replace( '_', '-', $this->slug ),
							'with_front'	=> true,
							'hierarchical'	=> $this->args['hierarchical'],
							'ep_mask'		=> EP_NONE,
						);
					} else {
						$this->args['rewrite'] = false;
					}
				}

				$this->args = Utility::validate_ui_args( $this->args );

				$this->args = Utility::validate_position_args( $this->args );

				// handle help
				$this->args = Utility::validate_help_args( $this->args, 'help' );

				// handle list help
				$this->args = Utility::validate_help_args( $this->args, 'list_help' );
			}

			return $status;
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the taxonomy arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'					=> '',
				'singular_title'		=> '',
				'labels'				=> array(),
				'messages'				=> array(),
				'description'			=> '',
				'public'				=> true,
				'show_ui'				=> null,
				'show_in_menu'			=> null,
				'show_in_nav_menus'		=> null,
				'show_tagcloud'			=> null,
				'show_in_quick_edit'	=> null,
				'show_admin_column'		=> false,
				'capabilities'			=> array(),
				'hierarchical'			=> false,
				'rewrite'				=> null,
				'query_var'				=> true,
				'sort'					=> null,
				'position'				=> null,
				'help'					=> array(
					'tabs'					=> array(),
					'sidebar'				=> '',
				),
				'list_help'				=> array(
					'tabs'					=> array(),
					'sidebar'				=> '',
				),
			);

			/**
			 * This filter can be used by the developer to modify the default values for each taxonomy component.
			 *
			 * @since 0.5.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_taxonomy_defaults', $defaults );
		}

		/**
		 * Returns whether this component supports multiple parents.
		 *
		 * @since 0.5.0
		 * @return bool
		 */
		protected function supports_multiparents() {
			return true;
		}

		/**
		 * Returns whether this component supports global slugs.
		 *
		 * If it does not support global slugs, the function either returns false for the slug to be globally unique
		 * or the class name of a parent component to ensure the slug is unique within that parent's scope.
		 *
		 * @since 0.5.0
		 * @return bool|string
		 */
		protected function supports_globalslug() {
			return false;
		}

		/**
		 * Returns the default labels for the taxonomy.
		 *
		 * @since 0.5.0
		 * @return array the array of taxonomy labels
		 */
		protected function get_default_labels() {
			return array(
				'name'							=> $this->args['title'],
				'singular_name'					=> $this->args['singular_title'],
				'menu_name'						=> $this->args['title'],
				'all_items'						=> sprintf( _x( 'All %s', 'all_items label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'add_new_item'					=> sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'edit_item'						=> sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'view_item'						=> sprintf( _x( 'View %s', 'view_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'update_item'					=> sprintf( _x( 'Update %s', 'update_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'new_item_name'					=> sprintf( _x( 'New %s Name', 'new_item_name label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'search_items'					=> sprintf( _x( 'Search %s', 'search_items label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'popular_items'					=> sprintf( _x( 'Popular %s', 'popular_items label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'not_found'						=> sprintf( _x( 'No %s found', 'not_found label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'no_terms'						=> sprintf( _x( 'No %s', 'no_terms label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'separate_items_with_commas'	=> sprintf( _x( 'Separate %s with commas', 'separate_items_with_commas label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'add_or_remove_items'			=> sprintf( _x( 'Add or remove %s', 'add_or_remove_items label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'choose_from_most_used'			=> sprintf( _x( 'Choose from the most used %s', 'choose_from_most_used label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'parent_item'					=> sprintf( _x( 'Parent %s', 'parent_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'parent_item_colon'				=> sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				// new accessibility labels added in WP 4.4
				'items_list'			=> sprintf( _x( '%s list', 'items_list label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				'items_list_navigation'	=> sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
				// additional label for post listings (handled by the plugin)
				'filter_by_item'				=> sprintf( _x( 'Filter by %s', 'filter_by_item label: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
			);
		}

		/**
		 * Returns the default messages for the taxonomy.
		 *
		 * @since 0.5.0
		 * @return array the array of taxonomy messages
		 */
		protected function get_default_messages() {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%s added.', 'term message: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 2 => sprintf( _x( '%s deleted.', 'term message: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 3 => sprintf( _x( '%s updated.', 'term message: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 4 => sprintf( _x( '%s not added.', 'term message: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 5 => sprintf( _x( '%s not updated.', 'term message: argument is the singular taxonomy label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 6 => sprintf( _x( '%s deleted.', 'bulk term message: argument is the plural taxonomy label', 'post-types-definitely' ), $this->args['title'] ),
			);
		}

	}

}
