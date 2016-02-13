<?php
/**
 * @package WPPTD
 * @version 0.6.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
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
	class PostTypeActionHandler {
		/**
		 * @since 0.6.1
		 * @var WPPTD\Components\PostType Holds the post type component this table handler should manage.
		 */
		protected $post_type = null;

		/**
		 * @since 0.6.1
		 * @var string Holds the slug of the post type component.
		 */
		protected $post_type_slug = '';

		/**
		 * Class constructor.
		 *
		 * @since 0.6.1
		 * @param WPPTD\Components\PostType $post_type the post type component to use this handler for
		 */
		public function __construct( $post_type ) {
			$this->post_type = $post_type;
			$this->post_type_slug = $this->post_type->slug;
		}

		/**
		 * This filter adjusts the available row actions.
		 *
		 * @since 0.6.1
		 * @param array $row_actions the original array of row actions
		 * @param WP_Post $post the current post object
		 * @return array the adjusted row actions array
		 */
		public function filter_row_actions( $row_actions, $post ) {
			$table_row_actions = $this->post_type->row_actions;

			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return $row_actions;
			}

			foreach ( $table_row_actions as $action_slug => $action_args ) {
				// do not allow overriding of existing actions
				if ( isset( $row_actions[ $action_slug ] ) ) {
					continue;
				}

				$base_url = get_edit_post_link( $post->ID, 'raw' );
				if ( ! $base_url ) {
					continue;
				}

				$row_actions[ $action_slug ] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'action', $action_slug, $base_url ), $action_slug . '-post_' . $post->ID ) ) . '" title="' . esc_attr( $action_args['title'] ) . '">' . esc_html( $action_args['title'] ) . '</a>';
			}

			return $row_actions;
		}

		/**
		 * This action is a general router to check whether a specific row action should be performed.
		 *
		 * It also determines the post ID the action should be performed on.
		 *
		 * @since 0.6.1
		 * @see WPPTD\PostTypeActionHandler::run_row_action()
		 */
		public function maybe_run_row_action() {
			$table_row_actions = $this->post_type->row_actions;

			$row_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_row_actions[ $row_action ] ) ) {
				return;
			}

			$request_data = $_REQUEST;

			$post_id = 0;
			if ( isset( $request_data['post'] ) ) {
				$post_id = (int) $request_data['post'];
			} elseif ( isset( $request_data['post_ID'] ) ) {
				$post_id = (int) $request_data['post_ID'];
			}

			if ( ! $post_id ) {
				return;
			}

			$this->run_row_action( $row_action, $post_id );
		}

		/**
		 * This action is a general router to check whether a specific bulk action should be performed.
		 *
		 * It also determines the post IDs the action should be performed on.
		 *
		 * @since 0.6.1
		 * @see WPPTD\PostTypeActionHandler::run_bulk_action()
		 */
		public function maybe_run_bulk_action() {
			$table_bulk_actions = $this->post_type->bulk_actions;

			$bulk_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return;
			}

			$request_data = $_REQUEST;

			$post_ids = array();
			if ( isset( $request_data['media'] ) ) {
				$post_ids = (array) $request_data['media'];
			} elseif ( isset( $request_data['ids'] ) ) {
				$post_ids = explode( ',', $request_data['ids'] );
			} elseif ( isset( $request_data['post'] ) && ! empty( $request_data['post'] ) ) {
				$post_ids = (array) $request_data['post'];
			}

			if ( ! $post_ids ) {
				return;
			}

			$post_ids = array_map( 'intval', $post_ids );

			$this->run_bulk_action( $bulk_action, $post_ids );
		}

		/**
		 * This action is a hack to extend the bulk actions dropdown with custom bulk actions.
		 *
		 * WordPress does not natively support this. That's why we need this ugly solution.
		 *
		 * Another thing the function does is checking whether a row/bulk action message outputted by the plugin
		 * is actually an error message. In that case, the CSS class of it is changed accordingly.
		 *
		 * @since 0.6.1
		 */
		public function hack_bulk_actions() {
			$table_bulk_actions = $this->post_type->bulk_actions;

			?>
			<script type="text/javascript">
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).ready( function( $ ) {
						if ( $( '#message .wpptd-error-hack' ).length > 0 ) {
							$( '#message' ).addClass( 'error' ).removeClass( 'updated' );
							$( '#message .wpptd-error-hack' ).remove();
						}

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
		 * This filter adjusts the bulk messages if a custom row/bulk action has just been executed.
		 *
		 * It is basically a hack to display a custom message for that action instead of the default message.
		 *
		 * @since 0.6.1
		 * @param array $bulk_messages the original array of bulk messages
		 * @param array $bulk_counts the counts of updated posts
		 * @return array the (temporarily) updated array of bulk messages
		 */
		public function maybe_hack_bulk_message( $bulk_messages, $bulk_counts ) {
			if ( 0 < $bulk_counts['updated'] ) {
				$action_message = get_transient( 'wpptd_post_' . $this->post_type_slug . '_bulk_row_action_message' );
				if ( $action_message ) {
					delete_transient( 'wpptd_post_' . $this->post_type_slug . '_bulk_row_action_message' );

					if ( ! isset( $bulk_messages[ $this->post_type_slug ] ) ) {
						$bulk_messages[ $this->post_type_slug ] = array();
					}
					$bulk_messages[ $this->post_type_slug ]['updated'] = $action_message;
				}
			}

			return $bulk_messages;
		}

		/**
		 * Performs a specific row action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every row action must accept exactly one parameter, the post ID.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient and printed out after the redirect.
		 *
		 * @since 0.6.1
		 * @param string $row_action the row action slug
		 * @param integer $post_id the post ID of the post to perform the action on
		 */
		protected function run_row_action( $row_action, $post_id ) {
			$table_row_actions = $this->post_type->row_actions;
			$post_type_singular_title = $this->post_type->singular_title;

			$sendback = $this->get_sendback_url();

			check_admin_referer( $row_action . '-post_' . $post_id );

			$action_message = false;
			$error = false;
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$action_message = sprintf( __( 'The %s was not updated because of missing privileges.', 'post-types-definitely' ), $post_type_singular_title );
				$error = true;
			} elseif ( empty( $table_row_actions[ $row_action ]['callback'] ) || ! is_callable( $table_row_actions[ $row_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s was not updated since an internal error occurred.', 'post-types-definitely' ), $post_type_singular_title );
				$error = true;
			} else {
				$action_message = call_user_func( $table_row_actions[ $row_action ]['callback'], $post_id );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				if ( $error ) {
					$action_message = '<span class="wpptd-error-hack hidden"></span>' . $action_message;
				}
				set_transient( 'wpptd_post_' . $this->post_type_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			wp_redirect( add_query_arg( 'updated', 1, $sendback ) );
			exit();
		}

		/**
		 * Performs a specific bulk action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every bulk action must accept exactly one parameter, an array of post IDs.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient and printed out after the redirect.
		 *
		 * @since 0.6.1
		 * @param string $bulk_action the bulk action slug
		 * @param array $post_ids the array of post IDs of the posts to perform the action on
		 */
		protected function run_bulk_action( $bulk_action, $post_ids ) {
			$table_bulk_actions = $this->post_type->bulk_actions;
			$post_type_title = $this->post_type->title;

			$sendback = wp_get_referer();
			if ( ! $sendback ) {
				$sendback = $this->get_sendback_url();
			} else {
				$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), $sendback );
			}

			check_admin_referer( 'bulk-posts' );

			$action_message = false;
			$error = false;
			if ( empty( $table_bulk_actions[ $bulk_action ]['callback'] ) || ! is_callable( $table_bulk_actions[ $bulk_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s were not updated since an internal error occurred.', 'post-types-definitely' ), $post_type_title );
				$error = true;
			} else {
				$action_message = call_user_func( $table_bulk_actions[ $bulk_action ]['callback'], $post_ids );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				if ( $error ) {
					$action_message = '<span class="wpptd-error-hack hidden"></span>' . $action_message;
				}
				set_transient( 'wpptd_post_' . $this->post_type_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			$sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

			wp_redirect( add_query_arg( 'updated', count( $post_ids ), $sendback ) );
			exit();
		}

		/**
		 * Returns the default URL to redirect to after a custom bulk/row action has been performed.
		 *
		 * @since 0.6.1
		 * @return string the default sendback URL
		 */
		protected function get_sendback_url() {
			$sendback = '';
			if ( 'attachment' === $this->post_type_slug ) {
				$sendback = admin_url( 'upload.php' );
			} else {
				$sendback = admin_url( 'edit.php' );
				if ( 'post' !== $this->post_type_slug ) {
					$sendback = add_query_arg( 'post_type', $this->post_type_slug, $sendback );
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
