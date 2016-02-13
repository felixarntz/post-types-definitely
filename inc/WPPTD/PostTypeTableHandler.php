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

if ( ! class_exists( 'WPPTD\PostTypeTableHandler' ) ) {
	/**
	 * This class handles the post list table for a post type registered with WPPTD.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class PostTypeTableHandler {
		/**
		 * @since 0.5.0
		 * @var WPPTD\Components\PostType Holds the post type component this table handler should manage.
		 */
		protected $component = null;

		/**
		 * @since 0.6.1
		 * @var WPPTD\PostTypeQueryFixes Holds the `WP_Query` fix instance for this post type.
		 */
		protected $query_fixes = null;

		/**
		 * @since 0.6.1
		 * @var WPPTD\PostTypeActionHandler Holds the action handler for this post type.
		 */
		protected $action_handler = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $post_type the post type component to use this handler for
		 */
		public function __construct( $post_type ) {
			$this->component = $post_type;
			$this->query_fixes = new PostTypeQueryFixes( $post_type );
			$this->action_handler = new PostTypeActionHandler( $post_type );
		}

		/**
		 * Returns the `WP_Query` fix instance for this post type.
		 *
		 * @since 0.6.1
		 * @return WPPTD\PostTypeQueryFixes the `WP_Query` fix instance for this post type
		 */
		public function get_query_fixes() {
			return $this->query_fixes;
		}

		/**
		 * Returns the action handler for this post type.
		 *
		 * @since 0.6.1
		 * @return WPPTD\PostTypeActionHandler the action handler for this post type
		 */
		public function get_action_handler() {
			return $this->action_handler;
		}

		/**
		 * This filter adjusts the taxonomies that should be presented in the list table.
		 *
		 * @since 0.5.0
		 * @param array $taxonomies the original taxonomy slugs
		 * @return array the adjusted taxonomy slugs
		 */
		public function get_table_taxonomies( $taxonomies ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( $column_args && ! empty( $column_args['taxonomy_slug'] ) && is_object_in_taxonomy( $this->component->slug, $column_args['taxonomy_slug'] ) ) {
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

		/**
		 * This filter adjusts the list table columns.
		 *
		 * Taxonomy columns are not dealt with here since they are handled by the `get_table_taxonomies()` method.
		 *
		 * @since 0.5.0
		 * @param array $columns the original table columns as $column_slug => $title
		 * @return array the adjusted table columns
		 */
		public function filter_table_columns( $columns ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->component->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
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
		 * @since 0.5.0
		 * @param array $columns the original sortable table columns as $column_slug => array( $sort_by, $desc )
		 * @return array the adjusted sortable table columns
		 */
		public function filter_table_sortable_columns( $columns ) {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					if ( $column_args['sortable'] ) {
						$field = ComponentManager::get( '*.' . $this->component->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
						}
					}
				} elseif ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
					if ( $column_args['sortable'] && is_object_in_taxonomy( $this->component->slug, $column_args['taxonomy_slug'] ) ) {
						$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
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
		 * Taxonomy columns are not dealt with here since WordPress renders them automatically.
		 *
		 * @since 0.5.0
		 * @param string $column_name the column name of the column that should be rendered
		 * @param integer $post_id the post ID for the current row
		 */
		public function render_table_column( $column_name, $post_id ) {
			$table_columns = $this->component->table_columns;

			if ( isset( $table_columns[ $column_name ] ) ) {
				if ( isset( $table_columns[ $column_name ]['meta_key'] ) && ! empty( $table_columns[ $column_name ]['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->component->slug . '.*.' . $table_columns[ $column_name ]['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
					if ( $field ) {
						$field->render_table_column( $post_id );
					}
				} elseif ( $table_columns[ $column_name ]['custom_callback'] && is_callable( $table_columns[ $column_name ]['custom_callback'] ) ) {
					call_user_func( $table_columns[ $column_name ]['custom_callback'], $post_id );
				}
			}
		}

		/**
		 * This function renders the necessary filter dropdowns for taxonomies and meta values.
		 *
		 * The taxonomy dropdown for 'category' will never be rendered here because WordPress creates it automatically if needed.
		 *
		 * @since 0.5.0
		 */
		public function render_table_column_filters() {
			$table_columns = $this->component->table_columns;

			foreach ( $table_columns as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->component->slug, $column_args['taxonomy_slug'] ) ) {
							$taxonomy = ComponentManager::get( '*.' . $this->component->slug . '.' . $column_args['taxonomy_slug'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
							if ( $taxonomy ) {
								$this->render_taxonomy_column_filter( $column_slug, $taxonomy );
							}
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$field = ComponentManager::get( '*.' . $this->component->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							$this->render_meta_column_filter( $column_slug, $field );
						}
					}
				}
			}
		}

		/**
		 * Prints a dropdown to filter by a term of a specific taxonomy.
		 *
		 * @since 0.5.0
		 * @param string $column_slug the slug of the taxonomy column
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component
		 */
		protected function render_taxonomy_column_filter( $column_slug, $taxonomy ) {
			$active_filters = $this->query_fixes->get_active_filters();

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
				'selected'			=> ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] ) ? absint( $active_filters[ $column_slug ] ) : 0,
			) );
		}

		/**
		 * Prints a dropdown to filter by a value of a specific meta field.
		 *
		 * @since 0.5.0
		 * @param string $column_slug the slug of the meta field column
		 * @param WPPTD\Components\Field $field the field component
		 */
		protected function render_meta_column_filter( $column_slug, $field ) {
			$active_filters = $this->query_fixes->get_active_filters();

			switch ( $field->type ) {
				case 'select':
				case 'multiselect':
				case 'radio':
				case 'multibox':
					echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
					echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
					foreach ( $field->options as $value => $label ) {
						echo '<option value="' . esc_attr( $value ) . '"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == $value ) ? ' selected="selected"' : '' ) . '>';
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
					echo '<option value="bool:true"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == 'bool:true' ) ? ' selected="selected"' : '' ) . '>';
					_e( 'Yes', 'post-types-definitely' );
					echo '</option>';
					echo '<option value="bool:false"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == 'bool:false' ) ? ' selected="selected"' : '' ) . '>';
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
					$options = Utility::get_all_meta_values( $field->slug, $this->component->slug );
					if ( count( $options ) > 0 ) {
						echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
						echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
						foreach ( $options as $option ) {
							echo '<option value="' . esc_attr( $option ) . '"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == $option ) ? ' selected="selected"' : '' ) . '>';
							echo $field->_field->parse( $option, true );
							echo '</option>';
						}
						echo '</select>';
					}
					break;
				default:
			}
		}

		/**
		 * Validates the post type component arguments that are related to the list table.
		 *
		 * @since 0.5.0
		 * @see WPPTD\Components\PostType::validate()
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
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The admin table column slug %s is invalid. It must be prefixed with either &quot;meta-&quot;, &quot;taxonomy-&quot; or &quot;custom-&quot;.', 'post-types-definitely' ), $column_slug ), '0.5.0' );
				}
			}

			$args = PostTypeActionHandler::validate_args( $args );

			return $args;
		}

	}

}
