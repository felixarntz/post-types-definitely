<?php
/**
 * WPPTD\PostTypeQueryHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\PostTypeQueryHandler' ) ) {
	/**
	 * This class adjusts `WP_Query` for a post type registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class PostTypeQueryHandler extends QueryHandler {
		/**
		 * @since 0.6.1
		 * @var array helper variable to temporarily hold the active filters in the post type list screen
		 */
		protected $active_filters = array();

		/**
		 * Returns the currently active filters.
		 *
		 * @since 0.6.1
		 * @return array current filters as $column_slug => $filter_value
		 */
		public function get_active_filters() {
			return $this->active_filters;
		}

		/**
		 * This filter registers the necessary query variables in WP_Query to detect whether we should filter by them.
		 *
		 * @since 0.6.1
		 * @param array $vars array of original query vars
		 * @return array the query vars array including the new ones
		 */
		public function register_table_filter_query_vars( $vars ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->component->slug, $column_args['taxonomy_slug'] ) ) {
							$vars[] = $column_slug;
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$vars[] = $column_slug;
					}
				}
			}

			return $vars;
		}

		/**
		 * This action actually adjusts the current query to filter by whatever filters are active.
		 *
		 * It builds the 'tax_query' and 'meta_query' keys and appends them to the query.
		 *
		 * @since 0.6.1
		 * @see WPPTD\PostTypeQueryHandler::filter_by_taxonomy()
		 * @see WPPTD\PostTypeQueryHandler::filter_by_meta()
		 * @param WP_Query $wp_query the current instance of WP_Query
		 */
		public function maybe_filter_by_table_columns( $wp_query ) {
			$table_columns = $this->component->table_columns;

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

		/**
		 * This filter modifies the current query to sort by a specific taxonomy term.
		 *
		 * WordPress does not natively support this, so the actual SQL query needs to be altered to achieve this.
		 *
		 * Code comes from http://scribu.net/wordpress/sortable-taxonomy-columns.html
		 *
		 * @since 0.6.1
		 * @param array $clauses array of SQL clauses
		 * @param WP_Query $wp_query the current instance of WP_Query
		 * @return array the modified array of SQL clauses
		 */
		public function maybe_sort_by_taxonomy_table_column( $clauses, $wp_query ) {
			global $wpdb;

			$table_columns = $this->component->table_columns;

			if ( ! isset( $wp_query->query['orderby'] ) || is_array( $wp_query->query['orderby'] ) ) {
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

		/**
		 * This action adjusts a few default settings in the table screen.
		 *
		 * If the 'date' column has been removed...
		 * - it also removes the months dropdown filter
		 * - it sets the default sort mode by 'title' (asc)
		 *
		 * @since 0.6.1
		 */
		public function maybe_sort_default() {
			$table_columns = $this->component->table_columns;

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

		/**
		 * Creates an array to append to the 'tax_query' in order to filter by a specific taxonomy term.
		 *
		 * @since 0.6.1
		 * @param integer $value the term ID to filter by
		 * @param string $column_slug the slug of the taxonomy column
		 * @param string $taxonomy_slug the taxonomy slug
		 * @return array the array to append to the current 'tax_query'
		 */
		protected function filter_by_taxonomy( $value, $column_slug, $taxonomy_slug ) {
			$term_id = absint( $value );
			if ( 0 < $term_id ) {
				$this->active_filters[ $column_slug ] = $term_id;
				return array(
					'taxonomy'	=> $taxonomy_slug,
					'field'		=> 'term_id',
					'terms'		=> $term_id,
				);
			}

			return array();
		}

		/**
		 * Creates an array to append to the 'meta_query' in order to filter by a specific meta field value.
		 *
		 * @since 0.6.1
		 * @param mixed $value the meta value to filter by
		 * @param string $column_slug the slug of the meta field column
		 * @param string $meta_key the meta key
		 * @return array the array to append to the current 'meta_query'
		 */
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
	}
}
