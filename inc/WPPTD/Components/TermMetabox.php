<?php
/**
 * WPPTD\Components\TermMetabox class
 *
 * @package WPPTD
 * @subpackage Components
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.0
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPDLib\FieldTypes\Manager as FieldManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\TermMetabox' ) ) {

	/**
	 * Class for a term metabox component.
	 *
	 * This denotes a metabox for a specific taxonomy in the WordPress admin.
	 *
	 * @internal
	 * @since 0.6.0
	 */
	class TermMetabox extends Metabox {

		/**
		 * Class constructor.
		 *
		 * @since 0.6.0
		 * @param string $slug the metabox slug
		 * @param array $args array of metabox properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_term_metabox_validated';
		}

		/**
		 * Adds the term metabox the taxonomy edit screen it belongs to.
		 *
		 * @since 0.6.0
		 * @param WPPTD\Components\Taxonomy $parent_taxonomy the parent taxonomy component of this metabox
		 */
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

		/**
		 * Renders the term metabox.
		 *
		 * It displays the title and description (if available) for the metabox.
		 * Then it shows the fields of this metabox or, if no fields are available, calls the callback function.
		 *
		 * @since 0.6.0
		 * @param WP_Term $term the term currently being shown
		 */
		public function render( $term ) {
			$parent_taxonomy = $this->get_parent();

			if ( 'side' == $this->args['context'] ) {
				echo '<div class="wpdlib-narrow">';
			}

			/**
			 * This action can be used to display additional content on top of this term metabox.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current taxonomy
			 */
			do_action( 'wpptd_term_metabox_before', $this->slug, $this->args, $parent_taxonomy->slug );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<p class="description">' . $this->args['description'] . '</p>';
			}

			if ( count( $this->get_children() ) > 0 ) {
				$table_atts = array(
					'class'		=> 'form-table wpdlib-form-table',
				);

				/**
				 * This filter can be used to adjust the term editing form table attributes.
				 *
				 * @since 0.6.0
				 * @param array the associative array of form table attributes
				 * @param WPPTD\Components\TermMetabox current metabox instance
				 */
				$table_atts = apply_filters( 'wpptd_term_table_atts', $table_atts, $this );

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

			/**
			 * This action can be used to display additional content at the bottom of this term metabox.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current taxonomy
			 */
			do_action( 'wpptd_term_metabox_after', $this->slug, $this->args, $parent_taxonomy->slug );

			if ( 'side' == $this->args['context'] ) {
				echo '</div>';
			}
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the term metabox arguments.
		 *
		 * @since 0.6.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'       => __( 'Metabox title', 'post-types-definitely' ),
				'description' => '',
				'context'     => null,
				'priority'    => null,
				'position'    => null,
				'callback'    => false, //only used if no fields are attached to this metabox
			);

			/**
			 * This filter can be used by the developer to modify the default values for each term metabox component.
			 *
			 * @since 0.6.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_term_metabox_defaults', $defaults );
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
