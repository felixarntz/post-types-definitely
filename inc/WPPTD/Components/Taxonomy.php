<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\Taxonomy' ) ) {

	class Taxonomy extends Base {

		protected $post_type_slugs = array();

		public function is_already_added() {
			return taxonomy_exists( $this->slug );
		}

		public function register() {
			if ( ! $this->is_already_added() ) {
				$_taxonomy_args = $this->args;

				unset( $_taxonomy_args['title'] );
				unset( $_taxonomy_args['singular_title'] );
				unset( $_taxonomy_args['messages'] );
				unset( $_taxonomy_args['post_types'] );
				unset( $_taxonomy_args['show_add_new_in_menu'] );
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
				//TODO: merge several properties into existing taxonomies
			}
		}

		public function add_to_menu() {
			$menu = $this->get_parent();

			if ( empty( $menu->slug ) ) {
				return;
			}

			if ( in_array( $this->slug, array( 'category', 'post_tag', 'post_format' ) ) ) {
				return;
			}

			if ( false === $menu->is_already_added( 'edit-tags.php?taxonomy=' . $this->slug ) ) {
				$taxonomy_obj = get_taxonomy( $this->slug );

				add_menu_page( '', $menu->label, $taxonomy_obj->cap->manage_terms, 'edit-tags.php?taxonomy=' . $this->slug, '', $menu->icon, $menu->position );
				$menu->added = true;
				$menu->subslug = 'edit-tags.php?taxonomy=' . $this->slug;
				$menu->sublabel = $this->args['labels']['menu_name'];
			} else {
				if ( false === $menu->subslug ) {
					return;
				}

				$taxonomy_obj = get_taxonomy( $this->slug );

				add_submenu_page( $menu->subslug, $this->args['labels']['name'], $this->args['labels']['menu_name'], $taxonomy_obj->cap->manage_terms, 'edit-tags.php?taxonomy=' . $this->slug );

				if ( $menu->sublabel !== true ) {
					global $submenu;

					if ( isset( $submenu[ $menu->subslug ] ) ) {
						$submenu[ $menu->subslug ][0][0] = $menu->sublabel;
						$menu->sublabel = true;
					}
				}
			}
		}

		public function render_help() {
			$screen = get_current_screen();

			foreach ( $this->args['help']['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $this->args['help']['sidebar'] ) ) {
				$screen->set_help_sidebar( $this->args['help']['sidebar'] );
			}
		}

		public function get_updated_messages() {
			return $this->args['messages'];
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {

				if ( in_array( $this->slug, array( 'category', 'post_tag', 'post_format', 'link_category', 'nav_menu' ) ) ) {
					return new UtilError( 'no_valid_taxonomy', sprintf( __( 'The taxonomy slug %s is forbidden since it would interfere with WordPress Core functionality.', 'wpptd' ), $this->slug ), '', ComponentManager::get_scope() );
				}

				// show notice if slug contains dashes
				if ( strpos( $this->slug, '-' ) !== false ) {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The taxonomy slug %s contains dashes which is discouraged. It will still work for the most part, but we recommend to adjust the slug if possible.', 'wpptd' ), $this->slug ), '0.5.0' );
				}

				// generate titles if not provided
				if ( empty( $this->args['title'] ) && isset( $this->args['label'] ) ) {
					$this->args['title'] = $this->args['label'];
					unset( $this->args['label'] );
				}
				if ( empty( $this->args['title'] ) ) {
					if ( empty( $this->args['singular_title'] ) ) {
						$this->args['singular_title'] = ucwords( str_replace( '_', '', $this->slug ) );
					}
					$this->args['title'] = $this->args['singular_title'] . 's';
				} elseif ( empty( $this->args['singular_title'] ) ) {
					$this->args['singular_title'] = $this->args['title'];
				}

				// generate taxonomy labels
				if ( ! is_array( $this->args['labels'] ) ) {
					$this->args['labels'] = array();
				}
				$default_labels = array(
					'name'							=> $this->args['title'],
					'singular_name'					=> $this->args['singular_title'],
					'menu_name'						=> $this->args['title'],
					'all_items'						=> sprintf( __( 'All %s', 'wpptd' ), $this->args['title'] ),
					'add_new_item'					=> sprintf( __( 'Add New %s', 'wpptd' ), $this->args['singular_title'] ),
					'edit_item'						=> sprintf( __( 'Edit %s', 'wpptd' ), $this->args['singular_title'] ),
					'view_item'						=> sprintf( __( 'View %s', 'wpptd' ), $this->args['singular_title'] ),
					'update_item'					=> sprintf( __( 'Update %s', 'wpptd' ), $this->args['singular_title'] ),
					'new_item_name'					=> sprintf( __( 'New %s Name', 'wpptd' ), $this->args['singular_title'] ),
					'search_items'					=> sprintf( __( 'Search %s', 'wpptd' ), $this->args['title'] ),
					'popular_items'					=> sprintf( __( 'Popular %s', 'wpptd' ), $this->args['title'] ),
					'not_found'						=> sprintf( __( 'No %s found', 'wpptd' ), $this->args['title'] ),
					'separate_items_with_commas'	=> sprintf( __( 'Separate %s with commas', 'wpptd' ), $this->args['title'] ),
					'add_or_remove_items'			=> sprintf( __( 'Add or remove %s', 'wpptd' ), $this->args['title'] ),
					'choose_from_most_used'			=> sprintf( __( 'Choose from the most used %s', 'wpptd' ), $this->args['title'] ),
					'parent_item'					=> sprintf( __( 'Parent %s', 'wpptd' ), $this->args['singular_title'] ),
					'parent_item_colon'				=> sprintf( __( 'Parent %s:', 'wpptd' ), $this->args['singular_title'] ),
				);
				foreach ( $default_labels as $type => $default_label ) {
					if ( ! isset( $this->args['labels'][ $type ] ) ) {
						$this->args['labels'][ $type ] = $default_label;
					}
				}

				// generate post type updated messages
				if ( ! is_array( $this->args['messages'] ) ) {
					$this->args['messages'] = array();
				}
				$default_messages = array(
					 0 => '',
					 1 => sprintf( __( '%s added.', 'wpptd' ), $this->args['singular_title'] ),
					 2 => sprintf( __( '%s deleted.', 'wpptd' ), $this->args['singular_title'] ),
					 3 => sprintf( __( '%s updated.', 'wpptd' ), $this->args['singular_title'] ),
					 4 => sprintf( __( '%s not added.', 'wpptd' ), $this->args['singular_title'] ),
					 5 => sprintf( __( '%s not updated.', 'wpptd' ), $this->args['singular_title'] ),
					 6 => sprintf( __( '%s deleted.', 'wpptd' ), $this->args['title'] ),
				);
				foreach ( $default_messages as $i => $default_message ) {
					if ( ! isset( $this->args['messages'][ $i ] ) ) {
						$this->args['messages'][ $i ] = $default_message;
					}
				}

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
				if ( null === $this->args['show_ui'] ) {
					$this->args['show_ui'] = $this->args['public'];
				}
				$menu = $this->get_parent();
				if ( $this->args['show_in_menu'] && empty( $menu->slug ) ) {
					$this->args['show_in_menu'] = true;
				} else {
					$this->args['show_in_menu'] = false;
				}

				// handle post types
				if ( is_array( $this->args['post_types'] ) ) {
					$this->post_type_slugs = $this->args['post_types'];
				}

				// handle help
				if( ! is_array( $this->args['help'] ) ) {
					$this->args['help'] = array();
				}
				if ( ! isset( $this->args['help']['tabs'] ) || ! is_array( $this->args['help']['tabs'] ) ) {
					$this->args['help']['tabs'] = array();
				}
				if ( ! isset( $this->args['help']['sidebar'] ) ) {
					$this->args['help']['sidebar'] = '';
				}
				foreach ( $this->args['help']['tabs'] as &$tab ) {
					$tab = wp_parse_args( $tab, array(
						'title'			=> __( 'Help tab title', 'wpptd' ),
						'content'		=> '',
						'callback'		=> false,
					) );
				}
				unset( $tab );
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
				'show_add_new_in_menu'	=> true,
				'show_in_nav_menus'		=> null,
				'show_tagcloud'			=> null,
				'show_in_quick_edit'	=> null,
				'show_admin_column'		=> false,
				'capabilities'			=> array(),
				'hierarchical'			=> false,
				'post_types'			=> array(),
				'rewrite'				=> null,
				'query_var'				=> true,
				'sort'					=> null,
				'help'					=> array(
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
			return false;
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

	}

}
