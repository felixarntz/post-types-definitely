<?php
/**
 * WPPTD\PostTypeActionHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\PostTypeActionHandler' ) ) {
	/**
	 * This class handles row and bulk actions for a post type registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class PostTypeActionHandler extends ActionHandler {
		/**
		 * This action adds the custom post type bulk actions.
		 *
		 * @since 0.6.7
		 * @access public
		 *
		 * @param array $actions The original bulk actions.
		 * @return array The modified bulk actions.
		 */
		public function add_bulk_actions( $actions ) {
			$table_bulk_actions = $this->component->bulk_actions;

			foreach ( $table_bulk_actions as $action_slug => $action_args ) {
				$actions[ $action_slug ] = $action_args['title'];
			}

			return $actions;
		}

		/**
		 * This action is a hack to extend the bulk actions dropdown with custom bulk actions via JavaScript.
		 *
		 * WordPress did not natively support this until version 4.7. That's why we need this ugly solution.
		 *
		 * Another thing the function does is checking whether a row/bulk action message outputted by the plugin
		 * is actually an error message. In that case, the CSS class of it is changed accordingly.
		 *
		 * @since 0.6.1
		 */
		public function hack_bulk_actions() {
			$table_bulk_actions = $this->component->bulk_actions;

			?>
			<script type="text/javascript">
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).ready( function( $ ) {
						var options = '';
						<?php if ( ! isset( $_GET['post_status'] ) || 'trash' != $_GET['post_status'] ) : ?>
						<?php foreach ( $table_bulk_actions as $action_slug => $action_args ) : ?>
						options += '<option value="<?php echo $action_slug; ?>"><?php echo $action_args['title']; ?></option>';
						<?php endforeach; ?>
						<?php endif; ?>

						if ( options ) {
							$( '#bulk-action-selector-top' ).append( options );
							$( '#bulk-action-selector-bottom' ).append( options );
						}
					});
				}
			</script>
			<?php
		}

		/**
		 * Hacks around with the bulk action error message if one exists.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.7
		 * @access public
		 */
		public function hack_bulk_action_error_message() {
			?>
			<script type="text/javascript">
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).ready( function( $ ) {
						if ( $( '#message .wpptd-error-hack' ).length > 0 ) {
							$( '#message' ).addClass( 'error' ).removeClass( 'updated' );
							$( '#message .wpptd-error-hack' ).remove();
						}
					});
				}
			</script>
			<?php
		}

		/**
		 * This filter adjusts the bulk messages if a custom row/bulk action has just been executed.
		 *
		 * It is basically a hack to display a custom message for that action instead of the default message.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param array $bulk_messages the original array of bulk messages
		 * @param array $bulk_counts the counts of updated posts
		 * @return array the (temporarily) updated array of bulk messages
		 */
		public function maybe_hack_action_message( $bulk_messages, $bulk_counts = array() ) {
			if ( 0 < $bulk_counts['updated'] ) {
				$transient_name = $this->get_message_transient_name();
				$action_message = get_transient( $transient_name );
				if ( $action_message ) {
					delete_transient( $transient_name );

					if ( ! isset( $bulk_messages[ $this->component->slug ] ) ) {
						$bulk_messages[ $this->component->slug ] = array();
					}
					$bulk_messages[ $this->component->slug ]['updated'] = $action_message;
				}
			}

			return $bulk_messages;
		}

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run row actions.
		 *
		 * @since 0.6.1
		 * @param WP_Post|integer $post a post object or ID
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected function get_row_capability_args( $post ) {
			if ( is_object( $post ) ) {
				$post = $post->ID;
			}
			return array( 'edit_post', $post );
		}

		/**
		 * Returns the base admin URL for a post.
		 *
		 * @since 0.6.1
		 * @param WP_Post $post a post object
		 * @return string base admin URL for this post
		 */
		protected function get_row_base_url( $post ) {
			return get_edit_post_link( $post->ID, 'raw' );
		}

		/**
		 * Returns the name of the nonce that should be used to check for a specific post row action.
		 *
		 * @since 0.6.1
		 * @param string $action_slug the slug of the action to perform
		 * @param WP_Post|integer $post a post object or ID
		 * @return string name of the nonce
		 */
		protected function get_row_nonce_name( $action_slug, $post ) {
			if ( is_object( $post ) ) {
				$post = $post->ID;
			}
			return $action_slug . '-post_' . $post;
		}

		/**
		 * Returns the ID of a post that a row action should be performed on.
		 *
		 * @since 0.6.1
		 * @return integer a post ID
		 */
		protected function get_row_id() {
			$request_data = $_REQUEST;

			if ( isset( $request_data['post'] ) ) {
				return $request_data['post'];
			} elseif ( isset( $request_data['post_ID'] ) ) {
				return $request_data['post_ID'];
			}

			return 0;
		}

		/**
		 * Returns the sendback URL to return to after a row action has been run.
		 *
		 * @since 0.6.1
		 * @param string $message the resulting notification of the row action
		 * @param boolean $error whether the message is an error message
		 * @return string the sendback URL to redirect to
		 */
		protected function get_row_sendback_url( $message, $error = false ) {
			return add_query_arg( 'updated', 1, $this->get_sendback_url() );
		}

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run bulk actions.
		 *
		 * @since 0.6.1
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected function get_bulk_capability_args() {
			return array( 'edit_posts' );
		}

		/**
		 * Returns the name of the nonce that should be used to check for a bulk action.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @return string name of the nonce
		 */
		protected function get_bulk_nonce_name() {
			return 'bulk-posts';
		}

		/**
		 * Returns an array of post IDs that a bulk action should be performed on.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @return array post IDs
		 */
		protected function get_bulk_ids() {
			$request_data = $_REQUEST;

			if ( isset( $request_data['media'] ) ) {
				return (array) $request_data['media'];
			} elseif ( isset( $request_data['ids'] ) ) {
				return explode( ',', $request_data['ids'] );
			} elseif ( isset( $request_data['post'] ) && ! empty( $request_data['post'] ) ) {
				return (array) $request_data['post'];
			}

			return array();
		}

		/**
		 * Returns the sendback URL to return to after a bulk action has been run.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param string $message the resulting notification of the bulk action
		 * @param boolean $error whether the message is an error message
		 * @param integer $count the number of posts affected
		 * @return string the sendback URL to redirect to
		 */
		protected function get_bulk_sendback_url( $message, $error = false, $count = 0 ) {
			$sendback = wp_get_referer();
			if ( ! $sendback ) {
				$sendback = $this->get_sendback_url();
			} else {
				$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), $sendback );
			}

			$sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

			return add_query_arg( 'updated', $count, $sendback );
		}

		/**
		 * Returns the name of the transient under which messages should be stored.
		 *
		 * @since 0.6.1
		 * @return string the name of the transient
		 */
		protected function get_message_transient_name() {
			return 'wpptd_post_' . $this->component->slug . '_bulk_row_action_message';
		}

		/**
		 * Returns the default URL to redirect to after a custom bulk/row action has been performed.
		 *
		 * @since 0.6.1
		 * @return string the default sendback URL
		 */
		protected function get_sendback_url() {
			$sendback = '';
			if ( 'attachment' === $this->component->slug ) {
				$sendback = admin_url( 'upload.php' );
			} else {
				$sendback = admin_url( 'edit.php' );
				if ( 'post' !== $this->component->slug ) {
					$sendback = add_query_arg( 'post_type', $this->component->slug, $sendback );
				}
			}

			return $sendback;
		}

		/**
		 * Validates the post type component arguments that are related to row and bulk actions.
		 *
		 * @since 0.6.1
		 * @see WPPTD\Components\PostType::validate()
		 * @see WPPTD\PostTypeTableHandler::validate_args()
		 * @param array $args the original arguments
		 * @return array the validated arguments
		 */
		public static function validate_args( $args ) {
			// handle row actions
			if ( ! $args['show_ui'] || ! is_array( $args['row_actions'] ) ) {
				$args['row_actions'] = array();
			}
			foreach ( $args['row_actions'] as $action_slug => &$action_args ) {
				if ( ! is_array( $action_args ) ) {
					$action_args = array();
				}
				$action_args = wp_parse_args( $action_args, array(
					'title'				=> '',
					'callback'			=> '',
				) );
			}

			// handle bulk actions
			if ( ! $args['show_ui'] || ! is_array( $args['bulk_actions'] ) ) {
				$args['bulk_actions'] = array();
			}
			foreach ( $args['bulk_actions'] as $action_slug => &$action_args ) {
				if ( ! is_array( $action_args ) ) {
					$action_args = array();
				}
				$action_args = wp_parse_args( $action_args, array(
					'title'				=> '',
					'callback'			=> '',
				) );
			}

			return $args;
		}
	}
}
