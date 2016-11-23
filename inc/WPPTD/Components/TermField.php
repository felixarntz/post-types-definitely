<?php
/**
 * WPPTD\Components\TermField class
 *
 * @package WPPTD
 * @subpackage Components
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.0
 */

namespace WPPTD\Components;

use WPPTD\Utility as Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\TermField' ) ) {

	/**
	 * Class for a term meta field component.
	 *
	 * This denotes a meta field, i.e. both the meta value and the visual input in the WordPress admin.
	 * The field slug is used as the meta key.
	 *
	 * @internal
	 * @since 0.6.0
	 */
	class TermField extends Field {

		/**
		 * Class constructor.
		 *
		 * @since 0.6.0
		 * @param string $slug the field slug
		 * @param array $args array of field properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_term_field_validated';
		}

		/**
		 * Registers the meta for this field.
		 *
		 * This method should only be called on WordPress >= 4.6 since it uses the `register_meta()` function
		 * with the new behavior introduced there.
		 *
		 * @since 0.6.5
		 * @param WPPTD\Components\TermMetabox $parent_metabox the parent metabox component of this field
		 * @param WPPTD\Components\Taxonomy $parent_taxonomy the parent taxonomy component of this field
		 */
		public function register( $parent_metabox = null, $parent_taxonomy = null ) {
			// Do not register meta at this point, unless it is specifically enabled for the REST API.
			if ( ! $this->args['show_in_rest'] ) {
				return;
			}

			if ( null === $parent_metabox ) {
				$parent_metabox = $this->get_parent();
			}
			if ( null === $parent_taxonomy ) {
				$parent_taxonomy = $parent_metabox->get_parent();
			}

			$show_in_rest = $this->args['show_in_rest'];
			if ( $show_in_rest && ! is_array( $show_in_rest ) ) {
				$show_in_rest = array(
					'name' => $this->args['title'],
				);
			}

			$args = array(
				// The following argument is currently not supported by Core.
				'object_subtype' => $parent_taxonomy->slug,
				'type'           => $this->get_meta_type(),
				'description'    => ( ! empty( $this->args['rest_description'] ) ? $this->args['rest_description'] : $this->args['description'] ),
				'single'         => $this->is_meta_single(),
				'auth_callback'  => $this->args['rest_auth_callback'],
				'show_in_rest'   => $show_in_rest,
				'default'        => $this->args['default'],
			);

			register_meta( 'term', $this->slug, $args );
		}

		/**
		 * Renders the term meta field.
		 *
		 * This function will show the input field(s) in the term editing screen.
		 *
		 * @since 0.6.0
		 * @param WP_Term $term the term currently being shown
		 */
		public function render( $term ) {
			$parent_metabox = $this->get_parent();
			$parent_taxonomy = $parent_metabox->get_parent();

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $this->args['id'] ) . '">' . $this->args['title'] . '</label></th>';
			echo '<td>';

			/**
			 * This action can be used to display additional content on top of this term meta field.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current taxonomy
			 */
			do_action( 'wpptd_term_field_before', $this->slug, $this->args, $parent_metabox->slug, $parent_taxonomy->slug );

			$meta_value = wpptd_get_term_meta_value( $term->term_id, $this->slug );

			$this->_field->display( $meta_value );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<br/><span class="description">' . $this->args['description'] . '</span>';
			}

			/**
			 * This action can be used to display additional content at the bottom of this term meta field.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current field
			 * @param array the arguments array for the current field
			 * @param string the slug of the current metabox
			 * @param string the slug of the current taxonomy
			 */
			do_action( 'wpptd_term_field_after', $this->slug, $this->args, $parent_metabox->slug, $parent_taxonomy->slug );

			echo '</td>';
			echo '</tr>';
		}

		/**
		 * Renders the meta value of this field for usage in a terms list table column.
		 *
		 * @since 0.6.0
		 * @param integer $term_id the term ID to display the meta value for
		 */
		public function render_table_column( $term_id ) {
			$formatted = Utility::get_default_formatted( $this->type );

			$output = wpptd_get_term_meta_value( $term_id, $this->slug, null, $formatted );

			/**
			 * This filter can be used by the developer to modify the way a specific meta value is printed in the terms list table.
			 *
			 * @since 0.6.0
			 * @param mixed the formatted meta value
			 * @param integer the term ID
			 */
			echo apply_filters( 'wpptd_' . wpptd_get_taxonomy( $term_id ) . '_term_table_meta_' . $this->slug . '_output', $output, $term_id );
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the term meta field arguments.
		 *
		 * @since 0.6.0
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

			/**
			 * This filter can be used by the developer to modify the default values for each term meta field component.
			 *
			 * @since 0.6.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_term_field_defaults', $defaults );
		}

		/**
		 * Returns whether this component supports global slugs.
		 *
		 * If it does not support global slugs, the function either returns false for the slug to be globally unique
		 * or the class name of a parent component to ensure the slug is unique within that parent's scope.
		 *
		 * @since 0.6.0
		 * @return bool|string
		 */
		protected function supports_globalslug() {
			return 'WPPTD\Components\Taxonomy';
		}
	}

}
