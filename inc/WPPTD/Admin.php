<?php
/**
 * @package WPPTD
 * @version 0.5.1
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

use WPDLib\Components\Manager as ComponentManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Admin' ) ) {
	/**
	 * This class performs the necessary actions in the WordPress admin.
	 *
	 * This includes both registering and displaying metaboxes and meta fields, but also handling additional post type and taxonomy tweaks.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Admin {

		/**
		 * @since 0.5.0
		 * @var WPPTD\Admin|null Holds the instance of this class.
		 */
		private static $instance = null;

		/**
		 * Gets the instance of this class. If it does not exist, it will be created.
		 *
		 * @since 0.5.0
		 * @return WPPTD\Admin
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
		 * @since 0.5.0
		 */
		private function __construct() {
			add_action( 'after_setup_theme', array( $this, 'add_hooks' ) );
		}

		/**
		 * Hooks in all the necessary actions and filters.
		 *
		 * This function should be executed after the plugin has been initialized.
		 *
		 * @since 0.5.0
		 */
		public function add_hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			$this->add_post_type_hooks();
			$this->add_taxonomy_hooks();
			$this->add_posts_table_hooks();
		}

		/**
		 * Enqueues necessary stylesheets and scripts.
		 *
		 * All assets are only enqueued if we are on a post type or taxonomy screen created by the plugin.
		 *
		 * @since 0.5.0
		 */
		public function enqueue_assets() {
			$screen = get_current_screen();

			if ( isset( $screen->taxonomy ) && $screen->taxonomy ) {
				// we don't need any additional assets for taxonomy screens
				/*$taxonomy = ComponentManager::get( '*.*.' . $screen->taxonomy, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
				if ( $taxonomy ) {

				}*/
			} elseif ( isset( $screen->post_type ) && $screen->post_type ) {
				$post_type = ComponentManager::get( '*.' . $screen->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
				if ( $post_type ) {
					switch ( $screen->base ) {
						case 'post':
							$post_type->enqueue_assets();
							break;
						case 'edit':
							break;
						default:
					}
				}
			}
		}

		/**
		 * Wrapper function to control saving meta values for a post type registered with the plugin.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 * @param integer $post_id post ID of the post to be saved
		 * @param WP_Post $post the post object to be saved
		 * @param boolean $update whether this will create a new post or update an existing one
		 */
		public function save_post_meta( $post_id, $post, $update = false ) {
			$nonce = isset( $_POST[ 'wpptd_edit_post_' . $post->post_type ] ) ? sanitize_key( $_POST[ 'wpptd_edit_post_' . $post->post_type ] ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpptd-save-post-' . $post->post_type ) ) {
				return;
			}

			$post_type = ComponentManager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->save_meta( $post_id, $post, $update );
			}
		}

		/**
		 * Displays validation errors for meta fields if any occurred.
		 *
		 * If there was a WordPress function like `add_meta_error()`, it would do something like this.
		 *
		 * @since 0.5.0
		 * @param WP_Post $post the current post
		 */
		public function display_post_meta_errors( $post ) {
			wp_nonce_field( 'wpptd-save-post-' . $post->post_type, 'wpptd_edit_post_' . $post->post_type );

			$errors = get_transient( 'wpptd_post_meta_error_' . $post->post_type . '_' . $post->ID );
			if ( $errors ) {
				echo '<div id="wpptd-post-meta-errors" class="notice notice-error is-dismissible"><p>';
				echo $errors;
				echo '</p></div>';
				delete_transient( 'wpptd_post_meta_error_' . $post->post_type . '_' . $post->ID );
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a post editing screen.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 */
		public function add_post_help() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->render_help();
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a post list screen.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 */
		public function add_post_list_help() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->render_list_help();
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a taxonomy editing or list screen.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 */
		public function add_term_or_term_list_help() {
			global $taxnow;

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				if ( isset( $_GET['tag_ID'] ) && is_numeric( $_GET['tag_ID'] ) ) {
					$taxonomy->render_help();
				} else {
					$taxonomy->render_list_help();
				}
			}
		}

		/**
		 * This filter returns the custom 'enter_title_here' string for a post type if available.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 * @param string $text the original text
		 * @param WP_Post $post the current post
		 * @return string the custom text to use (if available) or the original text
		 */
		public function get_post_enter_title_here( $text, $post ) {
			$post_type = ComponentManager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$_text = $post_type->get_enter_title_here( $post );
				if ( $_text ) {
					return $_text;
				}
			}
			return $text;
		}

		/**
		 * This filter returns the custom messages array for when a post of a certain post type has been modified.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 * @param array $messages the original messages
		 * @return array the custom messages to use
		 */
		public function get_post_updated_messages( $messages ) {
			global $post;

			$permalink = get_permalink( $post->ID );
			if ( ! $permalink ) {
				$permalink = '';
			}

			$revision = isset( $_GET['revision'] ) ? (int) $_GET['revision'] : false;

			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				if ( ! in_array( $post_type->slug, array( 'post', 'page', 'attachment' ) ) ) {
					$post_type_messages = $post_type->get_updated_messages( $post, $permalink, $revision );
					if ( $post_type_messages && is_array( $post_type_messages ) ) {
						$messages[ $post_type->slug ] = $post_type_messages;
					}
				}
			}

			return $messages;
		}

		/**
		 * This filter returns the custom bulk messages array for when posts of a certain post type have been modified.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 * @param array $messages the original messages
		 * @return array the custom messages to use
		 */
		public function get_bulk_post_updated_messages( $messages, $counts ) {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				if ( ! in_array( $post_type->slug, array( 'post', 'page', 'attachment' ) ) ) {
					$post_type_messages = $post_type->get_bulk_updated_messages( $counts );
					if ( $post_type_messages && is_array( $post_type_messages ) ) {
						$messages[ $post_type->slug ] = $post_type_messages;
					}
				}
			}

			return $messages;
		}

		/**
		 * This filter returns the custom media post type labels.
		 *
		 * As of WordPress 4.4, those strings are automatically included, so this filter is not used there.
		 *
		 * @see WPPTD\Components\PostType
		 * @since 0.5.0
		 * @param array $strings the original media strings
		 * @param WP_Post $post the current post
		 * @return array the media strings including the custom media post type labels
		 */
		public function get_media_view_strings( $strings, $post ) {
			if ( $post ) {
				$post_type = ComponentManager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
				if ( $post_type ) {
					$labels = $post_type->labels;
					if ( is_array( $labels ) ) {
						if ( isset( $labels['insert_into_item'] ) && $labels['insert_into_item'] ) {
							$strings['insertIntoPost'] = $labels['insert_into_item'];
						}
						if ( isset( $labels['uploaded_to_this_item'] ) && $labels['uploaded_to_this_item'] ) {
							$strings['uploadedToThisPost'] = $labels['uploaded_to_this_item'];
						}
					}
				}
			}

			return $strings;
		}

		/**
		 * This filter returns the custom messages array for when a term of a certain taxonomy has been modified.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $messages the original messages
		 * @return array the custom messages to use
		 */
		public function get_term_updated_messages( $messages ) {
			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! in_array( $taxonomy->slug, array( '_item', 'category', 'post_tag' ) ) ) {
					$taxonomy_messages = $taxonomy->get_updated_messages();
					if ( $taxonomy_messages ) {
						$messages[ $taxonomy->slug ] = $taxonomy_messages;
					}
				}
			}

			return $messages;
		}

		/**
		 * Hooks in the functions to handle filtering and sorting in the post type list table.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTableHandler
		 * @since 0.5.0
		 */
		public function handle_table_filtering_and_sorting() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();

				$post_type_table_handler->maybe_sort_default();

				add_action( 'restrict_manage_posts', array( $post_type_table_handler, 'render_table_column_filters' ) );
				add_filter( 'query_vars', array( $post_type_table_handler, 'register_table_filter_query_vars' ) );
				add_action( 'pre_get_posts', array( $post_type_table_handler, 'maybe_filter_by_table_columns' ), 10, 1 );
				add_action( 'pre_get_posts', array( $post_type_table_handler, 'maybe_sort_by_meta_table_column' ), 10, 1 );
				add_filter( 'posts_clauses', array( $post_type_table_handler, 'maybe_sort_by_taxonomy_table_column' ), 10, 2 );
			}
		}

		/**
		 * This filter adds the custom row actions for a post type to the row actions array.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTableHandler
		 * @since 0.5.0
		 * @param array $actions the original row actions
		 * @param WP_Post $post the current post
		 * @return array the row actions including the custom ones
		 */
		public function get_row_actions( $actions, $post ) {
			$post_type = ComponentManager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();

				$actions = $post_type_table_handler->filter_row_actions( $actions, $post );
			}
			return $actions;
		}

		/**
		 * Hooks in the function to handle a certain row action if that row action should be run.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTableHandler
		 * @since 0.5.0
		 */
		public function handle_row_actions() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();

				if ( isset( $_REQUEST['action'] ) && ! empty( $_REQUEST['action'] ) ) {
					add_action( 'admin_action_' . $_REQUEST['action'], array( $post_type_table_handler, 'maybe_run_row_action' ) );
				}
			}
		}

		/**
		 * Hooks in the function to handle a certain bulk action if that bulk action should be run.
		 *
		 * The function furthermore hooks in functions to add the custom bulk actions to the dropdown (hacky)
		 * and to correctly displayed messages for bulk and row actions (hacky too).
		 *
		 * It has to be hacky because WordPress natively does not support it :(
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTableHandler
		 * @since 0.5.0
		 */
		public function handle_bulk_actions() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();

				if ( ( ! isset( $_REQUEST['action'] ) || -1 == $_REQUEST['action'] ) && isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
					$_REQUEST['action'] = $_REQUEST['action2'];
				}
				if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
					add_action( 'admin_action_' . $_REQUEST['action'], array( $post_type_table_handler, 'maybe_run_bulk_action' ) );
				}
				add_action( 'admin_head', array( $post_type_table_handler, 'hack_bulk_actions' ), 100 );
				add_filter( 'bulk_post_updated_messages', array( $post_type_table_handler, 'maybe_hack_bulk_message' ), 100, 2 );
			}
		}

		/**
		 * Hooks in all functions related to a post type.
		 *
		 * @since 0.5.0
		 */
		protected function add_post_type_hooks() {
			add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 3 );
			add_action( 'edit_form_top', array( $this, 'display_post_meta_errors' ), 10, 1 );
			add_action( 'load-post.php', array( $this, 'add_post_help' ) );
			add_action( 'load-post-new.php', array( $this, 'add_post_help' ) );
			add_action( 'load-edit.php', array( $this, 'add_post_list_help' ) );
			add_filter( 'enter_title_here', array( $this, 'get_post_enter_title_here' ), 10, 2 );
			add_filter( 'post_updated_messages', array( $this, 'get_post_updated_messages' ) );
			add_filter( 'bulk_post_updated_messages', array( $this, 'get_bulk_post_updated_messages' ), 10, 2 );
			if ( version_compare( get_bloginfo( 'version' ), '4.4' ) < 0 ) {
				add_filter( 'media_view_strings', array( $this, 'get_media_view_strings' ), 10, 2 );
			}
		}

		/**
		 * Hooks in all functions related to a taxonomy.
		 *
		 * @since 0.5.0
		 */
		protected function add_taxonomy_hooks() {
			add_action( 'load-edit-tags.php', array( $this, 'add_term_or_term_list_help' ) );
			add_filter( 'term_updated_messages', array( $this, 'get_term_updated_messages' ) );
		}

		/**
		 * Hooks in all functions related to a post type list table.
		 *
		 * @since 0.5.0
		 */
		protected function add_posts_table_hooks() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();

				add_filter( 'manage_taxonomies_for_' . $post_type->slug . '_columns', array( $post_type_table_handler, 'get_table_taxonomies' ) );
				add_filter( 'manage_' . $post_type->slug . '_posts_columns', array( $post_type_table_handler, 'filter_table_columns' ) );
				add_filter( 'manage_edit-' . $post_type->slug . '_sortable_columns', array( $post_type_table_handler, 'filter_table_sortable_columns' ) );
				add_action( 'manage_' . $post_type->slug . '_posts_custom_column', array( $post_type_table_handler, 'render_table_column' ), 10, 2 );
			}
			add_action( 'load-edit.php', array( $this, 'handle_table_filtering_and_sorting' ) );

			add_filter( 'page_row_actions', array( $this, 'get_row_actions' ), 10, 2 );
			add_filter( 'post_row_actions', array( $this, 'get_row_actions' ), 10, 2 );
			add_action( 'load-post.php', array( $this, 'handle_row_actions' ) );
			add_action( 'load-edit.php', array( $this, 'handle_bulk_actions' ) );
		}
	}
}
