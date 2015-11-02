<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPPTD\Utility as Utility;
use WPPTD\PostTableHandler as PostTableHandler;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\PostType' ) ) {

	class PostType extends Base {
		protected $table_handler = null;

		protected $show_in_menu_manually = false;

		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->table_handler = new PostTableHandler( $this );
			$this->validate_filter = 'wpptd_post_type_validated';
		}

		public function get_table_handler() {
			return $this->table_handler;
		}

		public function is_already_added() {
			return post_type_exists( $this->slug );
		}

		public function register() {
			if ( ! $this->is_already_added() ) {
				$_post_type_args = $this->args;

				unset( $_post_type_args['title'] );
				unset( $_post_type_args['singular_title'] );
				unset( $_post_type_args['messages'] );
				unset( $_post_type_args['enter_title_here'] );
				unset( $_post_type_args['show_add_new_in_menu'] );
				unset( $_post_type_args['table_columns'] );
				unset( $_post_type_args['row_actions'] );
				unset( $_post_type_args['bulk_actions'] );
				unset( $_post_type_args['help'] );
				unset( $_post_type_args['list_help'] );

				$_post_type_args['label'] = $this->args['title'];
				$_post_type_args['register_meta_box_cb'] = array( $this, 'add_meta_boxes' );

				$post_type_args = array();
				foreach ( $_post_type_args as $key => $value ) {
					if ( null !== $value ) {
						$post_type_args[ $key ] = $value;
					}
				}

				register_post_type( $this->slug, $post_type_args );
			} else {
				// merge several properties into existing post type
				global $wp_post_types;

				if ( is_array( $this->args['supports'] ) ) {
					foreach ( $this->args['supports'] as $feature ) {
						add_post_type_support( $this->slug, $feature );
					}
				}

				if ( $this->args['labels'] ) {
					// merge the slug as $name into the arguments (required for `get_post_type_labels()`)
					$wp_post_types[ $this->slug ]->labels = get_post_type_labels( (object) array_merge( $this->args, array( 'name' => $this->slug ) ) );
					$wp_post_types[ $this->slug ]->label = $wp_post_types[ $this->slug ]->labels->name;
				}

				add_action( 'add_meta_boxes_' . $this->slug, array( $this, 'add_meta_boxes' ), 10, 1 );
			}
		}

		public function add_to_menu( $args ) {
			if ( ! $this->show_in_menu_manually ) {
				return false;
			}

			if ( 'submenu' == $args['mode'] && null === $args['menu_slug'] ) {
				return false;
			}

			$ret = false;

			$sub_slug = $this->get_menu_slug();

			if ( ! in_array( $this->slug, array( 'post', 'page', 'attachment' ) ) ) {
				$post_type_obj = get_post_type_object( $this->slug );
				if ( 'menu' === $args['mode'] ) {
					add_menu_page( '', $args['menu_label'], $post_type_obj->cap->edit_posts, $this->get_menu_slug(), '', $args['menu_icon'], $args['menu_position'] );
					$ret = $post_type_obj->labels->all_items;
					$add_new_label = $post_type_obj->labels->add_new;
				} else {
					add_submenu_page( $args['menu_slug'], $post_type_obj->labels->name, $post_type_obj->labels->menu_name, $post_type_obj->cap->edit_posts, $this->get_menu_slug() );
					$ret = $post_type_obj->labels->menu_name;
					$add_new_label = $post_type_obj->labels->add_new_item;
					$sub_slug = $args['menu_slug'];
				}

				if ( $this->args['show_add_new_in_menu'] ) {
					add_submenu_page( $sub_slug, '', $add_new_label, $post_type_obj->cap->create_posts, 'post-new.php?post_type=' . $this->slug );
				}
			}

			foreach ( $this->get_children( 'WPPTD\Components\Taxonomy' ) as $taxonomy ) {
				if ( $taxonomy->show_in_menu ) {
					$taxonomy_obj = get_taxonomy( $taxonomy->slug );
					add_submenu_page( $sub_slug, $taxonomy_obj->labels->name, $taxonomy_obj->labels->menu_name, $taxonomy_obj->cap->manage_terms, 'edit-tags.php?taxonomy=' . $taxonomy->slug . '&post_type=' . $this->slug );
				}
			}

			return $ret;
		}

		public function get_menu_slug() {
			if ( 'post' == $this->slug ) {
				return 'edit.php';
			} elseif ( 'attachment' == $this->slug ) {
				return 'upload.php';
			} elseif ( 'link' == $this->slug ) {
				return 'link-manager.php';
			}
			return 'edit.php?post_type=' . $this->slug;
		}

		public function add_meta_boxes( $post ) {
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				$metabox->register( $this );
			}
		}

		public function save_meta( $post_id, $post, $update = false ) {
			if ( ! $this->can_save_meta( $post_id, $post ) ) {
				return;
			}

			$meta_values_validated = $this->validate_meta_values( $_POST, $post_id );

			foreach ( $meta_values_validated as $field_slug => $meta_value_validated ) {
				if ( is_array( $meta_value_validated ) ) {
					delete_post_meta( $post_id, $field_slug );
					foreach ( $meta_value_validated as $mv ) {
						add_post_meta( $post_id, $field_slug, $mv );
					}
				} else {
					update_post_meta( $post_id, $field_slug, $meta_value_validated );
				}
			}
		}

		public function enqueue_assets() {
			$_fields = array();
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$_fields[] = $field->_field;
				}
			}

			FieldManager::enqueue_assets( $_fields );
		}

		public function render_help() {
			Utility::render_help( get_current_screen(), $this->args['help'] );
		}

		public function render_list_help() {
			Utility::render_help( get_current_screen(), $this->args['list_help'] );
		}

		public function get_updated_messages( $post, $permalink = '', $revision = false ) {
			if ( ! $this->args['messages'] ) {
				return array();
			}

			$messages = $this->args['messages'];

			if ( isset( $messages[1] ) ) {
				$messages[1] = sprintf( $messages[1], $permalink );
			}
			if ( isset( $messages[5] ) ) {
				if ( $revision ) {
					$messages[5] = sprintf( $messages[5], wp_post_revision_title( $revision, false ) );
				} else {
					$messages[5] = false;
				}
			}
			if ( isset( $messages[6] ) ) {
				$messages[6] = sprintf( $messages[6], $permalink );
			}
			if ( isset( $messages[8] ) ) {
				$messages[8] = sprintf( $messages[8], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );
			}
			if ( isset( $messages[9] ) ) {
				$messages[9] = sprintf( $messages[9], date_i18n( __( 'M j, Y @ H:i' ), strtotime( $post->post_date ) ), esc_url( $permalink ) );
			}
			if ( isset( $messages[10] ) ) {
				$messages[10] = sprintf( $messages[10], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );
			}

			return $messages;
		}

		public function get_bulk_updated_messages( $counts ) {
			if ( ! $this->args['bulk_messages'] ) {
				return array();
			}

			$messages = array();
			foreach ( $this->args['bulk_messages'] as $type => $_messages ) {
				list( $singular, $plural ) = $_messages;
				$messages[ $type ] = ( 1 === $counts[ $type ] ) ? $singular : $plural;
			}

			return $messages;
		}

		public function get_enter_title_here( $post ) {
			return $this->args['enter_title_here'];
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {

				if ( in_array( $this->slug, array( 'revision', 'nav_menu_item', 'action', 'author', 'order', 'plugin', 'theme' ) ) ) {
					return new UtilError( 'no_valid_post_type', sprintf( __( 'The post type slug %s is forbidden since it would interfere with WordPress Core functionality.', 'post-types-definitely' ), $this->slug ), '', ComponentManager::get_scope() );
				}

				// show notice if slug contains dashes
				if ( strpos( $this->slug, '-' ) !== false ) {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The post type slug %s contains dashes which is discouraged. It will still work for the most part, but we recommend to adjust the slug if possible.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
				}

				// generate titles if not provided
				$this->args = Utility::validate_post_type_and_taxonomy_titles( $this->args, $this->slug );

				// generate post type labels
				$this->args = Utility::validate_labels( $this->args, $this->get_default_labels(), 'labels' );

				// generate post type updated messages
				$this->args = Utility::validate_labels( $this->args, $this->get_default_messages(), 'messages' );

				// generate post type bulk action messages
				$this->args = Utility::validate_labels( $this->args, $this->get_default_bulk_messages(), 'bulk_messages' );

				// set some defaults
				if ( null === $this->args['rewrite'] ) {
					if ( $this->args['public'] ) {
						$this->args['rewrite'] = array(
							'slug'			=> str_replace( '_', '-', $this->slug ),
							'with_front'	=> false,
							'ep_mask'		=> EP_PERMALINK,
						);
					} else {
						$this->args['rewrite'] = false;
					}
				}

				$this->args = Utility::validate_ui_args( $this->args );

				$menu = $this->get_parent();
				if ( $this->args['show_in_menu'] && empty( $menu->slug ) ) {
					$this->args['show_in_menu'] = true;
				} elseif ( $this->args['show_in_menu'] ) {
					$this->args['show_in_menu'] = false;
					if ( null === $this->args['show_in_admin_bar'] ) {
						$this->args['show_in_admin_bar'] = true;
					}
					$this->show_in_menu_manually = true;
					if ( isset( $this->args['menu_position'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu position is unnecessarily provided for the post type %s - the menu position is already specified by its parent menu.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_position'] );
					}
					if ( isset( $this->args['menu_icon'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu icon is unnecessarily provided for the post type %s - the menu icon is already specified by its parent menu.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_icon'] );
					}
				}

				$this->args = Utility::validate_position_args( $this->args );

				// handle post table
				$this->args = $this->table_handler->validate_post_type_args( $this->args );

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
		 * Read the plugin guide for more information about the post type arguments.
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
				'bulk_messages'			=> array(),
				'enter_title_here'		=> '',
				'description'			=> '',
				'public'				=> false,
				'exclude_from_search'	=> null,
				'publicly_queryable'	=> null,
				'show_ui'				=> null,
				'show_in_menu'			=> null,
				'show_add_new_in_menu'	=> true,
				'show_in_admin_bar'		=> null,
				'show_in_nav_menus'		=> null,
				'capability_type'		=> 'post',
				'capabilities'			=> array(),
				'map_meta_cap'			=> null,
				'hierarchical'			=> false,
				'supports'				=> array( 'title', 'editor' ),
				'has_archive'			=> false,
				'rewrite'				=> null,
				'query_var'				=> true,
				'can_export'			=> true,
				'position'				=> null,
				'table_columns'			=> array(),
				'row_actions'			=> array(),
				'bulk_actions'			=> array(),
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
			 * This filter can be used by the developer to modify the default values for each post type component.
			 *
			 * @since 0.5.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_post_type_defaults', $defaults );
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

		protected function can_save_meta( $post_id, $post ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return false;
			}

			if ( get_post_type( $post_id ) != $this->slug ) {
				return false;
			}

			if ( wp_is_post_revision( $post ) ) {
				return false;
			}

			$post_type_obj = get_post_type_object( $this->slug );
			if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
				return false;
			}

			return true;
		}

		protected function validate_meta_values( $meta_values, $post_id ) {
			$meta_values_validated = array();

			$meta_values_old = array();

			$errors = array();

			$changes = false;

			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$meta_value_old = wpptd_get_post_meta_value( $post_id, $field->slug );
					if ( $meta_value_old === null ) {
						$meta_value_old = $field->default;
					}
					$meta_values_old[ $field->slug ] = $meta_value_old;

					$meta_value = null;
					if ( isset( $meta_values[ $field->slug ] ) ) {
						$meta_value = $meta_values[ $field->slug ];
					}

					list( $meta_value_validated, $error, $changed ) = $this->validate_meta_value( $field, $meta_value, $meta_value_old );

					$meta_values_validated[ $field->slug ] = $meta_value_validated;
					if ( $error ) {
						$errors[ $field->slug ] = $error;
					} elseif ( $changed ) {
						$changes = true;
					}
				}
			}

			if ( $changes ) {
				do_action( 'wpptd_update_meta_values_' . $this->slug, $meta_values_validated, $meta_values_old );
			}

			$meta_values_validated = apply_filters( 'wpptd_validated_meta_values', $meta_values_validated );

			$this->add_settings_message( $errors, $post_id );

			return $meta_values_validated;
		}

		protected function validate_meta_value( $field, $meta_value, $meta_value_old ) {
			$meta_value = $field->validate_meta_value( $meta_value );
			$error = false;
			$changed = false;

			if ( is_wp_error( $meta_value ) ) {
				$error = $meta_value;
				$meta_value = $meta_value_old;
			} elseif ( $meta_value != $meta_value_old ) {
				do_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug, $meta_value, $meta_value_old );
				$changed = true;
			}

			return array( $meta_value, $error, $changed );
		}

		protected function add_settings_message( $errors, $post_id ) {
			if ( count( $errors ) > 0 ) {
				$error_text = __( 'Some errors occurred while trying to save the following post meta:', 'post-types-definitely' );
				foreach ( $errors as $field_slug => $error ) {
					$error_text .= '<br/><em>' . $field_slug . '</em>: ' . $error->get_error_message();
				}

				set_transient( 'wpptd_meta_error_' . $this->slug . '_' . $post_id, $error_text, 120 );
			}
		}

		protected function get_default_labels() {
			return array(
				'name'					=> $this->args['title'],
				'singular_name'			=> $this->args['singular_title'],
				'menu_name'				=> $this->args['title'],
				'name_admin_bar'		=> $this->args['singular_title'],
				'all_items'				=> sprintf( _x( 'All %s', 'all_items label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'add_new'				=> _x( 'Add New', 'add_new label', 'post-types-definitely' ),
				'add_new_item'			=> sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'edit_item'				=> sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'new_item'				=> sprintf( _x( 'New %s', 'new_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'view_item'				=> sprintf( _x( 'View %s', 'view_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'search_items'			=> sprintf( _x( 'Search %s', 'search_items label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'not_found'				=> sprintf( _x( 'No %s found', 'not_found label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'not_found_in_trash'	=> sprintf( _x( 'No %s found in Trash', 'not_found_in_trash label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'parent_item_colon'		=> sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'featured_image'		=> sprintf( _x( 'Featured %s Image', 'featured_image label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'set_featured_image'	=> sprintf( _x( 'Set featured %s Image', 'set_featured_image label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'remove_featured_image'	=> sprintf( _x( 'Remove featured %s Image', 'remove_featured_image label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'use_featured_image'	=> sprintf( _x( 'Use as featured %s Image', 'use_featured_image label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				// new accessibility labels added in WP 4.4
				'items_list'			=> sprintf( _x( '%s list', 'items_list label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'items_list_navigation'	=> sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				'filter_items_list'		=> sprintf( _x( 'Filter %s list', 'filter_items_list label: argument is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				// additional labels for media library (as of WP 4.4 they are natively supported, in older versions they are handled by the plugin)
				'insert_into_item'		=> sprintf( _x( 'Insert into %s content', 'insert_into_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				'uploaded_to_this_item'	=> sprintf( _x( 'Uploaded to this %s', 'uploaded_to_this_item label: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
			);
		}

		protected function get_default_messages() {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%1$s updated. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 2 => _x( 'Custom field updated.', 'post message', 'post-types-definitely' ),
				 3 => _x( 'Custom field deleted.', 'post message', 'post-types-definitely' ),
				 4 => sprintf( _x( '%s updated.', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 5 => sprintf( _x( '%s restored to revision from %%s', 'post message: first argument is the singular post type label, second is the revision title', 'post-types-definitely' ), $this->args['singular_title'] ),
				 6 => sprintf( _x( '%1$s published. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 7 => sprintf( _x( '%s saved.', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 8 => sprintf( _x( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				 9 => sprintf( _x( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
				10 => sprintf( _x( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
			);
		}

		protected function get_default_bulk_messages() {
			return array(
				'updated'	=> array(
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				),
				'locked'	=> array(
					sprintf( _x( '%%s %s not updated, somebody is editing it.', 'bulk post message: first argument is a number, second is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
					sprintf( _x( '%%s %s not updated, somebody is editing them.', 'bulk post message: first argument is a number, second is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				),
				'deleted'	=> array(
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				),
				'trashed'	=> array(
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				),
				'untrashed'	=> array(
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the singular post type label', 'post-types-definitely' ), $this->args['singular_title'] ),
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the plural post type label', 'post-types-definitely' ), $this->args['title'] ),
				),
			);
		}

	}

}
