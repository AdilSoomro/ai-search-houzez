<?php
/**
 * AI Provider Interface
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai/providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AI_Search_Provider_Interface {

	/**
	 * Send a completion request with system prompt, messages, and optional tool schema.
	 *
	 * @param string      $system   System instructions.
	 * @param array       $messages Message history array.
	 * @param array|null  $tool     Tool/function definition array.
	 * @param array       $options  Additional options (model, temperature, etc.).
	 * @return array {
	 *     @type array|null $tool_args    Parsed arguments returned by tool call.
	 *     @type string     $text         Raw response text.
	 *     @type array      $usage        ['input_tokens' => int, 'output_tokens' => int].
	 *     @type int        $latency_ms   Latency in milliseconds.
	 * }
	 * @throws Exception On API or network errors.
	 */
	public function complete( $system, array $messages, $tool = null, array $options = [] );

	/**
	 * Test connection / validate API key with a minimal non-tool ping.
	 *
	 * @param string $api_key API Key to test.
	 * @param string $model   Optional model name.
	 * @return array {
	 *     @type bool   $success    True if connected.
	 *     @type int    $status_code HTTP response status code.
	 *     @type int    $latency_ms Response latency in ms.
	 *     @type string $message    Response text or error details.
	 * }
	 */
	public function test_connection( $api_key, $model = '' );

	/**
	 * Get default model name for this provider.
	 *
	 * @return string
	 */
	public function get_default_model();

	/**
	 * Get default lite model name for this provider.
	 *
	 * @return string
	 */
	public function get_default_lite_model();
}
