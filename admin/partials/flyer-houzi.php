<?php
/**
 * Houzi App Flyer Card Partial
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mockup_url = AI_SEARCH_PLUGIN_URL . 'admin/images/houzi-home.png';
?>


<!-- HOUZI MOBILE APP FLYER CARD -->
<div class="ai-card ai-card-flyer">
	<div class="ai-card-body">
		
		<div class="ai-flyer-header">
			<span class="ai-flyer-badge"><?php esc_html_e( 'Houzi Mobile App', 'ai-search' ); ?></span>
			<h3 class="ai-flyer-title"><?php esc_html_e( 'Need a mobile app for your Houzez website?', 'ai-search' ); ?></h3>
			<p class="ai-flyer-subtitle"><?php esc_html_e( 'Give your buyers and agents a dedicated mobile experience with white-label iOS and Android apps connected live to your property database.', 'ai-search' ); ?></p>
		</div>

		<div class="ai-flyer-image-wrap">
			<img src="<?php echo esc_url( $mockup_url ); ?>" alt="<?php esc_attr_e( 'Houzi Real Estate Mobile App', 'ai-search' ); ?>" class="ai-flyer-mockup" loading="lazy" />
		</div>

		<div class="ai-flyer-pills-grid">
			<div class="ai-flyer-pill">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
				<span><?php esc_html_e( 'iOS & Android', 'ai-search' ); ?></span>
			</div>
			<div class="ai-flyer-pill">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
				<span><?php esc_html_e( 'Real-Time Sync', 'ai-search' ); ?></span>
			</div>
			<div class="ai-flyer-pill">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
				<span><?php esc_html_e( 'Push Alerts', 'ai-search' ); ?></span>
			</div>
			<div class="ai-flyer-pill">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
				<span><?php esc_html_e( 'White-Label', 'ai-search' ); ?></span>
			</div>
		</div>

		<a href="https://houzi.booleanbites.com/" target="_blank" rel="noopener noreferrer" class="ai-flyer-cta-btn">
			<span><?php esc_html_e( 'Explore Houzi App', 'ai-search' ); ?></span>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
		</a>

	</div>
</div>

