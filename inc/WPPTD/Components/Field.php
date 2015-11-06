<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

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
	 * Class for a field component.
	 *
	 * This denotes a meta field, i.e. both the meta value and the visual input in the WordPress admin.
	 * The field slug is used as the meta key.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Field extends Base {

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param string $slug the field slug
		 * @param array $args array of field properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_field_validated';
		}

		/**
		 * @since 0.5.0
		 * @var WPDLib\FieldTypes\Base Holds the field type object from WPDLib.
		 */
		protected $_field = null;

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
		 * Renders the field.
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

			/**
			 * This action can be used to display additional content on top of this field.
			 *
			 * @since 0.5.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_field_before', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );

			$meta_value = wpptd_get_post_meta_value( $post->ID, $this->slug );

			$this->_field->display( $meta_value );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<br/><span class="description">' . $this->args['description'] . '</span>';
			}

			/**
			 * This action can be used to display additional content at the bottom of this field.
			 *
			 * @since 0.5.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_field_after', $this->slug, $this->args, $parent_metabox->slug, $parent_post_type->slug );

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
			$formatted = true;
			switch ( $this->type ) {
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
					$formatted = true;
			}

			/**
			 * This filter can be used by the developer to modify the way a specific meta value is printed in the posts list table.
			 *
			 * @since 0.5.0
			 * @param mixed the formatted meta value
			 * @param integer the post ID
			 */
			echo apply_filters( 'wpptd_' . get_post_type( $post_id ) . '_table_meta_' . $this->slug . '_output', wpptd_get_post_meta_value( $post_id, $this->slug, null, $formatted ), $post_id );
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
		 * Read the plugin guide for more information about the field arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'				=> __( 'Field title', 'post-types-definitely' ),
				'description'		=> '',
				'type'				=> 'text',
				'class'				=> '',
				'default'			=> null,
				'required'			=> false,
				'position'			=> null,
			);

			/**
			 * This filter can be used by the developer to modify the default values for each field component.
			 *
			 * @since 0.5.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_field_defaults', $defaults );
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
