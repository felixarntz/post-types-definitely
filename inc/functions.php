<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! function_exists( 'wpptd_get_post_meta_values' ) ) {
	/**
	 * Returns the meta values for a post.
	 *
	 * This function is basically a wrapper for the WordPress core function `get_post_meta()`
	 * when calling it only using the first parameter, however this function will automatically
	 * populate each meta value with its default value if the meta value is not available.
	 *
	 * Furthermore the $single parameter can be used to force an array or no array to be returned
	 * for each meta field. You should generally leave it set to null.
	 *
	 * @since 0.5.0
	 * @param integer $id the post ID to get the meta values for
	 * @param null|boolean $single whether to force arrays or no arrays being returned (default is to not force anything)
	 * @param boolean $formatted whether to return automatically formatted values, ready for output (default is false)
	 * @return array the meta values as an associative array
	 */
	function wpptd_get_post_meta_values( $id, $single = null, $formatted = false ) {
		$_meta_values = get_post_meta( $id );

		$meta_values = array();

		if ( doing_action( 'wpptd' ) || ! did_action( 'wpptd' ) ) {
			if ( $single ) {
				foreach ( $_meta_values as $key => $_mv ) {
					if ( count( $_mv ) > 0 ) {
						$meta_values[ $key ] = $_mv[0];
					} else {
						$meta_values[ $key ] = null;
					}
				}
				return $meta_values;
			} else {
				return $_meta_values;
			}
		}

		$post_type = \WPDLib\Components\Manager::get( '*.' . get_post_type( $id ), 'WPDLib\Components\Menu.WPPTD\Components\PostType', true );
		if ( $post_type ) {
			foreach ( $post_type->get_children( 'WPPTD\Components\Metabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$_meta_value = isset( $_meta_values[ $field->slug ] ) ? $_meta_values[ $field->slug ] : array();
					$meta_values[ $field->slug ] = \WPPTD\Utility::parse_meta_value( $_meta_value, $field, $single, $formatted );
				}
			}
		}

		return $meta_values;
	}
}

if ( ! function_exists( 'wpptd_get_post_meta_value' ) ) {
	/**
	 * Returns a single specified meta value for a post.
	 *
	 * This function is basically a wrapper for the WordPress core function `get_post_meta()`
	 * when calling it with specification of a meta key. If the required field meta value is not available,
	 * the function will automatically return its default value.
	 *
	 * Furthermore the $single parameter can be used to force an array or no array to be returned for the
	 * meta field. You should generally leave it set to null.
	 *
	 * @since 0.5.0
	 * @param integer $id the post ID to get the meta value for
	 * @param string $meta_key the meta key (field slug) to get the meta value for
	 * @param null|boolean $single whether to force an array or no array being returned (default is not to force anything)
	 * @param boolean $formatted whether to return an automatically formatted value, ready for output (default is false)
	 * @return mixed the meta value
	 */
	function wpptd_get_post_meta_value( $id, $meta_key, $single = null, $formatted = false ) {
		$_meta_value = get_post_meta( $id, $meta_key, false );

		if ( doing_action( 'wpptd' ) || ! did_action( 'wpptd' ) ) {
			if ( $single ) {
				if ( count( $_meta_value ) > 0 ) {
					return $_meta_value[0];
				}
				return null;
			} else {
				return $_meta_value;
			}
		}

		$meta_value = null;

		$field = \WPDLib\Components\Manager::get( '*.' . get_post_type( $id ) . '.*.' . $meta_key, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Metabox', true );
		if ( $field ) {
			$meta_value = \WPPTD\Utility::parse_meta_value( $_meta_value, $field, $single, $formatted );
		}

		return $meta_value;
	}
}
