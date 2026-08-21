<?php
/**
 * License Verification and Activation Manager for AI Search for Houzez
 *
 * Handles Envato purchase code verification via Envato Market API.
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_License {

	/**
	 * Production Envato settings
	 */
	const PROD_ITEM_ID = 39753350;
	const PROD_API_URL = 'https://api.envato.com/v3/market/author/sale?code=';
	const PROD_TOKEN   = 'DTEBcnRdUOmvIUkQLCi6YK6C1m20NTwn';

	/**
	 * License data option key
	 */
	const OPTION_LICENSE_DATA = 'ai_search_houzi_license_data';

	/**
	 * Check if plugin is activated with a valid license.
	 *
	 * @return bool
	 */
	public static function is_activated() {
		$activated = get_option( AI_Search_Settings::OPTION_IS_ACTIVATED, '0' );
		$key       = get_option( AI_Search_Settings::OPTION_LICENSE_KEY, '' );
		return ( ( '1' === $activated || true === $activated || 1 === $activated ) && ! empty( $key ) );
	}

	/**
	 * Get current license key.
	 *
	 * @return string
	 */
	public static function get_license_key() {
		return get_option( AI_Search_Settings::OPTION_LICENSE_KEY, '' );
	}

	/**
	 * Get stored license details (buyer, supported_until, etc.).
	 *
	 * @return array
	 */
	public static function get_license_data() {
		$data = get_option( self::OPTION_LICENSE_DATA, [] );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Activate and verify purchase code with Envato API.
	 *
	 * @param string $purchase_code The Envato purchase code / license key.
	 * @return array Array with success (bool), msg (string), and optional data (array).
	 */
	public static function activate( $purchase_code ) {
		$purchase_code = trim( sanitize_text_field( $purchase_code ) );

		if ( empty( $purchase_code ) ) {
			return [
				'success' => false,
				'msg'     => __( 'Please enter an item purchase code.', 'ai-search' ),
			];
		}

		$apiurl  = self::PROD_API_URL . esc_html( $purchase_code );
		$headers = [
			'User-Agent'    => 'Purchase code verification',
			'Authorization' => 'Bearer ' . self::PROD_TOKEN,
		];

		$response = wp_remote_request( $apiurl, [
			'headers' => $headers,
			'timeout' => 20,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'msg'     => __( 'There is a problem with the API connection, please try again.', 'ai-search' ),
				'error'   => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$response_body = json_decode( $body, true );

		if ( 200 === $response_code && is_array( $response_body ) ) {
			$purchase_array = isset( $response_body['item'] ) ? (array) $response_body['item'] : [];

			if ( isset( $purchase_array['id'] ) && (int) $purchase_array['id'] === (int) self::PROD_ITEM_ID ) {
				// Successfully verified! Save activation state
				update_option( AI_Search_Settings::OPTION_IS_ACTIVATED, '1' );
				update_option( AI_Search_Settings::OPTION_LICENSE_KEY, $purchase_code );

				$license_data = [
					'buyer'           => sanitize_text_field( $response_body['buyer'] ?? '' ),
					'sold_at'         => sanitize_text_field( $response_body['sold_at'] ?? '' ),
					'supported_until' => sanitize_text_field( $response_body['supported_until'] ?? '' ),
					'item_name'       => sanitize_text_field( $purchase_array['name'] ?? 'AI Search for Houzez' ),
					'item_id'         => (int) ( $purchase_array['id'] ?? self::PROD_ITEM_ID ),
					'activated_at'    => current_time( 'mysql' ),
				];

				update_option( self::OPTION_LICENSE_DATA, $license_data );

				return [
					'success' => true,
					'msg'     => __( 'Thanks for verifying your purchase! Plugin activated successfully.', 'ai-search' ),
					'data'    => $license_data,
				];
			} else {
				return [
					'success' => false,
					'msg'     => __( 'Purchase code does not match this item.', 'ai-search' ),
				];
			}
		}

		return [
			'success' => false,
			'msg'     => __( 'Invalid purchase code, please provide a valid purchase code.', 'ai-search' ),
		];
	}

	/**
	 * Deactivate current license.
	 *
	 * @return array
	 */
	public static function deactivate() {
		update_option( AI_Search_Settings::OPTION_IS_ACTIVATED, '0' );
		update_option( AI_Search_Settings::OPTION_LICENSE_KEY, '' );
		delete_option( self::OPTION_LICENSE_DATA );

		return [
			'success' => true,
			'msg'     => __( 'Plugin license deactivated successfully.', 'ai-search' ),
		];
	}
}
