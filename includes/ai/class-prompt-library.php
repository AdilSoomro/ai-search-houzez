<?php
/**
 * AI Prompt & Tool Schema Library
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Prompt_Library {

	/**
	 * Get taxonomy term slugs cached for 1 hour.
	 *
	 * @param string $taxonomy
	 * @return array
	 */
	public static function get_taxonomy_slugs( $taxonomy ) {
		$cache_key = 'ai_search_houzi_tax_' . substr( md5( $taxonomy ), 0, 12 );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'id=>slug',
		] );

		$slugs = [];
		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			$slugs = array_values( $terms );
		}

		set_transient( $cache_key, $slugs, HOUR_IN_SECONDS );
		return $slugs;
	}

	/**
	 * Build the search_properties tool schema with dynamic Houzez taxonomy enums.
	 *
	 * @return array
	 */
	public static function get_search_tool_schema() {
		$type_slugs    = self::get_taxonomy_slugs( 'property_type' );
		$status_slugs  = self::get_taxonomy_slugs( 'property_status' );
		$label_slugs   = self::get_taxonomy_slugs( 'property_label' );
		$feature_slugs = self::get_taxonomy_slugs( 'property_feature' );

		// Type definition
		$type_prop = [
			'type'        => 'array',
			'items'       => [ 'type' => 'string' ],
			'description' => 'Property type slugs (e.g. apartment, villa, house, single-family-home).',
		];
		if ( ! empty( $type_slugs ) && count( $type_slugs ) <= 150 ) {
			$type_prop['items']['enum'] = $type_slugs;
		}

		// Status definition
		$status_prop = [
			'type'        => 'array',
			'items'       => [ 'type' => 'string' ],
			'description' => 'Listing status slugs (e.g. for-sale, for-rent).',
		];
		if ( ! empty( $status_slugs ) && count( $status_slugs ) <= 150 ) {
			$status_prop['items']['enum'] = $status_slugs;
		}

		// Label definition
		$label_prop = [
			'type'        => 'array',
			'items'       => [ 'type' => 'string' ],
			'description' => 'Property label slugs (e.g. featured, hot, open-house).',
		];
		if ( ! empty( $label_slugs ) && count( $label_slugs ) <= 150 ) {
			$label_prop['items']['enum'] = $label_slugs;
		}

		// Features definition
		$feature_prop = [
			'type'        => 'array',
			'items'       => [ 'type' => 'string' ],
			'description' => 'Property feature/amenity slugs (e.g. swimming-pool, gym, central-air, garage, waterfront).',
		];
		if ( ! empty( $feature_slugs ) && count( $feature_slugs ) <= 150 ) {
			$feature_prop['items']['enum'] = $feature_slugs;
		}

		return [
			'name'        => 'search_properties',
			'description' => 'Extract structured real estate search parameters and refinement suggestions from a user query.',
			'parameters'  => [
				'type'       => 'object',
				'properties' => [
					'type'        => $type_prop,
					'status'      => $status_prop,
					'label'       => $label_prop,
					'features'    => $feature_prop,
					'bedrooms'    => [
						'type'        => 'integer',
						'description' => 'Minimum number of bedrooms requested.',
					],
					'bathrooms'   => [
						'type'        => 'integer',
						'description' => 'Minimum number of bathrooms requested.',
					],
					'min_price'   => [
						'type'        => 'number',
						'description' => 'Minimum budget or price constraint.',
					],
					'max_price'   => [
						'type'        => 'number',
						'description' => 'Maximum budget or price constraint.',
					],
					'min_area'    => [
						'type'        => 'number',
						'description' => 'Minimum square footage/area.',
					],
					'max_area'    => [
						'type'        => 'number',
						'description' => 'Maximum square footage/area.',
					],
					'keyword'     => [
						'type'        => 'string',
						'description' => 'Style, architectural details, or descriptive words that do not match a formal filter (e.g. modern, sea view, downtown, gated).',
					],
					'locations'   => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Names of cities, neighborhoods, states, or areas explicitly requested by the user. Expand obvious abbreviations (e.g. "NY" -> "New York", "LA" -> "Los Angeles", "MIA" -> "Miami").',
					],
					'explanation' => [
						'type'        => 'string',
						'description' => 'A clear, warm 1-sentence conversational summary of what the user is looking for.',
					],
					'suggestions' => [
						'type'        => 'array',
						'description' => '3 to 4 helpful smart refinement suggestions (e.g. Add Pool, Under $1M, 4+ Beds).',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'label' => [
									'type'        => 'string',
									'description' => 'Short button label (e.g. "Add Pool", "Under $1M", "4+ Beds", "For Rent").',
								],
								'patch' => [
									'type'        => 'object',
									'description' => 'Key-value dictionary of the exact filter parameter to apply when clicked. MUST contain at least one filter key. Examples: {"features": ["swimming-pool"]}, {"max_price": 1000000}, {"bedrooms": 4}, {"status": ["for-rent"]}.',
									'properties'  => [
										'features'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
										'type'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
										'status'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
										'bedrooms'   => [ 'type' => 'integer' ],
										'bathrooms'  => [ 'type' => 'integer' ],
										'max_price'  => [ 'type' => 'number' ],
										'min_price'  => [ 'type' => 'number' ],
										'keyword'    => [ 'type' => 'string' ],
									],
								],
							],
							'required'   => [ 'label', 'patch' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Build system prompt with context and taxonomy hints.
	 *
	 * @return string
	 */
	public static function get_system_prompt() {
		$custom = AI_Search_Settings::get_system_prompt();

		$type_slugs   = self::get_taxonomy_slugs( 'property_type' );
		$status_slugs = self::get_taxonomy_slugs( 'property_status' );
		$city_slugs   = self::get_taxonomy_slugs( 'property_city' );
		$area_slugs   = self::get_taxonomy_slugs( 'property_area' );

		$type_hint   = ! empty( $type_slugs ) ? implode( ', ', array_slice( $type_slugs, 0, 30 ) ) : 'apartment, villa, house, condo';
		$status_hint = ! empty( $status_slugs ) ? implode( ', ', array_slice( $status_slugs, 0, 10 ) ) : 'for-sale, for-rent';
		$loc_samples = array_merge(
			array_slice( $city_slugs, 0, 10 ),
			array_slice( $area_slugs, 0, 15 )
		);
		$loc_hint    = ! empty( $loc_samples ) ? implode( ', ', $loc_samples ) : 'miami, new-york, los-angeles, chicago, manhattan, brooklyn';

		$prompt = "You are the AI Real Estate Search Assistant for a Houzez-powered property website.\n";
		$prompt .= "Your task is to accurately understand the user's natural language home search request and extract structured search parameters by invoking the 'search_properties' tool.\n\n";
		$prompt .= "RULES:\n";
		$prompt .= "1. ALWAYS invoke the 'search_properties' tool.\n";
		$prompt .= "2. Match 'type' to available taxonomy slugs when applicable (available examples: {$type_hint}).\n";
		$prompt .= "3. Match 'status' or 'label' to site taxonomies (e.g., {$status_hint}). 'buy' or 'purchase' -> for-sale; 'rent', 'lease', 'holiday stay', 'vacation', or 'short-term' -> for-rent (or vacation-rental/short-term status if in list); 'auction', 'bid', or 'foreclosure' -> matching auction status or label.\n";
		$prompt .= "4. For locations, place the user's intended city, neighborhood, area, or state in the 'locations' array (examples of site locations: {$loc_hint}). Expand abbreviations (e.g. 'NYC' -> 'New York'). Only include places the user mentioned.\n";
		$prompt .= "5. Extract price bounds in standard numbers (e.g. 'under 1.5M' -> max_price: 1500000, '500k to 800k' -> min_price: 500000, max_price: 800000).\n";
		$prompt .= "6. If the user mentions bedroom or bathroom requirements (e.g. '3 beds', '2+ baths'), extract numeric 'bedrooms' and 'bathrooms'. If square footage, area, or size is requested (e.g. '10,000 sq ft', '500 sqm', 'under 5000 sqft', 'at least 2000 sq ft'), extract numeric 'min_area' or 'max_area'.\n";
		$prompt .= "7. Provide an informative, friendly 1-sentence 'explanation'.\n";
		$prompt .= "8. Provide 3-4 smart refinement 'suggestions'. Each suggestion MUST have a 'label' (e.g. 'Add Pool', 'Under $1M', '4+ Bedrooms', 'For Rent', '10,000+ sq ft') AND a non-empty 'patch' object containing the exact filter change (e.g. label: 'Add Pool' -> patch: {\"features\": [\"swimming-pool\"]}; label: 'Under $1M' -> patch: {\"max_price\": 1000000}; label: '4+ Beds' -> patch: {\"bedrooms\": 4}; label: 'For Rent' -> patch: {\"status\": [\"for-rent\"]}; label: '10,000+ sq ft' -> patch: {\"min_area\": 10000}).\n";
		$prompt .= "9. MULTI-TURN REFINEMENTS & DELETIONS: When [ACTIVE FILTERS BASELINE: ...] is provided, it contains the user's exact current filter state. Any filter (such as a city, neighborhood, type, price, or feature) NOT in this baseline was intentionally removed or deleted by the user. You must apply the refinement ONLY to this baseline. NEVER restore a removed location or filter from earlier conversation turns unless the user explicitly requests it again in the current refinement message.\n";



		if ( ! empty( $custom ) ) {
			$prompt .= "\nADDITIONAL INSTRUCTIONS:\n" . trim( $custom ) . "\n";
		}

		return $prompt;
	}
}
