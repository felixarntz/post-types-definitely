<?php
/**
 * WPPTD\PostTypeLabelGenerator class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\PostTypeLabelGenerator' ) ) {
	/**
	 * This class contains static methods to generate default post type labels.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class PostTypeLabelGenerator {

		/**
		 * This method generates default labels of any kind and provides public access to the class.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @param string $type either 'labels', 'messages' or 'bulk_messages'
		 * @param string $gender either 'masculine', 'feminine' or 'neuter'
		 * @return array an array of labels or empty array if invalid parameters
		 */
		public static function generate_labels( $plural_name, $singular_name, $type = 'labels', $gender = 'neuter' ) {
			$gender_map = array( 'm' => 'masculine', 'f' => 'feminine', 'n' => 'neuter' );
			if ( isset( $gender_map[ $gender ] ) ) {
				$gender = $gender_map[ $gender ];
			}

			$method_name = 'get_' . $type . '_' . $gender;

			if ( ! is_callable( array( __CLASS__, $method_name ) ) ) {
				$method_name = 'get_' . $type . '_neuter';
				if ( ! is_callable( array( __CLASS__, $method_name ) ) ) {
					return array();
				}
			}

			return call_user_func( array( __CLASS__, $method_name ), $plural_name, $singular_name );
		}

		/**
		 * This method creates default labels of masculine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_masculine( $plural_name, $singular_name ) {
			return array(
				'name'                  => $plural_name,
				'singular_name'         => $singular_name,
				'menu_name'             => $plural_name,
				'name_admin_bar'        => $singular_name,
				'all_items'             => sprintf( _x( 'All %s', 'all_items label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'add_new'               => _x( 'Add New', 'add_new label (masculine)', 'post-types-definitely' ),
				'add_new_item'          => sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'edit_item'             => sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'new_item'              => sprintf( _x( 'New %s', 'new_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'view_item'             => sprintf( _x( 'View %s', 'view_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'search_items'          => sprintf( _x( 'Search %s', 'search_items label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'not_found'             => sprintf( _x( 'No %s found', 'not_found label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'not_found_in_trash'    => sprintf( _x( 'No %s found in Trash', 'not_found_in_trash label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'parent_item_colon'     => sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'archives'              => sprintf( _x( '%s Archives', 'archives label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'featured_image'        => sprintf( _x( 'Featured %s Image', 'featured_image label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'set_featured_image'    => sprintf( _x( 'Set featured %s Image', 'set_featured_image label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'remove_featured_image' => sprintf( _x( 'Remove featured %s Image', 'remove_featured_image label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'use_featured_image'    => sprintf( _x( 'Use as featured %s Image', 'use_featured_image label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'            => sprintf( _x( '%s list', 'items_list label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation' => sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'filter_items_list'     => sprintf( _x( 'Filter %s list', 'filter_items_list label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				// additional labels for media library (as of WP 4.4 they are natively supported, in older versions they are handled by the plugin)
				'insert_into_item'      => sprintf( _x( 'Insert into %s content', 'insert_into_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				'uploaded_to_this_item' => sprintf( _x( 'Uploaded to this %s', 'uploaded_to_this_item label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				// new labels added in WP 4.7
				'view_items'            => sprintf( _x( 'View %s', 'view_items label: argument is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				'attributes'            => sprintf( _x( '%s Attributes', 'attributes label: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default labels of feminine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_feminine( $plural_name, $singular_name ) {
			return array(
				'name'                  => $plural_name,
				'singular_name'         => $singular_name,
				'menu_name'             => $plural_name,
				'name_admin_bar'        => $singular_name,
				'all_items'             => sprintf( _x( 'All %s', 'all_items label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'add_new'               => _x( 'Add New', 'add_new label (feminine)', 'post-types-definitely' ),
				'add_new_item'          => sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'edit_item'             => sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'new_item'              => sprintf( _x( 'New %s', 'new_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'view_item'             => sprintf( _x( 'View %s', 'view_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'search_items'          => sprintf( _x( 'Search %s', 'search_items label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'not_found'             => sprintf( _x( 'No %s found', 'not_found label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'not_found_in_trash'    => sprintf( _x( 'No %s found in Trash', 'not_found_in_trash label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'parent_item_colon'     => sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'archives'              => sprintf( _x( '%s Archives', 'archives label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'featured_image'        => sprintf( _x( 'Featured %s Image', 'featured_image label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'set_featured_image'    => sprintf( _x( 'Set featured %s Image', 'set_featured_image label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'remove_featured_image' => sprintf( _x( 'Remove featured %s Image', 'remove_featured_image label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'use_featured_image'    => sprintf( _x( 'Use as featured %s Image', 'use_featured_image label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'            => sprintf( _x( '%s list', 'items_list label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation' => sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'filter_items_list'     => sprintf( _x( 'Filter %s list', 'filter_items_list label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				// additional labels for media library (as of WP 4.4 they are natively supported, in older versions they are handled by the plugin)
				'insert_into_item'      => sprintf( _x( 'Insert into %s content', 'insert_into_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				'uploaded_to_this_item' => sprintf( _x( 'Uploaded to this %s', 'uploaded_to_this_item label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				// new labels added in WP 4.7
				'view_items'            => sprintf( _x( 'View %s', 'view_items label: argument is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				'attributes'            => sprintf( _x( '%s Attributes', 'attributes label: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default labels of neuter gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_neuter( $plural_name, $singular_name ) {
			return array(
				'name'                  => $plural_name,
				'singular_name'         => $singular_name,
				'menu_name'             => $plural_name,
				'name_admin_bar'        => $singular_name,
				'all_items'             => sprintf( _x( 'All %s', 'all_items label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'add_new'               => _x( 'Add New', 'add_new label (neuter)', 'post-types-definitely' ),
				'add_new_item'          => sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'edit_item'             => sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'new_item'              => sprintf( _x( 'New %s', 'new_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'view_item'             => sprintf( _x( 'View %s', 'view_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'search_items'          => sprintf( _x( 'Search %s', 'search_items label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'not_found'             => sprintf( _x( 'No %s found', 'not_found label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'not_found_in_trash'    => sprintf( _x( 'No %s found in Trash', 'not_found_in_trash label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'parent_item_colon'     => sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'archives'              => sprintf( _x( '%s Archives', 'archives label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'featured_image'        => sprintf( _x( 'Featured %s Image', 'featured_image label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'set_featured_image'    => sprintf( _x( 'Set featured %s Image', 'set_featured_image label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'remove_featured_image' => sprintf( _x( 'Remove featured %s Image', 'remove_featured_image label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'use_featured_image'    => sprintf( _x( 'Use as featured %s Image', 'use_featured_image label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'            => sprintf( _x( '%s list', 'items_list label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation' => sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'filter_items_list'     => sprintf( _x( 'Filter %s list', 'filter_items_list label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				// additional labels for media library (as of WP 4.4 they are natively supported, in older versions they are handled by the plugin)
				'insert_into_item'      => sprintf( _x( 'Insert into %s content', 'insert_into_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				'uploaded_to_this_item' => sprintf( _x( 'Uploaded to this %s', 'uploaded_to_this_item label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				// new labels added in WP 4.7
				'view_items'            => sprintf( _x( 'View %s', 'view_items label: argument is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				'attributes'            => sprintf( _x( '%s Attributes', 'attributes label: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default messages of masculine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_masculine( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%1$s updated. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( 'Custom %s field updated.', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( 'Custom %s field deleted.', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s updated.', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s restored to revision from %%s', 'post message: first argument is the singular post type label (masculine), second is the revision title', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%1$s published. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 7 => sprintf( _x( '%s saved.', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 8 => sprintf( _x( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				 9 => sprintf( _x( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
				10 => sprintf( _x( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default messages of feminine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_feminine( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%1$s updated. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( 'Custom %s field updated.', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( 'Custom %s field deleted.', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s updated.', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s restored to revision from %%s', 'post message: first argument is the singular post type label (feminine), second is the revision title', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%1$s published. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 7 => sprintf( _x( '%s saved.', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 8 => sprintf( _x( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				 9 => sprintf( _x( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
				10 => sprintf( _x( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default messages of neuter gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_neuter( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%1$s updated. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( 'Custom %s field updated.', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( 'Custom %s field deleted.', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s updated.', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s restored to revision from %%s', 'post message: first argument is the singular post type label (neuter), second is the revision title', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%1$s published. <a href="%%s">View %1$s</a>', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 7 => sprintf( _x( '%s saved.', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 8 => sprintf( _x( '%1$s submitted. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				 9 => sprintf( _x( '%1$s scheduled for: <strong>%%1\$s</strong>. <a target="_blank" href="%%2\$s">Preview %1$s</a>', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
				10 => sprintf( _x( '%1$s draft updated. <a target="_blank" href="%%s">Preview %1$s</a>', 'post message: argument is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default bulk messages of masculine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of bulk messages
		 */
		private static function get_bulk_messages_masculine( $plural_name, $singular_name ) {
			return array(
				'updated'	=> array(
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				),
				'locked'	=> array(
					sprintf( _x( '%%s %s not updated, somebody is editing it.', 'bulk post message: first argument is a number, second is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s not updated, somebody is editing them.', 'bulk post message: first argument is a number, second is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				),
				'deleted'	=> array(
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				),
				'trashed'	=> array(
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				),
				'untrashed'	=> array(
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (masculine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (masculine)', 'post-types-definitely' ), $plural_name ),
				),
			);
		}

		/**
		 * This method creates default bulk messages of feminine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of bulk messages
		 */
		private static function get_bulk_messages_feminine( $plural_name, $singular_name ) {
			return array(
				'updated'	=> array(
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				),
				'locked'	=> array(
					sprintf( _x( '%%s %s not updated, somebody is editing it.', 'bulk post message: first argument is a number, second is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s not updated, somebody is editing them.', 'bulk post message: first argument is a number, second is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				),
				'deleted'	=> array(
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				),
				'trashed'	=> array(
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				),
				'untrashed'	=> array(
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (feminine)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (feminine)', 'post-types-definitely' ), $plural_name ),
				),
			);
		}

		/**
		 * This method creates default bulk messages of neuter gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the post type's plural name
		 * @param string $singular_name the post type's singular name
		 * @return array an array of bulk messages
		 */
		private static function get_bulk_messages_neuter( $plural_name, $singular_name ) {
			return array(
				'updated'	=> array(
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s updated.', 'bulk post message: first argument is a number, second is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				),
				'locked'	=> array(
					sprintf( _x( '%%s %s not updated, somebody is editing it.', 'bulk post message: first argument is a number, second is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s not updated, somebody is editing them.', 'bulk post message: first argument is a number, second is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				),
				'deleted'	=> array(
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s permanently deleted.', 'bulk post message: first argument is a number, second is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				),
				'trashed'	=> array(
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s moved to the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				),
				'untrashed'	=> array(
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the singular post type label (neuter)', 'post-types-definitely' ), $singular_name ),
					sprintf( _x( '%%s %s restored from the Trash.', 'bulk post message: first argument is a number, second is the plural post type label (neuter)', 'post-types-definitely' ), $plural_name ),
				),
			);
		}
	}

}
