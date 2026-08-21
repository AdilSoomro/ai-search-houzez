<?php
/**
 * OpenAI Provider Adapter
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai/providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-ai-provider.php';

class AI_Search_Provider_OpenAI implements AI_Search_Provider_Interface {

	const API_URL = 'https://api.openai.com/v1/chat/completions';
	const DEFAULT_MODEL = 'gpt-5-mini';
	const DEFAULT_LITE_MODEL = 'gpt-5-nano';

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

		$payload_messages = [];
		if ( ! empty( $system ) ) {
			$payload_messages[] = [
				'role'    => 'system',
				'content' => $system,
			];
		}

		foreach ( $messages as $msg ) {
			$payload_messages[] = [
				'role'    => $msg['role'],
				'content' => $msg['content'],
			];
		}

		$body = [
			'model'       => $model,
			'messages'    => $payload_messages,
			'temperature' => $temperature,
		];

		if ( ! empty( $tool ) ) {
			$body['tools'] = [
				[
					'type'     => 'function',
					'function' => [
						'name'        => $tool['name'],
						'description' => $tool['description'] ?? '',
						'parameters'  => $tool['parameters'] ?? new stdClass(),
					],
				],
			];
			$body['tool_choice'] = [
				'type'     => 'function',
				'function' => [ 'name' => $tool['name'] ],
			];
		}

		$start_time = microtime( true );

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		] );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'OpenAI Connection Error: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = $data['error']['message'] ?? ( 'HTTP ' . $status_code . ': ' . $raw_body );
			throw new Exception( 'OpenAI API Error (' . $status_code . '): ' . $error_msg );
		}

		$text      = '';
		$tool_args = null;

		if ( isset( $data['choices'][0]['message'] ) ) {
			$msg = $data['choices'][0]['message'];
			$text = $msg['content'] ?? '';

			if ( ! empty( $msg['tool_calls'] ) ) {
				foreach ( $msg['tool_calls'] as $tc ) {
					if ( isset( $tc['function']['arguments'] ) ) {
						$args = json_decode( $tc['function']['arguments'], true );
						if ( is_array( $args ) ) {
							$tool_args = $args;
							break;
						}
					}
				}
			}
		}

		$input_tokens  = $data['usage']['prompt_tokens'] ?? 0;
		$output_tokens = $data['usage']['completion_tokens'] ?? 0;

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
			'model'       => $test_model,
			'messages'    => [
				[ 'role' => 'user', 'content' => 'Say hello in 5 words.' ],
			],
			'max_tokens'  => 20,
			'temperature' => 0.2,
		];

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
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
			$reply = $data['choices'][0]['message']['content'] ?? 'Connected successfully.';
			return [
				'success'     => true,
				'status_code' => $status_code,
				'latency_ms'  => $latency_ms,
				'message'     => trim( $reply ),
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
