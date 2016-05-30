<?php
/**
 * WPPTD\TaxonomyQueryHandler class
 *
 * @package WPPTD
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.6.1
 */

namespace WPPTD;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPPTD\TaxonomyQueryHandler' ) ) {
	/**
	 * This class adjusts `get_terms()` for a taxonomy registered with WPPTD.
	 *
	 * @internal
	 * @since 0.6.1
	 */
	class TaxonomyQueryHandler extends QueryHandler {
		// empty body
	}
}
