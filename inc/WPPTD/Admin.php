<?php
/**
 * @package WPPTD
 * @version 0.5.0
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
		 * This will hook functions into the 'admin_menu' and 'admin_enqueue_scripts' actions.
		 *
		 * @since 0.5.0
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'create_admin_menu' ), 50 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 3 );
			add_action( 'edit_form_top', array( $this, 'display_meta_errors' ), 10, 1 );
			add_action( 'load-post.php', array( $this, 'add_post_help' ) );
			add_action( 'load-post-new.php', array( $this, 'add_post_help' ) );
			add_action( 'load-edit.php', array( $this, 'add_post_list_help' ) );
			add_filter( 'enter_title_here', array( $this, 'get_post_enter_title_here' ), 10, 2 );
			add_filter( 'post_updated_messages', array( $this, 'get_post_updated_messages' ) );

			add_action( 'load-edit-tags.php', array( $this, 'add_term_help' ) );
			add_filter( 'term_updated_messages', array( $this, 'get_term_updated_messages' ) );
		}

		/**
		 * Adds post types and taxonomies to the WordPress admin menu.
		 *
		 * Every post type / taxonomy will be added to the menu it has been assigned to.
		 * Furthermore the function to add a help tab is hooked into the post type / taxonomy loading action.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 */
		public function create_admin_menu() {
			$post_types = ComponentManager::get( '*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType' );
			foreach ( $post_types as $post_type ) {
				$post_type->add_to_menu();
			}

			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy->add_to_menu();
			}
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
					//TODO: enqueue assets?
				}
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

		public function save_post_meta( $post_id, $post, $update = false ) {
			$post_type = \WPDLib\Components\Manager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->save_meta( $post_id, $post, $update );
			}
		}

		public function display_meta_errors( $post ) {
			$errors = get_transient( 'wpptd_meta_error_' . $post->post_type . '_' . $post->ID );
			if ( $errors ) {
				echo '<div id="wpptd-post-meta-errors" class="notice notice-error is-dismissible"><p>';
				echo $errors;
				echo '</p></div>';
				delete_transient( 'wpptd_meta_error_' . $post->post_type . '_' . $post->ID );
			}
		}

		public function add_post_help() {
			global $typenow;

			$post_type = \WPDLib\Components\Manager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->render_help();
			}
		}

		public function add_post_list_help() {
			global $typenow;

			$post_type = \WPDLib\Components\Manager::get( '*.' . $typenow, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$post_type->render_list_help();
			}
		}

		public function add_term_help() {
			global $taxnow;

			$taxonomy = \WPDLib\Components\Manager::get( '*.*' . $taxnow, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
			if ( $taxonomy ) {
				$taxonomy->render_help();
			}
		}

		public function get_post_enter_title_here( $text, $post ) {
			$post_type = \WPDLib\Components\Manager::get( '*.' . $post->post_type, 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
			if ( $post_type ) {
				$_text = $post_type->get_enter_title_here( $post );
				if ( $_text ) {
					return $_text;
				}
			}
			return $text;
		}

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
					$messages[ $post_type->slug ] = $post_type->get_updated_messages( $post, $permalink, $revision );
				}
			}

			return $messages;
		}

		public function get_term_updated_messages( $messages ) {
			$taxonomies = ComponentManager::get( '*.*.*', 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy' );
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! in_array( $taxonomy->slug, array( '_item', 'category', 'post_tag' ) ) ) {
					$messages[ $taxonomy->slug ] = $taxonomy->get_updated_messages();
				}
			}

			return $messages;
		}
	}
}
