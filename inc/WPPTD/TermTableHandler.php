<?php
/**
 * @package WPPTD
 * @version 0.6.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

use WPDLib\Components\Manager as ComponentManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\TermTableHandler' ) ) {
	/**
	 * This class handles the term list table for a taxonomy registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.0
	 */
	class TermTableHandler {
		/**
		 * @since 0.6.0
		 * @var WPPTD\Components\Taxonomy Holds the taxonomy component this table handler should manage.
		 */
		protected $taxonomy = null;

		/**
		 * @since 0.6.0
		 * @var string Holds the slug of the taxonomy component.
		 */
		protected $taxonomy_slug = '';

		/**
		 * Class constructor.
		 *
		 * @since 0.6.0
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component to use this handler for
		 */
		public function __construct( $taxonomy ) {
			$this->taxonomy = $taxonomy;
			$this->taxonomy_slug = $this->taxonomy->slug;
		}

		/**
		 * This filter adjusts the list table columns.
		 *
		 * @since 0.6.0
		 * @param array $columns the original table columns as $column_slug => $title
		 * @return array the adjusted table columns
		 */
		public function filter_table_columns( $columns ) {
			$table_columns = $this->taxonomy->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					$field = ComponentManager::get( '*.*.' . $this->taxonomy_slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy.WPPTD\Components\TermMetabox', true );
					if ( $field ) {
						$columns[ $column_slug ] = ! empty( $column_args['title'] ) ? $column_args['title'] : $field->title;
					}
				} elseif ( isset( $column_args['custom_callback'] ) && ! empty( $column_args['custom_callback'] ) ) {
					$columns[ $column_slug ] = $column_args['title'];
				}
			}

			return $columns;
		}

		/**
		 * This filter adjusts the sortable list table columns.
		 *
		 * Any column which is `'sortable' => true` will be added to the array.
		 *
		 * @since 0.6.0
		 * @param array $columns the original sortable table columns as $column_slug => array( $sort_by, $desc )
		 * @return array the adjusted sortable table columns
		 */
		public function filter_table_sortable_columns( $columns ) {
			$table_columns = $this->taxonomy->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					if ( $column_args['sortable'] ) {
						$field = ComponentManager::get( '*.*.' . $this->taxonomy_slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy.WPPTD\Components\TermMetabox', true );
						if ( $field ) {
							$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
						}
					}
				}
			}

			return $columns;
		}

		/**
		 * This function renders a list table column.
		 *
		 * For meta value columns, the corresponding field component takes care of rendering.
		 * For custom columns, the callback to render the column is called.
		 *
		 * @since 0.6.0
		 * @param string $output empty string as default output for the table column
		 * @param string $column_name the column name of the column that should be rendered
		 * @param integer $term_id the term ID for the current row
		 * @return string the actual output for the table column
		 */
		public function render_table_column( $output, $column_name, $term_id ) {
			$table_columns = $this->taxonomy->table_columns;

			if ( isset( $table_columns[ $column_name ] ) ) {
				ob_start();
				if ( isset( $table_columns[ $column_name ]['meta_key'] ) && ! empty( $table_columns[ $column_name ]['meta_key'] ) ) {
					$field = ComponentManager::get( '*.*.' . $this->taxonomy_slug . '.*.' . $table_columns[ $column_name ]['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy.WPPTD\Components\TermMetabox', true );
					if ( $field ) {
						$field->render_table_column( $term_id );
					}
				} elseif ( $table_columns[ $column_name ]['custom_callback'] && is_callable( $table_columns[ $column_name ]['custom_callback'] ) ) {
					call_user_func( $table_columns[ $column_name ]['custom_callback'], $term_id );
				}
				$output = ob_get_clean();
			}

			return $output;
		}

		/**
		 * This filter adjusts the available row actions.
		 *
		 * @since 0.6.0
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

				$path = 'edit-tags.php?taxonomy=' . $term->taxonomy;
				if ( isset( $_REQUEST['post_type'] ) ) {
					$path .= '&post_type=' . $_REQUEST['post_type'];
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
		 * @since 0.6.0
		 * @see WPPTD\TermTableHandler::run_row_action()
		 */
		public function maybe_run_row_action() {
			$table_row_actions = $this->taxonomy->row_actions;

			$row_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_row_actions[ $row_action ] ) ) {
				return;
			}

			$term_id = 0;
			if ( isset( $_GET['tag_ID'] ) ) {
				$term_id = (int) $_GET['tag_ID'];
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
		 * @since 0.6.0
		 * @see WPPTD\TermTableHandler::run_bulk_action()
		 */
		public function maybe_run_bulk_action() {
			$table_bulk_actions = $this->taxonomy->bulk_actions;

			$bulk_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return;
			}

			$term_ids = array();
			if ( isset( $_REQUEST['delete_tags'] ) ) {
				$term_ids = (array) $_REQUEST['delete_tags'];
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
		 * @since 0.6.0
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
		 * @since 0.6.0
		 * @param array $messages the original array of term messages
		 * @return array the (temporarily) updated array of term messages
		 */
		public function maybe_hack_action_message( $messages ) {
			if ( isset( $_REQUEST['updated'] ) && 0 < (int) $_REQUEST['updated'] && isset( $_REQUEST['message'] ) && 1 === (int) $_REQUEST['message'] ) {
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
		 * Validates the taxonomy component arguments that are related to the list table.
		 *
		 * @since 0.6.0
		 * @see WPPTD\Components\Taxonomy::validate()
		 * @param array $args the original arguments
		 * @return array the validated arguments
		 */
		public function validate_taxonomy_args( $args ) {
			// handle admin table columns
			if ( ! $args['show_ui'] || ! is_array( $args['table_columns'] ) ) {
				$args['table_columns'] = array();
			}
			$_table_columns = $args['table_columns'];
			$args['table_columns'] = array();
			$core_column_slugs = array( 'name', 'description', 'slug', 'links', 'posts' );
			foreach ( $_table_columns as $column_slug => $column_args ) {
				if ( strpos( $column_slug, 'meta-' ) === 0 || strpos( $column_slug, 'custom-' ) === 0 || in_array( $column_slug, $core_column_slugs ) ) {
					if ( false !== $column_args ) {
						if ( ! is_array( $column_args ) ) {
							$column_args = array();
						}
						$column_args = wp_parse_args( $column_args, array(
							'title'			=> '',
							'sortable'		=> false,
						) );
						if ( strpos( $column_slug, 'meta-' ) === 0 ) {
							if ( ! isset( $column_args['meta_key'] ) || empty( $column_args['meta_key'] ) ) {
								$column_args['meta_key'] = substr( $column_slug, strlen( 'meta-' ) );
							}
						} elseif ( strpos( $column_slug, 'custom-' ) === 0 ) {
							if ( ! isset( $column_args['custom_callback'] ) || empty( $column_args['custom_callback'] ) ) {
								$column_args['custom_callback'] = substr( $column_slug, strlen( 'custom-' ) );
							}
						}
					}
					$args['table_columns'][ $column_slug ] = $column_args;
				} else {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The admin table column slug %1$s (for taxonomy %2$s) is invalid. It must be prefixed with either &quot;meta-&quot; or &quot;custom-&quot;.', 'post-types-definitely' ), $column_slug, $this->taxonomy_slug ), '0.6.0' );
				}
			}

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
		 * @since 0.6.0
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
		 * @since 0.6.0
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
		 * @since 0.6.0
		 * @return string the default sendback URL
		 */
		protected function get_sendback_url() {
			$sendback = admin_url( 'edit-tags.php?taxonomy=' . $this->taxonomy_slug );
			if ( isset( $_REQUEST['post_type'] ) && 'post' !== $_REQUEST['post_type'] ) {
				$sendback = add_query_arg( 'post_type', $_REQUEST['post_type'], $sendback );
			}

			return $sendback;
		}

	}

}
