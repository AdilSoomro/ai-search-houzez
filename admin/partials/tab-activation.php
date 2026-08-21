<?php
/**
 * Activation Tab Partial
 *
 * @package    AI_Search
 * @subpackage AI_Search/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_activated = AI_Search_Settings::is_activated();
$license_key  = get_option( AI_Search_Settings::OPTION_LICENSE_KEY, '' );
$license_data = class_exists( 'AI_Search_License' ) ? AI_Search_License::get_license_data() : [];

// Mask purchase code for security if activated
$masked_key = $license_key;
if ( ! empty( $license_key ) && strlen( $license_key ) > 12 ) {
	$masked_key = substr( $license_key, 0, 8 ) . '-****-****-****-' . substr( $license_key, -8 );
}
?>

<div class="ai-admin-layout">
	<div class="ai-admin-main-col">
		<div class="ai-card">
			<div class="ai-card-header">
				<h3 class="ai-card-title"><?php esc_html_e( 'Plugin License & Activation', 'ai-search' ); ?></h3>
				<span class="ai-license-badge <?php echo $is_activated ? 'ai-license-active' : 'ai-license-inactive'; ?>">
					<?php echo $is_activated ? esc_html__( '✓ Activated', 'ai-search' ) : esc_html__( 'Unregistered', 'ai-search' ); ?>
				</span>
			</div>
			<div class="ai-card-body">

				<?php if ( $is_activated ) : ?>
					<div class="ai-activation-success-box" style="margin-bottom: 24px;">
						<div style="display:flex; align-items:center; gap:12px;">
							<div style="width:36px; height:36px; border-radius:50%; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:bold; flex-shrink:0;">✓</div>
							<div>
								<h4 style="margin:0 0 3px 0; font-size:15px; color:#064e3b;"><?php esc_html_e( 'License is Active & Verified', 'ai-search' ); ?></h4>
								<p style="margin:0; font-size:13px; color:#047857;"><?php esc_html_e( 'Your license is verified. Full plugin features and official product support are active on this website.', 'ai-search' ); ?></p>
							</div>
						</div>
					</div>

					<div class="ai-license-details-grid" style="margin-bottom: 24px;">
						<table class="widefat striped" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
							<tbody>
								<tr>
									<td style="width: 220px; font-weight: 600; padding: 12px 16px;"><?php esc_html_e( 'Purchase Code', 'ai-search' ); ?></td>
									<td style="padding: 12px 16px;"><code style="background:#f1f5f9; padding:3px 8px; border-radius:4px; font-size:13px;"><?php echo esc_html( $masked_key ); ?></code></td>
								</tr>
								<?php if ( ! empty( $license_data['buyer'] ) ) : ?>
								<tr>
									<td style="font-weight: 600; padding: 12px 16px;"><?php esc_html_e( 'Licensed Buyer', 'ai-search' ); ?></td>
									<td style="padding: 12px 16px;"><?php echo esc_html( $license_data['buyer'] ); ?></td>
								</tr>
								<?php endif; ?>
								<?php if ( ! empty( $license_data['item_name'] ) ) : ?>
								<tr>
									<td style="font-weight: 600; padding: 12px 16px;"><?php esc_html_e( 'Licensed Item', 'ai-search' ); ?></td>
									<td style="padding: 12px 16px;"><?php echo esc_html( $license_data['item_name'] ); ?></td>
								</tr>
								<?php endif; ?>
								<?php if ( ! empty( $license_data['supported_until'] ) ) : ?>
								<tr>
									<td style="font-weight: 600; padding: 12px 16px;"><?php esc_html_e( 'Support Active Until', 'ai-search' ); ?></td>
									<td style="padding: 12px 16px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $license_data['supported_until'] ) ) ); ?></td>
								</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

					<div class="ai-form-row">
						<button type="button" id="ai-deactivate-license-btn" class="button button-secondary" style="color: #dc2626; border-color: #fca5a5;">
							<span><?php esc_html_e( 'Deactivate License on This Site', 'ai-search' ); ?></span>
						</button>
						<div id="ai-license-feedback" class="ai-feedback-box" style="display:none; margin-top:12px;"></div>
					</div>

				<?php else : ?>

					<p class="ai-card-intro"><?php esc_html_e( 'Activate your plugin by entering your Envato item purchase code. Activation unlocks all AI search features, custom configurations, and official product support.', 'ai-search' ); ?></p>


					<div class="ai-form-row">
						<label for="ai_search_houzi_license_key" style="font-weight:600; display:block; margin-bottom:6px;"><?php esc_html_e( 'Item Purchase Code / License Key', 'ai-search' ); ?> <span style="color:#ef4444;">*</span></label>
						<input type="text" id="ai_search_houzi_license_key" name="ai_search_houzi_license_key" class="regular-text ai-input" style="width:100%; max-width:550px;" value="" placeholder="e.g. 12345678-abcd-1234-abcd-1234567890ab" autocomplete="off" />
						<p class="description" style="margin-top:6px;">
							<?php esc_html_e( 'You can find your purchase code in your Envato / ThemeForest Downloads page.', 'ai-search' ); ?>
							<a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Where is my purchase code?', 'ai-search' ); ?> &rarr;</a>
						</p>
					</div>

					<div class="ai-form-row" style="margin-top:22px;">
						<button type="button" id="ai-activate-license-btn" class="button button-primary ai-btn-accent" style="padding:6px 20px; font-weight:600;">
							<span><?php esc_html_e( 'Verify & Activate License', 'ai-search' ); ?></span>
						</button>
						<div id="ai-license-feedback" class="ai-feedback-box" style="display:none; margin-top:12px;"></div>
					</div>

				<?php endif; ?>

			</div>
		</div>
	</div>

	<!-- RIGHT COLUMN: SIDEBAR CARDS -->
	<div class="ai-admin-side-col">
		<?php include __DIR__ . '/flyer-houzi.php'; ?>
	</div>
</div>


