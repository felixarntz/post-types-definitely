<?php
/**
 * WPPTD\ActionHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\ActionHandler' ) ) {
	/**
	 * An abstract row and bulk actions handler for a post type or taxonomy.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	abstract class ActionHandler {
		/**
		 * @since 0.6.1
		 * @var WPDLib\Components\Base Holds the component this action handler should manage.
		 */
		protected $component = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.6.1
		 * @param WPDLib\Components\Base $component the component to use this handler for
		 */
		public function __construct( $component ) {
			$this->component = $component;
		}

		/**
		 * This filter adjusts the available row actions.
		 *
		 * @since 0.6.1
		 * @param array $row_actions the original array of row actions
		 * @param object $item the current post or term object
		 * @return array the adjusted row actions array
		 */
		public function filter_row_actions( $row_actions, $item ) {
			$table_row_actions = $this->component->row_actions;

			if ( ! call_user_func_array( 'current_user_can', $this->get_row_capability_args( $item ) ) ) {
				return $row_actions;
			}

			foreach ( $table_row_actions as $action_slug => $action_args ) {
				// do not allow overriding of existing actions
				if ( isset( $row_actions[ $action_slug ] ) ) {
					continue;
				}

				$url = $this->get_row_base_url( $item );
				if ( ! $url ) {
					continue;
				}

				$url = add_query_arg( 'action', $action_slug, $url );

				$nonce_name = $this->get_row_nonce_name( $action_slug, $item );
				if ( $nonce_name ) {
					$url = wp_nonce_url( $url, $nonce_name );
				}

				$row_actions[ $action_slug ] = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $action_args['title'] ) . '">' . esc_html( $action_args['title'] ) . '</a>';
			}

			return $row_actions;
		}

		/**
		 * This action is a general router to check whether a specific row action should be performed.
		 *
		 * It will run the action if necessary.
		 *
		 * @since 0.6.1
		 * @see WPPTD\ActionHandler::run_row_action()
		 */
		public function maybe_run_row_action() {
			$table_row_actions = $this->component->row_actions;

			$row_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_row_actions[ $row_action ] ) ) {
				return;
			}

			$item_id = $this->get_row_id();

			if ( ! $item_id ) {
				return;
			}

			$item_id = intval( $item_id );

			$this->run_row_action( $row_action, $item_id );
		}

		/**
		 * This action is a general router to check whether a specific bulk action should be performed.
		 *
		 * It will run the action if necessary.
		 *
		 * @since 0.6.7
		 * @access public
		 *
		 * @see WPPTD\ActionHandler::handle_bulk_action()
		 *
		 * @param string $sendback    Sendback URL to redirect to.
		 * @param string $bulk_action Slug of the bulk action to handle.
		 * @param array  $item_ids    Array of item IDs.
		 * @return string The modified sendback URL.
		 */
		public function maybe_handle_bulk_action( $sendback, $bulk_action, $item_ids ) {
			$table_bulk_actions = $this->component->bulk_actions;
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return $sendback;
			}

			if ( ! $item_ids ) {
				return $sendback;
			}

			$item_ids = array_map( 'intval', $item_ids );

			return $this->handle_bulk_action( $sendback, $bulk_action, $item_ids );
		}

		/**
		 * Displays a bulk action result message if necessary.
		 *
		 * @since 0.6.7
		 * @access public
		 */
		public function maybe_display_bulk_action_message() {
			if ( empty( $_GET['message'] ) || ! in_array( $_GET['message'], array( 'wpptd_bulk_action_error', 'wpptd_bulk_action_success' ), true ) ) {
				return;
			}

			$class = 'wpptd_bulk_action_error' === $_GET['message'] ? 'error' : 'updated';

			$transient_name = $this->get_message_transient_name();

			$action_message = get_transient( $transient_name );
			if ( ! $action_message ) {
				return;
			}

			delete_transient( $transient_name );

			echo '<div id="message" class="' . $class . ' notice is-dismissible"><p>' . $action_message . '</p></div>';
		}

		/**
		 * This action is a general router to check whether a specific bulk action should be performed.
		 *
		 * It will run the action if necessary.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @see WPPTD\ActionHandler::run_bulk_action()
		 */
		public function maybe_run_bulk_action() {
			$table_bulk_actions = $this->component->bulk_actions;

			$bulk_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return;
			}

			$item_ids = $this->get_bulk_ids();

			if ( ! $item_ids ) {
				return;
			}

			$item_ids = array_map( 'intval', $item_ids );

			$this->run_bulk_action( $bulk_action, $item_ids );
		}

		/**
		 * This action adds the custom bulk actions.
		 *
		 * @since 0.6.7
		 * @access public
		 *
		 * @param array $actions The original bulk actions.
		 * @return array The modified bulk actions.
		 */
		public abstract function add_bulk_actions( $actions );

		/**
		 * A hack to extend the bulk actions dropdown with custom bulk actions via JavaScript.
		 *
		 * WordPress did not natively support this until version 4.7. That's why we need this ugly solution.
		 *
		 * @since 0.6.1
		 */
		public abstract function hack_bulk_actions();

		/**
		 * A hack to display a custom message for the current action instead of the default message.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param array $bulk_messages the original array of bulk messages
		 * @param array $bulk_counts the counts of updated posts
		 * @return array the (temporarily) updated array of bulk messages
		 */
		public abstract function maybe_hack_action_message( $bulk_messages, $bulk_counts = array() );

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run row actions.
		 *
		 * @since 0.6.1
		 * @param object|integer $item a post or term object or ID
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected abstract function get_row_capability_args( $item );

		/**
		 * Returns the base admin URL for a post or term.
		 *
		 * @since 0.6.1
		 * @param object $item a post or term object
		 * @return string base admin URL for this post or term
		 */
		protected abstract function get_row_base_url( $item );

		/**
		 * Returns the name of the nonce that should be used to check for a specific post or term action.
		 *
		 * @since 0.6.1
		 * @param string $action_slug the slug of the action to perform
		 * @param object|integer $item a post or term object or ID
		 * @return string name of the nonce
		 */
		protected abstract function get_row_nonce_name( $action_slug, $item );

		/**
		 * Returns the ID of a post / term that a row action should be performed on.
		 *
		 * @since 0.6.1
		 * @return integer a post or term ID
		 */
		protected abstract function get_row_id();

		/**
		 * Returns the sendback URL to return to after a row action has been run.
		 *
		 * @since 0.6.1
		 * @param string $message the resulting notification of the row action
		 * @param boolean $error whether the message is an error message
		 * @return string the sendback URL to redirect to
		 */
		protected abstract function get_row_sendback_url( $message, $error = false );

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run bulk actions.
		 *
		 * @since 0.6.1
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected abstract function get_bulk_capability_args();

		/**
		 * Returns the name of the nonce that should be used to check for a bulk action.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @return string name of the nonce
		 */
		protected abstract function get_bulk_nonce_name();

		/**
		 * Returns an array of post / term IDs that a bulk action should be performed on.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @return array post or term IDs
		 */
		protected abstract function get_bulk_ids();

		/**
		 * Returns the sendback URL to return to after a bulk action has been run.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param string $message the resulting notification of the bulk action
		 * @param boolean $error whether the message is an error message
		 * @param integer $count the number of posts or terms affected
		 * @return string the sendback URL to redirect to
		 */
		protected abstract function get_bulk_sendback_url( $message, $error = false, $count = 0 );

		/**
		 * Returns the name of the transient under which messages should be stored.
		 *
		 * @since 0.6.1
		 * @return string the name of the transient
		 */
		protected abstract function get_message_transient_name();

		/**
		 * Performs a specific row action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every row action must accept exactly one parameter, the post or term ID.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient to be printed out after the redirect.
		 *
		 * @since 0.6.1
		 * @param string $row_action the row action slug
		 * @param integer $item_id the post or term ID to perform the action on
		 */
		protected function run_row_action( $row_action, $item_id ) {
			$row_actions = $this->component->row_actions;
			$component_singular_title = $this->component->singular_title;

			check_admin_referer( $this->get_row_nonce_name( $row_action, $item_id ) );

			$action_message = false;
			$error = false;
			if ( ! call_user_func_array( 'current_user_can', $this->get_row_capability_args( $item_id ) ) ) {
				$action_message = sprintf( __( 'The %s was not updated because of missing privileges.', 'post-types-definitely' ), $component_singular_title );
				$error = true;
			} elseif ( empty( $row_actions[ $row_action ]['callback'] ) || ! is_callable( $row_actions[ $row_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s was not updated since an internal error occurred.', 'post-types-definitely' ), $component_singular_title );
				$error = true;
			} else {
				$action_message = call_user_func( $row_actions[ $row_action ]['callback'], $item_id );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				if ( $error ) {
					// TODO: fix this hack (or only do it for posts)
					$action_message = '<span class="wpptd-error-hack hidden"></span>' . $action_message;
				}
				$transient_name = $this->get_message_transient_name();
				set_transient( $transient_name, $action_message, MINUTE_IN_SECONDS );
			}

			$sendback = $this->get_row_sendback_url( $action_message, $error );
			if ( ! $sendback ) {
				$sendback = admin_url();
			}

			wp_redirect( $sendback );
			exit();
		}

		/**
		 * Performs a specific bulk action and adjusts the redirect URL accordingly.
		 *
		 * The callback function of every bulk action must accept exactly one parameter, an array of post IDs.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient to be printed out after the redirect.
		 *
		 * @since 0.6.7
		 * @access protected
		 *
		 * @param string $sendback    Sendback URL to redirect to.
		 * @param string $bulk_action Slug of the bulk action to handle.
		 * @param array  $item_ids    Array of item IDs.
		 * @return string The modified sendback URL.
		 */
		protected function handle_bulk_action( $sendback, $bulk_action, $item_ids ) {
			$bulk_actions = $this->component->bulk_actions;
			$component_plural_name = $this->component->title;

			$action_message = false;
			$error = false;
			if ( ! call_user_func_array( 'current_user_can', $this->get_bulk_capability_args() ) ) {
				$action_message = sprintf( __( 'The %s were not updated because of missing privileges.', 'post-types-definitely' ), $component_plural_name );
				$error = true;
			} elseif ( empty( $bulk_actions[ $bulk_action ]['callback'] ) || ! is_callable( $bulk_actions[ $bulk_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s were not updated since an internal error occurred.', 'post-types-definitely' ), $component_plural_name );
				$error = true;
			} else {
				$action_message = call_user_func( $bulk_actions[ $bulk_action ]['callback'], $item_ids );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				$transient_name = $this->get_message_transient_name();
				set_transient( $transient_name, $action_message, MINUTE_IN_SECONDS );
			}

			return add_query_arg( 'message', 'wpptd_bulk_action_' . ( $error ? 'error' : 'success' ), $sendback );
		}

		/**
		 * Performs a specific bulk action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every bulk action must accept exactly one parameter, an array of post IDs.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient to be printed out after the redirect.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param string $bulk_action the bulk action slug
		 * @param array $item_ids the array of post or term IDs to perform the action on
		 */
		protected function run_bulk_action( $bulk_action, $item_ids ) {
			$bulk_actions = $this->component->bulk_actions;
			$component_plural_name = $this->component->title;

			check_admin_referer( $this->get_bulk_nonce_name() );

			$action_message = false;
			$error = false;
			if ( ! call_user_func_array( 'current_user_can', $this->get_bulk_capability_args() ) ) {
				$action_message = sprintf( __( 'The %s were not updated because of missing privileges.', 'post-types-definitely' ), $component_plural_name );
				$error = true;
			} elseif ( empty( $bulk_actions[ $bulk_action ]['callback'] ) || ! is_callable( $bulk_actions[ $bulk_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s were not updated since an internal error occurred.', 'post-types-definitely' ), $component_plural_name );
				$error = true;
			} else {
				$action_message = call_user_func( $bulk_actions[ $bulk_action ]['callback'], $item_ids );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				if ( $error ) {
					// TODO: fix this hack (or only do it for posts)
					$action_message = '<span class="wpptd-error-hack hidden"></span>' . $action_message;
				}
				$transient_name = $this->get_message_transient_name();
				set_transient( $transient_name, $action_message, MINUTE_IN_SECONDS );
			}

			$sendback = $this->get_bulk_sendback_url( $action_message, $error, count( $item_ids ) );
			if ( ! $sendback ) {
				$sendback = admin_url();
			}

			wp_redirect( $sendback );
			exit();
		}
	}
}
