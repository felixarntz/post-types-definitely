<?php
/**
 * WPPTD\QueryHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\QueryHandler' ) ) {
	/**
	 * An abstract query handler for a post type or taxonomy.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	abstract class QueryHandler {
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
		 * Modifies the current query to sort by a specific meta field.
		 *
		 * This method can be hooked into both `pre_get_posts()` and `get_terms_args()`
		 * to be compatible with posts and terms.
		 *
		 * @since 0.6.1
		 * @param WP_Query|array $query the current instance of WP_Query or an array of `get_terms()` arguments
		 */
		public function maybe_sort_by_meta_table_column( $query ) {
			if ( is_object( $query ) ) {
				$args_before = $query->query;
				$args_after = $query->query_vars;
			} else {
				$args_before = $query;
				$args_after = $query;
			}

			$table_columns = $this->component->table_columns;

			if ( ! isset( $args_before['orderby'] ) || is_array( $args_before['orderby'] ) ) {
				return $args_before;
			}

			$orderby = $args_before['orderby'];

			if ( ! isset( $table_columns[ $orderby ] ) ) {
				return $args_before;
			}

			if ( ! $table_columns[ $orderby ]['sortable'] ) {
				return $args_before;
			}

			if ( ! isset( $table_columns[ $orderby ]['meta_key'] ) || empty( $table_columns[ $orderby ]['meta_key'] ) ) {
				return $args_before;
			}

			$args_after['meta_key'] = $table_columns[ $orderby ]['meta_key'];
			$args_after['orderby'] = 'meta_value';

			if ( is_object( $query ) ) {
				$query->query_vars = $args_after;
			}

			return $args_after;
		}
	}
}
