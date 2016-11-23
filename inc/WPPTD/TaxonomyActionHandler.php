<?php
/**
 * WPPTD\TaxonomyActionHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
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
	class TaxonomyActionHandler extends ActionHandler {
		/**
		 * This action adds the custom taxonomy bulk actions.
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
		 * @since 0.6.1
		 */
		public function hack_bulk_actions() {
			$table_bulk_actions = $this->component->bulk_actions;

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
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @param array $bulk_messages the original array of term messages
		 * @return array the (temporarily) updated array of term messages
		 */
		public function maybe_hack_action_message( $bulk_messages, $bulk_counts = array() ) {
			$request_data = $_REQUEST;

			if ( isset( $request_data['updated'] ) && 0 < (int) $request_data['updated'] && isset( $request_data['message'] ) && 1 === (int) $request_data['message'] ) {
				$transient_name = $this->get_message_transient_name();
				$action_message = get_transient( $transient_name );
				if ( $action_message ) {
					delete_transient( $transient_name );

					if ( ! isset( $bulk_messages[ $this->component->slug ] ) ) {
						$bulk_messages[ $this->component->slug ] = array();
					}
					$bulk_messages[ $this->component->slug ][1] = $action_message;
				}
				$_SERVER['REQUEST_URI'] = remove_query_arg( 'updated', $_SERVER['REQUEST_URI'] );
			}

			return $bulk_messages;
		}

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run row actions.
		 *
		 * @since 0.6.1
		 * @param WP_Term|integer $term a term object or ID
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected function get_row_capability_args( $term ) {
			return array( 'manage_categories' );
		}

		/**
		 * Returns the base admin URL for a term.
		 *
		 * @since 0.6.1
		 * @param WP_Term $term a term object
		 * @return string base admin URL for this term
		 */
		protected function get_row_base_url( $term ) {
			$request_data = $_REQUEST;

			$path = 'edit-tags.php?taxonomy=' . $term->taxonomy;
			if ( isset( $request_data['post_type'] ) ) {
				$path .= '&post_type=' . $request_data['post_type'];
			}
			$path .= '&tag_ID=' . $term->term_id;

			return admin_url( $path );
		}

		/**
		 * Returns the name of the nonce that should be used to check for a specific term row action.
		 *
		 * @since 0.6.1
		 * @param string $action_slug the slug of the action to perform
		 * @param WP_Term|integer $term a term object or ID
		 * @return string name of the nonce
		 */
		protected function get_row_nonce_name( $action_slug, $term ) {
			if ( is_object( $term ) ) {
				$term = $term->term_id;
			}
			return $action_slug . '-term_' . $term;
		}

		/**
		 * Returns the ID of a term that a row action should be performed on.
		 *
		 * @since 0.6.1
		 * @return integer a term ID
		 */
		protected function get_row_id() {
			$request_data = $_REQUEST;

			if ( isset( $request_data['tag_ID'] ) ) {
				return $request_data['tag_ID'];
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
			$query_args = array(
				'updated'	=> 1,
				'message'	=> 1,
			);
			if ( $error ) {
				$query_args['error'] = 1;
			}

			return add_query_arg( $query_args, $this->get_sendback_url() );
		}

		/**
		 * Returns parameters to pass to `current_user_can()` to check whether the current user can run bulk actions.
		 *
		 * @since 0.6.1
		 * @return array parameters to pass on to `current_user_can()`
		 */
		protected function get_bulk_capability_args() {
			return array( 'manage_categories' );
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
			return 'bulk-tags';
		}

		/**
		 * Returns an array of term IDs that a bulk action should be performed on.
		 *
		 * This method is only used on WordPress < 4.7.
		 *
		 * @since 0.6.1
		 * @return array term IDs
		 */
		protected function get_bulk_ids() {
			$request_data = $_REQUEST;

			if ( isset( $request_data['delete_tags'] ) ) {
				return (array) $request_data['delete_tags'];
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
		 * @param integer $count the number of terms affected
		 * @return string the sendback URL to redirect to
		 */
		protected function get_bulk_sendback_url( $message, $error = false, $count = 0 ) {
			$sendback = wp_get_referer();
			if ( ! $sendback ) {
				$sendback = $this->get_sendback_url();
			}

			$query_args = array(
				'updated'	=> count( $term_ids ),
				'message'	=> 1,
			);
			if ( $error ) {
				$query_args['error'] = 1;
			}

			$sendback = remove_query_arg( array( 'action', 'action2' ), $sendback );

			return add_query_arg( $query_args, $sendback );
		}

		/**
		 * Returns the name of the transient under which messages should be stored.
		 *
		 * @since 0.6.1
		 * @return string the name of the transient
		 */
		protected function get_message_transient_name() {
			return 'wpptd_term_' . $this->component->slug . '_bulk_row_action_message';
		}

		/**
		 * Returns the default URL to redirect to after a custom bulk/row action has been performed.
		 *
		 * @since 0.6.1
		 * @return string the default sendback URL
		 */
		protected function get_sendback_url() {
			$sendback = admin_url( 'edit-tags.php?taxonomy=' . $this->component->slug );
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
