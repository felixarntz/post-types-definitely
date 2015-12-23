<?php
/**
 * @package WPPTD
 * @version 0.5.1
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD;

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
		 * Validates singular and plural titles.
		 *
		 * If not provided, they are generated from the slug.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @param string $slug the component slug
		 * @return array the validated arguments
		 */
		public static function validate_post_type_and_taxonomy_titles( $args, $slug ) {
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

			return $args;
		}

		/**
		 * Validates any kind of labels.
		 *
		 * @see WPPTD\Components\PostType
		 * @see WPPTD\Components\Taxonomy
		 * @since 0.5.0
		 * @param array $args array of arguments
		 * @param array $defaults the default labels to use
		 * @param string $key the name of the argument to validate
		 * @return array the validated arguments
		 */
		public static function validate_labels( $args, $defaults, $key ) {
			if ( false !== $args[ $key ] ) {
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
			} else {
				$args[ $key ] = array();
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
