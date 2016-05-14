<?php
/**
 * @package WPPTD
 * @version 0.6.4
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

use WPDLib\Components\Manager as ComponentManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Utility' ) ) {
	/**
	 * This class contains some utility functions.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Utility {

		/**
		 * This function correctly parses (and optionally formats) a meta value.
		 *
		 * @see wpptd_get_post_meta_values()
		 * @see wpptd_get_post_meta_value()
		 * @see wpptd_get_term_meta_values()
		 * @see wpptd_get_term_meta_value()
		 * @since 0.5.0
		 * @param mixed $meta_value the meta value to parse (or format)
		 * @param WPPTD\Components\Field|WPPTD\Components\TermField $field the field component the meta value belongs to
		 * @param null|boolean $single whether to force arrays or no arrays being returned (default is to not force anything)
		 * @param boolean $formatted whether to return automatically formatted values, ready for output (default is false)
		 * @return mixed the parsed (or formatted) meta value
		 */
		public static function parse_meta_value( $meta_value, $field, $single = null, $formatted = false ) {
			$_meta_value = $meta_value;
			$meta_value = null;

			$type_hint = $field->validate_meta_value( null, true );
			if ( is_array( $type_hint ) ) {
				$meta_value = $field->_field->parse( $_meta_value, $formatted );
				if ( $single !== null && $single ) {
					if ( count( $meta_value > 0 ) ) {
						$meta_value = $meta_value[0];
					} else {
						$meta_value = null;
					}
				}
			} else {
				if ( count( $_meta_value ) > 0 ) {
					$meta_value = $field->_field->parse( $_meta_value[0], $formatted );
				} else {
					$meta_value = $field->_field->parse( $field->default, $formatted );
				}
				if ( $single !== null && ! $single ) {
					$meta_value = array( $meta_value );
				}
			}

			return $meta_value;
		}

		/**
		 * This function is a low-level function to get related objects for another specific object.
		 *
		 * The base object can either be a post or a term (specified by an ID) while the related objects
		 * can either be posts, terms or users.
		 *
		 * The function looks through the registered meta fields of the base object that possibly
		 * contain related objects. It is also possible to only return related objects that are stored
		 * in a specific meta field or that have a specific type (that is post type, taxonomy or user role).
		 *
		 * @see wpptd_get_post_related_posts()
		 * @see wpptd_get_post_related_terms()
		 * @see wpptd_get_post_related_users()
		 * @see wpptd_get_term_related_posts()
		 * @see wpptd_get_term_related_terms()
		 * @see wpptd_get_term_related_users()
		 * @since 0.6.0
		 * @param string $mode mode of the ID specified (for the base object), either 'post' or 'term'
		 * @param integer $id a post ID or a term ID (depending the $mode parameter)
		 * @param string $objects_mode mode of the related objects to find, either 'posts', 'terms' or 'users'
		 * @param string $meta_key an optional meta key to only return objects that are stored in a meta field of that name (default is empty)
		 * @param string $object_type an optional type to only return objects of that type, either a specific post type, taxonomy or user role depending on the $objects_mode parameter
		 * @param boolean $single whether to only return a single object (default is false)
		 * @return WP_Post|WP_Term|WP_User|array|null either an object or null (if $single is true) or an array of objects or empty array otherwise
		 */
		public static function get_related_objects( $mode, $id, $objects_mode, $meta_key = '', $object_type = '', $single = false ) {
			// specify variables depending on the base mode
			switch ( $mode ) {
				case 'post':
					$get_func = 'get_post';
					$type_field = 'post_type';
					$component_path = '*.[TYPE]';
					$class_path = 'WPDLib\Components\Menu.WPPTD\Components\PostType';
					$meta_func = 'wpptd_get_post_meta_value';
					break;
				case 'term':
					if ( ! wpptd_supports_termmeta() ) {
						if ( $single ) {
							return null;
						}
						return array();
					}
					$get_func = 'get_term';
					$type_field = 'taxonomy';
					$component_path = '*.*.[TYPE]';
					$class_path = 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy';
					$meta_func = 'wpptd_get_term_meta_value';
					break;
				default:
					if ( $single ) {
						return null;
					}
					return array();
			}

			// specify variables depending on the objects mode
			switch ( $objects_mode ) {
				case 'posts':
					$objects_related_field = 'related_posts_fields';
					$objects_get_func = 'get_post';
					$objects_type_field = 'post_type';
					break;
				case 'terms':
					$objects_related_field = 'related_terms_fields';
					$objects_get_func = 'get_term';
					$objects_type_field = 'taxonomy';
					break;
				case 'users':
					$objects_related_field = 'related_users_fields';
					$objects_get_func = 'get_user_by';
					$objects_get_func_first_param = 'id';
					$objects_type_field = 'roles';
					break;
				default:
					if ( $single ) {
						return null;
					}
					return array();
			}

			// get the base object
			$obj = call_user_func( $get_func, $id );
			if ( ! $obj ) {
				if ( $single ) {
					return null;
				}
				return array();
			}

			// get the component for the base object
			$component = ComponentManager::get( str_replace( '[TYPE]', $obj->$type_field, $component_path ), $class_path, true );
			if ( ! $component ) {
				if ( $single ) {
					return null;
				}
				return array();
			}

			// get the necessary meta fields depending on the $meta_key and $object_type parameter
			$all_fields = $component->$objects_related_field;

			$fields = array();

			if ( ! empty( $meta_key ) && ! empty( $object_type ) ) {
				if ( isset( $all_fields[ $meta_key ] ) && ( 0 === count( $all_fields[ $meta_key ] ) || in_array( $object_type, $all_fields[ $meta_key ], true ) ) ) {
					$fields[] = $meta_key;
				}
			} elseif ( ! empty( $meta_key ) ) {
				if ( isset( $all_fields[ $meta_key ] ) ) {
					$fields[] = $meta_key;
				}
			} elseif ( ! empty( $object_type ) ) {
				foreach ( $all_fields as $field_name => $types ) {
					if ( 0 === count( $types ) || in_array( $object_type, $types, true ) ) {
						$fields[] = $field_name;
					}
				}
			} else {
				$fields = array_keys( $all_fields );
			}

			if ( 0 === count( $fields ) ) {
				if ( $single ) {
					return null;
				}
				return array();
			}

			// get the IDs of the related objects
			$object_ids = array();
			foreach ( $fields as $field ) {
				$val = call_user_func( $meta_func, $id, $field );
				if ( ! $val ) {
					continue;
				} elseif ( is_array( $val ) ) {
					$object_ids = array_merge( $object_ids, $val );
				} else {
					$object_ids[] = $val;
				}
			}

			$object_ids = array_filter( array_map( 'absint', $object_ids ) );

			if ( 0 === count( $object_ids ) ) {
				if ( $single ) {
					return null;
				}
				return array();
			}

			// get the related objects, also considering the $object_type variable if applicable
			$objects = array();
			foreach ( $object_ids as $object_id ) {
				if ( isset( $objects_get_func_first_param ) ) {
					$object = call_user_func( $objects_get_func, $objects_get_func_first_param, $object_id );
				} else {
					$object = call_user_func( $objects_get_func, $object_id );
				}
				if ( ! $object ) {
					continue;
				}
				if ( ! empty( $object_type ) ) {
					$type = $object->$objects_type_field;
					if ( is_array( $type ) && ! in_array( $object_type, $type, true ) ) {
						continue;
					} elseif ( ! is_array( $type ) && $object_type !== $type ) {
						continue;
					}
				}
				$objects[] = $object;
			}

			if ( 0 === count( $objects ) ) {
				if ( $single ) {
					return null;
				}
				return array();
			}

			if ( $single ) {
				return $objects[0];
			}
			return $objects;
		}

		/**
		 * This function registers a related objects field if applicable for the field parameters.
		 *
		 * This is a utility function that should only be called from a field component
		 * that can provide the required parameters.
		 *
		 * A field that is considered a related objects field must have a type of either
		 * 'radio', 'multibox', 'select' or 'multiselect' and its 'options' argument must contain
		 * only one element that has the key 'posts', 'terms' or 'users'.
		 *
		 * @since 0.6.0
		 * @param WPDLib\FieldTypes\Base $object the field type object
		 * @param array $args arguments for the field component
		 * @param WPDLib\Components\Base $component the field component
		 * @param WPDLib\Components\Base $component_parent the field component's parent component
		 */
		public static function maybe_register_related_objects_field( $object, $args, $component, $component_parent ) {
			if ( ! isset( $args['options'] ) || ! is_array( $args['options'] ) || 1 !== count( $args['options'] ) ) {
				return;
			}

			if ( ! is_a( $object, 'WPDLib\FieldTypes\Radio' ) ) {
				return;
			}

			$available_modes = array( 'posts', 'terms', 'users' );

			$property = '';
			$value = array();

			foreach ( $available_modes as $mode ) {
				if ( isset( $args['options'][ $mode ] ) ) {
					$property = 'related_' . $mode . '_fields';
					if ( ! $args['options'][ $mode ] || 'any' === $args['options'][ $mode ] ) {
						$value[ $component->slug ] = array();
					} else {
						$value[ $component->slug ] = (array) $args['options'][ $mode ];
					}
					break;
				}
			}

			if ( ! $property ) {
				return;
			}

			$component_grandparent = $component_parent->get_parent();
			if ( ! $component_grandparent ) {
				return;
			}

			$component_grandparent->$property = array_merge( $component_grandparent->$property, $value );
		}

		/**
		 * This function returns all meta values of a certain meta key.
		 *
		 * This function is used to populate filter dropdowns for meta fields.
		 *
		 * http://wordpress.stackexchange.com/questions/9394/getting-all-values-for-a-custom-field-key-cross-post
		 *
		 * @since 0.5.0
		 * @param string $meta_key the meta key to get all values for
		 * @param string $post_type the post type slug
		 * @return array an array of all (unique) meta values
		 */
		public static function get_all_meta_values( $meta_key, $post_type ) {
			global $wpdb;

			$query = "SELECT DISTINCT meta_value FROM " . $wpdb->postmeta . " AS m JOIN " . $wpdb->posts . " as p ON ( p.ID = m.post_id )";
			$query .= " WHERE m.meta_key = %s AND m.meta_value != '' AND p.post_type = %s ORDER BY m.meta_value ASC;";

			return $wpdb->get_col( $wpdb->prepare( $query, $meta_key, $post_type ) );
		}

		/**
		 * This function adds help tabs and a help sidebar to a screen.
		 *
		 * @since 0.5.0
		 * @param WP_Screen $screen the screen to add the help data to
		 * @param array $data help tabs and sidebar (if specified)
		 */
		public static function render_help( $screen, $data ) {
			foreach ( $data['tabs'] as $slug => $tab ) {
				$args = array_merge( array( 'id' => $slug ), $tab );

				$screen->add_help_tab( $args );
			}

			if ( ! empty( $data['sidebar'] ) ) {
				$screen->set_help_sidebar( $data['sidebar'] );
			}
		}

		/**
		 * Returns the default arguments to format a field value of a specific type.
		 *
		 * @since 0.6.0
		 * @param string $type the type of field to format a value of
		 * @return array|true either an array of arguments or just true if no special arguments needed
		 */
		public static function get_default_formatted( $type ) {
			$formatted = true;

			switch ( $type ) {
				case 'checkbox':
					$formatted = array( 'mode' => 'tick' );
					break;
				case 'color':
					$formatted = array( 'mode' => 'color' );
					break;
				case 'media':
					$formatted = array( 'mode' => 'link' );
					break;
				case 'multibox':
				case 'multiselect':
					$formatted = array( 'mode' => 'html', 'list' => true );
					break;
				case 'radio':
				case 'select':
					$formatted = array( 'mode' => 'html' );
					break;
				default:
			}

			return $formatted;
		}

		/**
		 * Validates singular and plural titles.
		 *
		 * If not provided, they are generated from the slug.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @param string $slug the component slug
		 * @param string $mode either 'post_type' or 'taxonomy'
		 * @return array the validated arguments
		 */
		public static function validate_titles( $args, $slug, $mode ) {
			if ( empty( $args['title'] ) && isset( $args['label'] ) ) {
				$args['title'] = $args['label'];
				unset( $args['label'] );
			}
			if ( empty( $args['title'] ) ) {
				if ( empty( $args['singular_title'] ) ) {
					$args['singular_title'] = ucwords( str_replace( '_', '', $slug ) );
				}
				$args['title'] = $args['singular_title'] . 's';
			} elseif ( empty( $args['singular_title'] ) ) {
				$args['singular_title'] = $args['title'];
			}
			if ( empty( $args['title_gender'] ) ) {
				$args['title_gender'] = 'n';
			}

			return $args;
		}

		/**
		 * Validates any kind of labels.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @param string $key the name of the argument to validate
		 * @param string $mode either 'post_type' or 'taxonomy'
		 * @return array the validated arguments
		 */
		public static function validate_labels( $args, $key, $mode ) {
			if ( false === $args[ $key ] ) {
				$args[ $key ] = array();
				return $args;
			}

			$defaults = array();
			if ( 'taxonomy' === $mode ) {
				$defaults = TaxonomyLabelGenerator::generate_labels( $args['title'], $args['singular_title'], $key, $args['title_gender'] );
			} else {
				$defaults = PostTypeLabelGenerator::generate_labels( $args['title'], $args['singular_title'], $key, $args['title_gender'] );
			}

			if ( ! is_array( $args[ $key ] ) ) {
				$args[ $key ] = array();
			}

			if ( 'bulk_messages' === $key ) {
				foreach ( $defaults as $type => $default_labels ) {
					if ( ! isset( $args[ $key ][ $type ] ) ) {
						$args[ $key ][ $type ] = $default_labels;
					} else {
						if ( ! is_array( $args[ $key ][ $type ] ) ) {
							$args[ $key ][ $type ] = array( $args[ $key ][ $type ] );
						}
						if ( count( $args[ $key ][ $type ] ) < 2 ) {
							$args[ $key ][ $type ][] = $default_labels[1];
						}
					}
				}
			} else {
				foreach ( $defaults as $type => $default_label ) {
					if ( ! isset( $args[ $key ][ $type ] ) ) {
						$args[ $key ][ $type ] = $default_label;
					}
				}
			}

			return $args;
		}

		/**
		 * Validates some common UI arguments.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @return array the validated arguments
		 */
		public static function validate_ui_args( $args ) {
			if ( null === $args['show_ui'] ) {
				$args['show_ui'] = $args['public'];
			}
			if ( null === $args['show_in_menu'] ) {
				$args['show_in_menu'] = $args['show_ui'];
			} elseif ( $args['show_in_menu'] && ! $args['show_ui'] ) {
				$args['show_in_menu'] = false;
			}

			return $args;
		}

		/**
		 * Validates the position argument.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @see WPPTD\Components\Metabox
		 * @see WPPTD\Components\Field
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @return array the validated arguments
		 */
		public static function validate_position_args( $args ) {
			if ( null !== $args['position'] ) {
				$args['position'] = floatval( $args['position'] );
			}

			return $args;
		}

		/**
		 * Validates any help arguments.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @param string $key the name of the argument to validate
		 * @return array the validated arguments
		 */
		public static function validate_help_args( $args, $key ) {
			if( ! is_array( $args[ $key ] ) ) {
				$args[ $key ] = array();
			}
			if ( ! isset( $args[ $key ]['tabs'] ) || ! is_array( $args[ $key ]['tabs'] ) ) {
				$args[ $key ]['tabs'] = array();
			}
			if ( ! isset( $args[ $key ]['sidebar'] ) ) {
				$args[ $key ]['sidebar'] = '';
			}
			foreach ( $args[ $key ]['tabs'] as &$tab ) {
				$tab = wp_parse_args( $tab, array(
					'title'			=> __( 'Help tab title', 'post-types-definitely' ),
					'content'		=> '',
					'callback'		=> false,
				) );
			}

			return $args;
		}

	}
}
