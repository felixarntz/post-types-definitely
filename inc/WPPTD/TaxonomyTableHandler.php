<?php
/**
 * WPPTD\TaxonomyTableHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.0
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
	class TaxonomyTableHandler extends TableHandler {
		/**
		 * Class constructor.
		 *
		 * @since 0.6.0
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component to use this handler for
		 * @param null $query_handler only for parent class, must not be used here
		 * @param null $action_handler only for parent class, must not be used here
		 */
		public function __construct( $taxonomy, $query_handler = null, $action_handler = null ) {
			parent::__construct( $taxonomy, new TaxonomyQueryHandler( $taxonomy ), new TaxonomyActionHandler( $taxonomy ) );
		}

		/**
		 * This function filters the output of a list table column.
		 *
		 * @since 0.6.1
		 * @param string $output empty string as default output for the table column
		 * @param string $column_name the column name of the column that should be rendered
		 * @param integer $term_id the term ID for the current row
		 * @return string the actual output for the table column
		 */
		public function filter_table_column_output( $output, $column_name, $term_id ) {
			$table_columns = $this->component->table_columns;

			if ( isset( $table_columns[ $column_name ] ) ) {
				ob_start();
				$this->render_table_column( $column_name, $term_id );
				$output = ob_get_clean();
			}

			return $output;
		}

		/**
		 * Returns a specific field child component of the taxonomy component.
		 *
		 * @since 0.6.1
		 * @param string $field_slug the slug of the field component to get
		 * @return WPPTD\Components\TermField the field component with the slug $field_slug
		 */
		protected function get_child_field( $field_slug ) {
			return ComponentManager::get( '*.*.' . $this->component->slug . '.*.' . $field_slug, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy.WPPTD\Components\TermMetabox', true );
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
