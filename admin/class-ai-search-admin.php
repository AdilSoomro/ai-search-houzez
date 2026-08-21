<?php
/**
 * Admin Panel Controller for AI Search
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Admin {

	/**
	 * Initialize Admin Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_head', [ $this, 'render_admin_menu_icon_styles' ] );
	}

	/**
	 * Output pure white opacity styling for menu icon.
	 */
	public function render_admin_menu_icon_styles() {
		echo '<style>
			#adminmenu #toplevel_page_ai-search-admin .wp-menu-image img {
				opacity: 1 !important;
				padding-top: 7px !important;
			}
		</style>';
	}


	/**
	 * Register Top-Level Main Menu in WordPress Dashboard.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'AI Search', 'ai-search' ),
			__( 'AI Search', 'ai-search' ),
			'manage_options',
			'ai-search-admin',
			[ $this, 'display_admin_page' ],
			plugins_url( 'images/menu-icon.svg', __FILE__ ),
			26
		);
	}


	/**
	 * Enqueue Admin Styles and Scripts.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_ai-search-admin' !== $hook_suffix ) {
			return;
		}

		$css_ver = file_exists( __DIR__ . '/css/ai-search-admin.css' ) ? filemtime( __DIR__ . '/css/ai-search-admin.css' ) : AI_SEARCH_VERSION;
		$js_ver  = file_exists( __DIR__ . '/js/ai-search-admin.js' ) ? filemtime( __DIR__ . '/js/ai-search-admin.js' ) : AI_SEARCH_VERSION;

		wp_enqueue_style(
			'ai-search-admin',
			plugins_url( 'css/ai-search-admin.css', __FILE__ ),
			[],
			$css_ver
		);

		wp_enqueue_script(
			'ai-search-admin',
			plugins_url( 'js/ai-search-admin.js', __FILE__ ),
			[ 'jquery' ],
			$js_ver,
			true
		);


		wp_localize_script( 'ai-search-admin', 'aiSearchAdminData', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ai_search_houzi_admin_nonce' ),
			'aliases'   => AI_Search_Settings::get_location_aliases(),
			'misses'    => AI_Search_Settings::get_location_misses(),
			'i18n'      => [
				'saving'              => __( 'Saving settings...', 'ai-search' ),
				'saved'               => __( 'Settings saved successfully!', 'ai-search' ),
				'testing'             => __( 'Testing connection...', 'ai-search' ),
				'installing'          => __( 'Installing AI Homepage...', 'ai-search' ),
				'install_success'     => __( 'Homepage installed successfully!', 'ai-search' ),
				'confirm_overwrite'   => __( 'An AI Smart Home page already exists. Overwrite it?', 'ai-search' ),
				'delete_alias_confirm'=> __( 'Are you sure you want to remove this alias mapping?', 'ai-search' ),
			],
		] );
	}

	/**
	 * Render Main Admin Page.
	 */
	public function display_admin_page() {
		$is_activated = AI_Search_Settings::is_activated();

		if ( ! $is_activated ) {
			$active_tab = 'activation';
		} else {
			$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'configurations';
		}

		include __DIR__ . '/partials/admin-header.php';

		if ( 'activation' === $active_tab || ! $is_activated ) {
			include __DIR__ . '/partials/tab-activation.php';
		} else {
			include __DIR__ . '/partials/tab-configurations.php';
		}

		include __DIR__ . '/partials/admin-footer.php';
	}
}


