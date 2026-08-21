<?php
/**
 * Plugin Name:       AI Search for Houzez
 * Plugin URI:        https://houzi.booleanbites.com
 * Description:       Power your Houzez website with smart natural language real estate search and Elementor hero widgets.
 * Version:           1.0.0
 * Author:            BooleanBites Ltd.
 * Author URI:        https://houzi.booleanbites.com
 * Text Domain:       ai-search
 * Domain Path:       /languages
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Update URI:        false
 *
 * @package           AI_Search
 */

if (!defined('ABSPATH')) {
	exit;
}

// Define Constants
define('AI_SEARCH_VERSION', '1.0.0');
define('AI_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_SEARCH_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require Core Classes
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-activator.php';
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-i18n.php';
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-settings.php';
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-license.php';
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-ajax.php';
require_once AI_SEARCH_PLUGIN_DIR . 'includes/class-ai-search-page-installer.php';


// Register Activation & Deactivation Hooks
register_activation_hook(__FILE__, ['AI_Search_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['AI_Search_Activator', 'deactivate']);

/**
 * The core plugin bootstrap class.
 */
class AI_Search
{

	/**
	 * Single instance.
	 *
	 * @var AI_Search|null
	 */
	private static $instance = null;

	/**
	 * @return AI_Search
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		$this->init_hooks();
	}

	/**
	 * Hook into WordPress lifecycle.
	 */
	private function init_hooks()
	{
		// Internationalization
		add_action('plugins_loaded', ['AI_Search_i18n', 'load_plugin_textdomain']);

		// Runtime dependency notice check
		add_action('admin_init', ['AI_Search_Activator', 'check_dependencies']);

		// AJAX Endpoints
		AI_Search_Ajax::init();

		// Admin Dashboard
		if (is_admin()) {
			require_once AI_SEARCH_PLUGIN_DIR . 'admin/class-ai-search-admin.php';
			$admin = new AI_Search_Admin();
			$admin->init();
		}

		// Frontend & Elementor Assets Registration
		add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
		add_action('elementor/frontend/after_register_styles', [$this, 'register_frontend_assets']);
		add_action('elementor/frontend/after_register_scripts', [$this, 'register_frontend_assets']);
		add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_editor_assets']);
		add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_assets']);

		// Prevent WordPress.org update collision / hijacking
		add_filter('site_transient_update_plugins', [$this, 'prevent_wporg_update_collision']);

		// Elementor Integration
		add_action('plugins_loaded', [$this, 'init_elementor']);
	}

	/**
	 * Remove our custom plugin from WordPress.org update responses.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function prevent_wporg_update_collision($transient)
	{
		if (is_object($transient)) {
			if (isset($transient->response[AI_SEARCH_PLUGIN_BASENAME])) {
				unset($transient->response[AI_SEARCH_PLUGIN_BASENAME]);
			}
			if (isset($transient->no_update[AI_SEARCH_PLUGIN_BASENAME])) {
				unset($transient->no_update[AI_SEARCH_PLUGIN_BASENAME]);
			}
		}
		return $transient;
	}


	/**
	 * Register Frontend Styles and Scripts.
	 */
	public function register_frontend_assets()
	{
		$css_file = AI_SEARCH_PLUGIN_DIR . 'public/css/ai-search-public.css';
		$js_file  = AI_SEARCH_PLUGIN_DIR . 'public/js/ai-search-public.js';
		$css_ver  = file_exists($css_file) ? filemtime($css_file) : AI_SEARCH_VERSION;
		$js_ver   = file_exists($js_file) ? filemtime($js_file) : AI_SEARCH_VERSION;

		wp_register_style(
			'ai-search-public',
			AI_SEARCH_PLUGIN_URL . 'public/css/ai-search-public.css',
			[],
			$css_ver
		);

		wp_register_script(
			'ai-search-public',
			AI_SEARCH_PLUGIN_URL . 'public/js/ai-search-public.js',
			['jquery'],
			$js_ver,
			true
		);


		wp_localize_script('ai-search-public', 'aiSearchPublicData', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('ai_search_houzi_nonce'),
			'i18n' => [
				'type' => __('Type', 'ai-search'),
				'status' => __('Status', 'ai-search'),
				'city' => __('City', 'ai-search'),
				'area' => __('Area', 'ai-search'),
				'state' => __('State', 'ai-search'),
				'country' => __('Country', 'ai-search'),
				'label' => __('Label', 'ai-search'),
				'features' => __('Features', 'ai-search'),
				'beds' => __('Beds', 'ai-search'),
				'baths' => __('Baths', 'ai-search'),
				'min_price' => __('Min Price', 'ai-search'),
				'max_price' => __('Max Price', 'ai-search'),
				'min_area' => __('Min SqFt', 'ai-search'),
				'max_area' => __('Max SqFt', 'ai-search'),
				'keyword' => __('Keyword', 'ai-search'),
			],
		]);
	}

	/**
	 * Enqueue assets specifically in Elementor Editor & Preview iframe.
	 */
	public function enqueue_editor_assets()
	{
		$this->register_frontend_assets();
		wp_enqueue_style('ai-search-public');
		wp_enqueue_script('ai-search-public');
	}


	/**
	 * Initialize Elementor Integration if Elementor is loaded.
	 */
	public function init_elementor()
	{
		if (did_action('elementor/loaded') || class_exists('\Elementor\Plugin')) {
			require_once AI_SEARCH_PLUGIN_DIR . 'includes/elementor/class-ai-search-elementor.php';
			AI_Search_Elementor::init();
		}
	}
}

// Bootstrap Plugin
AI_Search::instance();
