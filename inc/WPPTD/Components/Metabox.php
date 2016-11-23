<?php
/**
 * WPPTD\Components\Metabox class
 *
 * @package WPPTD
 * @subpackage Components
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPPTD\Components;

use WPPTD\App as App;
use WPPTD\Utility as Utility;
use WPDLib\Components\Base as Base;
use WPDLib\FieldTypes\Manager as FieldManager;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\Components\Metabox' ) ) {

	/**
	 * Class for a post metabox component.
	 *
	 * This denotes a metabox for a specific post type in the WordPress admin.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Metabox extends Base {

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 * @param string $slug the metabox slug
		 * @param array $args array of metabox properties
		 */
		public function __construct( $slug, $args ) {
			parent::__construct( $slug, $args );
			$this->validate_filter = 'wpptd_post_metabox_validated';
		}

		/**
		 * Adds the post metabox the post type edit screen it belongs to.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $parent_post_type the parent post type component of this metabox
		 */
		public function register( $parent_post_type = null ) {
			if ( null === $parent_post_type ) {
				$parent_post_type = $this->get_parent();
			}

			$context = $this->args['context'];
			if ( null === $context ) {
				$context = 'advanced';
			}

			$priority = $this->args['priority'];
			if ( null === $priority ) {
				$priority = 'default';
			}

			add_meta_box( $this->slug, $this->args['title'], array( $this, 'render' ), $parent_post_type->slug, $context, $priority );
		}

		/**
		 * Renders the post metabox.
		 *
		 * It displays the title and description (if available) for the metabox.
		 * Then it shows the fields of this metabox or, if no fields are available, calls the callback function.
		 *
		 * @since 0.5.0
		 * @param WP_Post $post the post currently being shown
		 */
		public function render( $post ) {
			$parent_post_type = $this->get_parent();

			if ( 'side' == $this->args['context'] ) {
				echo '<div class="wpdlib-narrow">';
			}

			if ( has_action( 'wpptd_metabox_before' ) ) {
				App::deprecated_action( 'wpptd_metabox_before', '0.6.0', 'wpptd_post_metabox_before' );

				/**
				 * This action can be used to display additional content on top of this metabox.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param string the slug of the current metabox
				 * @param array the arguments array for the current metabox
				 * @param string the slug of the current post type
				 */
				do_action( 'wpptd_metabox_before', $this->slug, $this->args, $parent_post_type->slug );
			}

			/**
			 * This action can be used to display additional content on top of this post metabox.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_post_metabox_before', $this->slug, $this->args, $parent_post_type->slug );

			if ( ! empty( $this->args['description'] ) ) {
				echo '<p class="description">' . $this->args['description'] . '</p>';
			}

			if ( count( $this->get_children() ) > 0 ) {
				$table_atts = array(
					'class'		=> 'form-table wpdlib-form-table',
				);

				if ( has_filter( 'wpptd_table_atts' ) ) {
					App::deprecated_filter( 'wpptd_table_atts', '0.6.0', 'wpptd_post_table_atts' );

					/**
					 * This filter can be used to adjust the form table attributes.
					 *
					 * @since 0.5.0
					 * @deprecated 0.6.0
					 * @param array the associative array of form table attributes
					 * @param WPPTD\Components\Metabox current metabox instance
					 */
					$table_atts = apply_filters( 'wpptd_table_atts', $table_atts, $this );
				}

				/**
				 * This filter can be used to adjust the post editing form table attributes.
				 *
				 * @since 0.6.0
				 * @param array the associative array of form table attributes
				 * @param WPPTD\Components\Metabox current metabox instance
				 */
				$table_atts = apply_filters( 'wpptd_post_table_atts', $table_atts, $this );

				echo '<table' . FieldManager::make_html_attributes( $table_atts, false, false ) . '>';

				foreach ( $this->get_children() as $field ) {
					$field->render( $post );
				}

				echo '</table>';
			} elseif ( $this->args['callback'] && is_callable( $this->args['callback'] ) ) {
				call_user_func( $this->args['callback'], $post );
			} else {
				App::doing_it_wrong( __METHOD__, sprintf( __( 'There are no fields to display for metabox %s. Either add some or provide a valid callback function instead.', 'post-types-definitely' ), $this->slug ), '0.5.0' );
			}

			if ( has_action( 'wpptd_metabox_after' ) ) {
				App::deprecated_action( 'wpptd_metabox_after', '0.6.0', 'wpptd_post_metabox_after' );

				/**
				 * This action can be used to display additional content at the bottom of this metabox.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param string the slug of the current metabox
				 * @param array the arguments array for the current metabox
				 * @param string the slug of the current post type
				 */
				do_action( 'wpptd_metabox_after', $this->slug, $this->args, $parent_post_type->slug );
			}

			/**
			 * This action can be used to display additional content at the bottom of this post metabox.
			 *
			 * @since 0.6.0
			 * @param string the slug of the current metabox
			 * @param array the arguments array for the current metabox
			 * @param string the slug of the current post type
			 */
			do_action( 'wpptd_post_metabox_after', $this->slug, $this->args, $parent_post_type->slug );

			if ( 'side' == $this->args['context'] ) {
				echo '</div>';
			}
		}

		/**
		 * Validates the arguments array.
		 *
		 * @since 0.5.0
		 * @param WPPTD\Components\PostType $parent the parent component
		 * @return bool|WPDLib\Util\Error an error object if an error occurred during validation, true if it was validated, false if it did not need to be validated
		 */
		public function validate( $parent = null ) {
			$status = parent::validate( $parent );

			if ( $status === true ) {
				$this->args = Utility::validate_position_args( $this->args );
			}

			return $status;
		}

		/**
		 * Returns the keys of the arguments array and their default values.
		 *
		 * Read the plugin guide for more information about the post metabox arguments.
		 *
		 * @since 0.5.0
		 * @return array
		 */
		protected function get_defaults() {
			$defaults = array(
				'title'       => __( 'Metabox title', 'post-types-definitely' ),
				'description' => '',
				'context'     => null,
				'priority'    => null,
				'callback'    => false, //only used if no fields are attached to this metabox
				'position'    => null,
			);

			if ( has_filter( 'wpptd_metabox_defaults' ) ) {
				App::deprecated_filter( 'wpptd_metabox_defaults', '0.6.0', 'wpptd_post_metabox_defaults' );

				/**
				 * This filter can be used by the developer to modify the default values for each metabox component.
				 *
				 * @since 0.5.0
				 * @deprecated 0.6.0
				 * @param array the associative array of default values
				 */
				$defaults = apply_filters( 'wpptd_metabox_defaults', $defaults );
			}

			/**
			 * This filter can be used by the developer to modify the default values for each post metabox component.
			 *
			 * @since 0.6.0
			 * @param array the associative array of default values
			 */
			return apply_filters( 'wpptd_post_metabox_defaults', $defaults );
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
