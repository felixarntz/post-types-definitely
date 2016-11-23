<?php
/**
 * WPPTD\Components\PostType class
 *
 * @package WPPTD
 * @subpackage Components
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPPTD\Utility as Utility;
use WPPTD\PostTypeTableHandler as PostTypeTableHandler;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\PostType' ) ) {
	/**
	 * Class for a post type component.
	 *
	 * This denotes a post type within WordPress.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class PostType extends Base {

		/**
		 * @since 0.5.0
		 * @var WPPTD\PostTypeTableHandler Holds the list table handler instance for this post type.
		 */
		protected $table_handler = null;

		/**
		 * @since 0.5.0
		 * @var bool Stores whether this post type should be outputted in the admin menu manually.
		 */
		protected $show_in_menu_manually = false;

		/**
		 * @since 0.6.0
		 * @var array Stores meta field keys that contain related posts.
		 */
		protected $related_posts_fields = array();

		/**
		 * @since 0.6.0
		 * @var array Stores meta field keys that contain related terms.
		 */
		protected $related_terms_fields = array();

		/**
		 * @since 0.6.0
		 * @var array Stores meta field keys that contain related users.
		 */
		protected $related_users_fields = array();

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param string $slug the post type slug
		 * @param array $args array of post type properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->table_handler = new PostTypeTableHandler( $this );
			$this->validate_filter = 'wpptd_post_type_validated';
		}

		/**
		 * Returns the table handler for this post type.
		 *
		 * @since 0.5.0
		 * @return WPPTD\PostTypeTableHandler the list table handler instance for this post type
		 */
		public function get_table_handler() {
			return $this->table_handler;
		}

		/**
		 * Checks whether this post type already exists in WordPress.
		 *
		 * @since 0.5.0
		 * @return bool true if the post type exists, otherwise false
		 */
		public function is_already_added() {
			return post_type_exists( $this->slug );
		}

		/**
		 * Registers the post type.
		 *
		 * If the post type already exists, some of the arguments will be merged into the existing post type object.
		 *
		 * @since 0.5.0
		 */
		public function register() {
			if ( ! $this->is_already_added() ) {
				$_post_type_args = $this->args;

				unset( $_post_type_args['title'] );
				unset( $_post_type_args['singular_title'] );
				unset( $_post_type_args['title_gender'] );
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

		/**
		 * Registers the meta for this post type.
		 *
		 * This method only works on WordPress >= 4.6 and therefore should only be called there.
		 *
		 * @since 0.6.5
		 */
		public function register_meta() {
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children( 'WPPTD\Components\Field' ) as $field ) {
					$field->register( $metabox, $this );
				}
			}
		}

		/**
		 * Adds the post type to the WordPress admin menu.
		 *
		 * The function will append the 'Add New' item and the related taxonomy pages to the menu as submenu items.
		 * This function is called by the WPDLib\Components\Menu class.
		 * The function returns the menu label this post type should have. This is then processed by the calling class.
		 *
		 * @since 0.5.0
		 * @see WPDLib\Components\Menu::add_menu_page()
		 * @param array $args an array with keys 'mode' (either 'menu' or 'submenu'), 'menu_label', 'menu_icon' and 'menu_position'
		 * @return string the menu label that this post type should have
		 */
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
					add_submenu_page( $sub_slug, $taxonomy_obj->labels->name, $taxonomy_obj->labels->menu_name, $taxonomy_obj->cap->manage_terms, 'edit-tags.php?taxonomy=' . $taxonomy->slug . '&amp;post_type=' . $this->slug );
				}
			}

			return $ret;
		}

		/**
		 * Returns the menu slug for this post type.
		 *
		 * This function is called by the WPDLib\Components\Menu class.
		 *
		 * @since 0.5.0
		 * @see WPDLib\Components\Menu::add_menu_page()
		 * @return string the post type's menu slug
		 */
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

		/**
		 * Registers the metaboxes for the post type.
		 *
		 * @since 0.5.0
		 * @see WPPTD\Components\Metabox::register()
		 * @param WP_Post $post the current post object
		 */
		public function add_meta_boxes( $post ) {
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				$metabox->register( $this );
			}
		}

		/**
		 * Validates and saves all meta field values.
		 *
		 * It will only do that if all requirements are met.
		 *
		 * @since 0.5.0
		 * @param integer $post_id the current post ID
		 * @param WP_Post $post the post object
		 * @param bool $update whether the post is being updated (true) or generated (false)
		 */
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

		/**
		 * Enqueues all the assets needed on the post editing screen of the post type.
		 *
		 * @since 0.5.0
		 * @see WPDLib\FieldTypes\Manager::enqueue_assets()
		 */
		public function enqueue_assets() {
			$_fields = array();
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$_fields[] = $field->_field;
				}
			}

			FieldManager::enqueue_assets( $_fields );
		}

		/**
		 * Renders the help tabs and sidebar on the post editing screen of the post type.
		 *
		 * @since 0.5.0
		 */
		public function render_help() {
			Utility::render_help( get_current_screen(), $this->args['help'] );
		}

		/**
		 * Renders the help tabs and sidebar on the posts list screen of the post type.
		 *
		 * @since 0.5.0
		 */
		public function render_list_help() {
			Utility::render_help( get_current_screen(), $this->args['list_help'] );
		}

		/**
		 * Returns the custom post updated messages for this post type.
		 *
		 * @since 0.5.0
		 * @param WP_Post $post the current post object
		 * @param string $permalink the post's permalink
		 * @param integer|false $revision the current revision (if applicable)
		 * @return array the custom messages
		 */
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

		/**
		 * Returns the custom bulk posts updated messages for this post type.
		 *
		 * @since 0.5.0
		 * @param array $counts the counts of updated posts
		 * @return array the custom bulk messages
		 */
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

		/**
		 * Returns the custom 'enter_title_here' placeholder text.
		 *
		 * @since 0.5.0
		 * @return string the custom 'enter_title_here' text or an empty string if not specified
		 */
		public function get_enter_title_here( $post ) {
			return $this->args['enter_title_here'];
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 * @param WPDLib\Components\Menu $parent the parent component
		 * @return bool|WPDLib\Util\Error an error object if an error occurred during validation, true if it was validated, false if it did not need to be validated
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
				$this->args = Utility::validate_titles( $this->args, $this->slug, 'post_type' );

				// generate post type labels
				$this->args = Utility::validate_labels( $this->args, 'labels', 'post_type' );

				// generate post type updated messages
				$this->args = Utility::validate_labels( $this->args, 'messages', 'post_type' );

				// generate post type bulk action messages
				$this->args = Utility::validate_labels( $this->args, 'bulk_messages', 'post_type' );

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

				// handle REST API default
				if ( null === $this->args['show_in_rest'] ) {
					if ( null !== $this->args['publicly_queryable'] ) {
						$this->args['show_in_rest'] = $this->args['publicly_queryable'];
					} elseif ( null !== $this->args['public'] ) {
						$this->args['show_in_rest'] = $this->args['public'];
					} else {
						$this->args['show_in_rest'] = false;
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
				$this->args = PostTypeTableHandler::validate_args( $this->args );

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
				'title'                => '',
				'singular_title'       => '',
				'title_gender'         => 'n',
				'labels'               => array(),
				'messages'             => array(),
				'bulk_messages'        => array(),
				'enter_title_here'     => '',
				'description'          => '',
				'public'               => false,
				'exclude_from_search'  => null,
				'publicly_queryable'   => null,
				'show_ui'              => null,
				'show_in_menu'         => null,
				'show_add_new_in_menu' => true,
				'show_in_admin_bar'    => null,
				'show_in_nav_menus'    => null,
				'show_in_rest'         => null,
				'capability_type'      => 'post',
				'capabilities'         => array(),
				'map_meta_cap'         => null,
				'hierarchical'         => false,
				'supports'             => array( 'title', 'editor' ),
				'has_archive'          => false,
				'rewrite'              => null,
				'query_var'            => true,
				'can_export'           => true,
				'position'             => null,
				'table_columns'        => array(),
				'row_actions'          => array(),
				'bulk_actions'         => array(),
				'help'                 => array(
					'tabs'                 => array(),
					'sidebar'              => '',
				),
				'list_help'            => array(
					'tabs'                 => array(),
					'sidebar'              => '',
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

		/**
		 * Checks whether all requirements to save meta values for a post are met.
		 *
		 * @since 0.5.0
		 * @param integer $post_id the post ID to save
		 * @param WP_Post $post the post object
		 * @return bool whether the meta values can be saved
		 */
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

		/**
		 * Validates a post's meta values for all meta fields of the post type.
		 *
		 * It iterates through all the meta fields of the post and validates each one's value.
		 * If a field is not set for some reason, its default value is saved.
		 *
		 * Furthermore this function adds settings errors if any occur.
		 *
		 * @since 0.5.0
		 * @param array $meta_values array of submitted meta values
		 * @param integer $post_id the current post ID
		 * @return array the validated meta values
		 */
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
				if ( has_action( 'wpptd_update_meta_values_' . $this->slug ) ) {
					App::deprecated_action( 'wpptd_update_meta_values_' . $this->slug, '0.6.0', 'wpptd_update_post_meta_values_' . $this->slug );

					/**
					 * This action can be used to perform additional steps when the meta values of this post type were updated.
					 *
					 * @since 0.5.0
					 * @deprecated 0.6.0
					 * @param array the updated meta values as $field_slug => $value
					 * @param array the previous meta values as $field_slug => $value
					 */
					do_action( 'wpptd_update_meta_values_' . $this->slug, $meta_values_validated, $meta_values_old );
				}

				/**
				 * This action can be used to perform additional steps when the meta values of this post type were updated.
				 *
				 * @since 0.6.0
				 * @param array the updated meta values as $field_slug => $value
				 * @param array the previous meta values as $field_slug => $value
				 */
				do_action( 'wpptd_update_post_meta_values_' . $this->slug, $meta_values_validated, $meta_values_old );
			}

			if ( has_filter( 'wpptd_validated_meta_values' ) ) {
				App::deprecated_filter( 'wpptd_validated_meta_values', '0.6.0', 'wpptd_validated_post_meta_values_' . $this->slug );

				/**
				 * This filter can be used by the developer to modify the validated meta values right before they are saved.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param array the associative array of meta keys (fields slugs) and their values
				 */
				$meta_values_validated = apply_filters( 'wpptd_validated_meta_values', $meta_values_validated );
			}

			/**
			 * This filter can be used by the developer to modify the validated meta values right before they are saved.
			 *
			 * @since 0.6.0
			 * @param array the associative array of meta keys (fields slugs) and their values
			 */
			$meta_values_validated = apply_filters( 'wpptd_validated_post_meta_values_' . $this->slug, $meta_values_validated );

			$this->add_settings_message( $errors, $post_id );

			return $meta_values_validated;
		}

		/**
		 * Validates a meta value.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\Field $field field object to validate the meta value for
		 * @param mixed $meta_value the meta value to validate
		 * @param mixed $meta_value_old the previous meta value
		 * @return array an array containing the validated value, a variable possibly containing a WP_Error object and a boolean value whether the meta value has changed
		 */
		protected function validate_meta_value( $field, $meta_value, $meta_value_old ) {
			$meta_value = $field->validate_meta_value( $meta_value );
			$error = false;
			$changed = false;

			if ( is_wp_error( $meta_value ) ) {
				$error = $meta_value;
				$meta_value = $meta_value_old;
			} elseif ( $meta_value != $meta_value_old ) {
				if ( has_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug ) ) {
					App::deprecated_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug, '0.6.0', 'wpptd_update_post_meta_value_' . $this->slug . '_' . $field->slug );

					/**
					 * This action can be used to perform additional steps when the meta value for a specific field of this post type has been updated.
					 *
					 * @since 0.5.0
					 * @deprecated 0.6.0
					 * @param mixed the updated meta value
					 * @param mixed the previous meta value
					 */
					do_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug, $meta_value, $meta_value_old );
				}

				/**
				 * This action can be used to perform additional steps when the meta value for a specific field of this post type has been updated.
				 *
				 * @since 0.6.0
				 * @param mixed the updated meta value
				 * @param mixed the previous meta value
				 */
				do_action( 'wpptd_update_post_meta_value_' . $this->slug . '_' . $field->slug, $meta_value, $meta_value_old );

				$changed = true;
			}

			return array( $meta_value, $error, $changed );
		}

		/**
		 * Adds settings errors and/or updated messages for the current post of this post type.
		 *
		 * @since 0.5.0
		 * @param array $errors an array (possibly) containing validation errors as $field_slug => $wp_error
		 * @param integer $post_id the ID of the current post
		 */
		protected function add_settings_message( $errors, $post_id ) {
			if ( count( $errors ) > 0 ) {
				$error_text = __( 'Some errors occurred while trying to save the following post meta:', 'post-types-definitely' );
				foreach ( $errors as $field_slug => $error ) {
					$error_text .= '<br/><em>' . $field_slug . '</em>: ' . $error->get_error_message();
				}

				set_transient( 'wpptd_post_meta_error_' . $this->slug . '_' . $post_id, $error_text, 120 );
			}
		}

	}

}
