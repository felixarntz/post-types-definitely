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
use WPDLib\FieldTypes\Manager as FieldManager;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\PostType' ) ) {

	class PostType extends Base {

		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_post_type_validated';
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
				//TODO: merge several properties into existing post type
			}
		}

		public function add_to_menu( $args ) {
			if ( ! $this->args['show_ui'] ) {
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
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( get_post_type( $post_id ) != $this->slug ) {
				return;
			}

			if ( wp_is_post_revision( $post ) ) {
				return;
			}

			$post_type_obj = get_post_type_object( $this->slug );
			if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
				return;
			}

			$meta_values = $_POST;

			$meta_values_validated = array();

			$meta_values_old = array();

			$errors = array();

			$changes = false;

			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$meta_value_old = wpptd_get_post_meta( $post_id, $field->slug );
					if ( $meta_value_old === null ) {
						$meta_value_old = $field->default;
					}
					$meta_values_old[ $field->slug ] = $meta_value_old;

					$meta_value = null;
					if ( isset( $meta_values[ $field->slug ] ) ) {
						$meta_value = $meta_values[ $field->slug ];
					}

					$meta_value = $field->validate_meta_value( $meta_value );
					if ( is_wp_error( $meta_value ) ) {
						$errors[ $field->slug ] = $meta_value;
						$meta_value = $meta_value_old;
					}

					$meta_values_validated[ $field->slug ] = $meta_value;

					if ( $meta_value != $meta_value_old ) {
						do_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug, $meta_value, $meta_value_old );
						$changes = true;
					}
				}
			}

			if ( $changes ) {
				do_action( 'wpptd_update_meta_values_' . $this->slug, $meta_values_validated, $meta_values_old );
			}

			$meta_values_validated = apply_filters( 'wpptd_validated_meta_values', $meta_values_validated );

			if ( count( $errors ) > 0 ) {
				$error_text = __( 'Some errors occurred while trying to save the following post meta:', 'wpptd' );
				foreach ( $errors as $field_slug => $error ) {
					$error_text .= '<br/><em>' . $field_slug . '</em>: ' . $error->get_error_message();
				}

				set_transient( 'wpptd_meta_error_' . $this->slug . '_' . $post_id, $error_text, 120 );
			}

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
			$screen = get_current_screen();

			foreach ( $this->args['help']['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $this->args['help']['sidebar'] ) ) {
				$screen->set_help_sidebar( $this->args['help']['sidebar'] );
			}
		}

		public function render_list_help() {
			$screen = get_current_screen();

			foreach ( $this->args['list_help']['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $this->args['list_help']['sidebar'] ) ) {
				$screen->set_help_sidebar( $this->args['list_help']['sidebar'] );
			}
		}

		public function get_updated_messages( $post, $permalink = '', $revision = false ) {
			$messages = $this->args['messages'];

			$messages[1] = sprintf( $messages[1], $permalink );
			if ( $revision ) {
				$messages[5] = sprintf( $messages[5], wp_post_revision_title( $revision, false ) );
			} else {
				$messages[5] = false;
			}
			$messages[6] = sprintf( $messages[6], $permalink );
			$messages[8] = sprintf( $messages[8], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );
			$messages[9] = sprintf( $messages[9], date_i18n( __( 'M j, Y @ H:i' ), strtotime( $post->post_date ) ), esc_url( $permalink ) );
			$messages[10] = sprintf( $messages[10], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );

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
					return new UtilError( 'no_valid_post_type', sprintf( __( 'The post type slug %s is forbidden since it would interfere with WordPress Core functionality.', 'wpptd' ), $this->slug ), '', ComponentManager::get_scope() );
				}

				// show notice if slug contains dashes
				if ( strpos( $this->slug, '-' ) !== false ) {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The post type slug %s contains dashes which is discouraged. It will still work for the most part, but we recommend to adjust the slug if possible.', 'wpptd' ), $this->slug ), '0.5.0' );
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

				// generate post type labels
				if ( ! is_array( $this->args['labels'] ) ) {
					$this->args['labels'] = array();
				}
				$default_labels = array(
					'name'					=> $this->args['title'],
					'singular_name'			=> $this->args['singular_title'],
					'menu_name'				=> $this->args['title'],
					'name_admin_bar'		=> $this->args['singular_title'],
					'all_items'				=> sprintf( __( 'All %s', 'wpptd' ), $this->args['title'] ),
					'add_new'				=> __( 'Add New', 'wpptd' ),
					'add_new_item'			=> sprintf( __( 'Add New %s', 'wpptd' ), $this->args['singular_title'] ),
					'edit_item'				=> sprintf( __( 'Edit %s', 'wpptd' ), $this->args['singular_title'] ),
					'new_item'				=> sprintf( __( 'New %s', 'wpptd' ), $this->args['singular_title'] ),
					'view_item'				=> sprintf( __( 'View %s', 'wpptd' ), $this->args['singular_title'] ),
					'search_items'			=> sprintf( __( 'Search %s', 'wpptd' ), $this->args['title'] ),
					'not_found'				=> sprintf( __( 'No %s found', 'wpptd' ), $this->args['title'] ),
					'not_found_in_trash'	=> sprintf( __( 'No %s found in Trash', 'wpptd' ), $this->args['title'] ),
					'parent_item_colon'		=> sprintf( __( 'Parent %s:', 'wpptd' ), $this->args['singular_title'] ),
					'featured_image'		=> sprintf( __( 'Featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'set_featured_image'	=> sprintf( __( 'Set featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'remove_featured_image'	=> sprintf( __( 'Remove featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'use_featured_image'	=> sprintf( __( 'Use as featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					// additional labels for media library
					'insert_into_item'		=> sprintf( __( 'Insert into %s content', 'wpptd' ), $this->args['singular_title'] ),
					'uploaded_to_this_item'	=> sprintf( __( 'Uploaded to this %s', 'wpptd' ), $this->args['singular_title'] ),
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
					 1 => sprintf( __( '%1$s updated. <a href="%%s">View %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 2 => __( 'Custom field updated.', 'wpptd' ),
					 3 => __( 'Custom field deleted.', 'wpptd' ),
					 4 => sprintf( __( '%s updated.', 'wpptd' ), $this->args['singular_title'] ),
					 5 => sprintf( __( '%s restored to revision from %%s', 'wpptd' ), $this->args['singular_title'] ),
					 6 => sprintf( __( '%1$s published. <a href="%%s">View %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 7 => sprintf( __( '%s saved.', 'wpptd' ), $this->args['singular_title'] ),
					 8 => sprintf( __( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 9 => sprintf( __( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					10 => sprintf( __( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
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
							'with_front'	=> false,
							'ep_mask'		=> EP_PERMALINK,
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
					if ( isset( $this->args['menu_position'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu position is unnecessarily provided for the post type %s - the menu position is already specified by its parent menu.', 'wpptd' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_position'] );
					}
					if ( isset( $this->args['menu_icon'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu icon is unnecessarily provided for the post type %s - the menu icon is already specified by its parent menu.', 'wpptd' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_icon'] );
					}
				}

				if ( null !== $this->args['position'] ) {
					$this->args['position'] = floatval( $this->args['position'] );
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

				// handle list help
				if( ! is_array( $this->args['list_help'] ) ) {
					$this->args['list_help'] = array();
				}
				if ( ! isset( $this->args['list_help']['tabs'] ) || ! is_array( $this->args['list_help']['tabs'] ) ) {
					$this->args['list_help']['tabs'] = array();
				}
				if ( ! isset( $this->args['list_help']['sidebar'] ) ) {
					$this->args['list_help']['sidebar'] = '';
				}
				foreach ( $this->args['list_help']['tabs'] as &$tab ) {
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
				'enter_title_here'		=> '',
				'description'			=> '',
				'public'				=> false,
				'exclude_from_search'	=> null,
				'publicly_queryable'	=> null,
				'show_ui'				=> null,
				'show_in_menu'			=> null,
				'show_add_new_in_menu'	=> true,
				'show_in_admin_bar'		=> null,
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

	}

}
