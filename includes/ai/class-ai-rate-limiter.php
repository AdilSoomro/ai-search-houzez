<?php
/**
 * AI Rate Limiter
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Rate_Limiter {

	/**
	 * Get client identifier (User ID or hashed IP).
	 *
	 * @return string
	 */
	public static function get_client_id() {
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip    = trim( $parts[0] );
		}

		return 'ip_' . substr( hash( 'sha256', $ip . wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * Check and increment rate limits.
	 *
	 * @return array {
	 *     @type bool   $allowed
	 *     @type int    $retry_after
	 *     @type int    $user_limit
	 *     @type int    $user_remaining
	 *     @type int    $site_limit
	 *     @type int    $site_remaining
	 *     @type string $message
	 * }
	 */
	public static function check_and_increment() {
		$user_limit = AI_Search_Settings::get_rate_per_user_hour();
		$site_limit = AI_Search_Settings::get_rate_per_site_day();

		$client_id = self::get_client_id();
		$hour_key  = 'ai_search_houzi_rt_u_' . $client_id . '_' . gmdate( 'YmdH' );
		$day_key   = 'ai_search_houzi_rt_s_' . gmdate( 'Ymd' );

		// Check Site-wide Daily Limit
		$site_count = (int) get_transient( $day_key );
		if ( $site_limit > 0 && $site_count >= $site_limit ) {
			return [
				'allowed'        => false,
				'retry_after'    => 3600,
				'user_limit'     => $user_limit,
				'user_remaining' => 0,
				'site_limit'     => $site_limit,
				'site_remaining' => 0,
				'message'        => __( 'Daily search limit for this website has been reached. Please try again tomorrow.', 'ai-search' ),
			];
		}

		// Check User Hourly Limit
		$user_count = (int) get_transient( $hour_key );
		if ( $user_limit > 0 && $user_count >= $user_limit ) {
			$minutes_left = 60 - (int) gmdate( 'i' );
			return [
				'allowed'        => false,
				'retry_after'    => $minutes_left * 60,
				'user_limit'     => $user_limit,
				'user_remaining' => 0,
				'site_limit'     => $site_limit,
				'site_remaining' => max( 0, $site_limit - $site_count ),
				'message'        => sprintf(
					__( "You've made many great searches! Please wait %d minute(s) before trying again.", 'ai-search' ),
					$minutes_left
				),
			];
		}

		// Increment counts
		$user_count++;
		$site_count++;

		set_transient( $hour_key, $user_count, HOUR_IN_SECONDS );
		set_transient( $day_key, $site_count, DAY_IN_SECONDS );

		return [
			'allowed'        => true,
			'retry_after'    => 0,
			'user_limit'     => $user_limit,
			'user_remaining' => max( 0, $user_limit - $user_count ),
			'site_limit'     => $site_limit,
			'site_remaining' => max( 0, $site_limit - $site_count ),
			'message'        => '',
		];
	}
}
