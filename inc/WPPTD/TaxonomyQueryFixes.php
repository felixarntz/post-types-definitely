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

if ( ! class_exists( 'WPPTD\TaxonomyQueryFixes' ) ) {
	/**
	 * This class adjusts `get_terms()` for a taxonomy registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class TaxonomyQueryFixes {
		/**
		 * @since 0.6.1
		 * @var WPPTD\Components\Taxonomy Holds the taxonomy component this table handler should manage.
		 */
		protected $component = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.6.1
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component to use this handler for
		 */
		public function __construct( $taxonomy ) {
			$this->component = $taxonomy;
		}

		/**
		 * This action modifies the current `get_terms()` arguments to sort by a specific meta field.
		 *
		 * @since 0.6.1
		 * @param array $args the arguments for `get_terms()`
		 * @param string|array $taxonomies taxonomies to query terms for
		 * @return array the fixed arguments
		 */
		public function maybe_sort_by_meta_table_column( $args, $taxonomies ) {
			$table_columns = $this->component->table_columns;

			if ( ! isset( $args['orderby'] ) || is_array( $args['orderby'] ) ) {
				return $args;
			}

			$orderby = $args['orderby'];

			if ( ! isset( $table_columns[ $orderby ] ) ) {
				return $args;
			}

			if ( ! $table_columns[ $orderby ]['sortable'] ) {
				return $args;
			}

			if ( ! isset( $table_columns[ $orderby ]['meta_key'] ) || empty( $table_columns[ $orderby ]['meta_key'] ) ) {
				return $args;
			}

			$args['meta_key'] = $table_columns[ $orderby ]['meta_key'];
			$args['orderby'] = 'meta_value';

			return $args;
		}
	}
}
