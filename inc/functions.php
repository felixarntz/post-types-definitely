<?php
/**
 * @package WPPTD
 * @version 0.5.1
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

if ( ! function_exists( 'wpptd_get_term_meta_values' ) ) {
	/**
	 * Returns the meta values for a term.
	 *
	 * This function is basically a wrapper for the WordPress core function `get_term_meta()`
	 * when calling it only using the first parameter, however this function will automatically
	 * populate each meta value with its default value if the meta value is not available.
	 *
	 * Furthermore the $single parameter can be used to force an array or no array to be returned
	 * for each meta field. You should generally leave it set to null.
	 *
	 * @since 0.6.0
	 * @param integer $id the term ID to get the meta values for
	 * @param null|boolean $single whether to force arrays or no arrays being returned (default is to not force anything)
	 * @param boolean $formatted whether to return automatically formatted values, ready for output (default is false)
	 * @return array the meta values as an associative array
	 */
	function wpptd_get_term_meta_values( $id, $single = null, $formatted = false ) {
		if ( ! wpptd_supports_termmeta() ) {
			return array();
		}

		$_meta_values = get_term_meta( $id );

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

		$taxonomy = \WPDLib\Components\Manager::get( '*.*.' . wpptd_get_taxonomy( $id ), 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy', true );
		if ( $taxonomy ) {
			foreach ( $taxonomy->get_children( 'WPPTD\Components\TermMetabox' ) as $metabox ) {
				foreach ( $metabox->get_children() as $field ) {
					$_meta_value = isset( $_meta_values[ $field->slug ] ) ? $_meta_values[ $field->slug ] : array();
					$meta_values[ $field->slug ] = \WPPTD\Utility::parse_meta_value( $_meta_value, $field, $single, $formatted );
				}
			}
		}

		return $meta_values;
	}
}

if ( ! function_exists( 'wpptd_get_term_meta_value' ) ) {
	/**
	 * Returns a single specified meta value for a term.
	 *
	 * This function is basically a wrapper for the WordPress core function `get_term_meta()`
	 * when calling it with specification of a meta key. If the required field meta value is not available,
	 * the function will automatically return its default value.
	 *
	 * Furthermore the $single parameter can be used to force an array or no array to be returned for the
	 * meta field. You should generally leave it set to null.
	 *
	 * @since 0.6.0
	 * @param integer $id the term ID to get the meta value for
	 * @param string $meta_key the meta key (field slug) to get the meta value for
	 * @param null|boolean $single whether to force an array or no array being returned (default is not to force anything)
	 * @param boolean $formatted whether to return an automatically formatted value, ready for output (default is false)
	 * @return mixed the meta value
	 */
	function wpptd_get_term_meta_value( $id, $meta_key, $single = null, $formatted = false ) {
		if ( ! wpptd_supports_termmeta() ) {
			if ( $single ) {
				return null;
			}
			return array();
		}

		$_meta_value = get_term_meta( $id, $meta_key, false );

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

		$field = \WPDLib\Components\Manager::get( '*.*.' . wpptd_get_taxonomy( $id ) . '.*.' . $meta_key, 'WPDLib\Components\Menu.WPPTD\Components\PostType.WPPTD\Components\Taxonomy.WPPTD\Components\TermMetabox', true );
		if ( $field ) {
			$meta_value = \WPPTD\Utility::parse_meta_value( $_meta_value, $field, $single, $formatted );
		}

		return $meta_value;
	}
}

if ( ! function_exists( 'wpptd_supports_termmeta' ) ) {
	/**
	 * Checks whether the current setup supports term meta.
	 *
	 * @since 0.6.0
	 * @return bool true if term meta is supported, otherwise false
	 */
	function wpptd_supports_termmeta() {
		return 0 <= version_compare( get_bloginfo( 'version' ), '4.4' ) && function_exists( 'get_term_meta' );
	}
}

if ( ! function_exists( 'wpptd_get_taxonomy' ) ) {
	/**
	 * Retrieves the taxonomy of a given term.
	 *
	 * This function is the term equivalent of the WP Core function `get_post_type()`.
	 *
	 * @since 0.6.0
	 * @param integer|WP_Term $term a term ID or term object
	 * @return string|false taxonomy on success, false otherwise
	 */
	function wpptd_get_taxonomy( $term ) {
		if ( $term = get_term( $term ) ) {
			return $term->taxonomy;
		}

		return false;
	}
}
