<?php
/**
 * WPPTD\TableHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\TableHandler' ) ) {
	/**
	 * An abstract list table handler for a post type or taxonomy.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	abstract class TableHandler {
		/**
		 * @since 0.5.0
		 * @var WPDLib\Components\Base Holds the component this table handler should manage.
		 */
		protected $component = null;

		/**
		 * @since 0.6.1
		 * @var WPPTD\QueryHandler Holds the query handler for this list table.
		 */
		protected $query_handler = null;

		/**
		 * @since 0.6.1
		 * @var WPPTD\ActionHandler Holds the action handler for this list table.
		 */
		protected $action_handler = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.6.1
		 * @param WPPTD\Components\PostType $post_type the post type component to use this handler for
		 * @param WPPTD\QueryHandler $query_handler the query handler for this list table
		 * @param WPPTD\ActionHandler $action_handler the action handler for this list table
		 */
		public function __construct( $component, $query_handler = null, $action_handler = null ) {
			$this->component = $component;
			if ( null !== $query_handler && is_a( $query_handler, 'WPPTD\QueryHandler' ) ) {
				$this->query_handler = $query_handler;
			}
			if ( null !== $action_handler && is_a( $action_handler, 'WPPTD\ActionHandler' ) ) {
				$this->action_handler = $action_handler;
			}
		}

		/**
		 * Returns the query handler for this list table.
		 *
		 * @since 0.6.1
		 * @return WPPTD\QueryHandler the query handler for this list table
		 */
		public function get_query_handler() {
			return $this->query_handler;
		}

		/**
		 * Returns the action handler for this list table.
		 *
		 * @since 0.6.1
		 * @return WPPTD\ActionHandler the action handler for this list table
		 */
		public function get_action_handler() {
			return $this->action_handler;
		}

		/**
		 * This filter adjusts the list table columns.
		 *
		 * @since 0.5.0
		 * @param array $columns the original table columns as $column_slug => $title
		 * @return array the adjusted table columns
		 */
		public function filter_table_columns( $columns ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				$column_title = $this->filter_table_column( $column_slug, $column_args );
				if ( false === $column_title ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( null !== $column_title ) {
					$columns[ $column_slug ] = $column_title;
				}
			}

			return $columns;
		}

		/**
		 * This filter adjusts the sortable list table columns.
		 *
		 * @since 0.5.0
		 * @param array $columns the original sortable table columns as $column_slug => array( $sort_by, $desc )
		 * @return array the adjusted sortable table columns
		 */
		public function filter_table_sortable_columns( $columns ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				$column_sort = $this->filter_table_sortable_column( $column_slug, $column_args );
				if ( false === $column_sort ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( null !== $column_sort ) {
					$columns[ $column_slug ] = $column_sort;
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
		 * @param string $column_name the column name of the column that should be rendered
		 * @param integer $term_id the term ID for the current row
		 * @return string the actual output for the table column
		 */
		public function render_table_column( $column_name, $term_id ) {
			$table_columns = $this->component->table_columns;
			if ( isset( $table_columns[ $column_name ] ) ) {
				if ( isset( $table_columns[ $column_name ]['meta_key'] ) && ! empty( $table_columns[ $column_name ]['meta_key'] ) ) {
					$field = $this->get_child_field( $table_columns[ $column_name ]['meta_key'] );
					if ( $field ) {
						$field->render_table_column( $term_id );
					}
				} elseif ( $table_columns[ $column_name ]['custom_callback'] && is_callable( $table_columns[ $column_name ]['custom_callback'] ) ) {
					call_user_func( $table_columns[ $column_name ]['custom_callback'], $term_id );
				}
			}
		}

		/**
		 * This function returns the title for a column if this column should be in the table.
		 *
		 * @since 0.6.1
		 * @param string $slug the column slug
		 * @param array $args the column arguments
		 * @return string|null|false either the column sort parameter, null for no changes or false to remove the column if it exists
		 */
		protected function filter_table_column( $slug, $args ) {
			if ( ! is_array( $args ) ) {
				return false;
			}

			if ( isset( $args['meta_key'] ) && ! empty( $args['meta_key'] ) ) {
				$field = $this->get_child_field( $args['meta_key'] );
				if ( $field ) {
					return ! empty( $args['title'] ) ? $args['title'] : $field->title;
				}
			} elseif ( isset( $args['custom_callback'] ) && ! empty( $args['custom_callback'] ) ) {
				return $args['title'];
			}

			return null;
		}

		/**
		 * This function returns the sort parameter for a column if this column should be sortable in the table.
		 *
		 * @since 0.6.1
		 * @param string $slug the column slug
		 * @param array $args the column arguments
		 * @return string|null|false either the column sort parameter, null for no changes or false to remove the column if it exists
		 */
		protected function filter_table_sortable_column( $slug, $args ) {
			if ( ! is_array( $args ) ) {
				return false;
			}

			if ( isset( $args['meta_key'] ) && ! empty( $args['meta_key'] ) ) {
				if ( $args['sortable'] ) {
					$field = $this->get_child_field( $args['meta_key'] );
					if ( $field ) {
						return ( is_string( $args['sortable'] ) && 'desc' === strtolower( $args['sortable'] ) ) ? array( $slug, true ) : array( $slug, false );
					}
				}
			}

			return null;
		}

		/**
		 * This abstract method should return a specific field child component of the list table component.
		 *
		 * @since 0.6.1
		 * @param string $field_slug the slug of the field component to get
		 * @return WPDLib\Components\Base the field component with the slug $field_slug
		 */
		protected abstract function get_child_field( $field_slug );
	}
}
