<?php
/**
 * WPPTD\Components\Field class
 *
 * @package WPPTD
 * @subpackage Components
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPPTD\Utility as Utility;
use WPDLib\Components\Manager as ComponentManager;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;
use WPDLib\Util\Error as UtilError;
use WP_Error as WPError;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\Field' ) ) {

	/**
	 * Class for a post meta field component.
	 *
	 * This denotes a meta field, i.e. both the meta value and the visual input in the WordPress admin.
	 * The field slug is used as the meta key.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Field extends Base {

		/**
		 * @since 0.5.0
		 * @var WPDLib\FieldTypes\Base Holds the field type object from WPDLib.
		 */
		protected $_field = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param string $slug the field slug
		 * @param array $args array of field properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_post_field_validated';
		}

		/**
		 * Magic get method.
		 *
		 * This function exists to allow direct access to properties that are stored on the internal WPDLib\FieldTypes\Base object of the field.
		 *
		 * @since 0.5.0
		 * @param string $property name of the property to find
		 * @return mixed value of the property or null if it does not exist
		 */
		public function __get( $property ) {
			$value = parent::__get( $property );
			if ( null === $value ) {
				$value = $this->_field->$property;
			}
			return $value;
		}

		/**
		 * Registers the meta for this field.
		 *
		 * This method should only be called on WordPress >= 4.6 since it uses the `register_meta()` function
		 * with the new behavior introduced there.
		 *
		 * @since 0.6.5
		 * @param WPPTD\Components\Metabox $parent_metabox the parent metabox component of this field
		 * @param WPPTD\Components\PostType $parent_post_type the parent post type component of this field
		 */
		public function register( $parent_metabox = null, $parent_post_type = null ) {
			// Do not register meta at this point, unless it is specifically enabled for the REST API.
			if ( ! $this->args['show_in_rest'] ) {
				return;
			}

			if ( null === $parent_metabox ) {
				$parent_metabox = $this->get_parent();
			}
			if ( null === $parent_post_type ) {
				$parent_post_type = $parent_metabox->get_parent();
			}

			$show_in_rest = $this->args['show_in_rest'];
			if ( $show_in_rest && ! is_array( $show_in_rest ) ) {
				$show_in_rest = array(
					'name' => $this->args['title'],
				);
			}

			$args = array(
				// The following argument is currently not supported by Core.
				'object_subtype' => $parent_post_type->slug,
				'type'           => $this->get_meta_type(),
				'description'    => ( ! empty( $this->args['rest_description'] ) ? $this->args['rest_description'] : $this->args['description'] ),
				'single'         => $this->is_meta_single(),
				'auth_callback'  => $this->args['rest_auth_callback'],
				'show_in_rest'   => $show_in_rest,
				'default'        => $this->args['default'],
			);

			register_meta( 'post', $this->slug, $args );
		}

		/**
		 * Renders the post meta field.
		 *
		 * This function will show the input field(s) in the post editing screen.
		 *
		 * @since 0.5.0
		 * @param WP_Post $post the post currently being shown
		 */
		public function render( $post ) {
			$parent_metabox = $this->get_parent();
			$parent_post_type = $parent_metabox->get_parent();

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $this->args['id'] ) . '">' . $this->args['title'] . '</label></th>';
			echo '<td>';

			if ( has_action( 'wpptd_field_before' ) ) {
				App::deprecated_action( 'wpptd_field_before', '0.6.0', 'wpptd_post_field_before' );

				/**
				 * This action can be used to display additional content on top of this field.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param string the slug of the current field
				 * @param array the arguments array for the current field
				 * @param string the slug of the current metabox
				 * @param string the slug of the current post type
				 */
				do_action( 'wpptd_field_before', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );
			}

			/**
			 * This action can be used to display additional content on top of this post meta field.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_post_field_before', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );

			$meta_value = wpptd_get_post_meta_value( $post->ID, $this->slug );

			$this->_field->display( $meta_value );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<br/><span class="description">' . $this->args['description'] . '</span>';
			}

			if ( has_action( 'wpptd_field_after' ) ) {
				App::deprecated_action( 'wpptd_field_after', '0.6.0', 'wpptd_post_field_after' );

				/**
				 * This action can be used to display additional content at the bottom of this field.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param string the slug of the current field
				 * @param array the arguments array for the current field
				 * @param string the slug of the current metabox
				 * @param string the slug of the current post type
				 */
				do_action( 'wpptd_field_after', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );
			}

			/**
			 * This action can be used to display additional content at the bottom of this post meta field.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_post_field_after', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );

			echo '</td>';
			echo '</tr>';
		}

		/**
		 * Renders the meta value of this field for usage in a posts list table column.
		 *
		 * @since 0.5.0
		 * @param integer $post_id the post ID to display the meta value for
		 */
		public function render_table_column( $post_id ) {
			$formatted = Utility::get_default_formatted( $this->type );

			$output = wpptd_get_post_meta_value( $post_id, $this->slug, null, $formatted );

			if ( has_filter( 'wpptd_' . get_post_type( $post_id ) . '_table_meta_' . $this->slug . '_output' ) ) {
				App::deprecated_filter( 'wpptd_' . get_post_type( $post_id ) . '_table_meta_' . $this->slug . '_output', '0.6.0', 'wpptd_' . get_post_type( $post_id ) . '_post_table_meta_' . $this->slug . '_output' );

				/**
				 * This filter can be used by the developer to modify the way a specific meta value is printed in the posts list table.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param mixed the formatted meta value
				 * @param integer the post ID
				 */
				$output = apply_filters( 'wpptd_' . get_post_type( $post_id ) . '_table_meta_' . $this->slug . '_output', $output, $post_id );
			}

			/**
			 * This filter can be used by the developer to modify the way a specific meta value is printed in the posts list table.
			 *
			 * @since 0.6.0
			 * @param mixed the formatted meta value
			 * @param integer the post ID
			 */
			echo apply_filters( 'wpptd_' . get_post_type( $post_id ) . '_post_table_meta_' . $this->slug . '_output', $output, $post_id );
		}

		/**
		 * Validates the meta value for this field.
		 *
		 * @see WPPTD\Components\PostType::save_meta()
		 * @since 0.5.0
		 * @param mixed $meta_value the new option value to validate
		 * @return mixed either the validated option or a WP_Error object
		 */
		public function validate_meta_value( $meta_value = null, $skip_required = false ) {
			if ( $this->args['required'] && ! $skip_required ) {
				if ( $meta_value === null || $this->_field->is_empty( $meta_value ) ) {
					return new WPError( 'invalid_empty_value', __( 'No value was provided for the required field.', 'post-types-definitely' ) );
				}
			}
			return $this->_field->validate( $meta_value );
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\Metabox $parent the parent component
		 * @return bool|WPDLib\Util\Error an error object if an error occurred during validation, true if it was validated, false if it did not need to be validated
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {
				if ( is_array( $this->args['class'] ) ) {
					$this->args['class'] = implode( ' ', $this->args['class'] );
				}

				if ( isset( $this->args['options'] ) && ! is_array( $this->args['options'] ) ) {
					$this->args['options'] = array();
				}

				$this->args['id'] = $this->slug;
				$this->args['name'] = $this->slug;

				$this->_field = FieldManager::get_instance( $this->args );
				if ( $this->_field === null ) {
					return new UtilError( 'no_valid_field_type', sprintf( __( 'The field type %1$s assigned to the field component %2$s is not a valid field type.', 'post-types-definitely' ), $this->args['type'], $this->slug ), '', ComponentManager::get_scope() );
				}

				Utility::maybe_register_related_objects_field( $this->_field, $this->args, $this, $parent );

				if ( null === $this->args['default'] ) {
					$this->args['default'] = $this->_field->validate();
				}

				$this->args = Utility::validate_position_args( $this->args );
			}

			return $status;
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the post meta field arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'              => __( 'Field title', 'post-types-definitely' ),
				'description'        => '',
				'type'               => 'text',
				'class'              => '',
				'default'            => null,
				'required'           => false,
				'position'           => null,
				'show_in_rest'       => false,
				'rest_description'   => '',
				'rest_auth_callback' => null,
			);

			if ( has_filter( 'wpptd_field_defaults' ) ) {
				App::deprecated_filter( 'wpptd_field_defaults', '0.6.0', 'wpptd_post_field_defaults' );

				/**
				 * This filter can be used by the developer to modify the default values for each field component.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param array the associative array of default values
				 */
				$defaults = apply_filters( 'wpptd_field_defaults', $defaults );
			}

			/**
			 * This filter can be used by the developer to modify the default values for each post meta field component.
			 *
			 * @since 0.6.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_post_field_defaults', $defaults );
		}

		/**
		 * Returns the meta type for this field.
		 *
		 * The meta type is used for the 'type' argument in the WordPress function `register_meta()`.
		 *
		 * @since 0.6.5
		 * @return string the meta type
		 */
		protected function get_meta_type() {
			$meta_type = 'string';

			switch ( $this->args['type'] ) {
				case 'checkbox':
					$meta_type = 'boolean';
					break;
				case 'media':
					if ( isset( $this->args['store'] ) && 'url' === $this->args['store'] ) {
						$meta_type = 'string';
					} else {
						$meta_type = 'integer';
					}
					break;
				case 'range':
				case 'number':
					if ( isset( $this->args['step'] ) && is_float( $this->args['step'] ) ) {
						$meta_type = 'number';
					} else {
						$meta_type = 'integer';
					}
					break;
				case 'repeatable':
					$meta_type = 'array';
					break;
			}

			/**
			 * This filter can be used to adjust the meta type for a field.
			 *
			 * The meta type is used in the WordPress function `register_meta()`. This filter allows
			 * to support custom fields as well.
			 *
			 * Note that this filter applies to both post and term meta.
			 *
			 * @since 0.6.5
			 * @param string $meta_type the original meta type detected
			 * @param WPPTD\Components\Field the field component
			 */
			return apply_filters( 'wpptd_field_meta_type', $meta_type, $this );
		}

		/**
		 * Returns whether the meta for this field is a single value or not.
		 *
		 * This is used for the 'single' argument in the WordPress function `register_meta()`.
		 *
		 * @since 0.6.5
		 * @return boolean whether this meta field has only a single value or not
		 */
		protected function is_meta_single() {
			$type_hint = $this->validate_meta_value( null, true );

			return ! is_array( $type_hint );
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
			return 'WPPTD\Components\PostType';
		}

	}

}
