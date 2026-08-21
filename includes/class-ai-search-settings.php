<?php
/**
 * Settings Manager for AI Search
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Settings {

	const OPTION_ENABLED                  = 'ai_search_houzi_enabled';
	const OPTION_PROVIDER                 = 'ai_search_houzi_provider';
	const OPTION_API_KEY                  = 'ai_search_houzi_api_key';
	const OPTION_MODEL                    = 'ai_search_houzi_model';
	const OPTION_LITE_MODEL               = 'ai_search_houzi_lite_model';
	const OPTION_SYSTEM_PROMPT            = 'ai_search_houzi_system_prompt';
	const OPTION_RATE_USER_HOUR           = 'ai_search_houzi_rate_per_user_hour';
	const OPTION_RATE_SITE_DAY            = 'ai_search_houzi_rate_per_site_day';
	const OPTION_LOCATION_RESOLVER_ENABLED= 'ai_search_houzi_location_resolver_enabled';
	const OPTION_LOCATION_ALIASES         = 'ai_search_houzi_location_aliases';
	const OPTION_LOCATION_MISSES          = 'ai_search_houzi_location_misses';
	const OPTION_USAGE_LOG                = 'ai_search_houzi_usage_log';
	const OPTION_LICENSE_KEY              = 'ai_search_houzi_license_key';
	const OPTION_IS_ACTIVATED             = 'ai_search_houzi_is_activated';

	/**
	 * Default fallback settings
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return [
			self::OPTION_ENABLED                  => '1',
			self::OPTION_PROVIDER                 => 'openai',
			self::OPTION_API_KEY                  => '',
			self::OPTION_MODEL                    => '',
			self::OPTION_LITE_MODEL               => '',
			self::OPTION_SYSTEM_PROMPT            => '',
			self::OPTION_RATE_USER_HOUR           => 30,
			self::OPTION_RATE_SITE_DAY            => 500,
			self::OPTION_LOCATION_RESOLVER_ENABLED=> '1',
			self::OPTION_LOCATION_ALIASES         => [],
			self::OPTION_LOCATION_MISSES          => [],
			self::OPTION_USAGE_LOG                => [],
			self::OPTION_LICENSE_KEY              => '',
			self::OPTION_IS_ACTIVATED             => '0',
		];
	}

	/**
	 * Is AI Search enabled?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = get_option( self::OPTION_ENABLED, '1' );
		return ( '1' === $enabled || true === $enabled || 1 === $enabled );
	}

	/**
	 * Get current AI provider slug.
	 *
	 * @return string
	 */
	public static function get_provider() {
		return get_option( self::OPTION_PROVIDER, 'openai' );
	}

	/**
	 * Get stored API key.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Get model name override.
	 *
	 * @return string
	 */
	public static function get_model() {
		return get_option( self::OPTION_MODEL, '' );
	}

	/**
	 * Get lite model override.
	 *
	 * @return string
	 */
	public static function get_lite_model() {
		return get_option( self::OPTION_LITE_MODEL, '' );
	}

	/**
	 * Get custom system prompt.
	 *
	 * @return string
	 */
	public static function get_system_prompt() {
		return get_option( self::OPTION_SYSTEM_PROMPT, '' );
	}

	/**
	 * Get rate limit per user per hour.
	 *
	 * @return int
	 */
	public static function get_rate_per_user_hour() {
		return (int) get_option( self::OPTION_RATE_USER_HOUR, 30 );
	}

	/**
	 * Get rate limit per site per day.
	 *
	 * @return int
	 */
	public static function get_rate_per_site_day() {
		return (int) get_option( self::OPTION_RATE_SITE_DAY, 500 );
	}

	/**
	 * Is location resolver enabled?
	 *
	 * @return bool
	 */
	public static function is_location_resolver_enabled() {
		$val = get_option( self::OPTION_LOCATION_RESOLVER_ENABLED, '1' );
		return ( '1' === $val || true === $val || 1 === $val );
	}

	/**
	 * Get location aliases array.
	 *
	 * @return array
	 */
	public static function get_location_aliases() {
		$aliases = get_option( self::OPTION_LOCATION_ALIASES, [] );
		return is_array( $aliases ) ? $aliases : [];
	}

	/**
	 * Save location aliases.
	 *
	 * @param array $aliases
	 * @return bool
	 */
	public static function set_location_aliases( array $aliases ) {
		return update_option( self::OPTION_LOCATION_ALIASES, $aliases );
	}

	/**
	 * Get location misses log.
	 *
	 * @return array
	 */
	public static function get_location_misses() {
		$misses = get_option( self::OPTION_LOCATION_MISSES, [] );
		return is_array( $misses ) ? $misses : [];
	}

	/**
	 * Record a location miss.
	 *
	 * @param string $phrase
	 */
	public static function log_location_miss( $phrase ) {
		$phrase = trim( strtolower( sanitize_text_field( $phrase ) ) );
		if ( empty( $phrase ) ) {
			return;
		}

		$misses = self::get_location_misses();
		$found  = false;

		foreach ( $misses as &$item ) {
			if ( strtolower( $item['phrase'] ) === $phrase ) {
				$item['count']     = ( $item['count'] ?? 1 ) + 1;
				$item['last_seen'] = current_time( 'mysql' );
				$found             = true;
				break;
			}
		}
		unset( $item );

		if ( ! $found ) {
			array_unshift( $misses, [
				'phrase'    => $phrase,
				'count'     => 1,
				'last_seen' => current_time( 'mysql' ),
			] );
		}


		// Cap at 50 entries
		if ( count( $misses ) > 50 ) {
			$misses = array_slice( $misses, 0, 50 );
		}

		update_option( self::OPTION_LOCATION_MISSES, $misses );
	}

	/**
	 * Remove a phrase from location misses.
	 *
	 * @param string $phrase
	 */
	public static function remove_location_miss( $phrase ) {
		$misses = self::get_location_misses();
		$clean  = [];
		$phrase = trim( strtolower( $phrase ) );

		foreach ( $misses as $item ) {
			if ( strtolower( $item['phrase'] ) !== $phrase ) {
				$clean[] = $item;
			}
		}

		update_option( self::OPTION_LOCATION_MISSES, $clean );
	}

	/**
	 * Get usage log.
	 *
	 * @return array
	 */
	public static function get_usage_log() {
		$log = get_option( self::OPTION_USAGE_LOG, [] );
		return is_array( $log ) ? $log : [];
	}

	/**
	 * Record usage metrics.
	 *
	 * @param int  $input_tokens
	 * @param int  $output_tokens
	 * @param int  $latency_ms
	 * @param bool $is_error
	 */
	public static function log_usage( $input_tokens = 0, $output_tokens = 0, $latency_ms = 0, $is_error = false ) {
		$today = current_time( 'Y-m-d' );
		$log   = self::get_usage_log();

		if ( ! isset( $log[ $today ] ) ) {
			$log[ $today ] = [
				'date'          => $today,
				'calls'         => 0,
				'errors'        => 0,
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'total_latency' => 0,
				'avg_latency'   => 0,
			];
		}

		$log[ $today ]['calls']++;
		if ( $is_error ) {
			$log[ $today ]['errors']++;
		}
		$log[ $today ]['input_tokens']  += (int) $input_tokens;
		$log[ $today ]['output_tokens'] += (int) $output_tokens;
		$log[ $today ]['total_latency'] += (int) $latency_ms;

		if ( $log[ $today ]['calls'] > 0 ) {
			$log[ $today ]['avg_latency'] = (int) round( $log[ $today ]['total_latency'] / $log[ $today ]['calls'] );
		}

		// Prune to last 30 days
		if ( count( $log ) > 30 ) {
			krsort( $log );
			$log = array_slice( $log, 0, 30, true );
		}

		update_option( self::OPTION_USAGE_LOG, $log );
	}

	/**
	 * Create provider instance based on settings.
	 *
	 * @param string|null $provider_slug Optional provider override.
	 * @param string|null $api_key       Optional API key override.
	 * @return AI_Search_Provider_Interface
	 * @throws Exception
	 */
	public static function make_provider( $provider_slug = null, $api_key = null ) {
		$provider_slug = $provider_slug ?: self::get_provider();
		$api_key       = ( null !== $api_key ) ? $api_key : self::get_api_key();

		require_once __DIR__ . '/ai/providers/interface-ai-provider.php';

		switch ( $provider_slug ) {
			case 'anthropic':
				require_once __DIR__ . '/ai/providers/class-provider-claude.php';
				return new AI_Search_Provider_Claude( $api_key );

			case 'gemini':
				require_once __DIR__ . '/ai/providers/class-provider-gemini.php';
				return new AI_Search_Provider_Gemini( $api_key );

			case 'openai':
			default:
				require_once __DIR__ . '/ai/providers/class-provider-openai.php';
				return new AI_Search_Provider_OpenAI( $api_key );
		}
	}

	/**
	 * Is plugin activated with a valid license?
	 *
	 * @return bool
	 */
	public static function is_activated() {
		if ( class_exists( 'AI_Search_License' ) ) {
			$activated = AI_Search_License::is_activated();
		} else {
			$opt       = get_option( self::OPTION_IS_ACTIVATED, '0' );
			$activated = ( '1' === $opt || true === $opt || 1 === $opt );
		}
		return apply_filters( 'ai_search_houzi_is_activated', $activated );
	}
}

