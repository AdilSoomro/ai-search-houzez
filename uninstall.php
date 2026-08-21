<?php
/**
 * Uninstall Handler for AI Search
 *
 * Cleans up options and transients on plugin deletion.
 *
 * @package AI_Search
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all ai_search_houzi_* options
$options = [
	'ai_search_houzi_enabled',
	'ai_search_houzi_provider',
	'ai_search_houzi_api_key',
	'ai_search_houzi_model',
	'ai_search_houzi_lite_model',
	'ai_search_houzi_system_prompt',
	'ai_search_houzi_rate_per_user_hour',
	'ai_search_houzi_rate_per_site_day',
	'ai_search_houzi_location_resolver_enabled',
	'ai_search_houzi_location_aliases',
	'ai_search_houzi_location_misses',
	'ai_search_houzi_usage_log',
	'ai_search_houzi_license_key',
	'ai_search_houzi_is_activated',
];

foreach ( $options as $opt ) {
	delete_option( $opt );
	delete_site_option( $opt );
}

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_search_houzi_%' OR option_name LIKE '_transient_timeout_ai_search_houzi_%'" );
