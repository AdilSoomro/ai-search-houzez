<?php
/**
 * AJAX Controller for AI Search
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/ai/class-ai-gateway.php';
require_once __DIR__ . '/ai/class-ai-rate-limiter.php';
require_once __DIR__ . '/class-ai-search-page-installer.php';

class AI_Search_Ajax {

	/**
	 * Register all AJAX endpoints.
	 */
	public static function init() {
		// Public Search Endpoints
		add_action( 'wp_ajax_ai_search_houzi_process_query', [ __CLASS__, 'process_query' ] );
		add_action( 'wp_ajax_nopriv_ai_search_houzi_process_query', [ __CLASS__, 'process_query' ] );

		add_action( 'wp_ajax_ai_search_houzi_get_properties', [ __CLASS__, 'get_properties' ] );
		add_action( 'wp_ajax_nopriv_ai_search_houzi_get_properties', [ __CLASS__, 'get_properties' ] );

		add_action( 'wp_ajax_ai_search_houzi_get_taxonomies', [ __CLASS__, 'get_taxonomies' ] );
		add_action( 'wp_ajax_nopriv_ai_search_houzi_get_taxonomies', [ __CLASS__, 'get_taxonomies' ] );

		// Admin-only Endpoints
		add_action( 'wp_ajax_ai_search_houzi_test_connection', [ __CLASS__, 'test_connection' ] );
		add_action( 'wp_ajax_ai_search_houzi_save_settings', [ __CLASS__, 'save_settings' ] );
		add_action( 'wp_ajax_ai_search_houzi_install_homepage', [ __CLASS__, 'install_homepage' ] );
		add_action( 'wp_ajax_ai_search_houzi_remove_miss', [ __CLASS__, 'remove_location_miss' ] );
		add_action( 'wp_ajax_ai_search_houzi_activate_license', [ __CLASS__, 'activate_license' ] );
		add_action( 'wp_ajax_ai_search_houzi_deactivate_license', [ __CLASS__, 'deactivate_license' ] );
	}


	/**
	 * AJAX endpoint: Process a natural language query through AI and return results.
	 */
	public static function process_query() {
		check_ajax_referer( 'ai_search_houzi_nonce', 'security' );

		if ( ! isset( $_SERVER['SERVER_PORT'] ) ) {
			$_SERVER['SERVER_PORT'] = is_ssl() ? 443 : 80;
		}

		// Rate limit verification
		$rate_check = AI_Search_Rate_Limiter::check_and_increment();
		if ( ! $rate_check['allowed'] ) {
			wp_send_json_error( [
				'code'        => 'rate_limit_exceeded',
				'message'     => $rate_check['message'],
				'retry_after' => $rate_check['retry_after'],
			], 429 );
		}

		$query           = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		$conversation_id = sanitize_key( wp_unslash( $_POST['conversation_id'] ?? '' ) );
		$posts_per_page  = isset( $_POST['posts_per_page'] ) ? (int) $_POST['posts_per_page'] : 4;
		$paged           = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		$current_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? self::sanitize_filter_array( $_POST['filters'] )
			: [];


		if ( empty( $query ) ) {
			wp_send_json_error( [ 'message' => __( 'Search query cannot be empty.', 'ai-search' ) ] );
		}

		// First-turn caching check (5-minute TTL)
		$cache_key = '';
		if ( empty( $conversation_id ) && empty( $current_filters ) && 1 === $paged ) {
			$cache_key = 'ai_search_houzi_q_' . md5( mb_strtolower( trim( $query ) ) );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				wp_send_json_success( $cached );
			}
		}

		try {
			$ai_result = AI_Search_Gateway::process_search( $query, $conversation_id, $current_filters );
			$filters   = $ai_result['filters'];

			$query_res  = self::query_properties( $filters, $posts_per_page, $paged );
			$cards_html = self::render_cards( $query_res['query'] );
			$view_all   = self::build_houzez_search_url( $filters );
			$term_names = self::get_term_display_names( $filters );

			$response_data = [
				'filters'         => $filters,
				'term_names'      => $term_names,
				'explanation'     => $ai_result['explanation'],
				'location'        => $ai_result['location'],
				'suggestions'     => $ai_result['suggestions'],
				'cards_html'      => $cards_html,
				'total_count'     => $query_res['total'],
				'paged'           => $query_res['paged'],
				'max_pages'       => $query_res['max_pages'],
				'has_prev'        => $query_res['has_prev'],
				'has_next'        => $query_res['has_next'],
				'view_all_url'    => $view_all,
				'conversation_id' => $ai_result['conversation_id'],
			];

			if ( ! empty( $cache_key ) ) {
				set_transient( $cache_key, $response_data, 5 * MINUTE_IN_SECONDS );
			}

			wp_send_json_success( $response_data );

		} catch ( Exception $e ) {
			wp_send_json_error( [
				'code'    => 'ai_error',
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * AJAX endpoint: Get properties matching structured filters (without re-calling LLM).
	 */
	public static function get_properties() {
		check_ajax_referer( 'ai_search_houzi_nonce', 'security' );

		if ( ! isset( $_SERVER['SERVER_PORT'] ) ) {
			$_SERVER['SERVER_PORT'] = is_ssl() ? 443 : 80;
		}

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? self::sanitize_filter_array( $_POST['filters'] )
			: [];

		$posts_per_page = isset( $_POST['posts_per_page'] ) ? (int) $_POST['posts_per_page'] : 4;
		$paged          = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;

		$query_res    = self::query_properties( $filters, $posts_per_page, $paged );
		$cards_html   = self::render_cards( $query_res['query'] );
		$view_all     = self::build_houzez_search_url( $filters );
		$term_names   = self::get_term_display_names( $filters );
		$explanation  = self::build_filters_explanation( $filters );

		wp_send_json_success( [
			'filters'      => $filters,
			'term_names'   => $term_names,
			'explanation'  => $explanation,
			'cards_html'   => $cards_html,
			'total_count'  => $query_res['total'],
			'paged'        => $query_res['paged'],
			'max_pages'    => $query_res['max_pages'],
			'has_prev'     => $query_res['has_prev'],
			'has_next'     => $query_res['has_next'],
			'view_all_url' => $view_all,
		] );
	}



	/**
	 * Build term display names mapping (slug => Term Name) for active filters.
	 *
	 * @param array $filters
	 * @return array
	 */
	public static function get_term_display_names( array $filters ) {
		$names = [];
		$tax_map = [
			'type'             => 'property_type',
			'property_type'    => 'property_type',
			'status'           => 'property_status',
			'property_status'  => 'property_status',
			'features'         => 'property_feature',
			'property_feature' => 'property_feature',
			'property_city'    => 'property_city',
			'city'             => 'property_city',
			'property_area'    => 'property_area',
			'area'             => 'property_area',
			'property_state'   => 'property_state',
			'state'            => 'property_state',
			'property_country' => 'property_country',
			'country'          => 'property_country',
			'label'            => 'property_label',
			'property_label'   => 'property_label',
		];

		foreach ( $tax_map as $key => $tax ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$slugs = (array) $filters[ $key ];
				foreach ( $slugs as $slug ) {
					if ( is_string( $slug ) && ! empty( $slug ) && ! isset( $names[ $slug ] ) ) {
						$term = get_term_by( 'slug', $slug, $tax );
						if ( $term && ! is_wp_error( $term ) ) {
							$names[ $slug ] = $term->name;
						}
					}
				}
			}
		}

		return $names;
	}


	/**
	 * AJAX endpoint: Fetch terms & field options for tag editor popover.
	 */
	public static function get_taxonomies() {
		check_ajax_referer( 'ai_search_houzi_nonce', 'security' );

		$taxonomies = [
			'type'             => 'property_type',
			'property_type'    => 'property_type',
			'status'           => 'property_status',
			'property_status'  => 'property_status',
			'property_city'    => 'property_city',
			'city'             => 'property_city',
			'property_area'    => 'property_area',
			'area'             => 'property_area',
			'property_state'   => 'property_state',
			'state'            => 'property_state',
			'property_country' => 'property_country',
			'country'          => 'property_country',
			'features'         => 'property_feature',
			'property_feature' => 'property_feature',
			'label'            => 'property_label',
			'property_label'   => 'property_label',
		];

		$labels = [
			'type'             => __( 'Property Type', 'ai-search' ),
			'property_type'    => __( 'Property Type', 'ai-search' ),
			'status'           => __( 'Property Status', 'ai-search' ),
			'property_status'  => __( 'Property Status', 'ai-search' ),
			'property_city'    => __( 'City', 'ai-search' ),
			'city'             => __( 'City', 'ai-search' ),
			'property_area'    => __( 'Area / Neighborhood', 'ai-search' ),
			'area'             => __( 'Area / Neighborhood', 'ai-search' ),
			'property_state'   => __( 'State', 'ai-search' ),
			'state'            => __( 'State', 'ai-search' ),
			'property_country' => __( 'Country', 'ai-search' ),
			'country'          => __( 'Country', 'ai-search' ),
			'features'         => __( 'Features & Amenities', 'ai-search' ),
			'property_feature' => __( 'Features & Amenities', 'ai-search' ),
			'label'            => __( 'Label', 'ai-search' ),
			'property_label'   => __( 'Label', 'ai-search' ),
		];

		$data = [];
		$cached_terms = [];

		foreach ( $taxonomies as $filter_key => $tax_name ) {
			if ( ! isset( $cached_terms[ $tax_name ] ) ) {
				$terms = get_terms( [
					'taxonomy'   => $tax_name,
					'hide_empty' => false,
				] );

				$term_list = [];
				if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$term_list[] = [
							'id'   => $t->term_id,
							'name' => $t->name,
							'slug' => $t->slug,
						];
					}
				}
				$cached_terms[ $tax_name ] = $term_list;
			}

			$data[ $filter_key ] = [
				'label' => $labels[ $filter_key ] ?? $tax_name,
				'terms' => $cached_terms[ $tax_name ],
			];
		}

		// Houzez Bedrooms & Bathrooms Lists (matching Houzez theme options)
		$beds_array = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ];
		if ( function_exists( 'houzez_option' ) ) {
			$adv_beds = houzez_option( 'adv_beds_list' );
			if ( ! empty( $adv_beds ) ) {
				$beds_custom = array_map( 'trim', explode( ',', $adv_beds ) );
				if ( ! empty( $beds_custom ) ) {
					$beds_array = $beds_custom;
				}
			}
		}

		$baths_array = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ];
		if ( function_exists( 'houzez_option' ) ) {
			$adv_baths = houzez_option( 'adv_baths_list' );
			if ( ! empty( $adv_baths ) ) {
				$baths_custom = array_map( 'trim', explode( ',', $adv_baths ) );
				if ( ! empty( $baths_custom ) ) {
					$baths_array = $baths_custom;
				}
			}
		}

		$data['bedrooms'] = [
			'label'   => __( 'Bedrooms', 'ai-search' ),
			'options' => $beds_array,
		];

		$data['bathrooms'] = [
			'label'   => __( 'Bathrooms', 'ai-search' ),
			'options' => $baths_array,
		];

		wp_send_json_success( $data );
	}


	/**
	 * Admin AJAX: Test API Connection.
	 */
	public static function test_connection() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-search' ) ] );
		}

		$provider_slug = sanitize_text_field( $_POST['provider'] ?? 'openai' );
		$api_key       = sanitize_text_field( $_POST['api_key'] ?? '' );
		$model         = sanitize_text_field( $_POST['model'] ?? '' );

		// If key was not typed in the form, use saved key
		if ( empty( $api_key ) ) {
			$api_key = AI_Search_Settings::get_api_key();
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter an API Key to test.', 'ai-search' ) ] );
		}

		try {
			$provider = AI_Search_Settings::make_provider( $provider_slug, $api_key );
			$result   = $provider->test_connection( $api_key, $model );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Admin AJAX: Save Settings.
	 */
	public static function save_settings() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-search' ) ] );
		}

		$enabled          = ! empty( $_POST['ai_search_houzi_enabled'] ) ? '1' : '0';
		$provider         = sanitize_text_field( $_POST['ai_search_houzi_provider'] ?? 'openai' );
		$api_key          = sanitize_text_field( $_POST['ai_search_houzi_api_key'] ?? '' );
		$model            = sanitize_text_field( $_POST['ai_search_houzi_model'] ?? '' );
		$lite_model       = sanitize_text_field( $_POST['ai_search_houzi_lite_model'] ?? '' );
		$system_prompt    = sanitize_textarea_field( wp_unslash( $_POST['ai_search_houzi_system_prompt'] ?? '' ) );
		$rate_user        = (int) ( $_POST['ai_search_houzi_rate_per_user_hour'] ?? 30 );
		$rate_site        = (int) ( $_POST['ai_search_houzi_rate_per_site_day'] ?? 500 );
		$resolver_enabled = ! empty( $_POST['ai_search_houzi_location_resolver_enabled'] ) ? '1' : '0';

		update_option( AI_Search_Settings::OPTION_ENABLED, $enabled );
		update_option( AI_Search_Settings::OPTION_PROVIDER, $provider );

		// Only overwrite key if user typed a new one (not empty)
		if ( ! empty( $api_key ) ) {
			update_option( AI_Search_Settings::OPTION_API_KEY, $api_key );
		}

		update_option( AI_Search_Settings::OPTION_MODEL, $model );
		update_option( AI_Search_Settings::OPTION_LITE_MODEL, $lite_model );
		update_option( AI_Search_Settings::OPTION_SYSTEM_PROMPT, $system_prompt );
		update_option( AI_Search_Settings::OPTION_RATE_USER_HOUR, max( 1, $rate_user ) );
		update_option( AI_Search_Settings::OPTION_RATE_SITE_DAY, max( 1, $rate_site ) );
		update_option( AI_Search_Settings::OPTION_LOCATION_RESOLVER_ENABLED, $resolver_enabled );

		// Save Location Aliases if passed
		if ( isset( $_POST['ai_search_houzi_location_aliases'] ) ) {
			$raw_aliases = json_decode( wp_unslash( $_POST['ai_search_houzi_location_aliases'] ), true );
			if ( is_array( $raw_aliases ) ) {
				$clean_aliases = [];
				foreach ( $raw_aliases as $entry ) {
					$clean_aliases[] = [
						'taxonomy' => sanitize_key( $entry['taxonomy'] ?? 'property_city' ),
						'slug'     => sanitize_title( $entry['slug'] ?? '' ),
						'aliases'  => is_array( $entry['aliases'] )
							? array_map( 'sanitize_text_field', $entry['aliases'] )
							: array_map( 'trim', explode( ',', sanitize_text_field( $entry['aliases'] ) ) ),
					];
				}
				AI_Search_Settings::set_location_aliases( $clean_aliases );
			}
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved successfully.', 'ai-search' ) ] );
	}

	/**
	 * Admin AJAX: Remove Location Miss.
	 */
	public static function remove_location_miss() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-search' ) ] );
		}

		$phrase = sanitize_text_field( $_POST['phrase'] ?? '' );
		if ( ! empty( $phrase ) ) {
			AI_Search_Settings::remove_location_miss( $phrase );
		}

		wp_send_json_success();
	}

	/**
	 * Admin AJAX: Install AI Search Homepage.
	 */
	public static function install_homepage() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-search' ) ] );
		}

		$overwrite         = ! empty( $_POST['overwrite'] ) && ( 'true' === $_POST['overwrite'] || true === $_POST['overwrite'] || '1' === $_POST['overwrite'] );
		$preserve_sections = ! isset( $_POST['preserve_sections'] ) || ( 'true' === $_POST['preserve_sections'] || true === $_POST['preserve_sections'] || '1' === $_POST['preserve_sections'] );

		$result = AI_Search_Page_Installer::install_homepage( $overwrite, $preserve_sections );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}


	/**
	 * Sanitize filter array from client request.
	 *
	 * @param array $raw
	 * @return array
	 */
	public static function sanitize_filter_array( array $raw ) {
		$clean = [];

		$array_keys = [ 'type', 'status', 'label', 'features', 'property_city', 'property_area', 'property_state', 'property_country' ];
		foreach ( $array_keys as $key ) {
			if ( ! empty( $raw[ $key ] ) ) {
				$val = is_array( $raw[ $key ] ) ? $raw[ $key ] : [ $raw[ $key ] ];
				$clean[ $key ] = array_map( 'sanitize_title', $val );
			}
		}

		$int_keys = [ 'bedrooms', 'bathrooms' ];
		foreach ( $int_keys as $key ) {
			if ( isset( $raw[ $key ] ) && '' !== $raw[ $key ] ) {
				$clean[ $key ] = (int) $raw[ $key ];
			}
		}

		$float_keys = [ 'min_price', 'max_price', 'min_area', 'max_area' ];
		foreach ( $float_keys as $key ) {
			if ( isset( $raw[ $key ] ) && '' !== $raw[ $key ] ) {
				$clean[ $key ] = (float) $raw[ $key ];
			}
		}

		if ( ! empty( $raw['keyword'] ) ) {
			$clean['keyword'] = sanitize_text_field( $raw['keyword'] );
		}

		return $clean;
	}

	/**
	 * Run WP_Query based on structured filters with pagination support.
	 *
	 * @param array $filters
	 * @param int   $posts_per_page
	 * @param int   $paged
	 * @return array
	 */
	public static function query_properties( array $filters, $posts_per_page = 4, $paged = 1 ) {
		$paged = max( 1, (int) $paged );
		$args  = [
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
		];

		$tax_query  = [];
		$meta_query = [];

		// Taxonomies mapping
		$tax_map = [
			'type'             => 'property_type',
			'status'           => 'property_status',
			'label'            => 'property_label',
			'features'         => 'property_feature',
			'property_city'    => 'property_city',
			'property_area'    => 'property_area',
			'property_state'   => 'property_state',
			'property_country' => 'property_country',
		];

		foreach ( $tax_map as $filter_key => $tax_name ) {
			if ( ! empty( $filters[ $filter_key ] ) ) {
				$slugs = (array) $filters[ $filter_key ];
				$tax_query[] = [
					'taxonomy' => $tax_name,
					'field'    => 'slug',
					'terms'    => $slugs,
				];
			}
		}

		// Meta filters mapping
		if ( ! empty( $filters['bedrooms'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_bedrooms',
				'value'   => $filters['bedrooms'],
				'type'    => 'NUMERIC',
				'compare' => '>=',
			];
		}

		if ( ! empty( $filters['bathrooms'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_bathrooms',
				'value'   => $filters['bathrooms'],
				'type'    => 'NUMERIC',
				'compare' => '>=',
			];
		}

		if ( ! empty( $filters['min_price'] ) && ! empty( $filters['max_price'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_price',
				'value'   => [ $filters['min_price'], $filters['max_price'] ],
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			];
		} elseif ( ! empty( $filters['min_price'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_price',
				'value'   => $filters['min_price'],
				'type'    => 'NUMERIC',
				'compare' => '>=',
			];
		} elseif ( ! empty( $filters['max_price'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_price',
				'value'   => $filters['max_price'],
				'type'    => 'NUMERIC',
				'compare' => '<=',
			];
		}

		if ( ! empty( $filters['min_area'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_size',
				'value'   => $filters['min_area'],
				'type'    => 'NUMERIC',
				'compare' => '>=',
			];
		}

		if ( ! empty( $filters['max_area'] ) ) {
			$meta_query[] = [
				'key'     => 'fave_property_size',
				'value'   => $filters['max_area'],
				'type'    => 'NUMERIC',
				'compare' => '<=',
			];
		}

		if ( ! empty( $filters['keyword'] ) ) {
			$args['s'] = $filters['keyword'];
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$query     = new WP_Query( $args );
		$max_pages = (int) $query->max_num_pages;

		return [
			'query'     => $query,
			'total'     => (int) $query->found_posts,
			'paged'     => $paged,
			'max_pages' => $max_pages,
			'has_prev'  => $paged > 1,
			'has_next'  => $paged < $max_pages,
		];
	}


	/**
	 * Render preview cards HTML using Houzez native listing item template.
	 *
	 * @param WP_Query $query
	 * @return string
	 */
	public static function render_cards( WP_Query $query ) {

		ob_start();
		if ( $query->have_posts() ) {
			$item_version = 'v1';
			if ( function_exists( 'houzez_option' ) ) {
				$layout = houzez_option( 'search_result_posts_layout', 'grid-view-v1' );
				if ( preg_match( '/(v\d+)/', $layout, $matches ) ) {
					$item_version = $matches[1];
				}
			}

			while ( $query->have_posts() ) {
				$query->the_post();

				$template_found = locate_template( 'template-parts/listing/item-' . $item_version . '.php' );
				if ( ! $template_found ) {
					$template_found = locate_template( 'template-parts/listing/item-v1.php' );
					$item_version = 'v1';
				}

				if ( $template_found ) {
					get_template_part( 'template-parts/listing/item', $item_version );
				} else {
					include dirname( __DIR__ ) . '/public/templates/property-card-preview.php';
				}
			}
			wp_reset_postdata();
		}
		return ob_get_clean();
	}


	/**
	 * Build Houzez Search Results URL with properly formatted GET parameters.
	 *
	 * @param array $filters
	 * @return string
	 */
	public static function build_houzez_search_url( array $filters ) {
		// Find Houzez Search Results Page
		$search_pages = get_posts( [
			'post_type'   => 'page',
			'meta_key'    => '_wp_page_template',
			'meta_value'  => 'template/template-search.php',
			'posts_per_page' => 1,
		] );

		$base_url = home_url( '/search-results/' );
		if ( ! empty( $search_pages ) ) {
			$base_url = get_permalink( $search_pages[0]->ID );
		}

		$get_params = [];

		if ( ! empty( $filters['type'] ) ) {
			$get_params['type'] = (array) $filters['type'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$get_params['status'] = (array) $filters['status'];
		}
		if ( ! empty( $filters['label'] ) ) {
			$get_params['label'] = (array) $filters['label'];
		}
		if ( ! empty( $filters['features'] ) ) {
			$get_params['feature'] = (array) $filters['features'];
		}

		// Location parameters mapped to Houzez search names
		if ( ! empty( $filters['property_city'] ) ) {
			$city = is_array( $filters['property_city'] ) ? $filters['property_city'][0] : $filters['property_city'];
			$get_params['location'] = $city;
		}
		if ( ! empty( $filters['property_area'] ) ) {
			$area = is_array( $filters['property_area'] ) ? $filters['property_area'][0] : $filters['property_area'];
			$get_params['area'] = $area;
		}
		if ( ! empty( $filters['property_state'] ) ) {
			$state = is_array( $filters['property_state'] ) ? $filters['property_state'][0] : $filters['property_state'];
			$get_params['state'] = $state;
		}
		if ( ! empty( $filters['property_country'] ) ) {
			$country = is_array( $filters['property_country'] ) ? $filters['property_country'][0] : $filters['property_country'];
			$get_params['country'] = $country;
		}

		if ( ! empty( $filters['bedrooms'] ) ) {
			$get_params['bedrooms'] = $filters['bedrooms'];
			$get_params['bedrooms-operator'] = '>=';
		}
		if ( ! empty( $filters['bathrooms'] ) ) {
			$get_params['bathrooms'] = $filters['bathrooms'];
			$get_params['bathrooms-operator'] = '>=';
		}

		if ( ! empty( $filters['min_price'] ) ) {
			$get_params['min-price'] = $filters['min_price'];
		}
		if ( ! empty( $filters['max_price'] ) ) {
			$get_params['max-price'] = $filters['max_price'];
		}
		if ( ! empty( $filters['min_area'] ) ) {
			$get_params['min-area'] = $filters['min_area'];
		}
		if ( ! empty( $filters['max_area'] ) ) {
			$get_params['max-area'] = $filters['max_area'];
		}
		if ( ! empty( $filters['keyword'] ) ) {
			$get_params['keyword'] = $filters['keyword'];
		}

		return add_query_arg( $get_params, $base_url );
	}

	/**
	 * Generate context-aware sample search prompts based on live site data.
	 *
	 * Queries published properties and active taxonomies (city, area, type, status, features, labels)
	 * on whichever WordPress site the plugin is activated, producing verified, real search prompts.
	 * Automatically adapts for residential, commercial/warehouse, vacation rentals, hotels, or auctions.
	 *
	 * @param int $limit Number of prompts to generate (default 3).
	 * @return array List of string prompts.
	 */
	public static function get_live_suggested_prompts( $limit = 3 ) {
		$prompts = [];

		// 1. Query active taxonomies with published posts
		$cities   = get_terms( [ 'taxonomy' => 'property_city', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ] );
		$areas    = get_terms( [ 'taxonomy' => 'property_area', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ] );
		$types    = get_terms( [ 'taxonomy' => 'property_type', 'hide_empty' => true, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC' ] );
		$statuses = get_terms( [ 'taxonomy' => 'property_status', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ] );
		$labels   = get_terms( [ 'taxonomy' => 'property_label', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ] );
		$features = get_terms( [ 'taxonomy' => 'property_feature', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ] );

		$city_names    = ( ! empty( $cities ) && ! is_wp_error( $cities ) ) ? wp_list_pluck( $cities, 'name' ) : [];
		$area_names    = ( ! empty( $areas ) && ! is_wp_error( $areas ) ) ? wp_list_pluck( $areas, 'name' ) : [];
		$type_names    = ( ! empty( $types ) && ! is_wp_error( $types ) ) ? wp_list_pluck( $types, 'name' ) : [];
		$status_names  = ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) ? wp_list_pluck( $statuses, 'name' ) : [];
		$label_names   = ( ! empty( $labels ) && ! is_wp_error( $labels ) ) ? wp_list_pluck( $labels, 'name' ) : [];
		$feature_names = ( ! empty( $features ) && ! is_wp_error( $features ) ) ? wp_list_pluck( $features, 'name' ) : [];

		// Combine locations with areas prioritized first, then cities
		$locations = array_values( array_unique( array_merge( $area_names, $city_names ) ) );

		// Helper: Classification categories
		$is_commercial_fn = function( $name ) {
			$keywords = [
				'warehouse', 'godown', 'storage', 'industrial', 'office', 'shop',
				'retail', 'commercial', 'land', 'plot', 'shed', 'facility',
				'depot', 'factory', 'building', 'lot', 'space', 'coworking', 'logistics'
			];
			$lower = strtolower( (string) $name );
			foreach ( $keywords as $kw ) {
				if ( strpos( $lower, $kw ) !== false ) {
					return true;
				}
			}
			return false;
		};

		$is_vacation_fn = function( $name ) {
			$keywords = [
				'vacation', 'holiday', 'short-term', 'short-rent', 'short stay',
				'cabin', 'chalet', 'cottage', 'resort', 'beachfront', 'daily',
				'nightly', 'bnb', 'bed and breakfast', 'lodge'
			];
			$lower = strtolower( (string) $name );
			foreach ( $keywords as $kw ) {
				if ( strpos( $lower, $kw ) !== false ) {
					return true;
				}
			}
			return false;
		};

		$is_hotel_fn = function( $name ) {
			$keywords = [
				'hotel', 'boutique-hotel', 'motel', 'resort', 'inn', 'suites',
				'serviced apartment', 'hostel', 'guest house', 'guesthouse'
			];
			$lower = strtolower( (string) $name );
			foreach ( $keywords as $kw ) {
				if ( strpos( $lower, $kw ) !== false ) {
					return true;
				}
			}
			return false;
		};

		$is_auction_fn = function( $name ) {
			$keywords = [
				'auction', 'foreclosure', 'foreclosed', 'bank-owned', 'distressed',
				'bid', 'short sale', 'reduced'
			];
			$lower = strtolower( (string) $name );
			foreach ( $keywords as $kw ) {
				if ( strpos( $lower, $kw ) !== false ) {
					return true;
				}
			}
			return false;
		};

		// 2. Fetch a few real published properties to check authentic property combinations
		$properties = get_posts( [
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		if ( ! empty( $properties ) ) {
			foreach ( $properties as $prop ) {
				$pid        = $prop->ID;
				$p_types    = wp_get_post_terms( $pid, 'property_type' );
				$p_status   = wp_get_post_terms( $pid, 'property_status' );
				$p_labels   = wp_get_post_terms( $pid, 'property_label' );
				$p_cities   = wp_get_post_terms( $pid, 'property_city' );
				$p_areas    = wp_get_post_terms( $pid, 'property_area' );
				$p_features = wp_get_post_terms( $pid, 'property_feature' );

				$beds  = get_post_meta( $pid, 'fave_property_bedrooms', true );
				$price = get_post_meta( $pid, 'fave_property_price', true );
				$size  = get_post_meta( $pid, 'fave_property_size', true );

				$type_name  = ! empty( $p_types ) ? $p_types[0]->name : '';
				$stat_name  = ! empty( $p_status ) ? $p_status[0]->name : '';
				$label_name = ! empty( $p_labels ) ? $p_labels[0]->name : '';
				$loc_name   = ! empty( $p_areas ) ? $p_areas[0]->name : ( ! empty( $p_cities ) ? $p_cities[0]->name : '' );
				$feat_name  = ! empty( $p_features ) ? $p_features[0]->name : '';

				$price_num       = floatval( preg_replace( '/[^0-9.]/', '', (string) $price ) );
				$formatted_price = '';
				if ( $price_num > 0 ) {
					if ( $price_num >= 1000000 ) {
						$formatted_price = '< $' . round( $price_num / 1000000, 1 ) . 'M';
					} elseif ( $price_num >= 1000 ) {
						$formatted_price = 'under $' . round( $price_num / 1000 ) . 'k';
					} else {
						$formatted_price = 'under $' . number_format( $price_num );
					}
				}

				$size_num       = floatval( preg_replace( '/[^0-9.]/', '', (string) $size ) );
				$formatted_size = '';
				if ( $size_num > 0 ) {
					$formatted_size = number_format( $size_num ) . ' sq ft';
				}

				if ( $type_name && $loc_name ) {
					$candidate     = '';
					$type_lower    = strtolower( $type_name );
					$stat_lower    = strtolower( $stat_name );
					$is_comm       = $is_commercial_fn( $type_name );
					$is_vacation   = $is_vacation_fn( $type_name ) || $is_vacation_fn( $stat_name );
					$is_hotel      = $is_hotel_fn( $type_name );
					$is_auction    = $is_auction_fn( $stat_name ) || $is_auction_fn( $label_name );

					if ( $is_auction ) {
						// Auction / Foreclosure Patterns
						if ( $formatted_price ) {
							$candidate = ucfirst( $type_lower ) . ' auction in ' . $loc_name . ' with starting bid ' . $formatted_price;
						} else {
							$candidate = ucfirst( $type_lower ) . ' for auction in ' . $loc_name;
						}
					} elseif ( $is_hotel ) {
						// Hotel & Hospitality Patterns
						if ( $feat_name ) {
							$candidate = 'Luxury ' . $type_lower . ' in ' . $loc_name . ' with ' . strtolower( $feat_name );
						} else {
							$candidate = 'Top-rated ' . $type_lower . ' in ' . $loc_name;
						}
					} elseif ( $is_vacation ) {
						// Vacation & Short-Term Rental Patterns
						if ( $feat_name ) {
							$candidate = ucfirst( $type_lower ) . ' in ' . $loc_name . ' with ' . strtolower( $feat_name );
						} elseif ( ! empty( $beds ) && intval( $beds ) > 0 ) {
							$candidate = intval( $beds ) . '-bedroom vacation ' . $type_lower . ' in ' . $loc_name;
						} else {
							$candidate = 'Holiday ' . $type_lower . ' for rent in ' . $loc_name;
						}
					} elseif ( $is_comm ) {
						// Commercial / Warehouse / Storage Patterns
						if ( $formatted_size && $stat_name ) {
							$candidate = $formatted_size . ' ' . $type_lower . ' ' . $stat_lower . ' in ' . $loc_name;
						} elseif ( $feat_name ) {
							$candidate = ucfirst( $type_lower ) . ' in ' . $loc_name . ' with ' . strtolower( $feat_name );
						} elseif ( $formatted_price && $stat_name ) {
							$candidate = ucfirst( $type_lower ) . ' ' . $stat_lower . ' in ' . $loc_name . ' ' . $formatted_price;
						} elseif ( $stat_name ) {
							$candidate = ucfirst( $type_lower ) . ' ' . $stat_lower . ' in ' . $loc_name;
						}
					} else {
						// Residential Patterns (Bedrooms / Luxury)
						if ( ! empty( $beds ) && intval( $beds ) > 0 && $stat_name ) {
							$candidate = intval( $beds ) . '-bed ' . $type_lower . ' ' . $stat_lower . ' in ' . $loc_name;
						} elseif ( $feat_name && $formatted_price ) {
							$candidate = ucfirst( $type_lower ) . ' in ' . $loc_name . ' with ' . strtolower( $feat_name ) . ' ' . $formatted_price;
						} elseif ( $feat_name ) {
							$candidate = 'Modern ' . $type_lower . ' in ' . $loc_name . ' with ' . strtolower( $feat_name );
						} elseif ( $stat_name ) {
							$candidate = ucfirst( $type_lower ) . ' ' . $stat_lower . ' in ' . $loc_name;
						}
					}

					if ( $candidate && ! in_array( $candidate, $prompts, true ) ) {
						$prompts[] = $candidate;
					}
				}

				if ( count( $prompts ) >= $limit ) {
					break;
				}
			}
		}

		// 3. Fallback: Synthesize from active taxonomy terms if properties loop didn't yield enough
		if ( count( $prompts ) < $limit && ! empty( $type_names ) && ! empty( $locations ) ) {
			$l_count = count( $locations );
			$f_count = count( $feature_names );
			$s_count = count( $status_names );

			foreach ( $type_names as $idx => $t_name ) {
				if ( count( $prompts ) >= $limit ) {
					break;
				}

				$loc_item  = $locations[ $idx % $l_count ];
				$feat_item = $f_count > 0 ? $feature_names[ $idx % $f_count ] : '';
				$stat_item = $s_count > 0 ? strtolower( $status_names[ $idx % $s_count ] ) : 'for rent';

				$is_comm     = $is_commercial_fn( $t_name );
				$is_vacation = $is_vacation_fn( $t_name ) || ( $s_count > 0 && $is_vacation_fn( $status_names[ $idx % $s_count ] ) );
				$is_hotel    = $is_hotel_fn( $t_name );
				$is_auction  = $s_count > 0 && $is_auction_fn( $status_names[ $idx % $s_count ] );

				if ( $is_auction ) {
					$p = ucfirst( strtolower( $t_name ) ) . ' for auction in ' . $loc_item;
				} elseif ( $is_hotel ) {
					$p = $feat_item ? 'Luxury hotel in ' . $loc_item . ' with ' . strtolower( $feat_item ) : 'Boutique hotel in ' . $loc_item;
				} elseif ( $is_vacation ) {
					$p = $feat_item ? 'Vacation rental in ' . $loc_item . ' with ' . strtolower( $feat_item ) : 'Holiday home for rent in ' . $loc_item;
				} elseif ( $is_comm ) {
					$p = $feat_item ? ucfirst( strtolower( $t_name ) ) . ' in ' . $loc_item . ' with ' . strtolower( $feat_item ) : ucfirst( strtolower( $t_name ) ) . ' ' . $stat_item . ' in ' . $loc_item;
				} else {
					if ( $idx === 0 && $feat_item ) {
						$p = 'Luxury ' . strtolower( $t_name ) . ' in ' . $loc_item . ' with ' . strtolower( $feat_item );
					} else {
						$p = 'Modern 3-bed ' . strtolower( $t_name ) . ' ' . $stat_item . ' in ' . $loc_item;
					}
				}

				if ( ! in_array( $p, $prompts, true ) ) {
					$prompts[] = $p;
				}
			}
		}

		// 4. Safe Generic Fallbacks if database is completely empty
		$has_comm_tax     = false;
		$has_vacation_tax = false;
		$has_hotel_tax    = false;
		$has_auction_tax  = false;

		if ( ! empty( $type_names ) ) {
			foreach ( $type_names as $tn ) {
				if ( $is_commercial_fn( $tn ) ) $has_comm_tax = true;
				if ( $is_vacation_fn( $tn ) )   $has_vacation_tax = true;
				if ( $is_hotel_fn( $tn ) )      $has_hotel_tax = true;
			}
		}
		if ( ! empty( $status_names ) ) {
			foreach ( $status_names as $sn ) {
				if ( $is_auction_fn( $sn ) )    $has_auction_tax = true;
				if ( $is_vacation_fn( $sn ) )   $has_vacation_tax = true;
			}
		}

		if ( $has_auction_tax ) {
			$generic_defaults = [
				__( 'Property for auction with starting bid < $500k', 'ai-search' ),
				__( 'Foreclosed home for auction', 'ai-search' ),
				__( 'Bank-owned commercial property for sale', 'ai-search' ),
			];
		} elseif ( $has_hotel_tax ) {
			$generic_defaults = [
				__( 'Luxury boutique hotel with pool and spa', 'ai-search' ),
				__( 'Beach resort with ocean view', 'ai-search' ),
				__( 'Serviced hotel suites for stay', 'ai-search' ),
			];
		} elseif ( $has_vacation_tax ) {
			$generic_defaults = [
				__( 'Beachfront vacation villa with private pool', 'ai-search' ),
				__( 'Cozy mountain cabin with hot tub for rent', 'ai-search' ),
				__( 'Short-term luxury apartment for holiday stay', 'ai-search' ),
			];
		} elseif ( $has_comm_tax ) {
			$generic_defaults = [
				__( 'Commercial warehouse for lease', 'ai-search' ),
				__( 'Storage facility with 24/7 access', 'ai-search' ),
				__( 'Industrial godown for sale', 'ai-search' ),
			];
		} else {
			$generic_defaults = [
				__( 'Modern 3-bedroom apartment for rent', 'ai-search' ),
				__( 'Luxury villa with swimming pool', 'ai-search' ),
				__( 'Family home with garden for sale', 'ai-search' ),
			];
		}

		foreach ( $generic_defaults as $def ) {
			if ( count( $prompts ) >= $limit ) {
				break;
			}
			if ( ! in_array( $def, $prompts, true ) ) {
				$prompts[] = $def;
			}
		}

		return array_slice( $prompts, 0, $limit );
	}

	/**
	 * Build a friendly, accurate, and localized conversational explanation sentence
	 * describing the exact current active filters state.
	 *
	 * @param array $filters
	 * @return string
	 */
	public static function build_filters_explanation( array $filters ) {
		if ( empty( $filters ) ) {
			return __( 'Showing all available properties.', 'ai-search' );
		}

		$get_name = function( $slug, $tax ) {
			$term = get_term_by( 'slug', $slug, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
			return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		};

		// 1. Bedrooms
		$beds_str = '';
		if ( ! empty( $filters['bedrooms'] ) && intval( $filters['bedrooms'] ) > 0 ) {
			$beds_str = intval( $filters['bedrooms'] ) . '-bedroom ';
		}

		// 2. Property Type
		$type_str = __( 'properties', 'ai-search' );
		if ( ! empty( $filters['type'] ) ) {
			$types = (array) $filters['type'];
			$type_names = array_map( function( $slug ) use ( $get_name ) {
				$name = $get_name( $slug, 'property_type' );
				if ( substr( strtolower( $name ), -1 ) !== 's' ) {
					return $name . 's';
				}
				return $name;
			}, $types );
			$type_str = strtolower( implode( ' & ', $type_names ) );
		}

		// 3. Status
		$status_str = '';
		if ( ! empty( $filters['status'] ) ) {
			$statuses = (array) $filters['status'];
			$stat_names = array_map( function( $slug ) use ( $get_name ) {
				return strtolower( $get_name( $slug, 'property_status' ) );
			}, $statuses );
			$status_str = ' ' . implode( ' / ', $stat_names );
		}

		// 4. Locations
		$loc_parts = [];
		if ( ! empty( $filters['property_area'] ) ) {
			foreach ( (array) $filters['property_area'] as $slug ) {
				$loc_parts[] = $get_name( $slug, 'property_area' );
			}
		}
		if ( ! empty( $filters['property_city'] ) ) {
			foreach ( (array) $filters['property_city'] as $slug ) {
				$c_name = $get_name( $slug, 'property_city' );
				if ( ! in_array( $c_name, $loc_parts, true ) ) {
					$loc_parts[] = $c_name;
				}
			}
		}
		if ( ! empty( $filters['property_state'] ) ) {
			foreach ( (array) $filters['property_state'] as $slug ) {
				$s_name = $get_name( $slug, 'property_state' );
				if ( ! in_array( $s_name, $loc_parts, true ) ) {
					$loc_parts[] = $s_name;
				}
			}
		}
		$loc_str = '';
		if ( ! empty( $loc_parts ) ) {
			$loc_str = ' in ' . implode( ', ', $loc_parts );
		}

		// 5. Features
		$feat_str = '';
		if ( ! empty( $filters['features'] ) ) {
			$feats = (array) $filters['features'];
			$feat_names = array_map( function( $slug ) use ( $get_name ) {
				return $get_name( $slug, 'property_feature' );
			}, $feats );
			$feat_str = ' with ' . implode( ', ', $feat_names );
		}

		// 6. Price Bounds
		$price_str = '';
		$min_p = ! empty( $filters['min_price'] ) ? floatval( $filters['min_price'] ) : 0;
		$max_p = ! empty( $filters['max_price'] ) ? floatval( $filters['max_price'] ) : 0;

		$fmt_price = function( $p ) {
			if ( $p >= 1000000 ) return '$' . round( $p / 1000000, 2 ) . 'M';
			if ( $p >= 1000 ) return '$' . round( $p / 1000 ) . 'k';
			return '$' . number_format( $p );
		};

		if ( $min_p > 0 && $max_p > 0 ) {
			$price_str = ' priced between ' . $fmt_price( $min_p ) . ' and ' . $fmt_price( $max_p );
		} elseif ( $max_p > 0 ) {
			$price_str = ' under ' . $fmt_price( $max_p );
		} elseif ( $min_p > 0 ) {
			$price_str = ' starting from ' . $fmt_price( $min_p );
		}

		// 7. Area / SqFt Bounds
		$area_str = '';
		$min_a = ! empty( $filters['min_area'] ) ? floatval( $filters['min_area'] ) : 0;
		$max_a = ! empty( $filters['max_area'] ) ? floatval( $filters['max_area'] ) : 0;

		if ( $min_a > 0 && $max_a > 0 ) {
			$area_str = ' (' . number_format( $min_a ) . ' - ' . number_format( $max_a ) . ' sq ft)';
		} elseif ( $min_a > 0 ) {
			$area_str = ' (' . number_format( $min_a ) . '+ sq ft)';
		} elseif ( $max_a > 0 ) {
			$area_str = ' (under ' . number_format( $max_a ) . ' sq ft)';
		}

		// 8. Keyword
		$kw_str = '';
		if ( ! empty( $filters['keyword'] ) ) {
			$kw_str = ' matching "' . esc_html( $filters['keyword'] ) . '"';
		}

		return sprintf(
			__( "I've found %s%s%s%s%s%s%s%s.", 'ai-search' ),
			$beds_str,
			$type_str,
			$status_str,
			$loc_str,
			$price_str,
			$area_str,
			$feat_str,
			$kw_str
		);
	}

	/**
	 * AJAX endpoint: Activate plugin license with Envato / Sandbox verification.
	 */
	public static function activate_license() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized user.', 'ai-search' ) ], 403 );
		}

		$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		$result      = AI_Search_License::activate( $license_key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( [ 'message' => $result['msg'] ] );
		}

	}

	/**
	 * AJAX endpoint: Deactivate plugin license.
	 */
	public static function deactivate_license() {
		check_ajax_referer( 'ai_search_houzi_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized user.', 'ai-search' ) ], 403 );
		}

		$result = AI_Search_License::deactivate();
		wp_send_json_success( $result );
	}
}





