<?php
/**
 * Anthropic Claude Provider Adapter
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai/providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-ai-provider.php';

class AI_Search_Provider_Claude implements AI_Search_Provider_Interface {

	const API_URL = 'https://api.anthropic.com/v1/messages';
	const DEFAULT_MODEL = 'claude-haiku-4-5';
	const DEFAULT_LITE_MODEL = 'claude-haiku-4-5';
	const ANTHROPIC_VERSION = '2023-06-01';

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
	 * {@inheritdoc}
	 */
	public function complete( $system, array $messages, $tool = null, array $options = [] ) {
		$model = ! empty( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL;
		$temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2;
		$max_tokens  = ! empty( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 1024;

		$payload_messages = [];
		foreach ( $messages as $msg ) {
			$role = ( 'user' === $msg['role'] ) ? 'user' : 'assistant';
			$payload_messages[] = [
				'role'    => $role,
				'content' => $msg['content'],
			];
		}

		$body = [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'messages'    => $payload_messages,
			'temperature' => $temperature,
		];

		if ( ! empty( $system ) ) {
			$body['system'] = $system;
		}

		if ( ! empty( $tool ) ) {
			$body['tools'] = [
				[
					'name'        => $tool['name'],
					'description' => $tool['description'] ?? '',
					'input_schema'=> $tool['parameters'] ?? new stdClass(),
				],
			];
			$body['tool_choice'] = [
				'type' => 'tool',
				'name' => $tool['name'],
			];
		}

		$start_time = microtime( true );

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
				'content-type'      => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		] );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Claude Connection Error: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = $data['error']['message'] ?? ( 'HTTP ' . $status_code . ': ' . $raw_body );
			throw new Exception( 'Claude API Error (' . $status_code . '): ' . $error_msg );
		}

		$text      = '';
		$tool_args = null;

		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) ) {
					if ( 'text' === $block['type'] ) {
						$text .= $block['text'] ?? '';
					} elseif ( 'tool_use' === $block['type'] ) {
						if ( isset( $block['input'] ) && is_array( $block['input'] ) ) {
							$tool_args = $block['input'];
						}
					}
				}
			}
		}

		$input_tokens  = $data['usage']['input_tokens'] ?? 0;
		$output_tokens = $data['usage']['output_tokens'] ?? 0;

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

		$body = [
			'model'      => $test_model,
			'max_tokens' => 20,
			'messages'   => [
				[ 'role' => 'user', 'content' => 'Say hello in 5 words.' ],
			],
		];

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
				'content-type'      => 'application/json',
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
			if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
				foreach ( $data['content'] as $block ) {
					if ( 'text' === ( $block['type'] ?? '' ) ) {
						$reply .= $block['text'] ?? '';
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
