<?php
/**
 * AI Gateway
 *
 * Central dispatcher for natural language property search parsing.
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-prompt-library.php';
require_once __DIR__ . '/class-location-resolver.php';
require_once __DIR__ . '/class-ai-rate-limiter.php';

class AI_Search_Gateway
{

	/**
	 * Process a natural language real estate search query.
	 *
	 * @param string      $query           The user's search text.
	 * @param string|null $conversation_id Optional UUID for multi-turn search.
	 * @param array       $current_filters Optional active filter state from client.
	 * @return array {
	 *     @type bool   $success
	 *     @type array  $filters
	 *     @type string $explanation
	 *     @type array  $location
	 *     @type array  $suggestions
	 *     @type string $conversation_id
	 * }
	 * @throws Exception
	 */
	public static function process_search($query, $conversation_id = null, array $current_filters = [])
	{
		if (!AI_Search_Settings::is_enabled()) {
			throw new Exception(__('AI Search is currently disabled.', 'ai-search'));
		}

		$api_key = AI_Search_Settings::get_api_key();
		if (empty($api_key)) {
			throw new Exception(__('AI Search API Key is not configured in WordPress settings.', 'ai-search'));
		}

		// Ensure conversation ID
		if (empty($conversation_id)) {
			$conversation_id = wp_generate_uuid4();
			$history = [];
		} else {
			$conv_key = 'ai_search_houzi_conv_' . sanitize_key($conversation_id);
			$history = get_transient($conv_key);
			$history = is_array($history) ? $history : [];
		}

		$system = AI_Search_Prompt_Library::get_system_prompt();
		$tool = AI_Search_Prompt_Library::get_search_tool_schema();

		// Build message list
		$messages = [];
		foreach ($history as $h) {
			$messages[] = [
				'role' => $h['role'],
				'content' => $h['content'],
			];
		}

		// If multi-turn refinement, inject active filter baseline context
		$user_content = trim($query);
		if (!empty($conversation_id) && (!empty($current_filters) || !empty($history))) {
			$filter_summary = !empty($current_filters) ? wp_json_encode($current_filters) : '{}';
			$user_content = "[ACTIVE FILTERS BASELINE: {$filter_summary}]\nRefinement request: {$query}\nNote: Apply this refinement strictly on top of the active filters baseline above. Any previous filter (such as a city or feature) NOT in this baseline was deleted by the user and MUST NOT be restored unless explicitly requested in this message.";
		}

		$messages[] = [
			'role' => 'user',
			'content' => $user_content,
		];

		$provider_model = AI_Search_Settings::get_model();
		$options = [];
		if (!empty($provider_model)) {
			$options['model'] = $provider_model;
		}

		$provider = AI_Search_Settings::make_provider();

		// Execute Call (with 1 retry if tool_args missing)
		$response = null;
		$retry_done = false;

		for ($attempt = 0; $attempt < 2; $attempt++) {
			try {
				$response = $provider->complete($system, $messages, $tool, $options);
			} catch (Exception $e) {
				AI_Search_Settings::log_usage(0, 0, 0, true);
				throw $e;
			}

			if (!empty($response['tool_args']) && is_array($response['tool_args'])) {
				break;
			}

			// If no tool args returned on first try, prompt once more strictly
			if (0 === $attempt) {
				$retry_done = true;
				$messages[] = [
					'role' => 'assistant',
					'content' => $response['text'] ?: 'I will now search for properties.',
				];
				$messages[] = [
					'role' => 'user',
					'content' => 'Please invoke the search_properties tool with the structured parameters now.',
				];
			}
		}

		// Log usage
		$input_tokens = $response['usage']['input_tokens'] ?? 0;
		$output_tokens = $response['usage']['output_tokens'] ?? 0;
		$latency_ms = $response['latency_ms'] ?? 0;
		AI_Search_Settings::log_usage($input_tokens, $output_tokens, $latency_ms, false);

		$args = $response['tool_args'] ?? [];

		// Extract raw locations and resolve them
		$raw_locations = $args['locations'] ?? [];
		if (is_string($raw_locations)) {
			$raw_locations = [$raw_locations];
		}
		$loc_result = AI_Search_Location_Resolver::resolve_all($raw_locations);

		// Clean and normalize filter parameters
		$filters = [];

		if (!empty($args['type'])) {
			$filters['type'] = is_array($args['type']) ? $args['type'] : [$args['type']];
		}
		if (!empty($args['status'])) {
			$filters['status'] = is_array($args['status']) ? $args['status'] : [$args['status']];
		}
		if (!empty($args['label'])) {
			$filters['label'] = is_array($args['label']) ? $args['label'] : [$args['label']];
		}
		if (!empty($args['features'])) {
			$filters['features'] = is_array($args['features']) ? $args['features'] : [$args['features']];
		}
		if (!empty($args['bedrooms'])) {
			$filters['bedrooms'] = (int) $args['bedrooms'];
		}
		if (!empty($args['bathrooms'])) {
			$filters['bathrooms'] = (int) $args['bathrooms'];
		}
		if (!empty($args['min_price'])) {
			$filters['min_price'] = (float) $args['min_price'];
		}
		if (!empty($args['max_price'])) {
			$filters['max_price'] = (float) $args['max_price'];
		}
		if (!empty($args['min_area'])) {
			$filters['min_area'] = (float) $args['min_area'];
		}
		if (!empty($args['max_area'])) {
			$filters['max_area'] = (float) $args['max_area'];
		}
		if (!empty($args['keyword'])) {
			$filters['keyword'] = sanitize_text_field($args['keyword']);
		}

		// Inject resolved locations into filters (respecting user deletions in multi-turn)
		$query_lower = strtolower($query);
		foreach ($loc_result['resolved'] as $res) {
			$tax = $res['taxonomy'];

			// If this is a refinement turn and the location was not in $current_filters AND not mentioned in the new query,
			// skip it (it came from older conversation turns and was deleted by the user).
			if (!empty($current_filters)) {
				$raw_input = strtolower(trim($res['input']));
				$res_name = strtolower(trim($res['name']));
				$res_slug = strtolower(trim($res['slug']));

				$was_in_current = !empty($current_filters[$tax]) && in_array($res['slug'], (array) $current_filters[$tax], true);
				$is_mentioned_in_query = (
					(!empty($raw_input) && strpos($query_lower, $raw_input) !== false) ||
					(!empty($res_name) && strpos($query_lower, $res_name) !== false) ||
					(!empty($res_slug) && strpos($query_lower, $res_slug) !== false)
				);

				if (!$was_in_current && !$is_mentioned_in_query) {
					continue;
				}
			}

			if (!isset($filters[$tax])) {
				$filters[$tax] = [];
			}
			if (!in_array($res['slug'], $filters[$tax], true)) {
				$filters[$tax][] = $res['slug'];
			}
		}

		// If multi-turn refinement, carry over existing valid filters from $current_filters that were not overridden
		if (!empty($current_filters)) {
			// Type: keep existing if not specified in new query
			if (empty($filters['type']) && !empty($current_filters['type'])) {
				$filters['type'] = $current_filters['type'];
			}
			// Status: keep existing if not specified in new query
			if (empty($filters['status']) && !empty($current_filters['status'])) {
				$filters['status'] = $current_filters['status'];
			}
			// Features: merge existing features with new features
			if (!empty($current_filters['features'])) {
				$existing_feats = (array) $current_filters['features'];
				$new_feats = !empty($filters['features']) ? (array) $filters['features'] : [];
				$filters['features'] = array_values(array_unique(array_merge($existing_feats, $new_feats)));
			}
			// Locations: keep current locations if no new location was provided
			foreach (['property_city', 'property_area', 'property_state', 'property_country'] as $loc_tax) {
				if (empty($filters[$loc_tax]) && !empty($current_filters[$loc_tax])) {
					$filters[$loc_tax] = $current_filters[$loc_tax];
				}
			}
		}


		// For multi-turn refinements or when explanation is missing, generate synchronized explanation from merged filters
		if ( ! empty( $current_filters ) && class_exists( 'AI_Search_Ajax' ) ) {
			$explanation = AI_Search_Ajax::build_filters_explanation( $filters );
		} else {
			$explanation = ! empty( $args['explanation'] )
				? sanitize_text_field( $args['explanation'] )
				: ( class_exists( 'AI_Search_Ajax' ) ? AI_Search_Ajax::build_filters_explanation( $filters ) : __( 'Here are matching properties for your search.', 'ai-search' ) );
		}

		// If a location requested by the user was unresolved (not in database), ensure explanation is accurate and not misleading
		if ( ! empty( $loc_result['unresolved'] ) ) {
			foreach ( $loc_result['unresolved'] as $un_loc ) {
				$un_quoted = preg_quote( trim( $un_loc ), '/' );
				// Remove phrases like "in the Downtown area", "in Downtown", "around Downtown", etc.
				$explanation = preg_replace( '/\s*(in\s+the\s+' . $un_quoted . '\s+area|in\s+' . $un_quoted . '\s+area|around\s+' . $un_quoted . '|in\s+' . $un_quoted . ')/i', '', $explanation );
			}
			$explanation = rtrim( trim( $explanation ), '.' );
			$unresolved_str = implode( ', ', $loc_result['unresolved'] );
			if ( empty( $loc_result['resolved'] ) ) {
				$explanation .= sprintf( __( " (Note: '%s' is not in our location listings, showing results across all areas).", 'ai-search' ), $unresolved_str );
			} else {
				$explanation .= sprintf( __( " (Note: '%s' was not found in location listings).", 'ai-search' ), $unresolved_str );
			}
		}



		$suggestions = [];
		if (!empty($args['suggestions']) && is_array($args['suggestions'])) {
			foreach ($args['suggestions'] as $s) {
				if (empty($s['label'])) {
					continue;
				}
				$patch = !empty($s['patch']) ? (array) $s['patch'] : [];
				// If patch is empty, infer it reliably from the label
				if (empty($patch)) {
					$lbl = strtolower($s['label']);
					if (strpos($lbl, 'pool') !== false) {
						$patch = ['features' => ['swimming-pool']];
					} elseif (strpos($lbl, 'under') !== false || strpos($lbl, '<') !== false || strpos($lbl, '1m') !== false || strpos($lbl, 'million') !== false || strpos($lbl, 'k') !== false || strpos($lbl, '$') !== false) {
						if (preg_match('/(\d+(?:\.\d+)?)\s*m/i', $lbl, $m)) {
							$patch = ['max_price' => (float) $m[1] * 1000000];
						} elseif (preg_match('/(\d+(?:\.\d+)?)\s*k/i', $lbl, $m)) {
							$patch = ['max_price' => (float) $m[1] * 1000];
						} elseif (preg_match('/\$?\s*([0-9,]+)/', $lbl, $m)) {
							$val = (float) str_replace(',', '', $m[1]);
							if ($val > 0) {
								$patch = ['max_price' => $val];
							}
						}
					} elseif (strpos($lbl, 'bed') !== false) {
						if (preg_match('/(\d+)/', $lbl, $m)) {
							$patch = ['bedrooms' => (int) $m[1]];
						}
					} elseif (strpos($lbl, 'bath') !== false) {
						if (preg_match('/(\d+)/', $lbl, $m)) {
							$patch = ['bathrooms' => (int) $m[1]];
						}
					} elseif (strpos($lbl, 'rent') !== false) {
						$patch = ['status' => ['for-rent']];
					} elseif (strpos($lbl, 'sale') !== false) {
						$patch = ['status' => ['for-sale']];
					} elseif (strpos($lbl, 'villa') !== false) {
						$patch = ['type' => ['villa']];
					} elseif (strpos($lbl, 'house') !== false || strpos($lbl, 'home') !== false) {
						$patch = ['type' => ['single-family-home']];
					} elseif (strpos($lbl, 'condo') !== false || strpos($lbl, 'apartment') !== false) {
						$patch = ['type' => ['apartment']];
					}
				}

				if (!empty($patch)) {
					$suggestions[] = [
						'label' => sanitize_text_field($s['label']),
						'patch' => $patch,
					];
				}
			}
		}


		// Update conversation history (keep last 6 pairs = 12 items)
		$history[] = ['role' => 'user', 'content' => $query];
		$history[] = ['role' => 'assistant', 'content' => $explanation];

		if (count($history) > 12) {
			$history = array_slice($history, -12);
		}

		$conv_key = 'ai_search_houzi_conv_' . sanitize_key($conversation_id);
		set_transient($conv_key, $history, 30 * MINUTE_IN_SECONDS);

		return [
			'success' => true,
			'filters' => $filters,
			'raw_args' => $args,
			'explanation' => $explanation,
			'location' => $loc_result,
			'suggestions' => $suggestions,
			'conversation_id' => $conversation_id,
		];
	}
}
