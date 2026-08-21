<?php
/**
 * Google Gemini Provider Adapter
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai/providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-ai-provider.php';

class AI_Search_Provider_Gemini implements AI_Search_Provider_Interface {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
	const DEFAULT_MODEL = 'gemini-3-flash-preview';
	const DEFAULT_LITE_MODEL = 'gemini-3.1-flash-lite';

	/**
	 * @var string
	 */
	private $api_key;

	/**
	 * @param string $api_key
	 */
	public function __construct( $api_key = '' ) {
		$this->api_key = $api_key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_model() {
		return self::DEFAULT_MODEL;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_lite_model() {
		return self::DEFAULT_LITE_MODEL;
	}

	/**
	 * Clean JSON schema for Gemini compatibility (removes unsupported keys like $schema or additionalProperties if present).
	 *
	 * @param array $schema
	 * @return array
	 */
	private function sanitize_gemini_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		unset( $schema['$schema'] );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $prop ) {
				$schema['properties'][ $key ] = $this->sanitize_gemini_schema( $prop );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = $this->sanitize_gemini_schema( $schema['items'] );
		}

		return $schema;
	}

	/**
	 * {@inheritdoc}
	 */
	public function complete( $system, array $messages, $tool = null, array $options = [] ) {
		$model = ! empty( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL;
		$temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2;

		$url = self::API_BASE . urlencode( $model ) . ':generateContent?key=' . urlencode( $this->api_key );

		$contents = [];
		foreach ( $messages as $msg ) {
			$role = ( 'user' === $msg['role'] ) ? 'user' : 'model';
			$contents[] = [
				'role'  => $role,
				'parts' => [
					[ 'text' => $msg['content'] ],
				],
			];
		}

		$body = [
			'contents'         => $contents,
			'generationConfig' => [
				'temperature' => $temperature,
			],
		];

		if ( ! empty( $system ) ) {
			$body['systemInstruction'] = [
				'parts' => [
					[ 'text' => $system ],
				],
			];
		}

		if ( ! empty( $tool ) ) {
			$params = $tool['parameters'] ?? [];
			$clean_params = $this->sanitize_gemini_schema( $params );

			$body['tools'] = [
				[
					'functionDeclarations' => [
						[
							'name'        => $tool['name'],
							'description' => $tool['description'] ?? '',
							'parameters'  => ! empty( $clean_params ) ? $clean_params : new stdClass(),
						],
					],
				],
			];
			$body['toolConfig'] = [
				'functionCallingConfig' => [
					'mode' => 'ANY',
				],
			];
		}

		$start_time = microtime( true );

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		] );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Gemini Connection Error: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = $data['error']['message'] ?? ( 'HTTP ' . $status_code . ': ' . $raw_body );
			throw new Exception( 'Gemini API Error (' . $status_code . '): ' . $error_msg );
		}

		$text      = '';
		$tool_args = null;

		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
				if ( isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] ) ) {
					$tool_args = $part['functionCall']['args'];
				}
			}
		}

		$input_tokens  = $data['usageMetadata']['promptTokenCount'] ?? 0;
		$output_tokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

		return [
			'tool_args'  => $tool_args,
			'text'       => $text,
			'usage'      => [
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
			],
			'latency_ms' => $latency_ms,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection( $api_key, $model = '' ) {
		$test_model = ! empty( $model ) ? $model : self::DEFAULT_MODEL;
		$start_time = microtime( true );

		$url = self::API_BASE . urlencode( $test_model ) . ':generateContent?key=' . urlencode( $api_key );

		$body = [
			'contents'         => [
				[
					'role'  => 'user',
					'parts' => [
						[ 'text' => 'Say hello in 5 words.' ],
					],
				],
			],
			'generationConfig' => [
				'maxOutputTokens' => 20,
				'temperature'     => 0.2,
			],
		];

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		] );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'     => false,
				'status_code' => 0,
				'latency_ms'  => $latency_ms,
				'message'     => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$reply = '';
			if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
				foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
					if ( isset( $part['text'] ) ) {
						$reply .= $part['text'];
					}
				}
			}
			return [
				'success'     => true,
				'status_code' => $status_code,
				'latency_ms'  => $latency_ms,
				'message'     => trim( $reply ) ?: 'Connected successfully.',
				'model'       => $test_model,
			];
		}

		$error_msg = $data['error']['message'] ?? ( 'HTTP ' . $status_code );
		return [
			'success'     => false,
			'status_code' => $status_code,
			'latency_ms'  => $latency_ms,
			'message'     => $error_msg,
			'model'       => $test_model,
		];
	}
}
