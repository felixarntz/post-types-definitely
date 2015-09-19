<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;
use WPDLib\Util\Error as UtilError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\PostType' ) ) {

	class PostType extends Base {

		protected $active_filters = array();

		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_post_type_validated';
		}

		public function is_already_added() {
			return post_type_exists( $this->slug );
		}

		public function register() {
			if ( ! $this->is_already_added() ) {
				$_post_type_args = $this->args;

				unset( $_post_type_args['title'] );
				unset( $_post_type_args['singular_title'] );
				unset( $_post_type_args['messages'] );
				unset( $_post_type_args['enter_title_here'] );
				unset( $_post_type_args['show_add_new_in_menu'] );
				unset( $_post_type_args['table_columns'] );
				unset( $_post_type_args['row_actions'] );
				unset( $_post_type_args['bulk_actions'] );
				unset( $_post_type_args['help'] );
				unset( $_post_type_args['list_help'] );

				$_post_type_args['label'] = $this->args['title'];
				$_post_type_args['register_meta_box_cb'] = array( $this, 'add_meta_boxes' );

				$post_type_args = array();
				foreach ( $_post_type_args as $key => $value ) {
					if ( null !== $value ) {
						$post_type_args[ $key ] = $value;
					}
				}

				register_post_type( $this->slug, $post_type_args );
			} else {
				//TODO: merge several properties into existing post type
			}
		}

		public function add_to_menu( $args ) {
			if ( ! $this->args['show_ui'] ) {
				return false;
			}

			if ( 'submenu' == $args['mode'] && null === $args['menu_slug'] ) {
				return false;
			}

			$ret = false;

			$sub_slug = $this->get_menu_slug();

			if ( ! in_array( $this->slug, array( 'post', 'page', 'attachment' ) ) ) {
				$post_type_obj = get_post_type_object( $this->slug );
				if ( 'menu' === $args['mode'] ) {
					add_menu_page( '', $args['menu_label'], $post_type_obj->cap->edit_posts, $this->get_menu_slug(), '', $args['menu_icon'], $args['menu_position'] );
					$ret = $post_type_obj->labels->all_items;
					$add_new_label = $post_type_obj->labels->add_new;
				} else {
					add_submenu_page( $args['menu_slug'], $post_type_obj->labels->name, $post_type_obj->labels->menu_name, $post_type_obj->cap->edit_posts, $this->get_menu_slug() );
					$ret = $post_type_obj->labels->menu_name;
					$add_new_label = $post_type_obj->labels->add_new_item;
					$sub_slug = $args['menu_slug'];
				}

				if ( $this->args['show_add_new_in_menu'] ) {
					add_submenu_page( $sub_slug, '', $add_new_label, $post_type_obj->cap->create_posts, 'post-new.php?post_type=' . $this->slug );
				}
			}

			foreach ( $this->get_children( 'WPPTD\Components\Taxonomy' ) as $taxonomy ) {
				if ( $taxonomy->show_in_menu ) {
					$taxonomy_obj = get_taxonomy( $taxonomy->slug );
					add_submenu_page( $sub_slug, $taxonomy_obj->labels->name, $taxonomy_obj->labels->menu_name, $taxonomy_obj->cap->manage_terms, 'edit-tags.php?taxonomy=' . $taxonomy->slug . '&post_type=' . $this->slug );
				}
			}

			return $ret;
		}

		public function get_menu_slug() {
			if ( 'post' == $this->slug ) {
				return 'edit.php';
			} elseif ( 'attachment' == $this->slug ) {
				return 'upload.php';
			} elseif ( 'link' == $this->slug ) {
				return 'link-manager.php';
			}
			return 'edit.php?post_type=' . $this->slug;
		}

		public function add_meta_boxes( $post ) {
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				$metabox->register( $this );
			}
		}

		public function save_meta( $post_id, $post, $update = false ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( get_post_type( $post_id ) != $this->slug ) {
				return;
			}

			if ( wp_is_post_revision( $post ) ) {
				return;
			}

			$post_type_obj = get_post_type_object( $this->slug );
			if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
				return;
			}

			$meta_values = $_POST;

			$meta_values_validated = array();

			$meta_values_old = array();

			$errors = array();

			$changes = false;

			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$meta_value_old = wpptd_get_post_meta( $post_id, $field->slug );
					if ( $meta_value_old === null ) {
						$meta_value_old = $field->default;
					}
					$meta_values_old[ $field->slug ] = $meta_value_old;

					$meta_value = null;
					if ( isset( $meta_values[ $field->slug ] ) ) {
						$meta_value = $meta_values[ $field->slug ];
					}

					$meta_value = $field->validate_meta_value( $meta_value );
					if ( is_wp_error( $meta_value ) ) {
						$errors[ $field->slug ] = $meta_value;
						$meta_value = $meta_value_old;
					}

					$meta_values_validated[ $field->slug ] = $meta_value;

					if ( $meta_value != $meta_value_old ) {
						do_action( 'wpptd_update_meta_value_' . $this->slug . '_' . $field->slug, $meta_value, $meta_value_old );
						$changes = true;
					}
				}
			}

			if ( $changes ) {
				do_action( 'wpptd_update_meta_values_' . $this->slug, $meta_values_validated, $meta_values_old );
			}

			$meta_values_validated = apply_filters( 'wpptd_validated_meta_values', $meta_values_validated );

			if ( count( $errors ) > 0 ) {
				$error_text = __( 'Some errors occurred while trying to save the following post meta:', 'wpptd' );
				foreach ( $errors as $field_slug => $error ) {
					$error_text .= '<br/><em>' . $field_slug . '</em>: ' . $error->get_error_message();
				}

				set_transient( 'wpptd_meta_error_' . $this->slug . '_' . $post_id, $error_text, 120 );
			}

			foreach ( $meta_values_validated as $field_slug => $meta_value_validated ) {
				if ( is_array( $meta_value_validated ) ) {
					delete_post_meta( $post_id, $field_slug );
					foreach ( $meta_value_validated as $mv ) {
						add_post_meta( $post_id, $field_slug, $mv );
					}
				} else {
					update_post_meta( $post_id, $field_slug, $meta_value_validated );
				}
			}
		}

		public function enqueue_assets() {
			$_fields = array();
			foreach ( $this->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$_fields[] = $field->_field;
				}
			}

			FieldManager::enqueue_assets( $_fields );
		}

		public function render_help() {
			$screen = get_current_screen();

			foreach ( $this->args['help']['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $this->args['help']['sidebar'] ) ) {
				$screen->set_help_sidebar( $this->args['help']['sidebar'] );
			}
		}

		public function render_list_help() {
			$screen = get_current_screen();

			foreach ( $this->args['list_help']['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $this->args['list_help']['sidebar'] ) ) {
				$screen->set_help_sidebar( $this->args['list_help']['sidebar'] );
			}
		}

		public function get_updated_messages( $post, $permalink = '', $revision = false ) {
			$messages = $this->args['messages'];

			$messages[1] = sprintf( $messages[1], $permalink );
			if ( $revision ) {
				$messages[5] = sprintf( $messages[5], wp_post_revision_title( $revision, false ) );
			} else {
				$messages[5] = false;
			}
			$messages[6] = sprintf( $messages[6], $permalink );
			$messages[8] = sprintf( $messages[8], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );
			$messages[9] = sprintf( $messages[9], date_i18n( __( 'M j, Y @ H:i' ), strtotime( $post->post_date ) ), esc_url( $permalink ) );
			$messages[10] = sprintf( $messages[10], esc_url( add_query_arg( 'preview', 'true', $permalink ) ) );

			return $messages;
		}

		public function get_enter_title_here( $post ) {
			return $this->args['enter_title_here'];
		}

		public function get_table_taxonomies( $taxonomies ) {
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( $column_args && ! empty( $column_args['taxonomy_slug'] ) && is_object_in_taxonomy( $this->slug, $column_args['taxonomy_slug'] ) ) {
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
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
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
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( false === $column_args ) {
					if ( isset( $columns[ $column_slug ] ) ) {
						unset( $columns[ $column_slug ] );
					}
				} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
					if ( $column_args['sortable'] ) {
						$field = ComponentManager::get( '*.' . $this->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
						}
					}
				} elseif ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
					if ( $column_args['sortable'] && is_object_in_taxonomy( $this->slug, $column_args['taxonomy_slug'] ) ) {
						$columns[ $column_slug ] = ( is_string( $column_args['sortable'] ) && 'desc' === strtolower( $column_args['sortable'] ) ) ? array( $column_slug, true ) : array( $column_slug, false );
					}
				}
			}

			return $columns;
		}

		public function render_table_column( $column_name, $post_id ) {
			if ( isset( $this->args['table_columns'][ $column_name ] ) ) {
				if ( isset( $this->args['table_columns'][ $column_name ]['meta_key'] ) && ! empty( $this->args['table_columns'][ $column_name ]['meta_key'] ) ) {
					$field = ComponentManager::get( '*.' . $this->slug . '.*.' . $this->args['table_columns'][ $column_name ]['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
					if ( $field ) {
						$field->render_table_column( $post_id );
					}
				} elseif ( $this->args['custom_callback'] && is_callable( $this->args['custom_callback'] ) ) {
					call_user_func( $this->args['custom_callback'], $post_id );
				}
			}
		}

		public function render_table_column_filters() {
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->slug, $column_args['taxonomy_slug'] ) ) {
							$taxonomy = ComponentManager::get( '*.' . $this->slug . '.' . $column_args['taxonomy_slug'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
							if ( $taxonomy ) {
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
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$field = ComponentManager::get( '*.' . $this->slug . '.*.' . $column_args['meta_key'], 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
						if ( $field ) {
							switch ( $field->type ) {
								case 'select':
								case 'multiselect':
								case 'radio':
								case 'multibox':
									echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
									echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'wpptd' ) . '</option>';
									foreach ( $field->options as $value => $label ) {
										echo $value;
										echo $this->active_filters[ $column_slug ];
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
									echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'wpptd' ) . '</option>';
									echo '<option value="bool:true"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == 'bool:true' ) ? ' selected="selected"' : '' ) . '>';
									_e( 'Yes', 'wpptd' );
									echo '</option>';
									echo '<option value="bool:false"' . ( ( isset( $this->active_filters[ $column_slug ] ) && $this->active_filters[ $column_slug ] == 'bool:false' ) ? ' selected="selected"' : '' ) . '>';
									_e( 'No', 'wpptd' );
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
									$options = $this->get_all_meta_values( $column_args['meta_key'] );
									if ( count( $options ) > 0 ) {
										echo '<select name="' . $column_slug . '" id="' . $column_slug . '" class="postform">';
										echo '<option value="">' . esc_html( $field->title ) . ': ' . __( 'All', 'wpptd' ) . '</option>';
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
					}
				}
			}
		}

		public function register_table_filter_query_vars( $vars ) {
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] ) {
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) ) {
						if ( 'category' !== $column_args['taxonomy_slug'] && is_object_in_taxonomy( $this->slug, $column_args['taxonomy_slug'] ) ) {
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
			$tax_query = array();
			$meta_query = array();
			foreach ( $this->args['table_columns'] as $column_slug => $column_args ) {
				if ( is_array( $column_args ) && $column_args['filterable'] && isset( $wp_query->query[ $column_slug ] ) ) {
					$this->active_filters[ $column_slug ] = false;
					if ( isset( $column_args['taxonomy_slug'] ) && ! empty( $column_args['taxonomy_slug'] ) && 'category' !== $column_args['taxonomy_slug'] ) {
						$term_id = absint( $wp_query->query[ $column_slug ] );
						if ( $term_id > 0 ) {
							$this->active_filters[ $column_slug ] = $term_id;
							$tax_query[] = array(
								'taxonomy'	=> $column_args['taxonomy_slug'],
								'field'		=> 'term_id',
								'terms'		=> $term_id,
							);
						}
					} elseif ( isset( $column_args['meta_key'] ) && ! empty( $column_args['meta_key'] ) ) {
						$meta_value = stripslashes( $wp_query->query[ $column_slug ] );
						if ( $meta_value ) {
							$this->active_filters[ $column_slug ] = $meta_value;
							if ( 'bool:true' === $meta_value ) {
								$meta_query[] = array(
									'key'		=> $column_args['meta_key'],
									'value'		=> array( '', '0', 'false', 'null' ),
									'compare'	=> 'NOT IN',
								);
							} elseif ( 'bool:false' === $meta_value ) {
								$meta_query[] = array(
									'key'		=> $column_args['meta_key'],
									'value'		=> array( '', '0', 'false', 'null' ),
									'compare'	=> 'IN',
								);
							} else {
								$meta_query[] = array(
									'key'		=> $column_args['meta_key'],
									'value'		=> $meta_value,
									'compare'	=> '=',
								);
							}
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
			if ( ! isset( $wp_query->query['orderby'] ) ) {
				return;
			}

			$orderby = $wp_query->query['orderby'];

			if ( ! isset( $this->args['table_columns'][ $orderby ] ) ) {
				return;
			}

			if ( ! $this->args['table_columns'][ $orderby ]['sortable'] ) {
				return;
			}

			if ( ! isset( $this->args['table_columns'][ $orderby ]['meta_key'] ) || empty( $this->args['table_columns'][ $orderby ]['meta_key'] ) ) {
				return;
			}

			$wp_query->set( 'meta_key', $this->args['table_columns'][ $orderby ]['meta_key'] );
			$wp_query->set( 'orderby', 'meta_value' );
		}

		public function maybe_sort_by_taxonomy_table_column( $clauses, $wp_query ) {
			global $wpdb;

			if ( ! isset( $wp_query->query['orderby'] ) ) {
				return $clauses;
			}

			$orderby = $wp_query->query['orderby'];

			if ( ! isset( $this->args['table_columns'][ $orderby ] ) ) {
				return $clauses;
			}

			if ( ! $this->args['table_columns'][ $orderby ]['sortable'] ) {
				return $clauses;
			}

			if ( ! isset( $this->args['table_columns'][ $orderby ]['taxonomy_slug'] ) || empty( $this->args['table_columns'][ $orderby ]['taxonomy_slug'] ) ) {
				return $clauses;
			}

			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->term_relationships . " AS wpptd_tr ON ( " . $wpdb->posts . ".ID = wpptd_tr.object_id )";
			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->term_taxonomy . " AS wpptd_tt ON ( wpptd_tr.term_taxonomy_id = wpptd_tt.term_taxonomy_id )";
			$clauses['join'] .= " LEFT OUTER JOIN " . $wpdb->terms . " AS wpptd_t ON ( wpptd_tt.term_id = wpptd_t.term_id )";
			$clauses['where'] .= $wpdb->prepare( " AND ( taxonomy = %s OR taxonomy IS NULL )", $this->args['table_columns'][ $orderby ]['taxonomy_slug'] );
			$clauses['groupby'] = 'wpptd_tr.object_id';
			$clauses['orderby'] = "GROUP_CONCAT( wpptd_t.name ORDER BY name ASC ) " . ( ( 'asc' === strtolower( $wp_query->query['order'] ) ) ? 'ASC' : 'DESC' );

			return $clauses;
		}

		public function maybe_sort_default() {
			if ( isset( $_GET['orderby'] ) ) {
				return;
			}

			if ( ! isset( $this->args['table_columns']['date'] ) || $this->args['table_columns']['date'] ) {
				return;
			}

			// remove month dropdown if the date is irrelevant
			add_filter( 'disable_months_dropdown', '__return_true' );

			// sort by title if the date is irrelevant
			$_GET['orderby'] = 'title';
			$_GET['order'] = 'asc';
		}

		public function filter_row_actions( $row_actions ) {
			return $row_actions;
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {

				if ( in_array( $this->slug, array( 'revision', 'nav_menu_item', 'action', 'author', 'order', 'plugin', 'theme' ) ) ) {
					return new UtilError( 'no_valid_post_type', sprintf( __( 'The post type slug %s is forbidden since it would interfere with WordPress Core functionality.', 'wpptd' ), $this->slug ), '', ComponentManager::get_scope() );
				}

				// show notice if slug contains dashes
				if ( strpos( $this->slug, '-' ) !== false ) {
					App::doing_it_wrong( __METHOD__, sprintf( __( 'The post type slug %s contains dashes which is discouraged. It will still work for the most part, but we recommend to adjust the slug if possible.', 'wpptd' ), $this->slug ), '0.5.0' );
				}

				// generate titles if not provided
				if ( empty( $this->args['title'] ) && isset( $this->args['label'] ) ) {
					$this->args['title'] = $this->args['label'];
					unset( $this->args['label'] );
				}
				if ( empty( $this->args['title'] ) ) {
					if ( empty( $this->args['singular_title'] ) ) {
						$this->args['singular_title'] = ucwords( str_replace( '_', '', $this->slug ) );
					}
					$this->args['title'] = $this->args['singular_title'] . 's';
				} elseif ( empty( $this->args['singular_title'] ) ) {
					$this->args['singular_title'] = $this->args['title'];
				}

				// generate post type labels
				if ( ! is_array( $this->args['labels'] ) ) {
					$this->args['labels'] = array();
				}
				$default_labels = array(
					'name'					=> $this->args['title'],
					'singular_name'			=> $this->args['singular_title'],
					'menu_name'				=> $this->args['title'],
					'name_admin_bar'		=> $this->args['singular_title'],
					'all_items'				=> sprintf( __( 'All %s', 'wpptd' ), $this->args['title'] ),
					'add_new'				=> __( 'Add New', 'wpptd' ),
					'add_new_item'			=> sprintf( __( 'Add New %s', 'wpptd' ), $this->args['singular_title'] ),
					'edit_item'				=> sprintf( __( 'Edit %s', 'wpptd' ), $this->args['singular_title'] ),
					'new_item'				=> sprintf( __( 'New %s', 'wpptd' ), $this->args['singular_title'] ),
					'view_item'				=> sprintf( __( 'View %s', 'wpptd' ), $this->args['singular_title'] ),
					'search_items'			=> sprintf( __( 'Search %s', 'wpptd' ), $this->args['title'] ),
					'not_found'				=> sprintf( __( 'No %s found', 'wpptd' ), $this->args['title'] ),
					'not_found_in_trash'	=> sprintf( __( 'No %s found in Trash', 'wpptd' ), $this->args['title'] ),
					'parent_item_colon'		=> sprintf( __( 'Parent %s:', 'wpptd' ), $this->args['singular_title'] ),
					'featured_image'		=> sprintf( __( 'Featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'set_featured_image'	=> sprintf( __( 'Set featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'remove_featured_image'	=> sprintf( __( 'Remove featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					'use_featured_image'	=> sprintf( __( 'Use as featured %s Image', 'wpptd' ), $this->args['singular_title'] ),
					// additional labels for media library
					'insert_into_item'		=> sprintf( __( 'Insert into %s content', 'wpptd' ), $this->args['singular_title'] ),
					'uploaded_to_this_item'	=> sprintf( __( 'Uploaded to this %s', 'wpptd' ), $this->args['singular_title'] ),
				);
				foreach ( $default_labels as $type => $default_label ) {
					if ( ! isset( $this->args['labels'][ $type ] ) ) {
						$this->args['labels'][ $type ] = $default_label;
					}
				}

				// generate post type updated messages
				if ( ! is_array( $this->args['messages'] ) ) {
					$this->args['messages'] = array();
				}
				$default_messages = array(
					 0 => '',
					 1 => sprintf( __( '%1$s updated. <a href="%%s">View %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 2 => __( 'Custom field updated.', 'wpptd' ),
					 3 => __( 'Custom field deleted.', 'wpptd' ),
					 4 => sprintf( __( '%s updated.', 'wpptd' ), $this->args['singular_title'] ),
					 5 => sprintf( __( '%s restored to revision from %%s', 'wpptd' ), $this->args['singular_title'] ),
					 6 => sprintf( __( '%1$s published. <a href="%%s">View %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 7 => sprintf( __( '%s saved.', 'wpptd' ), $this->args['singular_title'] ),
					 8 => sprintf( __( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					 9 => sprintf( __( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
					10 => sprintf( __( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'wpptd' ), $this->args['singular_title'] ),
				);
				foreach ( $default_messages as $i => $default_message ) {
					if ( ! isset( $this->args['messages'][ $i ] ) ) {
						$this->args['messages'][ $i ] = $default_message;
					}
				}

				// set some defaults
				if ( null === $this->args['rewrite'] ) {
					if ( $this->args['public'] ) {
						$this->args['rewrite'] = array(
							'slug'			=> str_replace( '_', '-', $this->slug ),
							'with_front'	=> false,
							'ep_mask'		=> EP_PERMALINK,
						);
					} else {
						$this->args['rewrite'] = false;
					}
				}
				if ( null === $this->args['show_ui'] ) {
					$this->args['show_ui'] = $this->args['public'];
				}
				$menu = $this->get_parent();
				if ( $this->args['show_in_menu'] && empty( $menu->slug ) ) {
					$this->args['show_in_menu'] = true;
				} else {
					$this->args['show_in_menu'] = false;
					if ( isset( $this->args['menu_position'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu position is unnecessarily provided for the post type %s - the menu position is already specified by its parent menu.', 'wpptd' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_position'] );
					}
					if ( isset( $this->args['menu_icon'] ) ) {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'A menu icon is unnecessarily provided for the post type %s - the menu icon is already specified by its parent menu.', 'wpptd' ), $this->slug ), '0.5.0' );
						unset( $this->args['menu_icon'] );
					}
				}

				if ( null !== $this->args['position'] ) {
					$this->args['position'] = floatval( $this->args['position'] );
				}

				// handle admin table columns
				if ( ! $this->args['show_ui'] || ! is_array( $this->args['table_columns'] ) ) {
					$this->args['table_columns'] = array();
				}
				$_table_columns = $this->args['table_columns'];
				$this->args['table_columns'] = array();
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
						$this->args['table_columns'][ $column_slug ] = $column_args;
					} else {
						App::doing_it_wrong( __METHOD__, sprintf( __( 'The admin table column slug %1$s (for post type %2$s) is invalid. It must be prefix with either &quot;meta-&quot;, &quot;taxonomy-&quot; or &quot;custom-&quot;.', 'wpptd' ), $column_slug, $this->slug ), '0.5.0' );
					}
				}

				// handle row actions
				if ( ! $this->args['show_ui'] || ! is_array( $this->args['row_actions'] ) ) {
					$this->args['row_actions'] = array();
				}

				// handle bulk actions
				if ( ! $this->args['show_ui'] || ! is_array( $this->args['bulk_actions'] ) ) {
					$this->args['bulk_actions'] = array();
				}

				// handle help
				if( ! is_array( $this->args['help'] ) ) {
					$this->args['help'] = array();
				}
				if ( ! isset( $this->args['help']['tabs'] ) || ! is_array( $this->args['help']['tabs'] ) ) {
					$this->args['help']['tabs'] = array();
				}
				if ( ! isset( $this->args['help']['sidebar'] ) ) {
					$this->args['help']['sidebar'] = '';
				}
				foreach ( $this->args['help']['tabs'] as &$tab ) {
					$tab = wp_parse_args( $tab, array(
						'title'			=> __( 'Help tab title', 'wpptd' ),
						'content'		=> '',
						'callback'		=> false,
					) );
				}
				unset( $tab );

				// handle list help
				if( ! is_array( $this->args['list_help'] ) ) {
					$this->args['list_help'] = array();
				}
				if ( ! isset( $this->args['list_help']['tabs'] ) || ! is_array( $this->args['list_help']['tabs'] ) ) {
					$this->args['list_help']['tabs'] = array();
				}
				if ( ! isset( $this->args['list_help']['sidebar'] ) ) {
					$this->args['list_help']['sidebar'] = '';
				}
				foreach ( $this->args['list_help']['tabs'] as &$tab ) {
					$tab = wp_parse_args( $tab, array(
						'title'			=> __( 'Help tab title', 'wpptd' ),
						'content'		=> '',
						'callback'		=> false,
					) );
				}
				unset( $tab );
			}

			return $status;
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the post type arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'					=> '',
				'singular_title'		=> '',
				'labels'				=> array(),
				'messages'				=> array(),
				'enter_title_here'		=> '',
				'description'			=> '',
				'public'				=> false,
				'exclude_from_search'	=> null,
				'publicly_queryable'	=> null,
				'show_ui'				=> null,
				'show_in_menu'			=> null,
				'show_add_new_in_menu'	=> true,
				'show_in_admin_bar'		=> null,
				'capability_type'		=> 'post',
				'capabilities'			=> array(),
				'map_meta_cap'			=> null,
				'hierarchical'			=> false,
				'supports'				=> array( 'title', 'editor' ),
				'has_archive'			=> false,
				'rewrite'				=> null,
				'query_var'				=> true,
				'can_export'			=> true,
				'position'				=> null,
				'table_columns'			=> array(),
				'row_actions'			=> array(),
				'bulk_actions'			=> array(),
				'help'					=> array(
					'tabs'					=> array(),
					'sidebar'				=> '',
				),
				'list_help'				=> array(
					'tabs'					=> array(),
					'sidebar'				=> '',
				),
			);

			/**
			 * This filter can be used by the developer to modify the default values for each post type component.
			 *
			 * @since 0.5.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_post_type_defaults', $defaults );
		}

		/**
		 * Returns whether this component supports multiple parents.
		 *
		 * @since 0.5.0
		 * @return bool
		 */
		protected function supports_multiparents() {
			return false;
		}

		/**
		 * Returns whether this component supports global slugs.
		 *
		 * If it does not support global slugs, the function either returns false for the slug to be globally unique
		 * or the class name of a parent component to ensure the slug is unique within that parent's scope.
		 *
		 * @since 0.5.0
		 * @return bool|string
		 */
		protected function supports_globalslug() {
			return false;
		}

		protected function get_all_meta_values( $meta_key ) {
			global $wpdb;

			$query = "SELECT DISTINCT meta_value FROM " . $wpdb->postmeta . " AS m JOIN " . $wpdb->posts . " as p ON ( p.ID = m.post_id )";
			$query .= " WHERE m.meta_key = %s AND m.meta_value != '' AND p.post_type = %s ORDER BY m.meta_value ASC;";

			return $wpdb->get_col( $wpdb->prepare( $query, $meta_key, $this->slug ) );
		}

	}

}
