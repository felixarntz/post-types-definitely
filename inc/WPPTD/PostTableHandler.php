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

if ( ! class_exists( 'WPPTD\PostTableHandler' ) ) {
	/**
	 * This class handles the post list table for a post type registered with WPPTD.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class PostTableHandler {
		protected $post_type = null;

		protected $post_type_slug = '';

		protected $active_filters = array();

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		public function __construct( $post_type ) {
			$this->post_type = $post_type;
			$this->post_type_slug = $this->post_type->slug;
		}

		public function get_table_taxonomies( $taxonomies ) {
			$table_columns = $this->post_type->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( $column_args && ! empty( $column_args['taxonomy_slug'] ) && is_object_in_taxonomy( $this->post_type_slug, $column_args['taxonomy_slug'] ) ) {
					if ( ! in_array( $column_args['taxonomy_slug'], $taxonomies ) ) {
						$taxonomies[] = $column_args['taxonomy_slug'];
					} elseif ( ! $column_args ) {
						if ( strpos( $column_slug, 'taxonomy-' ) === 0 ) {
							$taxonomy_slug = substr( $column_slug, 9 );
							$column_key = 'taxonomy-' . $taxonomy_slug;
							if ( 'category' === $taxonomy_slug ) {
								$column_key = 'categories';
							} elseif ( 'post_tag' === $taxonomy_slug || 'tag' === $taxonomy_slug ) {
								$column_key = 'tags';
							}
							if ( false !== ( $key = array_search( $column_key, $taxonomies ) ) ) {
								unset( $taxonomies[ $key ] );
							}
						}
					}
				}
			}

			return array_values( $taxonomies );
		}

		public function filter_table_columns( $columns ) {
			$table_columns = $this->post_type->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->post_type_slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
					if ( $field ) {
						$columns[ $column_slug ] = ! empty( $column_args['title'] ) ? $column_args['title'] : $field->title;
					}
				} elseif ( isset( $column_args['custom_callback'] ) && ! empty( $column_args['custom_callback'] ) ) {
					$columns[ $column_slug ] = $column_args['title'];
				}
			}

			return $columns;
		}

		public function filter_table_sortable_columns( $columns ) {
			$table_columns = $this->post_type->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					if ( $column_args['sortable'] ) {
						$field = ComponentManager::get( '*.' . $this->post_type_slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
						}
					}
				} elseif ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
					if ( $column_args['sortable'] && is_object_in_taxonomy( $this->post_type_slug, $column_args['taxonomy_slug'] ) ) {
						$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
					}
				}
			}

			return $columns;
		}

		public function render_table_column( $column_name, $post_id ) {
			$table_columns = $this->post_type->table_columns;

			if ( isset( $table_columns[ $column_name ] ) ) {
				if ( isset( $table_columns[ $column_name ]['meta_key'] ) && ! empty( $table_columns[ $column_name ]['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->post_type_slug . '.*.' . $table_columns[ $column_name ]['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
					if ( $field ) {
						$field->render_table_column( $post_id );
					}
				} elseif ( $table_columns[ $column_name ]['custom_callback'] && is_callable( $table_columns[ $column_name ]['custom_callback'] ) ) {
					call_user_func( $table_columns[ $column_name ]['custom_callback'], $post_id );
				}
			}
		}

		public function render_table_column_filters() {
			$table_columns = $this->post_type->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->post_type_slug, $column_args['taxonomy_slug'] ) ) {
							$taxonomy = ComponentManager::get( '*.' . $this->post_type_slug . '.' . $column_args['taxonomy_slug'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
							if ( $taxonomy ) {
								$this->render_taxonomy_column_filter( $column_slug, $taxonomy );
							}
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$field = ComponentManager::get( '*.' . $this->post_type_slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							$this->render_meta_column_filter( $column_slug, $field );
						}
					}
				}
			}
		}

		public function register_table_filter_query_vars( $vars ) {
			$table_columns = $this->post_type->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->post_type_slug, $column_args['taxonomy_slug'] ) ) {
							$vars[] = $column_slug;
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$vars[] = $column_slug;
					}
				}
			}

			return $vars;
		}

		public function maybe_filter_by_table_columns( $wp_query ) {
			$table_columns = $this->post_type->table_columns;

			$tax_query = array();
			$meta_query = array();

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] && isset( $wp_query->query[ $column_slug ] ) ) {
					$this->active_filters[ $column_slug ] = false;
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) && 'category' !== $column_args['taxonomy_slug'] ) {
						$query_item = $this->filter_by_taxonomy( $wp_query->query[ $column_slug ], $column_slug, $column_args['taxonomy_slug'] );
						if ( $query_item ) {
							$tax_query[] = $query_item;
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$query_item = $this->filter_by_meta( $wp_query->query[ $column_slug ], $column_slug, $column_args['meta_key'] );
						if ( $query_item ) {
							$meta_query[] = $query_item;
						}
					}
				}
			}

			if ( $tax_query ) {
				$orig_tax_query = $wp_query->get( 'tax_query' );
				if ( ! $orig_tax_query ) {
					$orig_tax_query = array();
				}
				$tax_query = array_merge( $orig_tax_query, $tax_query );
				$wp_query->set( 'tax_query', $tax_query );
			}

			if ( $meta_query ) {
				$orig_meta_query = $wp_query->get( 'meta_query' );
				if ( ! $orig_meta_query ) {
					$orig_meta_query = array();
				}
				$meta_query = array_merge( $orig_meta_query, $meta_query );
				$wp_query->set( 'meta_query', $meta_query );
			}
		}

		public function maybe_sort_by_meta_table_column( $wp_query ) {
			$table_columns = $this->post_type->table_columns;

			if ( ! isset( $wp_query->query['orderby'] ) ) {
				return;
			}

			$orderby = $wp_query->query['orderby'];

			if ( ! isset( $table_columns[ $orderby ] ) ) {
				return;
			}

			if ( ! $table_columns[ $orderby ]['sortable'] ) {
				return;
			}

			if ( ! isset( $table_columns[ $orderby ]['meta_key'] ) || empty( $table_columns[ $orderby ]['meta_key'] ) ) {
				return;
			}

			$wp_query->set( 'meta_key', $table_columns[ $orderby ]['meta_key'] );
			$wp_query->set( 'orderby', 'meta_value' );
		}

		public function maybe_sort_by_taxonomy_table_column( $clauses, $wp_query ) {
			global $wpdb;

			$table_columns = $this->post_type->table_columns;

			if ( ! isset( $wp_query->query['orderby'] ) ) {
				return $clauses;
			}

			$orderby = $wp_query->query['orderby'];

			if ( ! isset( $table_columns[ $orderby ] ) ) {
				return $clauses;
			}

			if ( ! $table_columns[ $orderby ]['sortable'] ) {
				return $clauses;
			}

			if ( ! isset( $table_columns[ $orderby ]['taxonomy_slug'] ) || empty( $table_columns[ $orderby ]['taxonomy_slug'] ) ) {
				return $clauses;
			}

			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->term_relationships . " AS wpptd_tr ON ( " . $wpdb->posts . ".ID = wpptd_tr.object_id )";
			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->term_taxonomy . " AS wpptd_tt ON ( wpptd_tr.term_taxonomy_id = wpptd_tt.term_taxonomy_id )";
			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->terms . " AS wpptd_t ON ( wpptd_tt.term_id = wpptd_t.term_id )";
			$clauses['where'] .= $wpdb->prepare( " AND ( taxonomy = %s OR taxonomy IS NULL )", $table_columns[ $orderby ]['taxonomy_slug'] );
			$clauses['groupby'] = 'wpptd_tr.object_id';
			$clauses['orderby'] = "GROUP_CONCAT( wpptd_t.name ORDER BY name ASC ) " . ( ( 'asc' === strtolower( $wp_query->query['order'] ) ) ? 'ASC' : 'DESC' );

			return $clauses;
		}

		public function maybe_sort_default() {
			$table_columns = $this->post_type->table_columns;

			if ( isset( $_GET['orderby'] ) ) {
				return;
			}

			if ( ! isset( $table_columns['date'] ) || $table_columns['date'] ) {
				return;
			}

			// remove month dropdown if the date is irrelevant
			add_filter( 'disable_months_dropdown', '__return_true' );

			// sort by title if the date is irrelevant
			$_GET['orderby'] = 'title';
			$_GET['order'] = 'asc';
		}

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

		public function maybe_run_row_action() {
			$table_row_actions = $this->post_type->row_actions;

			$row_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_row_actions[ $row_action ] ) ) {
				return;
			}

			$post_id = 0;
			if ( isset( $_GET['post'] ) ) {
				$post_id = (int) $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = (int) $_POST['post_ID'];
			}

			if ( ! $post_id ) {
				return;
			}

			$this->run_row_action( $row_action, $post_id );
		}

		public function maybe_run_bulk_action() {
			$table_bulk_actions = $this->post_type->bulk_actions;

			$bulk_action = substr( current_action(), strlen( 'admin_action_' ) );
			if ( ! isset( $table_bulk_actions[ $bulk_action ] ) ) {
				return;
			}

			$post_ids = array();
			if ( isset( $_REQUEST['media'] ) ) {
				$post_ids = (array) $_REQUEST['media'];
			} elseif ( isset( $_REQUEST['ids'] ) ) {
				$post_ids = explode( ',', $_REQUEST['ids'] );
			} elseif ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) {
				$post_ids = (array) $_REQUEST['post'];
			}

			if ( ! $post_ids ) {
				return;
			}

			$post_ids = array_map( 'intval', $post_ids );

			$this->run_bulk_action( $bulk_action, $post_ids );
		}

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
						<?php if ( ! isset( $_REQUEST['post_status'] ) || 'trash' != $_REQUEST['post_status'] ) : ?>
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

		public function maybe_hack_bulk_message( $bulk_messages, $bulk_counts ) {
			if ( $bulk_counts['updated'] > 0 ) {
				$action_message = get_transient( 'wpptd_' . $this->post_type_slug . '_bulk_row_action_message' );
				if ( $action_message ) {
					delete_transient( 'wpptd_' . $this->post_type_slug . '_bulk_row_action_message' );

					if ( ! isset( $bulk_messages[ $this->post_type_slug ] ) ) {
						$bulk_messages[ $this->post_type_slug ] = array();
					}
					$bulk_messages[ $this->post_type_slug ]['updated'] = $action_message;
				}
			}

			return $bulk_messages;
		}

		public function validate_post_type_args( $args ) {// handle admin table columns
			if ( ! $args['show_ui'] || ! is_array( $args['table_columns'] ) ) {
				$args['table_columns'] = array();
			}
			$_table_columns = $args['table_columns'];
			$args['table_columns'] = array();
			$core_column_slugs = array( 'title', 'author', 'comments', 'date' );
			foreach ( $_table_columns as $column_slug => $column_args ) {
				if ( strpos( $column_slug, 'meta-' ) === 0 || strpos( $column_slug, 'taxonomy-' ) === 0 || strpos( $column_slug, 'custom-' ) === 0 || in_array( $column_slug, $core_column_slugs ) ) {
					if ( false !== $column_args ) {
						if ( ! is_array( $column_args ) ) {
							$column_args = array();
						}
						$column_args = wp_parse_args( $column_args, array(
							'title'			=> '',
							'filterable'	=> false,
							'sortable'		=> false,
						) );
						if ( strpos( $column_slug, 'meta-' ) === 0 ) {
							if ( ! isset( $column_args['meta_key'] ) || empty( $column_args['meta_key'] ) ) {
								$column_args['meta_key'] = substr( $column_slug, strlen( 'meta-' ) );
							}
						} elseif ( strpos( $column_slug, 'taxonomy-' ) === 0 ) {
							if ( ! isset( $column_args['taxonomy_slug'] ) || empty( $column_args['taxonomy_slug'] ) ) {
								$column_args['taxonomy_slug'] = substr( $column_slug, strlen( 'taxonomy-' ) );
							}
						} elseif ( strpos( $column_slug, 'custom-' ) === 0 ) {
							if ( ! isset( $column_args['custom_callback'] ) || empty( $column_args['custom_callback'] ) ) {
								$column_args['custom_callback'] = substr( $column_slug, strlen( 'custom-' ) );
							}
						}
					}
					$args['table_columns'][ $column_slug ] = $column_args;
				} else {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The admin table column slug %1$s (for post type %2$s) is invalid. It must be prefix with either &quot;meta-&quot;, &quot;taxonomy-&quot; or &quot;custom-&quot;.', 'post-types-definitely' ), $column_slug, $this->post_type_slug ), '0.5.0' );
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

		protected function render_taxonomy_column_filter( $column_slug, $taxonomy ) {
			$labels = $taxonomy->labels;
			echo '<label class="screen-reader-text" for="' . $column_slug . '">' . $labels['filter_by_item'] . '</label>';
			wp_dropdown_categories( array(
				'taxonomy'			=> $taxonomy->slug,
				'name'				=> $column_slug,
				'show_option_all'	=> $labels['all_items'],
				'hide_empty'		=> 0,
				'hierarchical'		=> $taxonomy->hierarchical ? 1 : 0,
				'show_count'		=> 0,
				'orderby'			=> 'name',
				'selected'			=> ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] ) ? absint( $this->active_filters[ $column_slug ] ) : 0,
			) );
		}

		protected function render_meta_column_filter( $column_slug, $field ) {
			switch ( $field->type ) {
				case 'select':
				case 'multiselect':
				case 'radio':
				case 'multibox':
					echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
					echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
					foreach ( $field->options as $value => $label ) {
						echo '<option value="' . esc_attr( $value ) . '"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == $value ) ? ' selected="selected"' : '' ) . '>';
						if ( is_array( $label ) ) {
							if ( isset( $label['label'] ) && ! empty( $label['label'] ) ) {
								echo esc_html( $label['label'] );
							} elseif ( isset( $label['image'] ) ) {
								echo esc_html( $label['image'] );
							} elseif ( isset( $label['color'] ) ) {
								echo esc_html( $label['color'] );
							}
						} else {
							echo esc_html( $label );
						}
						echo '</option>';
					}
					echo '</select>';
					break;
				case 'checkbox':
					echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
					echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
					echo '<option value="bool:true"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == 'bool:true' ) ? ' selected="selected"' : '' ) . '>';
					_e( 'Yes', 'post-types-definitely' );
					echo '</option>';
					echo '<option value="bool:false"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == 'bool:false' ) ? ' selected="selected"' : '' ) . '>';
					_e( 'No', 'post-types-definitely' );
					echo '</option>';
					echo '</select>';
					break;
				case 'text':
				case 'email':
				case 'url':
				case 'datetime':
				case 'date':
				case 'time':
				case 'color':
				case 'media':
					$options = General::get_all_meta_values( $field->slug, $this->post_type_slug );
					if ( count( $options ) > 0 ) {
						echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
						echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
						foreach ( $options as $option ) {
							echo '<option value="' . esc_attr( $option ) . '"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == $option ) ? ' selected="selected"' : '' ) . '>';
							echo $field->_field->parse( $option, true );
							echo '</option>';
						}
						echo '</select>';
					}
					break;
				default:
			}
		}

		protected function filter_by_taxonomy( $value, $column_slug, $taxonomy_slug ) {
			$term_id = absint( $value );
			if ( $term_id > 0 ) {
				$this->active_filters[ $column_slug ] = $term_id;
				return array(
					'taxonomy'	=> $taxonomy_slug,
					'field'		=> 'term_id',
					'terms'		=> $term_id,
				);
			}

			return array();
		}

		protected function filter_by_meta( $value, $column_slug, $meta_key ) {
			$meta_value = stripslashes( $value);
			if ( $meta_value ) {
				$this->active_filters[ $column_slug ] = $meta_value;
				if ( 'bool:true' === $meta_value ) {
					return array(
						'key'		=> $meta_key,
						'value'		=> array( '', '0', 'false', 'null' ),
						'compare'	=> 'NOT IN',
					);
				} elseif ( 'bool:false' === $meta_value ) {
					return array(
						'key'		=> $meta_key,
						'value'		=> array( '', '0', 'false', 'null' ),
						'compare'	=> 'IN',
					);
				} else {
					return array(
						'key'		=> $meta_key,
						'value'		=> $meta_value,
						'compare'	=> '=',
					);
				}
			}

			return array();
		}

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
				set_transient( 'wpptd_' . $this->post_type_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			wp_redirect( add_query_arg( 'updated', 1, $sendback ) );
			exit();
		}

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
				set_transient( 'wpptd_' . $this->post_type_slug . '_bulk_row_action_message', $action_message, MINUTE_IN_SECONDS );
			}

			$sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

			wp_redirect( add_query_arg( 'updated', count( $post_ids ), $sendback ) );
			exit();
		}

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

	}

}