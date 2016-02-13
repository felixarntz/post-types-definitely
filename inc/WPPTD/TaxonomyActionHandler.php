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

if ( ! class_exists( 'WPPTD\TaxonomyActionHandler' ) ) {
	/**
	 * This class handles row and bulk actions for a taxonomy registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class TaxonomyActionHandler {
		/**
		 * @since 0.6.1
		 * @var WPPTD\Components\Taxonomy Holds the taxonomy component this table handler should manage.
		 */
		protected $taxonomy = null;

		/**
		 * @since 0.6.1
		 * @var string Holds the slug of the taxonomy component.
		 */
		protected $taxonomy_slug = '';

		/**
		 * Class constructor.
		 *
		 * @since 0.6.1
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component to use this handler for
		 */
		public function __construct( $taxonomy ) {
			$this->taxonomy = $taxonomy;
			$this->taxonomy_slug = $this->taxonomy->slug;
		}

		/**
		 * This filter adjusts the available row actions.
		 *
		 * @since 0.6.1
		 * @param array $row_actions the original array of row actions
		 * @param WP_Term $term the current term object
		 * @return array the adjusted row actions array
		 */
		public function filter_row_actions( $row_actions, $term ) {
			$table_row_actions = $this->taxonomy->row_actions;

			if ( ! current_user_can( 'manage_categories' ) ) {
				return $row_actions;
			}

			foreach ( $table_row_actions as $action_slug => $action_args ) {
				// do not allow overriding of existing actions
				if ( isset( $row_actions[ $action_slug ] ) ) {
					continue;
				}

				$request_data = $_REQUEST;

				$path = 'edit-tags.php?taxonomy=' . $term->taxonomy;
				if ( isset( $request_data['post_type'] ) ) {
					$path .= '&post_type=' . $request_data['post_type'];
				}
				$path .= '&tag_ID=' . $term->term_id;

				$base_url = admin_url( $path );

				$row_actions[ $action_slug ] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'action', $action_slug, $base_url ), $action_slug . '-term_' . $term->term_id ) ) . '" title="' . esc_attr( $action_args['title'] ) . '">' . esc_html( $action_args['title'] ) . '</a>';
			}

			return $row_actions;
		}

		/**
		 * This action is a general router to check whether a specific row action should be performed.
		 *
		 * It also determines the term ID the action should be performed on.
		 *
		 * @since 0.6.1
		 * @see WPPTD\TaxonomyActionHandler::run_row_action()
		 */
		public function maybe_run_row_action() {
			$table_row_actions = $this->taxonomy->row_actions;

			$row_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_row_actions[ $row_action ] ) ) {
				return;
			}

			$request_data = $_REQUEST;

			$term_id = 0;
			if ( isset( $request_data['tag_ID'] ) ) {
				$term_id = (int) $request_data['tag_ID'];
			}

			if ( ! $term_id ) {
				return;
			}

			$this->run_row_action( $row_action, $term_id );
		}

		/**
		 * This action is a general router to check whether a specific bulk action should be performed.
		 *
		 * It also determines the term IDs the action should be performed on.
		 *
		 * @since 0.6.1
		 * @see WPPTD\TaxonomyActionHandler::run_bulk_action()
		 */
		public function maybe_run_bulk_action() {
			$table_bulk_actions = $this->taxonomy->bulk_actions;

			$bulk_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return;
			}

			$request_data = $_REQUEST;

			$term_ids = array();
			if ( isset( $request_data['delete_tags'] ) ) {
				$term_ids = (array) $request_data['delete_tags'];
			}

			if ( ! $term_ids ) {
				return;
			}

			$term_ids = array_map( 'intval', $term_ids );

			$this->run_bulk_action( $bulk_action, $term_ids );
		}

		/**
		 * This action is a hack to extend the bulk actions dropdown with custom bulk actions.
		 *
		 * WordPress does not natively support this. That's why we need this ugly solution.
		 *
		 * @since 0.6.1
		 */
		public function hack_bulk_actions() {
			$table_bulk_actions = $this->taxonomy->bulk_actions;

			?>
			<script type="text/javascript">
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).ready( function( $ ) {
						var options = '';
						<?php foreach ( $table_bulk_actions as $action_slug => $action_args ) : ?>
						options += '<option value="<?php echo $action_slug; ?>"><?php echo $action_args['title']; ?></option>';
						<?php endforeach; ?>

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
		 * This filter adjusts the term messages if a custom row/bulk action has just been executed.
		 *
		 * It is basically a hack to display a custom message for that action instead of the default message.
		 *
		 * @since 0.6.1
		 * @param array $messages the original array of term messages
		 * @return array the (temporarily) updated array of term messages
		 */
		public function maybe_hack_action_message( $messages ) {
			$request_data = $_REQUEST;

			if ( isset( $request_data['updated'] ) && 0 < (int) $request_data['updated'] && isset( $request_data['message'] ) && 1 === (int) $request_data['message'] ) {
				$action_message = get_transient( 'wpptd_term_' . $this->taxonomy_slug . '_bulk_row_action_message' );
				if ( $action_message ) {
					delete_transient( 'wpptd_term_' . $this->taxonomy_slug . '_bulk_row_action_message' );

					if ( ! isset( $messages[ $this->taxonomy_slug ] ) ) {
						$messages[ $this->taxonomy_slug ] = array();
					}
					$messages[ $this->taxonomy_slug ][1] = $action_message;
				}
				$_SERVER['REQUEST_URI'] = remove_query_arg( 'updated', $_SERVER['REQUEST_URI'] );
			}

			return $messages;
		}

		/**
		 * Performs a specific row action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every row action must accept exactly one parameter, the term ID.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient and printed out after the redirect.
		 *
		 * @since 0.6.1
		 * @param string $row_action the row action slug
		 * @param integer $term_id the term ID of the term to perform the action on
		 */
		protected function run_row_action( $row_action, $term_id ) {
			$table_row_actions = $this->taxonomy->row_actions;
			$taxonomy_singular_title = $this->taxonomy->singular_title;

			$sendback = $this->get_sendback_url();

			check_admin_referer( $row_action . '-term_' . $term_id );

			$action_message = false;
			$error = false;
			if ( ! current_user_can( 'manage_categories' ) ) {
				$action_message = sprintf( __( 'The %s was not updated because of missing privileges.', 'post-types-definitely' ), $taxonomy_singular_title );
				$error = true;
			} elseif ( empty( $table_row_actions[ $row_action ]['callback'] ) || ! is_callable( $table_row_actions[ $row_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s was not updated since an internal error occurred.', 'post-types-definitely' ), $taxonomy_singular_title );
				$error = true;
			} else {
				$action_message = call_user_func( $table_row_actions[ $row_action ]['callback'], $term_id );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				set_transient( 'wpptd_term_' . $this->taxonomy_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			$query_args = array(
				'updated'	=> 1,
				'message'	=> 1,
			);
			if ( $error ) {
				$query_args['error'] = 1;
			}

			wp_redirect( add_query_arg( $query_args, $sendback ) );
			exit();
		}

		/**
		 * Performs a specific bulk action and redirects back to the list table screen afterwards.
		 *
		 * The callback function of every bulk action must accept exactly one parameter, an array of term IDs.
		 * It must return (depending on whether the action was successful or not)...
		 * - either a string to use as the success message
		 * - a WP_Error object with a custom message to use as the error message
		 *
		 * The message is temporarily stored in a transient and printed out after the redirect.
		 *
		 * @since 0.6.1
		 * @param string $bulk_action the bulk action slug
		 * @param array $term_ids the array of term IDs of the terms to perform the action on
		 */
		protected function run_bulk_action( $bulk_action, $term_ids ) {
			$table_bulk_actions = $this->taxonomy->bulk_actions;
			$taxonomy_title = $this->taxonomy->title;

			$sendback = wp_get_referer();
			if ( ! $sendback ) {
				$sendback = $this->get_sendback_url();
			}

			check_admin_referer( 'bulk-tags' );

			$action_message = false;
			$error = false;
			if ( ! current_user_can( 'manage_categories' ) ) {
				$action_message = sprintf( __( 'The %s were not updated because of missing privileges.', 'post-types-definitely' ), $taxonomy_title );
				$error = true;
			} elseif ( empty( $table_bulk_actions[ $bulk_action ]['callback'] ) || ! is_callable( $table_bulk_actions[ $bulk_action ]['callback'] ) ) {
				$action_message = sprintf( __( 'The %s were not updated since an internal error occurred.', 'post-types-definitely' ), $taxonomy_title );
				$error = true;
			} else {
				$action_message = call_user_func( $table_bulk_actions[ $bulk_action ]['callback'], $term_ids );
				if ( is_wp_error( $action_message ) ) {
					$action_message = $action_message->get_error_message();
					$error = true;
				}
			}

			if ( $action_message && is_string( $action_message ) ) {
				set_transient( 'wpptd_term_' . $this->taxonomy_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			$query_args = array(
				'updated'	=> count( $term_ids ),
				'message'	=> 1,
			);
			if ( $error ) {
				$query_args['error'] = 1;
			}

			$sendback = remove_query_arg( array( 'action', 'action2' ), $sendback );

			wp_redirect( add_query_arg( $query_args, $sendback ) );
			exit();
		}

		/**
		 * Returns the default URL to redirect to after a custom bulk/row action has been performed.
		 *
		 * @since 0.6.1
		 * @return string the default sendback URL
		 */
		protected function get_sendback_url() {
			$sendback = admin_url( 'edit-tags.php?taxonomy=' . $this->taxonomy_slug );
			$request_data = $_REQUEST;

			if ( isset( $request_data['post_type'] ) && 'post' !== $request_data['post_type'] ) {
				$sendback = add_query_arg( 'post_type', $request_data['post_type'], $sendback );
			}

			return $sendback;
		}

		/**
		 * Validates the taxonomy component arguments that are related to row and bulk actions.
		 *
		 * @since 0.6.1
		 * @see WPPTD\Components\Taxonomy::validate()
		 * @see WPPTD\TaxonomyTableHandler::validate_args()
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
