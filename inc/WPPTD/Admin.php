<?php
/**
 * WPPTD\Admin class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
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
			$this->add_terms_table_hooks();
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
				$taxonomy = ComponentManager::get( '*.*.' . $screen->taxonomy, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
				if ( $taxonomy ) {
					if ( ( 0 <= version_compare( get_bloginfo( 'version' ), '4.5' ) && 'term' === $screen->base ) || ( 0 > version_compare( get_bloginfo( 'version' ), '4.5' ) && 'edit-tags' === $screen->base && isset( $_GET['tag_ID'] ) && is_numeric( $_GET['tag_ID'] ) ) ) {
						if ( wpptd_supports_termmeta() ) {
							$taxonomy->enqueue_assets();
						}

						/**
						 * This action can be used to enqueue additional scripts and stylesheets on a term editing screen for a specific taxonomy.
						 *
						 * @since 0.6.0
						 */
						do_action( 'wpptd_taxonomy_' . $taxonomy->slug . '_edit_enqueue_scripts' );
					} elseif ( 'edit-tags' === $screen->base ) {
						/**
						 * This action can be used to enqueue additional scripts and stylesheets on a terms list screen for a specific taxonomy.
						 *
						 * @since 0.6.0
						 */
						do_action( 'wpptd_taxonomy_' . $taxonomy->slug . '_list_enqueue_scripts' );
					}
				}
			} elseif ( isset( $screen->post_type ) && $screen->post_type ) {
				$post_type = ComponentManager::get( '*.' . $screen->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
				if ( $post_type ) {
					switch ( $screen->base ) {
						case 'post':
							$post_type->enqueue_assets();

							/**
							 * This action can be used to enqueue additional scripts and stylesheets on a post editing screen for a specific post type.
							 *
							 * @since 0.6.0
							 */
							do_action( 'wpptd_post_type_' . $post_type->slug . '_edit_enqueue_scripts' );
							break;
						case 'edit':
							/**
							 * This action can be used to enqueue additional scripts and stylesheets on a posts list screen for a specific post type.
							 *
							 * @since 0.6.0
							 */
							do_action( 'wpptd_post_type_' . $post_type->slug . '_list_enqueue_scripts' );
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
		 * Displays validation errors for post meta fields if any occurred.
		 *
		 * If there was a WordPress function like `add_post_meta_error()`, it would do something like this.
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
		 * Hooks in the functions to handle filtering and sorting in the post type list table.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTypeTableHandler
		 * @see WPPTD\PostTypeQueryHandler
		 * @since 0.5.0
		 */
		public function handle_table_filtering_and_sorting() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_table_handler = $post_type->get_table_handler();
				$post_type_query_handler = $post_type_table_handler->get_query_handler();

				$post_type_query_handler->maybe_sort_default();

				add_action( 'restrict_manage_posts', array( $post_type_table_handler, 'render_table_column_filters' ) );
				add_filter( 'query_vars', array( $post_type_query_handler, 'register_table_filter_query_vars' ) );
				add_action( 'pre_get_posts', array( $post_type_query_handler, 'maybe_filter_by_table_columns' ), 10, 1 );
				add_action( 'pre_get_posts', array( $post_type_query_handler, 'maybe_sort_by_meta_table_column' ), 10, 1 );
				add_filter( 'posts_clauses', array( $post_type_query_handler, 'maybe_sort_by_taxonomy_table_column' ), 10, 2 );
			}
		}

		/**
		 * This filter adds the custom row actions for a post type to the row actions array.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTypeActionHandler
		 * @since 0.5.0
		 * @param array $actions the original row actions
		 * @param WP_Post $post the current post
		 * @return array the row actions including the custom ones
		 */
		public function get_row_actions( $actions, $post ) {
			$post_type = ComponentManager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_action_handler = $post_type->get_table_handler()->get_action_handler();

				$actions = $post_type_action_handler->filter_row_actions( $actions, $post );
			}
			return $actions;
		}

		/**
		 * Hooks in the function to handle a certain row action if that row action should be run.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\PostTypeActionHandler
		 * @since 0.5.0
		 */
		public function handle_row_actions() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_action_handler = $post_type->get_table_handler()->get_action_handler();

				if ( isset( $_REQUEST['action'] ) && ! empty( $_REQUEST['action'] ) ) {
					add_action( 'admin_action_' . $_REQUEST['action'], array( $post_type_action_handler, 'maybe_run_row_action' ) );
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
		 * @see WPPTD\PostTypeActionHandler
		 * @since 0.5.0
		 */
		public function handle_bulk_actions() {
			global $typenow;

			$post_type = ComponentManager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type_action_handler = $post_type->get_table_handler()->get_action_handler();

				if ( version_compare( get_bloginfo( 'version' ), '4.7', '<' ) ) {
					if ( ( ! isset( $_REQUEST['action'] ) || -1 == $_REQUEST['action'] ) && isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
						$_REQUEST['action'] = $_REQUEST['action2'];
					}
					if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
						add_action( 'admin_action_' . $_REQUEST['action'], array( $post_type_action_handler, 'maybe_run_bulk_action' ) );
					}
					add_action( 'admin_head', array( $post_type_action_handler, 'hack_bulk_actions' ), 100 );
					add_action( 'admin_head', array( $post_type_action_handler, 'hack_bulk_action_error_message' ), 100 );
					add_filter( 'bulk_post_updated_messages', array( $post_type_action_handler, 'maybe_hack_action_message' ), 100, 2 );
				} else {
					add_filter( 'bulk_actions-edit-' . $post_type->slug, array( $post_type_action_handler, 'add_bulk_actions' ) );
					add_filter( 'handle_bulk_actions-edit-' . $post_type->slug, array( $post_type_action_handler, 'maybe_handle_bulk_action' ), 10, 3 );
					add_action( 'admin_notices', array( $post_type_action_handler, 'maybe_display_bulk_action_message' ) );
				}
			}
		}

		/**
		 * Initializes the plugin's term meta UI.
		 *
		 * The actions where metaboxes can be added are run in this function.
		 *
		 * @since 0.6.0
		 */
		public function initialize_term_ui() {
			$screen = get_current_screen();
			if ( ! isset( $_REQUEST['tag_ID'] ) ) {
				return;
			}

			$term = get_term( absint( $_REQUEST['tag_ID'] ) );
			if ( ! is_a( $term, 'WP_Term' ) ) {
				return;
			}

			$taxonomy = $screen->taxonomy;
			if ( empty( $taxonomy ) ) {
				$taxonomy = $term->taxonomy;
			}

			$taxonomy = ComponentManager::get( '*.*.' . $taxonomy, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( ! $taxonomy ) {
				return;
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_scripts' ) );

			add_meta_box( 'submitdiv', __( 'Update' ), array( $this, 'term_submit_metabox' ), null, 'side', 'core' );

			/**
			 * This action can be used to add metaboxes to the term editing screen.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current taxonomy
			 * @param WP_Term $term the current term object
			 */
			do_action( 'wpptd_add_term_meta_boxes', $taxonomy->slug, $term );

			/**
			 * This action can be used to add metaboxes to the term editing screen of a specific taxonomy.
			 *
			 * @since 0.6.0
			 * @param WP_Term $term the current term object
			 */
			do_action( 'wpptd_add_term_meta_boxes_' . $taxonomy->slug, $term );
		}

		/**
		 * Enqueues the metabox scripts.
		 *
		 * These are needed on the term edit page for the custom term meta UI.
		 *
		 * @since 0.6.0
		 */
		public function enqueue_metabox_scripts() {
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'postbox' );
		}

		/**
		 * Wraps the original Edit Term content (top part).
		 *
		 * This function is very hacky since it is actually hooked into the form tag itself.
		 * That is also the reason for the weird-looking HTML (it IS correct though).
		 *
		 * It is only needed on WordPress version 4.4 where the '{$taxonomy}_term_edit_form_top' action did not exist yet.
		 *
		 * @since 0.6.0
		 */
		public function wrap_term_ui_top_hack() {
			echo '>';

			?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
			<?php

			echo '<div id="post-body-content"';
		}

		/**
		 * Wraps the original Edit Term content (top part).
		 *
		 * This function is used on WordPress >= 4.5
		 *
		 * @since 0.6.1
		 */
		public function wrap_term_ui_top() {
			?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
			<?php
		}

		/**
		 * Wraps the original Edit Term content (bottom part).
		 *
		 * The necessary nonce fields for the metaboxes and the metabox script are printed.
		 * Furthermore the metaboxes are outputted with a UI that works similar like when editing a post.
		 *
		 * The original Submit button is hidden via CSS since we now have a metabox that contains it already.
		 *
		 * @since 0.6.0
		 * @param WP_Term $term the current term object
		 * @param string $taxonomy the current taxonomy
		 */
		public function wrap_term_ui_bottom( $term, $taxonomy ) {
			?>
					</div>

					<?php wp_nonce_field( 'wpptd-save-term-' . $taxonomy, 'wpptd_edit_term_' . $taxonomy ); ?>
					<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
					<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

					<div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( null, 'side', $term ); ?>
					</div>

					<div id="postbox-container-2" class="postbox-container">
						<?php do_meta_boxes( null, 'normal', $term ); ?>
						<?php do_meta_boxes( null, 'advanced', $term ); ?>
					</div>

				</div>

				<br class="clear" />

				<style type="text/css">
					#poststuff + .submit {
						display: none;
					}
				</style>

				<script type="text/javascript">
					//<![CDATA[
					jQuery( document ).ready( function ( $ ) {
						// close postboxes that should be closed
						$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
						// postboxes setup
						postboxes.add_postbox_toggles( 'edit-<?php echo $taxonomy; ?>' );
					});
					//]]>
				</script>

			</div>

			<?php
		}

		/**
		 * Renders the Submit metabox for the Edit Term screen.
		 *
		 * This metabox now contains the Submit button (similar like when editing a post).
		 *
		 * @since 0.6.0
		 * @param WP_Term $term the current term object
		 */
		public function term_submit_metabox( $term ) {
			$screen = get_current_screen();

			$tax = $screen->taxonomy;
			if ( empty( $tax ) ) {
				$tax = $term->taxonomy;
			}
			$tax = get_taxonomy( $tax );

			$type = $screen->post_type;
			if ( empty( $type ) ) {
				$type = reset( $tax->object_type );
			}
			$type = get_post_type_object( $type );

			$base_url = 'edit.php';
			$args = array();
			if ( $tax->query_var ) {
				$args[ $tax->query_var ] = $term->slug;
			} else {
				$args['taxonomy'] = $tax->name;
				$args['term'] = $term->slug;
			}

			switch ( $type->name ) {
				case 'post':
					break;
				case 'attachment':
					$base_url = 'upload.php';
					break;
				case 'link':
					$base_url = 'link-manager.php';
					$args['cat_id'] = $term->term_id;
					break;
				default:
					$args['post_type'] = $type->name;
			}

			?>
			<div class="submitbox" id="submitpost">
				<div id="minor-publishing">
					<div style="display:none;">
						<?php submit_button( __( 'Update' ), 'button', 'save' ); ?>
					</div>
					<div class="misc-publishing-actions">
						<div class="misc-pub-section">
							<?php if ( 1 == $term->count ) : ?>
								<span><?php printf( __( '<a href="%1$s">%2$s %3$s</a> available for %4$s %5$s', 'post-types-definitely' ), esc_url( add_query_arg( $args, $base_url ) ), number_format_i18n( $term->count ), $type->labels->singular_name, $tax->labels->singular_name, $term->name ); ?></span>
							<?php else : ?>
								<span><?php printf( __( '<a href="%1$s">%2$s %3$s</a> available for %4$s %5$s', 'post-types-definitely' ), esc_url( add_query_arg( $args, $base_url ) ), number_format_i18n( $term->count ), $type->labels->name, $tax->labels->singular_name, $term->name ); ?></span>
							<?php endif; ?>
						</div>
						<?php
						/**
						 * This action can be used to print additional content in the Misc section of the Term Submit metabox.
						 *
						 * This is the equivalent of a post's `post_submitbox_misc_actions` hook.
						 *
						 * @since 0.6.0
						 * @param WP_Term $term the current term object
						 */
						do_action( 'wpptd_term_submitbox_misc_actions', $term );
						?>
					</div>
					<div class="clear"></div>
				</div>
				<div id="major-publishing-actions">
					<?php
					/**
					 * This action can be used to print additional content in the Submit section of the Term Submit metabox.
					 *
					 * This is the equivalent of a post's `post_submitbox_start` hook.
					 *
					 * @since 0.6.0
					 */
					do_action( 'wpptd_term_submitbox_start' );
					?>
					<div id="publishing-action">
					<?php submit_button( __( 'Update' ), 'primary button-large', 'publish', false ); ?>
					</div>
					<div class="clear"></div>
				</div>
			</div>
			<?php
		}

		/**
		 * Wrapper function to control saving meta values for a taxonomy registered with the plugin.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.6.0
		 * @param integer $term_id term ID of the term to be saved
		 * @param integer $tt_id term taxonomy ID of the term to be saved
		 * @param string $tax the term's taxonomy name
		 */
		public function save_term_meta( $term_id, $tt_id, $tax ) {
			$nonce = isset( $_POST[ 'wpptd_edit_term_' . $tax ] ) ? sanitize_key( $_POST[ 'wpptd_edit_term_' . $tax ] ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpptd-save-term-' . $tax ) ) {
				return;
			}

			$taxonomy = ComponentManager::get( '*.*.' . $tax, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$term = get_term( $term_id );
				$taxonomy->save_meta( $term_id, $term, true );
			}
		}

		/**
		 * Displays validation errors for term meta fields if any occurred.
		 *
		 * This function is very hacky since it is actually hooked into the form tag itself.
		 * That is also the reason for the weird-looking HTML (it IS correct though).
		 *
		 * It is only needed on WordPress version 4.4 where the '{$taxonomy}_term_edit_form_top' action did not exist yet.
		 *
		 * @since 0.6.0
		 */
		public function display_term_meta_errors_hack() {
			if ( ! isset( $_REQUEST['tag_ID'] ) ) {
				return;
			}

			$term = get_term( (int) $_REQUEST['tag_ID'] );

			$errors = get_transient( 'wpptd_term_meta_error_' . $term->taxonomy . '_' . $term->term_id );
			if ( $errors ) {
				echo '><div id="wpptd-term-meta-errors" class="notice notice-error is-dismissible"><p>';
				echo $errors;
				echo '</p></div';
				delete_transient( 'wpptd_term_meta_error_' . $term->taxonomy . '_' . $term->term_id );
			}
		}

		/**
		 * Displays validation errors for term meta fields if any occurred.
		 *
		 * This function is used on WordPress >= 4.5
		 *
		 * @since 0.6.1
		 */
		public function display_term_meta_errors( $term ) {
			$errors = get_transient( 'wpptd_term_meta_error_' . $term->taxonomy . '_' . $term->term_id );
			if ( $errors ) {
				?>
				<div id="wpptd-term-meta-errors" class="notice notice-error is-dismissible">
					<p><?php echo $errors; ?></p>
				</div>
				<?php
				delete_transient( 'wpptd_term_meta_error_' . $term->taxonomy . '_' . $term->term_id );
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a taxonomy editing screen.
		 *
		 * This is only used on WordPress >= 4.5.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.6.2
		 */
		public function add_term_help() {
			global $taxnow;

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy->render_help();
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a taxonomy list screen.
		 *
		 * This is only used on WordPress >= 4.5.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.6.2
		 */
		public function add_term_list_help() {
			global $taxnow;

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy->render_list_help();
			}
		}

		/**
		 * Wrapper function to control the addition of help tabs to a taxonomy editing or list screen.
		 *
		 * This is only used on WordPress < 4.5.
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
		 * Hooks in the functions to handle sorting in the taxonomy list table.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @see WPPTD\TaxonomyTableHandler
		 * @see WPPTD\TaxonomyQueryHandler
		 * @since 0.6.1
		 */
		public function handle_term_table_sorting() {
			global $taxnow;

			// do not run this on a term edit form
			if ( isset( $_GET['tag_ID'] ) ) {
				return;
			}

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy_table_handler = $taxonomy->get_table_handler();
				$taxonomy_query_handler = $taxonomy_table_handler->get_query_handler();

				add_filter( 'get_terms_args', array( $taxonomy_query_handler, 'maybe_sort_by_meta_table_column' ), 10, 2 );
			}
		}

		/**
		 * This filter adds the custom row actions for a taxonomy to the row actions array.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @see WPPTD\TaxonomyActionHandler
		 * @since 0.6.0
		 * @param array $actions the original row actions
		 * @param WP_Term $term the current term
		 * @return array the row actions including the custom ones
		 */
		public function get_term_row_actions( $actions, $term ) {
			$taxonomy = ComponentManager::get( '*.*.' . $term->taxonomy, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy_action_handler = $taxonomy->get_table_handler()->get_action_handler();

				$actions = $taxonomy_action_handler->filter_row_actions( $actions, $term );
			}
			return $actions;
		}

		/**
		 * Hooks in the function to handle a certain row action if that row action should be run.
		 *
		 * @see WPPTD\Components\Taxonomy
		 * @see WPPTD\TaxonomyActionHandler
		 * @since 0.6.0
		 */
		public function handle_term_row_actions() {
			global $taxnow;

			// do not run this on a terms list
			if ( ! isset( $_GET['tag_ID'] ) ) {
				return;
			}

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy_action_handler = $taxonomy->get_table_handler()->get_action_handler();

				if ( isset( $_REQUEST['action'] ) && ! empty( $_REQUEST['action'] ) ) {
					add_action( 'admin_action_' . $_REQUEST['action'], array( $taxonomy_action_handler, 'maybe_run_row_action' ) );
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
		 * @see WPPTD\Components\Taxonomy
		 * @see WPPTD\TaxonomyActionHandler
		 * @since 0.6.0
		 */
		public function handle_term_bulk_actions() {
			global $taxnow;

			// do not run this on a term edit form
			if ( isset( $_GET['tag_ID'] ) ) {
				return;
			}

			$taxonomy = ComponentManager::get( '*.*.' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy_action_handler = $taxonomy->get_table_handler()->get_action_handler();

				if ( version_compare( get_bloginfo( 'version' ), '4.7', '<' ) ) {
					if ( ( ! isset( $_REQUEST['action'] ) || -1 == $_REQUEST['action'] ) && isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
						$_REQUEST['action'] = $_REQUEST['action2'];
					}
					if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
						add_action( 'admin_action_' . $_REQUEST['action'], array( $taxonomy_action_handler, 'maybe_run_bulk_action' ) );
					}

					add_action( 'admin_head', array( $taxonomy_action_handler, 'hack_bulk_actions' ), 100 );
					add_filter( 'term_updated_messages', array( $taxonomy_action_handler, 'maybe_hack_action_message' ), 100 );
				} else {
					add_filter( 'bulk_actions-edit-' . $taxonomy->slug, array( $taxonomy_action_handler, 'add_bulk_actions' ) );
					add_filter( 'handle_bulk_actions-edit-' . $taxonomy->slug, array( $taxonomy_action_handler, 'maybe_handle_bulk_action' ), 10, 3 );
					add_action( 'admin_notices', array( $taxonomy_action_handler, 'maybe_display_bulk_action_message' ) );
				}
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
			if ( 0 > version_compare( get_bloginfo( 'version' ), '4.5' ) ) {
				add_action( 'load-edit-tags.php', array( $this, 'add_term_or_term_list_help' ) );
			} else {
				add_action( 'load-edit-tags.php', array( $this, 'add_term_list_help' ) );
				add_action( 'load-term.php', array( $this, 'add_term_help' ) );
			}

			add_filter( 'term_updated_messages', array( $this, 'get_term_updated_messages' ) );

			if ( wpptd_supports_termmeta() ) {
				$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );

				$edit_form_top_hook_suffix = '_term_edit_form_top';
				$edit_form_top_method_suffix = '';
				if ( 0 > version_compare( get_bloginfo( 'version' ), '4.5' ) ) {
					// so hacky (luckily only in WordPress < 4.5)
					$edit_form_top_hook_suffix = '_term_edit_form_tag';
					$edit_form_top_method_suffix = '_hack';
				}

				foreach ( $taxonomies as $taxonomy ) {
					add_action( $taxonomy->slug . $edit_form_top_hook_suffix, array( $this, 'display_term_meta_errors' . $edit_form_top_method_suffix ), 9998 );
					add_action( $taxonomy->slug . $edit_form_top_hook_suffix, array( $this, 'wrap_term_ui_top' . $edit_form_top_method_suffix ), 9999 );

					add_action( $taxonomy->slug . '_edit_form', array( $this, 'wrap_term_ui_bottom' ), 9999, 2 );
				}

				add_action( 'load-edit-tags.php', array( $this, 'initialize_term_ui' ) );
				add_action( 'edit_term', array( $this, 'save_term_meta' ), 10, 3 );
			}
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

		/**
		 * Hooks in all functions related to a taxonomy list table.
		 *
		 * @since 0.6.0
		 */
		protected function add_terms_table_hooks() {
			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_table_handler = $taxonomy->get_table_handler();

				add_filter( 'manage_edit-' . $taxonomy->slug . '_columns', array( $taxonomy_table_handler, 'filter_table_columns' ) );
				if ( 0 <= version_compare( get_bloginfo( 'version' ), '4.5' ) ) {
					add_filter( 'manage_edit-' . $taxonomy->slug . '_sortable_columns', array( $taxonomy_table_handler, 'filter_table_sortable_columns' ) );
				}
				add_filter( 'manage_' . $taxonomy->slug . '_custom_column', array( $taxonomy_table_handler, 'filter_table_column_output' ), 10, 3 );

				add_filter( $taxonomy->slug . '_row_actions', array( $this, 'get_term_row_actions' ), 10, 2 );
			}
			add_action( 'load-edit-tags.php', array( $this, 'handle_term_table_sorting' ) );

			add_action( 'load-edit-tags.php', array( $this, 'handle_term_row_actions' ) );
			add_action( 'load-edit-tags.php', array( $this, 'handle_term_bulk_actions' ) );
		}
	}
}
