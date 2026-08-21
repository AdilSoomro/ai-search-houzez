<?php
/**
 * Elementor Integration Loader for AI Search
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/elementor
 */

if (!defined('ABSPATH')) {
	exit;
}

class AI_Search_Elementor
{

	/**
	 * Initialize Elementor hooks.
	 */
	public static function init()
	{
		// Category registration
		add_action('elementor/elements/categories_registered', [__CLASS__, 'register_categories']);

		// Widget registration
		add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);
	}

	/**
	 * Register AI Search Elementor Category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_categories($elements_manager)
	{
		$elements_manager->add_category(
			'ai-search-elements',
			[
				'title' => __('AI Search Elements', 'ai-search'),
				'icon' => 'fa fa-magic',
			]
		);
	}

	/**
	 * Register AI Search Widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_widgets($widgets_manager)
	{
		require_once __DIR__ . '/widgets/class-widget-ai-search-hero.php';
		$widgets_manager->register(new AI_Search_Hero_Widget());
	}
}
