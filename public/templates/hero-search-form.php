<?php
/**
 * Hero Search Form Markup Template
 *
 * @package    AI_Search
 * @subpackage AI_Search/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title       = $settings['title'] ?? __( 'Find Your Dream Home With AI', 'ai-search' );
$subtitle    = $settings['subtitle'] ?? __( 'Search naturally using conversational prompts — just describe what you desire.', 'ai-search' );
$badge_text  = $settings['badge_text'] ?? __( '✦ Powered by AI', 'ai-search' );
$placeholder = $settings['placeholder'] ?? __( 'e.g. 3-bedroom modern villa in Miami with pool under $1.5M...', 'ai-search' );
$button_text = $settings['button_text'] ?? __( 'Ask AI', 'ai-search' );

if ( ! empty( $settings['prompts'] ) && is_array( $settings['prompts'] ) ) {
	$prompts = $settings['prompts'];
} elseif ( class_exists( 'AI_Search_Ajax' ) ) {
	$live = AI_Search_Ajax::get_live_suggested_prompts( 3 );
	$prompts = [];
	foreach ( $live as $pt ) {
		$prompts[] = [ 'prompt_text' => $pt ];
	}
} else {
	$prompts = [
		[ 'prompt_text' => __( 'Modern 3-bedroom apartment for rent', 'ai-search' ) ],
		[ 'prompt_text' => __( 'Luxury villa with swimming pool', 'ai-search' ) ],
		[ 'prompt_text' => __( 'Family house with garden for sale', 'ai-search' ) ],
	];
}

$posts_per_page = $settings['posts_per_page'] ?? 4;
$widget_id      = 'ai-search-hero-' . wp_rand( 1000, 9999 );

?>

<div id="<?php echo esc_attr( $widget_id ); ?>" class="ai-search-hero-container" data-posts-per-page="<?php echo esc_attr( $posts_per_page ); ?>">
	
	<!-- BACKGROUND OVERLAY -->
	<div class="ai-search-hero-bg-overlay"></div>

	<div class="ai-search-hero-wrapper">
		
		<!-- HERO INITIAL HEADER (Fades out / compresses when searching) -->
		<div class="ai-search-hero-header">
			<?php if ( ! empty( $badge_text ) ) : ?>
				<div class="ai-search-badge-pill">
					<span class="ai-badge-sparkle">✦</span>
					<span class="ai-badge-text"><?php echo esc_html( $badge_text ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $title ) ) : ?>
				<h1 class="ai-search-hero-title"><?php echo esc_html( $title ); ?></h1>
			<?php endif; ?>

			<?php if ( ! empty( $subtitle ) ) : ?>
				<p class="ai-search-hero-subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>

		<!-- MAIN SPLIT / HERO INTERACTION SECTION -->
		<div class="ai-search-main-layout">
			
			<!-- SEARCH & FILTER SIDEBAR (Transitions to Left / Right in RTL) -->
			<div class="ai-search-panel-search">
				
				<div class="ai-search-box-card">
					
					<!-- PRIMARY NATURAL LANGUAGE SEARCH FORM -->
					<form class="ai-search-form" onsubmit="return false;">
						<div class="ai-search-input-wrap">
							<div class="ai-search-input-content">
								<div class="ai-search-input-icon">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
									</svg>
								</div>

								<div class="ai-search-input-field-container">
									<textarea class="ai-search-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" maxlength="500" rows="1"></textarea>
								</div>


							</div>

							<button type="submit" class="ai-search-submit-btn">
								<span class="ai-btn-text"><?php echo esc_html( $button_text ); ?></span>
								<span class="ai-btn-loader" style="display:none;">
									<svg class="ai-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
										<circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"></circle>
									</svg>
								</span>
								<svg class="ai-btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M5 12h14M12 5l7 7-7 7"/>
								</svg>
							</button>
						</div>

					</form>

					<!-- INITIAL SUGGESTIONS CHIPS (Visible in Initial state) -->
					<?php if ( ! empty( $prompts ) ) : ?>
						<div class="ai-search-prompt-chips">
							<span class="ai-prompt-label"><?php esc_html_e( 'Try asking:', 'ai-search' ); ?></span>
							<div class="ai-chips-list">
								<?php foreach ( $prompts as $p ) : 
									$p_text = is_array( $p ) ? ( $p['prompt_text'] ?? '' ) : $p;
									if ( empty( $p_text ) ) continue;
								?>
									<button type="button" class="ai-prompt-chip" data-prompt="<?php echo esc_attr( $p_text ); ?>">
										<span><?php echo esc_html( $p_text ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- AI RESULTS STATE: CONVERSATION SUMMARY & ACTIVE TAGS -->
					<div class="ai-search-active-state" style="display:none;">
						
						<!-- ACTIVE STATE TOP BAR -->
						<div class="ai-active-header">
							<span class="ai-active-title"><?php esc_html_e( 'AI Search Refinement', 'ai-search' ); ?></span>
							<button type="button" class="ai-new-search-btn">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
								<span><?php esc_html_e( 'New Search', 'ai-search' ); ?></span>
							</button>
						</div>

						<!-- AI EXPLANATION BUBBLE -->
						<div class="ai-explanation-box">
							<div class="ai-explanation-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
							</div>
							<div class="ai-explanation-text"></div>
						</div>


						<!-- ACTIVE TAXONOMY TAGS / CHIPS -->
						<div class="ai-tags-section">
							<div class="ai-tags-header">
								<span class="ai-tags-title"><?php esc_html_e( 'Active Filters', 'ai-search' ); ?></span>
								<button type="button" class="ai-tags-reset-btn"><?php esc_html_e( 'Reset All', 'ai-search' ); ?></button>
							</div>
							<div class="ai-tags-container">
								<!-- Populated dynamically via JS -->
							</div>
						</div>

						<!-- SMART REFINEMENT SUGGESTIONS -->
						<div class="ai-refinements-section" style="display:none;">
							<div class="ai-refinements-title"><?php esc_html_e( 'Suggested Refinements', 'ai-search' ); ?></div>
							<div class="ai-refinements-list">
								<!-- Refinement suggestion pills dynamically injected -->
							</div>
						</div>

						<!-- CONVERSATIONAL MULTI-TURN REFINEMENT INPUT -->
						<div class="ai-refine-input-section">
							<form class="ai-refine-form" onsubmit="return false;">
								<div class="ai-refine-input-wrap">
									<input type="text" class="ai-refine-input" placeholder="<?php esc_attr_e( 'Refine search (e.g. "Actually make it 4 beds and under $1.2M")...', 'ai-search' ); ?>" />
									<button type="submit" class="ai-refine-submit-btn" title="<?php esc_attr_e( 'Send refinement', 'ai-search' ); ?>">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
									</button>
								</div>
							</form>
						</div>

					</div><!-- /ai-search-active-state -->

				</div><!-- /ai-search-box-card -->

			</div><!-- /ai-search-panel-search -->

			<!-- RESULTS PREVIEW PANEL (Slides in on right in LTR / left in RTL) -->
			<div class="ai-search-panel-results" style="display:none;">
				
				<div class="ai-results-header">
					<div class="ai-results-count-wrap">
						<span class="ai-results-count-badge"></span>
						<span class="ai-results-title"><?php esc_html_e( 'Live Matches', 'ai-search' ); ?></span>
					</div>
					<button type="button" class="ai-results-close-btn" title="<?php esc_attr_e( 'Back to centered search', 'ai-search' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>

				<!-- RESULTS CONTENT: CARDS GRID OR EMPTY/ERROR -->
				<div class="ai-results-body">
					
					<!-- SKELETON LOADER -->
					<div class="ai-results-skeleton" style="display:none;">
						<div class="ai-skeleton-card"></div>
						<div class="ai-skeleton-card"></div>
						<div class="ai-skeleton-card"></div>
						<div class="ai-skeleton-card"></div>
					</div>

					<!-- CARDS GRID -->
					<div class="ai-results-grid grid-view listing-view"></div>


					<!-- ZERO RESULTS / ERROR STATE -->
					<div class="ai-results-empty" style="display:none;">
						<div class="ai-empty-icon">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
						</div>
						<h3 class="ai-empty-title"><?php esc_html_e( 'No Matching Properties Found', 'ai-search' ); ?></h3>
						<p class="ai-empty-desc"><?php esc_html_e( 'Try broadening your budget, adjusting bedroom count, or clicking a refinement chip.', 'ai-search' ); ?></p>
					</div>

				</div><!-- /ai-results-body -->

				<!-- STICKY BOTTOM BAR -->
				<div class="ai-results-footer" style="display:none;">
					<div class="ai-footer-info">
						<span class="ai-footer-count"></span>
					</div>

					<!-- PAGINATION CONTROLS -->
					<div class="ai-pagination-wrap" style="display:none;">
						<button type="button" class="ai-page-btn ai-page-prev" title="<?php esc_attr_e( 'Previous Page', 'ai-search' ); ?>" disabled>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
							<span><?php esc_html_e( 'Prev', 'ai-search' ); ?></span>
						</button>
						<div class="ai-page-indicator">
							<span class="ai-page-current">1</span><span class="ai-page-sep">/</span><span class="ai-page-total">1</span>
						</div>
						<button type="button" class="ai-page-btn ai-page-next" title="<?php esc_attr_e( 'Next Page', 'ai-search' ); ?>">
							<span><?php esc_html_e( 'Next', 'ai-search' ); ?></span>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
						</button>
					</div>

					<a href="#" class="ai-view-all-btn" target="_self">
						<span><?php esc_html_e( 'View All In Search Results', 'ai-search' ); ?></span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
					</a>
				</div>


			</div><!-- /ai-search-panel-results -->

		</div><!-- /ai-search-main-layout -->

	</div><!-- /ai-search-hero-wrapper -->

</div>

