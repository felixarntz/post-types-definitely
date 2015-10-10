<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\TermField' ) ) {

	class TermField extends Field {
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_term_field_validated';
		}

		public function render( $term ) {
			$parent_metabox = $this->get_parent();
			$parent_taxonomy = $parent_metabox->get_parent();

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $this->args['id'] ) . '">' . $this->args['title'] . '</label></th>';
			echo '<td>';

			do_action( 'wpptd_term_field_before', $this->slug, $this->args, $parent_metabox->slug, $parent_taxonomy->slug );

			$meta_value = wpptd_get_term_meta( $term->term_id, $this->slug );

			$this->_field->display( $meta_value );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<br/><span class="description">' . $this->args['description'] . '</span>';
			}

			do_action( 'wpptd_term_field_after', $this->slug, $this->args, $parent_metabox->slug, $parent_taxonomy->slug );

			echo '</td>';
			echo '</tr>';
		}

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

			return apply_filters( 'wpptd_term_field_defaults', $defaults );
		}

		protected function supports_globalslug() {
			return 'WPPTD\Components\Taxonomy';
		}
	}

}
