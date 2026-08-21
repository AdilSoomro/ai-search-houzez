<?php
/**
 * Internationalization & Localization Loader
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_i18n {

	/**
	 * Load plugin textdomain.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain(
			'ai-search',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
