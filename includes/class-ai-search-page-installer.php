<?php
/**
 * 1-Click Homepage Installer
 *
 * Installs an Elementor-powered AI Search Homepage modeled after Houzez01,
 * with optional deep-copy preservation of current active homepage sections.
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Page_Installer {

	const PAGE_TITLE = 'AI Smart Home';
	const PAGE_SLUG  = 'ai-smart-home';

	/**
	 * Find existing AI Smart Home page if any.
	 *
	 * @return WP_Post|null
	 */
	public static function get_existing_page() {
		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			return $page;
		}

		$query = new WP_Query( [
			'post_type'      => 'page',
			'title'          => self::PAGE_TITLE,
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 1,
		] );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return null;
	}

	/**
	 * Install or overwrite the AI Search homepage.
	 *
	 * @param bool $overwrite         Whether to overwrite an existing AI Smart Home page.
	 * @param bool $preserve_sections Whether to deep copy and preserve sections from active front page.
	 * @return array
	 */
	public static function install_homepage( $overwrite = false, $preserve_sections = true ) {
		$existing_page = self::get_existing_page();

		if ( $existing_page && ! $overwrite ) {
			return [
				'success'    => false,
				'exists'     => true,
				'page_id'    => $existing_page->ID,
				'page_title' => $existing_page->post_title,
				'view_url'   => get_permalink( $existing_page->ID ),
				'edit_url'   => admin_url( 'post.php?post=' . $existing_page->ID . '&action=elementor' ),
				'message'    => sprintf(
					__( 'A page titled "%s" already exists. Would you like to overwrite it or keep the current one?', 'ai-search' ),
					$existing_page->post_title
				),
			];
		}

		$json_path = dirname( __DIR__ ) . '/templates/default-homepage-elementor.json';
		if ( ! file_exists( $json_path ) ) {
			return [
				'success' => false,
				'message' => __( 'Homepage layout template file not found.', 'ai-search' ),
			];
		}

		$template_raw = file_get_contents( $json_path );
		if ( empty( $template_raw ) ) {
			return [
				'success' => false,
				'message' => __( 'Failed to read homepage layout template JSON.', 'ai-search' ),
			];
		}

		$template_sections = json_decode( $template_raw, true );
		$hero_section      = ( is_array( $template_sections ) && ! empty( $template_sections[0] ) )
			? $template_sections[0]
			: null;

		// Dynamically construct suggestion prompts based on live site data
		if ( is_array( $hero_section ) && class_exists( 'AI_Search_Ajax' ) ) {
			$live_prompts = AI_Search_Ajax::get_live_suggested_prompts( 3 );
			if ( ! empty( $live_prompts ) ) {
				$prompts_repeater = [];
				foreach ( $live_prompts as $p_text ) {
					$prompts_repeater[] = [
						'prompt_text' => $p_text,
					];
				}

				if ( ! empty( $hero_section['elements'] ) ) {
					foreach ( $hero_section['elements'] as &$col ) {
						if ( ! empty( $col['elements'] ) ) {
							foreach ( $col['elements'] as &$widget ) {
								if ( isset( $widget['widgetType'] ) && 'ai_search_hero' === $widget['widgetType'] ) {
									if ( ! isset( $widget['settings'] ) ) {
										$widget['settings'] = [];
									}
									$widget['settings']['prompts'] = $prompts_repeater;
								}
							}
						}
					}
					unset( $col, $widget );
				}

				$template_sections[0] = $hero_section;
				$template_raw         = wp_json_encode( $template_sections );
			}
		}

		$final_elementor_json = $template_raw;
		$page_template        = 'template/template-homepage.php';
		
		// Default Houzez Page Meta for Transparent Header Overlay
		$meta_to_apply = [
			'fave_main_menu_trans'    => 'yes',
			'fave_header_type'        => 'elementor',
			'fave_adv_search'         => 'hide',

			'fave_page_title'         => 'hide',
			'fave_page_breadcrumb'    => 'hide',
			'fave_page_background'    => 'yes',
			'fave_page_header_search' => '0',
			'fave_header_full_screen' => '0',
		];


		// If preserve_sections is enabled, deep-copy active front page sections & meta
		$current_front_id = (int) get_option( 'page_on_front' );
		if ( $preserve_sections && $current_front_id > 0 && ( ! $existing_page || $current_front_id !== $existing_page->ID ) ) {
			$current_el_data = get_post_meta( $current_front_id, '_elementor_data', true );
			if ( ! empty( $current_el_data ) ) {
				$existing_sections = json_decode( $current_el_data, true );
				if ( is_array( $existing_sections ) && ! empty( $existing_sections ) && $hero_section ) {
					// Prepend AI Search Hero section at the top of existing sections
					$merged_sections      = array_merge( [ $hero_section ], $existing_sections );
					$final_elementor_json = wp_json_encode( $merged_sections );
				}
			}

			// Copy over page template and Houzez custom layout meta
			$custom_template = get_post_meta( $current_front_id, '_wp_page_template', true );
			if ( ! empty( $custom_template ) && 'default' !== $custom_template ) {
				$page_template = $custom_template;
			}

			$all_meta = get_post_meta( $current_front_id );
			if ( is_array( $all_meta ) ) {
				foreach ( $all_meta as $meta_key => $meta_vals ) {
					// Copy Houzez (fave_*) and elementor page settings meta
					if ( strpos( $meta_key, 'fave_' ) === 0 || strpos( $meta_key, '_elementor_page_settings' ) === 0 ) {
						$meta_to_apply[ $meta_key ] = maybe_unserialize( $meta_vals[0] );
					}
				}
			}
		}

		$page_id = 0;
		if ( $existing_page && $overwrite ) {
			$page_id = $existing_page->ID;
			wp_update_post( [
				'ID'          => $page_id,
				'post_title'  => self::PAGE_TITLE,
				'post_status' => 'publish',
			] );
		} else {
			$page_id = wp_insert_post( [
				'post_title'   => self::PAGE_TITLE,
				'post_name'    => self::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			] );

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				return [
					'success' => false,
					'message' => __( 'Failed to create homepage page.', 'ai-search' ),
				];
			}
		}

		// Update Elementor Post Meta with mandatory wp_slash
		update_post_meta( $page_id, '_wp_page_template', $page_template );
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $page_id, '_elementor_data', wp_slash( $final_elementor_json ) );

		// Apply Houzez layout & transparent header meta
		foreach ( $meta_to_apply as $k => $v ) {
			update_post_meta( $page_id, $k, $v );
		}


		// Set as Front Page
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		// Clear Elementor CSS cache
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		$view_url = get_permalink( $page_id );
		$edit_url = admin_url( 'post.php?post=' . $page_id . '&action=elementor' );

		return [
			'success'    => true,
			'page_id'    => $page_id,
			'page_title' => self::PAGE_TITLE,
			'view_url'   => $view_url,
			'edit_url'   => $edit_url,
			'preserved'  => ( $preserve_sections && ! empty( $copied_meta ) ),
			'message'    => __( 'AI Search Homepage installed successfully with your layout active!', 'ai-search' ),
		];
	}
}
