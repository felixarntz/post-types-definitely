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

if ( ! class_exists( 'WPPTD\TaxonomyTableHandler' ) ) {
	/**
	 * This class handles the term list table for a taxonomy registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.0
	 */
	class TaxonomyTableHandler {
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
		 * @since 0.6.1
		 * @var WPPTD\TaxonomyQueryFixes Holds the `get_terms()` fix instance for this taxonomy.
		 */
		protected $query_fixes = null;

		/**
		 * @since 0.6.1
		 * @var WPPTD\TaxonomyActionHandler Holds the action handler for this taxonomy.
		 */
		protected $action_handler = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.6.0
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component to use this handler for
		 */
		public function __construct( $taxonomy ) {
			$this->taxonomy = $taxonomy;
			$this->taxonomy_slug = $this->taxonomy->slug;
			$this->query_fixes = new TaxonomyQueryFixes( $taxonomy );
			$this->action_handler = new TaxonomyActionHandler( $taxonomy );
		}

		/**
		 * Returns the `get_terms()` fix instance for this taxonomy.
		 *
		 * @since 0.6.1
		 * @return WPPTD\TaxonomyQueryFixes the `get_terms()` fix instance for this taxonomy
		 */
		public function get_query_fixes() {
			return $this->query_fixes;
		}

		/**
		 * Returns the action handler for this taxonomy.
		 *
		 * @since 0.6.1
		 * @return WPPTD\TaxonomyActionHandler the action handler for this taxonomy
		 */
		public function get_action_handler() {
			return $this->action_handler;
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
		 * Validates the taxonomy component arguments that are related to the list table.
		 *
		 * @since 0.6.0
		 * @see WPPTD\Components\Taxonomy::validate()
		 * @param array $args the original arguments
		 * @return array the validated arguments
		 */
		public static function validate_args( $args ) {
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
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The admin table column slug %s is invalid. It must be prefixed with either &quot;meta-&quot; or &quot;custom-&quot;.', 'post-types-definitely' ), $column_slug ), '0.6.0' );
				}
			}

			$args = TaxonomyActionHandler::validate_args( $args );

			return $args;
		}

	}

}
