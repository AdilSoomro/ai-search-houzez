<?php
/**
 * Admin Header Partial
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin/partials
 */

if (!defined('ABSPATH')) {
	exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'configurations';
?>

<div class="wrap ai-search-admin-wrap">
	<div class="ai-admin-header-card">
		<div class="ai-admin-header-left">
			<div class="ai-admin-logo-badge">
				<svg width="30" height="30" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path
						d="M2.77302 8.60866C2.55626 8.82542 2.69174 9.20476 2.98978 9.20476H4.29034L9.98034 4.15554L15.6703 9.20476H16.9709C17.2689 9.20476 17.4044 8.82542 17.1877 8.63576L10.9557 3.10833C10.4138 2.59353 9.54681 2.59353 9.00488 3.10833L2.77302 8.60866Z"
						fill="white" />
					<path fill-rule="evenodd" clip-rule="evenodd"
						d="M10 7.44141C12.2544 7.44141 14.082 9.26025 14.082 11.5039C14.082 13.7476 11.8066 16.3672 10 17.168C8.19336 16.3672 5.91797 13.7476 5.91797 11.5039C5.91797 9.26025 7.74556 7.44141 10 7.44141ZM9.19666 10.2422L8.77913 11.5018L7.52649 11.9217V12.1794L8.77913 12.5993L9.19666 13.859H9.4529L9.87043 12.5993L11.1231 12.1794V11.9217L9.87043 11.5018L9.4529 10.2422H9.19666ZM11.3574 9.15577L11.1424 9.80431L10.4975 10.0205V10.2782L11.1424 10.4943L11.3574 11.1429H11.6136L11.8286 10.4943L12.4735 10.2782V10.0205L11.8286 9.80431L11.6136 9.15577H11.3574Z"
						fill="white" />
				</svg>
			</div>

			<div>
				<div class="ai-admin-title"><?php esc_html_e('AI Search for Houzez', 'ai-search'); ?> <span
						class="ai-admin-version">v<?php echo esc_html(AI_SEARCH_VERSION); ?></span></div>
				<p class="ai-admin-subtitle">

					<?php esc_html_e('Power your Houzez website with smart conversational real estate search.', 'ai-search'); ?>
				</p>
			</div>
		</div>

		<div class="ai-admin-header-right">
			<a href="<?php echo esc_url(AI_SEARCH_PLUGIN_URL . 'docs/index.html'); ?>" target="_blank"
				class="button button-secondary" title="<?php esc_attr_e('Open Offline Documentation', 'ai-search'); ?>"
				style="display:inline-flex; align-items:center; gap:5px;">
				<span class="dashicons dashicons-book" style="margin-top:2px;"></span>
				<span><?php esc_html_e('Documentation', 'ai-search'); ?></span>
			</a>
			<?php if ($is_activated && 'configurations' === $active_tab): ?>
				<button type="button" id="ai-save-settings-top-btn" class="button button-primary ai-btn-accent">
					<span><?php esc_html_e('Save Changes', 'ai-search'); ?></span>
				</button>
			<?php endif; ?>
		</div>

	</div>

	<!-- WP Core Notice Anchor (Forces admin notices to render here below header card) -->
	<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e('AI Search for Houzez', 'ai-search'); ?></h1>
	<hr class="wp-header-end" style="display:none;" />

	<!-- NAV TABS -->

	<nav class="nav-tab-wrapper ai-nav-tab-wrapper">
		<?php if ($is_activated): ?>
			<a href="?page=ai-search-admin&tab=configurations"
				class="nav-tab <?php echo ('configurations' === $active_tab) ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				<span><?php esc_html_e('Configurations', 'ai-search'); ?></span>
			</a>
		<?php endif; ?>
		<a href="?page=ai-search-admin&tab=activation"
			class="nav-tab <?php echo ('activation' === $active_tab) ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-shield-alt"></span>
			<span><?php esc_html_e('Activation', 'ai-search'); ?></span>
		</a>
	</nav>