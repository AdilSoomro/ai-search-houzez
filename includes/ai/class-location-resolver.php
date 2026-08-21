<?php
/**
 * Location Resolver for AI Search
 *
 * Resolves natural language location phrases into Houzez taxonomy terms.
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Location_Resolver {

	const TAXONOMIES = [
		'property_city',
		'property_area',
		'property_state',
		'property_country',
	];

	/**
	 * Cache of all terms across location taxonomies.
	 *
	 * @var array|null
	 */
	private static $terms_cache = null;

	/**
	 * Load and cache location terms.
	 *
	 * @return array
	 */
	public static function get_all_location_terms() {
		if ( null !== self::$terms_cache ) {
			return self::$terms_cache;
		}

		$cache_key = 'ai_search_houzi_all_loc_terms';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			self::$terms_cache = $cached;
			return self::$terms_cache;
		}

		$all_terms = [];
		foreach ( self::TAXONOMIES as $tax ) {
			$terms = get_terms( [
				'taxonomy'   => $tax,
				'hide_empty' => false,
			] );

			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$all_terms[] = [
						'term_id'  => $t->term_id,
						'name'     => $t->name,
						'slug'     => $t->slug,
						'taxonomy' => $tax,
						'clean_name'=> self::normalize_string( $t->name ),
					];
				}
			}
		}

		self::$terms_cache = $all_terms;
		set_transient( $cache_key, $all_terms, HOUR_IN_SECONDS );
		return self::$terms_cache;
	}

	/**
	 * Normalize a string for matching (lowercase, strip extra spaces & punctuation).
	 *
	 * @param string $str
	 * @return string
	 */
	public static function normalize_string( $str ) {
		$str = mb_strtolower( (string) $str, 'UTF-8' );
		$str = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $str );
		$str = preg_replace( '/\s+/', ' ', $str );
		return trim( $str );
	}

	/**
	 * Resolve an array of raw location strings.
	 *
	 * @param array $raw_locations
	 * @return array {
	 *     @type array $resolved   Array of ['input', 'taxonomy', 'slug', 'name']
	 *     @type array $unresolved Array of strings
	 * }
	 */
	public static function resolve_all( array $raw_locations ) {
		$resolved   = [];
		$unresolved = [];

		if ( ! AI_Search_Settings::is_location_resolver_enabled() ) {
			return [
				'resolved'   => $resolved,
				'unresolved' => $raw_locations,
			];
		}

		foreach ( $raw_locations as $raw ) {
			$raw = trim( (string) $raw );
			if ( empty( $raw ) ) {
				continue;
			}

			$match = self::resolve_single( $raw );
			if ( $match ) {
				$resolved[] = $match;
			} else {
				$unresolved[] = $raw;
				AI_Search_Settings::log_location_miss( $raw );
			}
		}

		return [
			'resolved'   => $resolved,
			'unresolved' => $unresolved,
		];
	}

	/**
	 * Resolve a single location string via the 5-step ladder.
	 *
	 * @param string $input
	 * @return array|null
	 */
	public static function resolve_single( $input ) {
		$clean_input = self::normalize_string( $input );
		if ( empty( $clean_input ) ) {
			return null;
		}

		$all_terms = self::get_all_location_terms();

		// 1. Check Admin Alias Table
		$aliases = AI_Search_Settings::get_location_aliases();
		if ( ! empty( $aliases ) && is_array( $aliases ) ) {
			foreach ( $aliases as $alias_group ) {
				$target_tax  = $alias_group['taxonomy'] ?? 'property_city';
				$target_slug = $alias_group['slug'] ?? '';
				$alias_list  = $alias_group['aliases'] ?? [];

				if ( is_string( $alias_list ) ) {
					$alias_list = array_map( 'trim', explode( ',', $alias_list ) );
				}

				foreach ( $alias_list as $a ) {
					if ( self::normalize_string( $a ) === $clean_input ) {
						// Look up name for target slug
						$term_name = $target_slug;
						$term_obj = get_term_by( 'slug', $target_slug, $target_tax );
						if ( $term_obj && ! is_wp_error( $term_obj ) ) {
							$term_name = $term_obj->name;
						}
						return [
							'input'    => $input,
							'taxonomy' => $target_tax,
							'slug'     => $target_slug,
							'name'     => $term_name,
							'method'   => 'alias',
						];
					}
				}
			}
		}

		// 2. Exact Match (Slug or Clean Name)
		$slug_candidate = sanitize_title( $clean_input );
		foreach ( $all_terms as $term ) {
			if ( $term['clean_name'] === $clean_input || $term['slug'] === $slug_candidate ) {
				return [
					'input'    => $input,
					'taxonomy' => $term['taxonomy'],
					'slug'     => $term['slug'],
					'name'     => $term['name'],
					'method'   => 'exact',
				];
			}
		}

		// 3. Token / Substring Match (e.g. "Miami Beach" when term is "Miami")
		foreach ( $all_terms as $term ) {
			if ( ! empty( $term['clean_name'] ) && (
				strpos( $clean_input, $term['clean_name'] ) !== false ||
				strpos( $term['clean_name'], $clean_input ) !== false
			) ) {
				return [
					'input'    => $input,
					'taxonomy' => $term['taxonomy'],
					'slug'     => $term['slug'],
					'name'     => $term['name'],
					'method'   => 'partial',
				];
			}
		}

		// 4. Fuzzy Levenshtein Match (for typos on words >= 4 chars)
		$best_term = null;
		$best_dist = 999;
		$input_len = mb_strlen( $clean_input );

		if ( $input_len >= 4 ) {
			foreach ( $all_terms as $term ) {
				$dist = levenshtein( $clean_input, $term['clean_name'] );
				// Allow distance 1 for 4-5 chars, distance 2 for 6+ chars
				$max_allowed = ( $input_len <= 5 ) ? 1 : 2;

				if ( $dist <= $max_allowed && $dist < $best_dist ) {
					$best_dist = $dist;
					$best_term = $term;
				}
			}

			if ( $best_term ) {
				return [
					'input'    => $input,
					'taxonomy' => $best_term['taxonomy'],
					'slug'     => $best_term['slug'],
					'name'     => $best_term['name'],
					'method'   => 'fuzzy',
				];
			}
		}

		return null;
	}
}
