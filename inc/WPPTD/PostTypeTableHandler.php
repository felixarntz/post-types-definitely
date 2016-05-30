<?php
/**
 * WPPTD\PostTypeTableHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
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
	class PostTypeTableHandler extends TableHandler {
		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $post_type the post type component to use this handler for
		 * @param null $query_handler only for parent class, must not be used here
		 * @param null $action_handler only for parent class, must not be used here
		 */
		public function __construct( $post_type, $query_handler = null, $action_handler = null ) {
			parent::__construct( $post_type, new PostTypeQueryHandler( $post_type ), new PostTypeActionHandler( $post_type ) );
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
						$field = $this->get_child_field( $column_args['meta_key'] );
						if ( $field ) {
							$this->render_meta_column_filter( $column_slug, $field );
						}
					}
				}
			}
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

			if ( isset( $args['taxonomy_slug'] ) && ! empty( $args['taxonomy_slug'] ) ) {
				if ( $args['sortable'] && is_object_in_taxonomy( $this->component->slug, $args['taxonomy_slug'] ) ) {
					return ( is_string( $args['sortable'] ) && 'desc' === strtolower( $args['sortable'] ) ) ? array( $slug, true ) : array( $slug, false );
				}
			}

			return parent::filter_table_sortable_column( $slug, $args );
		}

		/**
		 * Prints a dropdown to filter by a term of a specific taxonomy.
		 *
		 * @since 0.5.0
		 * @param string $column_slug the slug of the taxonomy column
		 * @param WPPTD\Components\Taxonomy $taxonomy the taxonomy component
		 */
		protected function render_taxonomy_column_filter( $column_slug, $taxonomy ) {
			$active_filters = $this->query_handler->get_active_filters();

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
			$active_filters = $this->query_handler->get_active_filters();

			switch ( $field->type ) {
				case 'select':
				case 'multiselect':
				case 'radio':
				case 'multibox':
					$this->render_meta_column_choice_filter( $column_slug, $field, $active_filters );
					break;
				case 'checkbox':
					$this->render_meta_column_bool_filter( $column_slug, $field, $active_filters );
					break;
				case 'datetime':
				case 'date':
				case 'time':
				case 'text':
				case 'email':
				case 'url':
				case 'color':
				case 'media':
					$this->render_meta_column_input_filter( $column_slug, $field, $active_filters );
					break;
				default:
			}
		}

		/**
		 * Prints a filter dropdown for a meta field with selectable choices.
		 *
		 * @since 0.6.1
		 * @param string $column_slug the slug of the meta field column
		 * @param WPPTD\Components\Field $field the field component
		 * @param array $active_filters currently active filters and their values
		 */
		protected function render_meta_column_choice_filter( $column_slug, $field, $active_filters ) {
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
		}

		/**
		 * Prints a filter dropdown for a meta field with a Yes/No choice.
		 *
		 * @since 0.6.1
		 * @param string $column_slug the slug of the meta field column
		 * @param WPPTD\Components\Field $field the field component
		 * @param array $active_filters currently active filters and their values
		 */
		protected function render_meta_column_bool_filter( $column_slug, $field, $active_filters ) {
			echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
			echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
			echo '<option value="bool:true"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == 'bool:true' ) ? ' selected="selected"' : '' ) . '>';
			_e( 'Yes', 'post-types-definitely' );
			echo '</option>';
			echo '<option value="bool:false"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == 'bool:false' ) ? ' selected="selected"' : '' ) . '>';
			_e( 'No', 'post-types-definitely' );
			echo '</option>';
			echo '</select>';
		}

		/**
		 * Prints a filter dropdown for a meta field with flexible input.
		 *
		 * @since 0.6.1
		 * @param string $column_slug the slug of the meta field column
		 * @param WPPTD\Components\Field $field the field component
		 * @param array $active_filters currently active filters and their values
		 */
		protected function render_meta_column_input_filter( $column_slug, $field, $active_filters ) {
			$options = Utility::get_all_meta_values( $field->slug, $this->component->slug );

			echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
			echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'post-types-definitely' ) . '</option>';
			foreach ( $options as $option ) {
				echo '<option value="' . esc_attr( $option ) . '"' . ( ( isset( $active_filters[ $column_slug ] ) && $active_filters[ $column_slug ] == $option ) ? ' selected="selected"' : '' ) . '>';
				echo $field->_field->parse( $option, true );
				echo '</option>';
			}
			echo '</select>';
		}

		/**
		 * Returns a specific field child component of the post type component.
		 *
		 * @since 0.6.1
		 * @param string $field_slug the slug of the field component to get
		 * @return WPPTD\Components\Field the field component with the slug $field_slug
		 */
		protected function get_child_field( $field_slug ) {
			return ComponentManager::get( '*.' . $this->component->slug . '.*.' . $field_slug, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
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
