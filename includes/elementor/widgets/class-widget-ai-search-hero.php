<?php
/**
 * Elementor AI Search Hero Widget
 *
 * @package    AI_Search
 * @subpackage AI_Search/includes/elementor/widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Search_Hero_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ai_search_hero';
	}

	public function get_title() {
		return __( 'AI Search Hero', 'ai-search' );
	}

	public function get_icon() {
		return 'eicon-site-search';
	}

	public function get_style_depends() {
		return [ 'ai-search-public' ];
	}

	public function get_script_depends() {
		return [ 'ai-search-public' ];
	}

	public function get_categories() {
		return [ 'ai-search-elements', 'houzez-elements' ];
	}

	public function get_keywords() {
		return [ 'ai', 'search', 'real estate', 'hero', 'smart search', 'houzez' ];
	}


	protected function register_controls() {

		// ==================== CONTENT: HERO HEADER ====================
		$this->start_controls_section(
			'section_header_content',
			[
				'label' => __( 'Hero Header', 'ai-search' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'badge_text',
			[
				'label'       => __( 'Badge Text', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( '✦ Powered by AI', 'ai-search' ),
				'placeholder' => __( 'e.g. ✦ Powered by AI', 'ai-search' ),
			]
		);

		$this->add_control(
			'title',
			[
				'label'       => __( 'Hero Title', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Find Your Dream Home With AI', 'ai-search' ),
				'placeholder' => __( 'Enter hero title', 'ai-search' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'subtitle',
			[
				'label'       => __( 'Hero Subtitle', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Search naturally using conversational prompts — just describe what you desire.', 'ai-search' ),
				'placeholder' => __( 'Enter hero subtitle', 'ai-search' ),
				'rows'        => 3,
			]
		);

		$this->end_controls_section();

		// ==================== CONTENT: SEARCH BAR & PROMPTS ====================
		$this->start_controls_section(
			'section_search_content',
			[
				'label' => __( 'Search Input & Suggestions', 'ai-search' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'placeholder',
			[
				'label'       => __( 'Input Placeholder', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'e.g. 3-bedroom modern villa in Miami with pool under $1.5M...', 'ai-search' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'button_text',
			[
				'label'   => __( 'Button Text', 'ai-search' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Ask AI', 'ai-search' ),
			]
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'prompt_text',
			[
				'label'       => __( 'Prompt Text', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Luxury villa in Miami with pool < $2M', 'ai-search' ),
				'label_block' => true,
			]
		);

		$default_prompts = [];
		if ( class_exists( 'AI_Search_Ajax' ) ) {
			$live = AI_Search_Ajax::get_live_suggested_prompts( 3 );
			foreach ( $live as $pt ) {
				$default_prompts[] = [ 'prompt_text' => $pt ];
			}
		}
		if ( empty( $default_prompts ) ) {
			$default_prompts = [
				[ 'prompt_text' => __( 'Modern 3-bedroom apartment for rent', 'ai-search' ) ],
				[ 'prompt_text' => __( 'Luxury villa with swimming pool', 'ai-search' ) ],
				[ 'prompt_text' => __( 'Family house with garden for sale', 'ai-search' ) ],
			];
		}

		$this->add_control(
			'prompts',
			[
				'label'       => __( 'Suggested Prompt Chips', 'ai-search' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => $default_prompts,
				'title_field' => '{{{ prompt_text }}}',
			]
		);


		$this->add_control(
			'posts_per_page',
			[
				'label'   => __( 'Inline Result Previews', 'ai-search' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 12,
				'step'    => 1,
				'default' => 4,
			]
		);

		$this->end_controls_section();

		// ==================== STYLE: TYPOGRAPHY & COLORS ====================
		$this->start_controls_section(
			'section_style_header',
			[
				'label' => __( 'Typography & Colors', 'ai-search' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'title_color',
			[
				'label'     => __( 'Title Color', 'ai-search' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .ai-search-hero-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .ai-search-hero-title',
			]
		);

		$this->add_control(
			'subtitle_color',
			[
				'label'     => __( 'Subtitle Color', 'ai-search' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#cbd5e1',
				'selectors' => [
					'{{WRAPPER}} .ai-search-hero-subtitle' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'subtitle_typography',
				'selector' => '{{WRAPPER}} .ai-search-hero-subtitle',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		// Ensure Public assets are enqueued
		wp_enqueue_style( 'ai-search-public' );
		wp_enqueue_script( 'ai-search-public' );

		$template_path = defined( 'AI_SEARCH_PLUGIN_DIR' )
			? AI_SEARCH_PLUGIN_DIR . 'public/templates/hero-search-form.php'
			: dirname( dirname( dirname( __FILE__ ) ) ) . '/public/templates/hero-search-form.php';

		include $template_path;
	}
}


