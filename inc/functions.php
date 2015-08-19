<?php
/**
 * @package WPPTD
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! function_exists( 'wpptd_get_post_meta' ) ) {
	function wpptd_get_post_metas( $id, $single = null, $formatted = false ) {
		$_meta_values = get_post_custom( $id );

		$meta_values = array();

		if ( doing_action( 'wpptd' ) || ! did_action( 'wpptd' ) ) {
			if ( $single ) {
				foreach ( $_meta_values as $key => $_mv ) {
					if ( count( $_mv ) > 0 ) {
						$meta_values[ $key ] = $_mv[0];
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
					$type_hint = $field->validate_meta_value( null, true );
					$meta_value = null;
					if ( is_array( $type_hint ) ) {
						if ( isset( $_meta_values[ $field->slug ] ) ) {
							$meta_value = $field->_field->parse( $_meta_values[ $field->slug ], $formatted );
						} else {
							$meta_value = array();
						}
						if ( $single !== null && $single ) {
							if ( count( $meta_value > 0 ) ) {
								$meta_value = $meta_value[0];
							} else {
								$meta_value = null;
							}
						}
					} else {
						if ( isset( $_meta_values[ $field->slug ] ) ) {
							$meta_value = $field->_field->parse( $_meta_values[ $field->slug ][0], $formatted );
						} else {
							$meta_value = $field->_field->parse( $field->default, $formatted );
						}
						if ( $single !== null && ! $single ) {
							$meta_value = array( $meta_value );
						}
					}
					$meta_values[ $field->slug ] = $meta_value;
				}
			}
		}

		return $meta_values;
	}

	function wpptd_get_post_meta( $id, $meta_key, $single = null, $formatted = false ) {
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
			$type_hint = $field->validate_meta_value( null, true );
			if ( is_array( $type_hint ) ) {
				if ( count( $_meta_value ) > 0 ) {
					$meta_value = $field->_field->parse( $_meta_value, $formatted );
				} else {
					$meta_value = array();
				}
				if ( $single !== null && $single ) {
					if ( count( $meta_value > 0 ) ) {
						$meta_value = $meta_value[0];
					} else {
						$meta_value = null;
					}
				}
			} else {
				if ( count( $_meta_value ) > 0 ) {
					$meta_value = $field->_field->parse( $_meta_value[0], $formatted );
				} else {
					$meta_value = $field->default;
				}
				if ( $single !== null && ! $single ) {
					$meta_value = array( $meta_value );
				}
			}
		}

		return $meta_value;
	}
}
