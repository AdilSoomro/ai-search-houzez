<?php
/**
 * Admin Footer Partial
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

	<div class="ai-admin-footer">
		<div class="ai-admin-footer-left">
			<span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <a href="https://booleanbites.com/" target="_blank" rel="noopener noreferrer">BooleanBites Ltd.</a> <?php esc_html_e( 'AI Search and Houzi real estate app are products of BooleanBites Ltd.', 'ai-search' ); ?></span>
		</div>
		<div class="ai-admin-footer-right">
			<a href="<?php echo esc_url( AI_SEARCH_PLUGIN_URL . 'docs/index.html' ); ?>" target="_blank"><?php esc_html_e( 'Documentation', 'ai-search' ); ?></a>
			<span class="ai-footer-dot">&bull;</span>

			<a href="https://houzi.booleanbites.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Houzi App', 'ai-search' ); ?></a>
			<span class="ai-footer-dot">&bull;</span>
			<a href="https://booleanbites.com/contact/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support & Inquiries', 'ai-search' ); ?></a>
			<span class="ai-footer-dot">&bull;</span>
			<span><?php echo esc_html( 'v' . AI_SEARCH_VERSION ); ?></span>
		</div>

	</div>

</div><!-- /ai-search-admin-wrap -->
