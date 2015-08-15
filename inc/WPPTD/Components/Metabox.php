<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\Metabox' ) ) {

	class Metabox extends Base {

		/**
		 * Adds the metabox the post type edit screen it belongs to.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $parent_post_type the parent post type component of this metabox
		 */
		public function register( $parent_post_type = null ) {
			if ( null === $parent_post_type ) {
				$parent_post_type = $this->get_parent();
			}

			add_meta_box( $this->slug, $this->args['title'], array( $this, 'render' ), $parent_post_type->slug, $this->args['context'], $this->args['priority'] );
		}

		/**
		 * Renders the metabox.
		 *
		 * It displays the title and description (if available) for the metabox.
		 * Then it shows the fields of this metabox or, if no fields are available, calls the callback function.
		 *
		 * @since 0.5.0
		 * @param WP_Post $post the post currently being shown
		 */
		public function render( $post ) {
			$parent_post_type = $this->get_parent();

			/**
			 * This action can be used to display additional content on top of this metabox.
			 *
			 * @since 0.5.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_metabox_before', $this->slug, $this->args, $parent_post_type->slug );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<p class="description">' . $this->args['description'] . '</p>';
			}

			if ( count( $this->get_children() ) > 0 ) {
				$table_atts = array(
					'class'		=> 'form-table',
				);
				$table_atts = apply_filters( 'wpptd_table_atts', $table_atts, $this );

				echo '<table' . FieldManager::make_html_attributes( $table_atts, false, false ) . '>';

				foreach ( $this->get_children() as $field ) {
					$field->render( $post );
				}

				echo '</table>';
			} elseif ( $this->args['callback'] && is_callable( $this->args['callback'] ) ) {
				call_user_func( $this->args['callback'], $post );
			} else {
				App::doing_it_wrong( __METHOD__, sprintf( __( 'There are no fields to display for metabox %s. Either add some or provide a valid callback function instead.', 'wpptd' ), $this->slug ), '0.5.0' );
			}

			/**
			 * This action can be used to display additional content at the bottom of this metabox.
			 *
			 * @since 0.5.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current tab
			 */
			do_action( 'wpptd_metabox_after', $this->slug, $this->args, $parent_post_type->slug );
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the metabox arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'			=> __( 'Metabox title', 'wpptd' ),
				'description'	=> '',
				'context'		=> 'advanced',
				'priority'		=> 'default',
				'callback'		=> false, //only used if no fields are attached to this metabox
			);

			/**
			 * This filter can be used by the developer to modify the default values for each metabox component.
			 *
			 * @since 0.5.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_metabox_defaults', $defaults );
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
