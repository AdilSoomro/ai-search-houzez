<?php
/**
 * Configurations Tab Partial
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled          = AI_Search_Settings::is_enabled();
$provider         = AI_Search_Settings::get_provider();
$api_key          = AI_Search_Settings::get_api_key();
$has_key          = ! empty( $api_key );
$model            = AI_Search_Settings::get_model();
$lite_model       = AI_Search_Settings::get_lite_model();
$system_prompt    = AI_Search_Settings::get_system_prompt();
$rate_user        = AI_Search_Settings::get_rate_per_user_hour();
$rate_site        = AI_Search_Settings::get_rate_per_site_day();
$resolver_enabled = AI_Search_Settings::is_location_resolver_enabled();
$usage_log        = AI_Search_Settings::get_usage_log();
$location_misses  = AI_Search_Settings::get_location_misses();
$existing_page    = AI_Search_Page_Installer::get_existing_page();
$front_page_id    = (int) get_option( 'page_on_front' );
$is_front_page    = ( $existing_page && $front_page_id === $existing_page->ID );
?>

<form id="ai-search-settings-form" method="post" onsubmit="return false;">
	
	<div class="ai-admin-layout">
		
		<!-- LEFT COLUMN: MAIN CONFIGURATION CARDS -->
		<div class="ai-admin-main-col">
			
			<!-- CARD 1: GENERAL & PROVIDER SETTINGS -->
			<div class="ai-card">
				<div class="ai-card-header">
					<h3 class="ai-card-title"><?php esc_html_e( 'AI Provider & Model Settings', 'ai-search' ); ?></h3>
					<label class="ai-toggle-switch">
						<input type="checkbox" name="ai_search_houzi_enabled" value="1" <?php checked( $enabled, true ); ?> />
						<span class="ai-toggle-slider"></span>
					</label>
				</div>
				<div class="ai-card-body">
					
					<div class="ai-form-row">
						<label for="ai_search_houzi_provider"><?php esc_html_e( 'AI Provider', 'ai-search' ); ?></label>
						<select id="ai_search_houzi_provider" name="ai_search_houzi_provider" class="ai-select">
							<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (ChatGPT)</option>
							<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
							<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google (Gemini)</option>
						</select>
						<p class="ai-field-desc"><?php esc_html_e( 'Choose your preferred AI foundation provider for query intelligence.', 'ai-search' ); ?></p>
					</div>

					<div class="ai-form-row">
						<label for="ai_search_houzi_api_key"><?php esc_html_e( 'API Key', 'ai-search' ); ?></label>
						<div class="ai-input-with-button">
							<input type="password" id="ai_search_houzi_api_key" name="ai_search_houzi_api_key" class="regular-text ai-input" placeholder="<?php echo $has_key ? '••••••••••••••••••••••••••••••••' : esc_attr__( 'Enter your provider API Key', 'ai-search' ); ?>" autocomplete="new-password" />
							<button type="button" id="ai-toggle-key-visibility" class="button ai-btn-icon" title="<?php esc_attr_e( 'Toggle Visibility', 'ai-search' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" id="ai-test-connection-btn" class="button button-secondary">
								<span class="dashicons dashicons-update"></span>
								<span><?php esc_html_e( 'Test Connection', 'ai-search' ); ?></span>
							</button>
						</div>
						<p class="ai-field-desc"><?php esc_html_e( 'The key is encrypted and never exposed on frontend client scripts. Leave empty to preserve current key.', 'ai-search' ); ?></p>
						
						<!-- TEST CONNECTION FEEDBACK BOX -->
						<div id="ai-test-feedback" class="ai-feedback-box" style="display:none;"></div>
					</div>

					<div class="ai-form-row-grid">
						<div class="ai-form-col">
							<label for="ai_search_houzi_model"><?php esc_html_e( 'Primary Model Override', 'ai-search' ); ?></label>
							<input type="text" id="ai_search_houzi_model" name="ai_search_houzi_model" class="regular-text ai-input" value="<?php echo esc_attr( $model ); ?>" placeholder="e.g. gpt-5-mini, claude-haiku-4-5, gemini-3-flash-preview" />
							<p class="ai-field-desc"><?php esc_html_e( 'Leave blank to use recommended provider defaults (OpenAI: gpt-5-mini, Claude: claude-haiku-4-5, Gemini: gemini-3-flash-preview).', 'ai-search' ); ?></p>
						</div>
						<div class="ai-form-col">
							<label for="ai_search_houzi_lite_model"><?php esc_html_e( 'Lite / Fast Model Override', 'ai-search' ); ?></label>
							<input type="text" id="ai_search_houzi_lite_model" name="ai_search_houzi_lite_model" class="regular-text ai-input" value="<?php echo esc_attr( $lite_model ); ?>" placeholder="e.g. gpt-5-nano, gemini-3.1-flash-lite" />
							<p class="ai-field-desc"><?php esc_html_e( 'Optional lighter model used for quick tasks.', 'ai-search' ); ?></p>
						</div>
					</div>

					<div class="ai-form-row">
						<label for="ai_search_houzi_system_prompt"><?php esc_html_e( 'Custom System Prompt Guidelines', 'ai-search' ); ?></label>
						<textarea id="ai_search_houzi_system_prompt" name="ai_search_houzi_system_prompt" rows="3" class="large-text ai-textarea" placeholder="<?php esc_attr_e( 'Optional: Add custom instructions or localized domain rules (e.g. "Prioritize waterfront villas when user asks for beach houses").', 'ai-search' ); ?>"><?php echo esc_textarea( $system_prompt ); ?></textarea>
					</div>

				</div>
			</div>

			<!-- CARD 2: LOCATION RESOLVER & ALIASES -->
			<div class="ai-card">
				<div class="ai-card-header">
					<h3 class="ai-card-title"><?php esc_html_e( 'Location Intelligence & Custom Aliases', 'ai-search' ); ?></h3>
					<label class="ai-toggle-switch">
						<input type="checkbox" name="ai_search_houzi_location_resolver_enabled" value="1" <?php checked( $resolver_enabled, true ); ?> />
						<span class="ai-toggle-slider"></span>
					</label>
				</div>
				<div class="ai-card-body">
					<p class="ai-card-intro"><?php esc_html_e( 'Map colloquial city nicknames, abbreviations, or airport codes to your official Houzez taxonomy terms.', 'ai-search' ); ?></p>

					<!-- ALIASES TABLE -->
					<div class="ai-alias-table-wrap">
						<table id="ai-alias-table" class="widefat ai-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Taxonomy', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Target Term Slug', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Recognized Aliases (Comma separated)', 'ai-search' ); ?></th>
									<th style="width:60px;"><?php esc_html_e( 'Action', 'ai-search' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<!-- Populated dynamically via JS from aiSearchAdminData.aliases -->
							</tbody>
						</table>
						<button type="button" id="ai-add-alias-row-btn" class="button button-secondary ai-btn-sm" style="margin-top:10px;">
							<span class="dashicons dashicons-plus-alt2"></span>
							<span><?php esc_html_e( 'Add Location Alias', 'ai-search' ); ?></span>
						</button>
					</div>

					<!-- UNRESOLVED MISSES LOG -->
					<div class="ai-misses-section">
						<h4 class="ai-section-title"><?php esc_html_e( 'Recent Unresolved Location Searches', 'ai-search' ); ?></h4>
						<?php if ( empty( $location_misses ) ) : ?>
							<p class="ai-empty-text"><?php esc_html_e( 'No unresolved location misses recorded yet.', 'ai-search' ); ?></p>
						<?php else : ?>
							<table class="widefat ai-table ai-table-sm">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Search Phrase', 'ai-search' ); ?></th>
										<th><?php esc_html_e( 'Hits', 'ai-search' ); ?></th>
										<th><?php esc_html_e( 'Last Seen', 'ai-search' ); ?></th>
										<th><?php esc_html_e( 'Action', 'ai-search' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $location_misses as $miss ) : ?>
										<tr>
											<td><code><?php echo esc_html( $miss['phrase'] ); ?></code></td>
											<td><span class="ai-badge-count"><?php echo esc_html( $miss['count'] ?? 1 ); ?></span></td>
											<td><?php echo esc_html( $miss['last_seen'] ?? '-' ); ?></td>
											<td>
												<button type="button" class="button button-small ai-add-miss-as-alias-btn" data-phrase="<?php echo esc_attr( $miss['phrase'] ); ?>">
													<?php esc_html_e( '+ Add as Alias', 'ai-search' ); ?>
												</button>
												<button type="button" class="button button-small button-link-delete ai-dismiss-miss-btn" data-phrase="<?php echo esc_attr( $miss['phrase'] ); ?>">
													<?php esc_html_e( 'Dismiss', 'ai-search' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>

				</div>
			</div>

			<!-- CARD 3: USAGE ANALYTICS DASHBOARD -->
			<div class="ai-card">
				<div class="ai-card-header">
					<h3 class="ai-card-title"><?php esc_html_e( 'AI Usage Analytics (Last 7 Days)', 'ai-search' ); ?></h3>
				</div>
				<div class="ai-card-body">
					<?php if ( empty( $usage_log ) ) : ?>
						<p class="ai-empty-text"><?php esc_html_e( 'No usage data recorded yet. Queries made from the frontend will appear here.', 'ai-search' ); ?></p>
					<?php else : ?>
						<table class="widefat ai-table ai-table-sm">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Total Calls', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Errors', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Input Tokens', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Output Tokens', 'ai-search' ); ?></th>
									<th><?php esc_html_e( 'Avg Latency', 'ai-search' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php 
								$recent_days = array_slice( $usage_log, 0, 7, true );
								foreach ( $recent_days as $row ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $row['date'] ); ?></strong></td>
										<td><?php echo esc_html( number_format_i18n( $row['calls'] ) ); ?></td>
										<td><?php echo ( $row['errors'] > 0 ) ? '<span class="ai-error-tag">' . esc_html( $row['errors'] ) . '</span>' : '0'; ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['input_tokens'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['output_tokens'] ) ); ?></td>
										<td><?php echo esc_html( $row['avg_latency'] ); ?> ms</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- /ai-admin-main-col -->

		<!-- RIGHT COLUMN: SIDEBAR CARDS -->
		<div class="ai-admin-side-col">
			
			<!-- SIDE CARD 1: 1-CLICK HOMEPAGE INSTALLER -->
			<div class="ai-card ai-card-highlight">
				<div class="ai-card-header">
					<h3 class="ai-card-title"><?php esc_html_e( '1-Click Homepage Installer', 'ai-search' ); ?></h3>
				</div>
				<div class="ai-card-body">
					<p class="ai-card-desc"><?php esc_html_e( 'Generates a ready-to-use Elementor homepage with the AI Search Hero and Houzez widgets, and sets it as your site Front Page.', 'ai-search' ); ?></p>
					
					<div class="ai-homepage-status">
						<div class="ai-status-indicator <?php echo $is_front_page ? 'ai-status-active' : 'ai-status-inactive'; ?>"></div>
						<span>
							<?php 
							if ( $is_front_page ) {
								esc_html_e( 'AI Smart Home is your current front page.', 'ai-search' );
							} elseif ( $existing_page ) {
								esc_html_e( 'Page created but not set as front page.', 'ai-search' );
							} else {
								esc_html_e( 'Not installed yet.', 'ai-search' );
							}
							?>
						</span>
					</div>

					<div class="ai-homepage-actions">
						<div class="ai-installer-options" style="margin-bottom:14px; background:rgba(0,0,0,0.02); padding:10px 12px; border-radius:6px; border:1px solid #e2e8f0;">
							<label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; cursor:pointer; line-height:1.4;">
								<input type="checkbox" id="ai-preserve-sections-chk" name="ai_preserve_sections" value="1" checked style="margin-top:2px;" />
								<span>
									<strong><?php esc_html_e( 'Preserve current homepage sections', 'ai-search' ); ?></strong><br/>
									<span style="font-size:11px; color:#64748b;"><?php esc_html_e( 'Deep copies your active homepage and inserts the AI Search Hero at the top.', 'ai-search' ); ?></span>
								</span>
							</label>
						</div>

						<button type="button" id="ai-install-homepage-btn" class="button button-primary button-hero ai-btn-full">
							<span class="dashicons dashicons-admin-home"></span>
							<span><?php echo $existing_page ? esc_html__( 'Reinstall / Update Homepage', 'ai-search' ) : esc_html__( 'Install AI Search Homepage', 'ai-search' ); ?></span>
						</button>

						<?php if ( $existing_page ) : ?>
							<div class="ai-page-links">
								<a href="<?php echo esc_url( get_permalink( $existing_page->ID ) ); ?>" class="button button-secondary" target="_blank">
									<?php esc_html_e( 'View Page', 'ai-search' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $existing_page->ID . '&action=elementor' ) ); ?>" class="button button-secondary" target="_blank">
									<?php esc_html_e( 'Edit in Elementor', 'ai-search' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>

					<div id="ai-install-feedback" class="ai-feedback-box" style="display:none;"></div>
				</div>
			</div>

			<!-- SIDE CARD 2: RATE LIMITING -->
			<div class="ai-card">
				<div class="ai-card-header">
					<h3 class="ai-card-title"><?php esc_html_e( 'Rate Limiting & Safety', 'ai-search' ); ?></h3>
				</div>
				<div class="ai-card-body">
					<div class="ai-form-row">
						<label for="ai_search_houzi_rate_per_user_hour"><?php esc_html_e( 'Max Searches per User / Hour', 'ai-search' ); ?></label>
						<input type="number" id="ai_search_houzi_rate_per_user_hour" name="ai_search_houzi_rate_per_user_hour" class="small-text ai-input" value="<?php echo esc_attr( $rate_user ); ?>" min="1" max="500" />
						<p class="ai-field-desc"><?php esc_html_e( 'Applies per IP for visitors or per user ID when logged in.', 'ai-search' ); ?></p>
					</div>

					<div class="ai-form-row">
						<label for="ai_search_houzi_rate_per_site_day"><?php esc_html_e( 'Max Searches per Site / Day', 'ai-search' ); ?></label>
						<input type="number" id="ai_search_houzi_rate_per_site_day" name="ai_search_houzi_rate_per_site_day" class="small-text ai-input" value="<?php echo esc_attr( $rate_site ); ?>" min="1" max="10000" />
						<p class="ai-field-desc"><?php esc_html_e( 'Site-wide budget safety cap to prevent API overages.', 'ai-search' ); ?></p>
					</div>
				</div>
			</div>

			<!-- SAVE ACTION CARD -->
			<div class="ai-card ai-card-save-sticky">
				<div class="ai-card-body">
					<button type="button" id="ai-save-settings-side-btn" class="button button-primary button-large ai-btn-full ai-btn-accent">
						<span><?php esc_html_e( 'Save All Changes', 'ai-search' ); ?></span>
					</button>
					<div id="ai-save-feedback" class="ai-feedback-box" style="display:none; margin-top:10px;"></div>
				</div>
			</div>

			<?php include __DIR__ . '/flyer-houzi.php'; ?>

		</div><!-- /ai-admin-side-col -->

	</div><!-- /ai-admin-layout -->

</form>

