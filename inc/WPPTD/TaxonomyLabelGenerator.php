<?php
/**
 * WPPTD\TaxonomyLabelGenerator class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\TaxonomyLabelGenerator' ) ) {
	/**
	 * This class contains static methods to generate default taxonomy labels.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class TaxonomyLabelGenerator {

		/**
		 * This method generates default labels of any kind and provides public access to the class.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @param string $type either 'labels' or 'messages'
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
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_masculine( $plural_name, $singular_name ) {
			return array(
				'name'							=> $plural_name,
				'singular_name'					=> $singular_name,
				'menu_name'						=> $plural_name,
				'all_items'						=> sprintf( _x( 'All %s', 'all_items label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'add_new_item'					=> sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'edit_item'						=> sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'view_item'						=> sprintf( _x( 'View %s', 'view_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'update_item'					=> sprintf( _x( 'Update %s', 'update_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'new_item_name'					=> sprintf( _x( 'New %s Name', 'new_item_name label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'search_items'					=> sprintf( _x( 'Search %s', 'search_items label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'popular_items'					=> sprintf( _x( 'Popular %s', 'popular_items label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'not_found'						=> sprintf( _x( 'No %s found', 'not_found label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'no_terms'						=> sprintf( _x( 'No %s', 'no_terms label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'separate_items_with_commas'	=> sprintf( _x( 'Separate %s with commas', 'separate_items_with_commas label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'add_or_remove_items'			=> sprintf( _x( 'Add or remove %s', 'add_or_remove_items label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'choose_from_most_used'			=> sprintf( _x( 'Choose from the most used %s', 'choose_from_most_used label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'parent_item'					=> sprintf( _x( 'Parent %s', 'parent_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				'parent_item_colon'				=> sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'			=> sprintf( _x( '%s list', 'items_list label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation'	=> sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
				// additional label for post listings (handled by the plugin)
				'filter_by_item'				=> sprintf( _x( 'Filter by %s', 'filter_by_item label: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default labels of feminine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_feminine( $plural_name, $singular_name ) {
			return array(
				'name'							=> $plural_name,
				'singular_name'					=> $singular_name,
				'menu_name'						=> $plural_name,
				'all_items'						=> sprintf( _x( 'All %s', 'all_items label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'add_new_item'					=> sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'edit_item'						=> sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'view_item'						=> sprintf( _x( 'View %s', 'view_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'update_item'					=> sprintf( _x( 'Update %s', 'update_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'new_item_name'					=> sprintf( _x( 'New %s Name', 'new_item_name label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'search_items'					=> sprintf( _x( 'Search %s', 'search_items label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'popular_items'					=> sprintf( _x( 'Popular %s', 'popular_items label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'not_found'						=> sprintf( _x( 'No %s found', 'not_found label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'no_terms'						=> sprintf( _x( 'No %s', 'no_terms label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'separate_items_with_commas'	=> sprintf( _x( 'Separate %s with commas', 'separate_items_with_commas label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'add_or_remove_items'			=> sprintf( _x( 'Add or remove %s', 'add_or_remove_items label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'choose_from_most_used'			=> sprintf( _x( 'Choose from the most used %s', 'choose_from_most_used label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'parent_item'					=> sprintf( _x( 'Parent %s', 'parent_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				'parent_item_colon'				=> sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'			=> sprintf( _x( '%s list', 'items_list label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation'	=> sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
				// additional label for post listings (handled by the plugin)
				'filter_by_item'				=> sprintf( _x( 'Filter by %s', 'filter_by_item label: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default labels of neuter gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of labels
		 */
		private static function get_labels_neuter( $plural_name, $singular_name ) {
			return array(
				'name'							=> $plural_name,
				'singular_name'					=> $singular_name,
				'menu_name'						=> $plural_name,
				'all_items'						=> sprintf( _x( 'All %s', 'all_items label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'add_new_item'					=> sprintf( _x( 'Add New %s', 'add_new_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'edit_item'						=> sprintf( _x( 'Edit %s', 'edit_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'view_item'						=> sprintf( _x( 'View %s', 'view_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'update_item'					=> sprintf( _x( 'Update %s', 'update_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'new_item_name'					=> sprintf( _x( 'New %s Name', 'new_item_name label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'search_items'					=> sprintf( _x( 'Search %s', 'search_items label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'popular_items'					=> sprintf( _x( 'Popular %s', 'popular_items label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'not_found'						=> sprintf( _x( 'No %s found', 'not_found label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'no_terms'						=> sprintf( _x( 'No %s', 'no_terms label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'separate_items_with_commas'	=> sprintf( _x( 'Separate %s with commas', 'separate_items_with_commas label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'add_or_remove_items'			=> sprintf( _x( 'Add or remove %s', 'add_or_remove_items label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'choose_from_most_used'			=> sprintf( _x( 'Choose from the most used %s', 'choose_from_most_used label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'parent_item'					=> sprintf( _x( 'Parent %s', 'parent_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				'parent_item_colon'				=> sprintf( _x( 'Parent %s:', 'parent_item_colon label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				// new accessibility labels added in WP 4.4
				'items_list'			=> sprintf( _x( '%s list', 'items_list label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				'items_list_navigation'	=> sprintf( _x( '%s list navigation', 'items_list_navigation label: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
				// additional label for post listings (handled by the plugin)
				'filter_by_item'				=> sprintf( _x( 'Filter by %s', 'filter_by_item label: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
			);
		}

		/**
		 * This method creates default messages of masculine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_masculine( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%s added.', 'term message: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( '%s deleted.', 'term message: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( '%s updated.', 'term message: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s not added.', 'term message: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s not updated.', 'term message: argument is the singular taxonomy label (masculine)', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%s deleted.', 'bulk term message: argument is the plural taxonomy label (masculine)', 'post-types-definitely' ), $plural_name ),
			);
		}

		/**
		 * This method creates default messages of feminine gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_feminine( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%s added.', 'term message: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( '%s deleted.', 'term message: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( '%s updated.', 'term message: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s not added.', 'term message: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s not updated.', 'term message: argument is the singular taxonomy label (feminine)', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%s deleted.', 'bulk term message: argument is the plural taxonomy label (feminine)', 'post-types-definitely' ), $plural_name ),
			);
		}

		/**
		 * This method creates default messages of neuter gender.
		 *
		 * @since 0.6.1
		 * @param string $plural_name the taxonomy's plural name
		 * @param string $singular_name the taxonomy's singular name
		 * @return array an array of messages
		 */
		private static function get_messages_neuter( $plural_name, $singular_name ) {
			return array(
				 0 => '',
				 1 => sprintf( _x( '%s added.', 'term message: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				 2 => sprintf( _x( '%s deleted.', 'term message: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				 3 => sprintf( _x( '%s updated.', 'term message: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				 4 => sprintf( _x( '%s not added.', 'term message: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				 5 => sprintf( _x( '%s not updated.', 'term message: argument is the singular taxonomy label (neuter)', 'post-types-definitely' ), $singular_name ),
				 6 => sprintf( _x( '%s deleted.', 'bulk term message: argument is the plural taxonomy label (neuter)', 'post-types-definitely' ), $plural_name ),
			);
		}
	}

}
