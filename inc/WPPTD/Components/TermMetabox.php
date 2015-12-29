<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPDLib\FieldTypes\Manager as FieldManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\TermMetabox' ) ) {

	class TermMetabox extends Metabox {
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_term_metabox_validated';
		}

		public function register( $parent_taxonomy = null ) {
			if ( null === $parent_taxonomy ) {
				$parent_taxonomy = $this->get_parent();
			}

			$context = $this->args['context'];
			if ( null === $context ) {
				$context = 'advanced';
			}

			$priority = $this->args['priority'];
			if ( null === $priority ) {
				$priority = 'default';
			}

			add_meta_box( $this->slug, $this->args['title'], array( $this, 'render' ), 'edit-' . $parent_taxonomy->slug, $context, $priority );
		}

		public function render( $term ) {
			$parent_taxonomy = $this->get_parent();

			if ( 'side' == $this->args['context'] ) {
				echo '<div class="wpdlib-narrow">';
			}

			do_action( 'wpptd_term_metabox_before', $this->slug, $this->args, $parent_taxonomy->slug );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<p class="description">' . $this->args['description'] . '</p>';
			}

			if ( count( $this->get_children() ) > 0 ) {
				$table_atts = array(
					'class'		=> 'form-table wpdlib-form-table',
				);
				$table_atts = apply_filters( 'wpptd_table_atts', $table_atts, $this );

				echo '<table' . FieldManager::make_html_attributes( $table_atts, false, false ) . '>';

				foreach ( $this->get_children() as $field ) {
					$field->render( $term );
				}

				echo '</table>';
			} elseif ( $this->args['callback'] && is_callable( $this->args['callback'] ) ) {
				call_user_func( $this->args['callback'], $term );
			} else {
				App::doing_it_wrong( __METHOD__, sprintf( __( 'There are no fields to display for metabox %s. Either add some or provide a valid callback function instead.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
			}

			do_action( 'wpptd_term_metabox_after', $this->slug, $this->args, $parent_taxonomy->slug );

			if ( 'side' == $this->args['context'] ) {
				echo '</div>';
			}
		}

		protected function get_defaults() {
			$defaults = array(
				'title'			=> __( 'Metabox title', 'post-types-definitely' ),
				'description'	=> '',
				'context'		=> null,
				'priority'		=> null,
				'position'		=> null,
				'callback'		=> false, //only used if no fields are attached to this metabox
			);

			return apply_filters( 'wpptd_term_metabox_defaults', $defaults );
		}

		protected function supports_globalslug() {
			return 'WPPTD\Components\Taxonomy';
		}
	}

}
