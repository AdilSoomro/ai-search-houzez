<?php
/**
 * Activation & Deactivation Handler for AI Search
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		// 1. Check PHP Version >= 7.4
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__ ) . '/ai-search-houzez.php' ) );
			wp_die(
				esc_html__( 'AI Search for Houzez requires PHP version 7.4 or higher.', 'ai-search' ),
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		// 2. Check WordPress Version >= 5.9
		global $wp_version;
		if ( version_compare( $wp_version, '5.9', '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__ ) . '/ai-search-houzez.php' ) );
			wp_die(
				esc_html__( 'AI Search for Houzez requires WordPress version 5.9 or higher.', 'ai-search' ),
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}


		// Initialize default options if not existing
		$defaults = AI_Search_Settings::get_defaults();
		foreach ( $defaults as $key => $val ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $val );
			}
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Clean temporary transients
		delete_transient( 'ai_search_houzi_all_loc_terms' );
	}

	/**
	 * Check runtime theme & plugin dependencies.
	 *
	 * @return bool
	 */
	public static function check_dependencies() {
		$missing = [];

		// Check Houzez Theme
		$theme = wp_get_theme();
		$parent = $theme->parent();
		$is_houzez = ( 'houzez' === $theme->get_template() || ( $parent && 'houzez' === $parent->get_template() ) );
		if ( ! $is_houzez && ! function_exists( 'houzez_option' ) ) {
			$missing[] = __( 'Houzez Real Estate Theme', 'ai-search' );
		}

		// Check Elementor
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			$missing[] = __( 'Elementor Page Builder plugin', 'ai-search' );
		}

		if ( ! empty( $missing ) ) {
			add_action( 'admin_notices', function() use ( $missing ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong><?php esc_html_e( 'AI Search for Houzez Notice:', 'ai-search' ); ?></strong>
						<?php
						printf(
							esc_html__( 'The following recommended dependencies are not active: %s. Some features (such as Elementor Hero widgets or Houzez search filters) may be limited.', 'ai-search' ),
							'<strong>' . esc_html( implode( ', ', $missing ) ) . '</strong>'
						);
						?>
					</p>
				</div>
				<?php
			} );
			return false;
		}

		return true;
	}
}
