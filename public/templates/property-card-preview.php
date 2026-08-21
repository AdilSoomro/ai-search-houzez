<?php
/**
 * Property Preview Card Template for AI Search Results
 *
 * @package    AI_Search
 * @subpackage AI_Search/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

$prop_id    = get_the_ID();
$title      = get_the_title();
$permalink  = get_permalink();
$thumb_url  = get_the_post_thumbnail_url( $prop_id, 'houzez-item-image-1' );
if ( empty( $thumb_url ) ) {
	$thumb_url = get_the_post_thumbnail_url( $prop_id, 'medium_large' ) ?: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=600&q=80';
}

$price = '';
if ( function_exists( 'houzez_listing_price_v1' ) ) {
	ob_start();
	houzez_listing_price_v1();
	$price = ob_get_clean();
}
if ( empty( $price ) && function_exists( 'houzez_get_property_price' ) ) {
	$price = houzez_get_property_price( $prop_id );
}
if ( empty( $price ) ) {
	$raw_price = get_post_meta( $prop_id, 'fave_property_price', true );
	$price = ! empty( $raw_price ) ? '$' . number_format_i18n( (float) $raw_price ) : '';
}

$bedrooms  = get_post_meta( $prop_id, 'fave_property_bedrooms', true );
$bathrooms = get_post_meta( $prop_id, 'fave_property_bathrooms', true );
$area_size = get_post_meta( $prop_id, 'fave_property_size', true );
$area_unit = get_post_meta( $prop_id, 'fave_property_size_prefix', true ) ?: 'Sq Ft';
$address   = get_post_meta( $prop_id, 'fave_property_map_address', true );

$types    = wp_get_post_terms( $prop_id, 'property_type' );
$statuses = wp_get_post_terms( $prop_id, 'property_status' );
$type_name   = ( ! empty( $types ) && ! is_wp_error( $types ) ) ? $types[0]->name : '';
$status_name = ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) ? $statuses[0]->name : '';
$is_featured = ( '1' === (string) get_post_meta( $prop_id, 'fave_featured', true ) );
?>
<div class="ai-search-card" data-property-id="<?php echo esc_attr( $prop_id ); ?>">
	<div class="ai-search-card-inner">
		<div class="ai-search-card-media">
			<a href="<?php echo esc_url( $permalink ); ?>" tabindex="-1">
				<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			</a>
			<div class="ai-search-card-badges">
				<?php if ( $is_featured ) : ?>
					<span class="ai-badge ai-badge-featured"><?php esc_html_e( 'Featured', 'ai-search' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $status_name ) ) : ?>
					<span class="ai-badge ai-badge-status"><?php echo esc_html( $status_name ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $type_name ) ) : ?>
					<span class="ai-badge ai-badge-type"><?php echo esc_html( $type_name ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $price ) ) : ?>
				<div class="ai-search-card-price"><?php echo wp_kses_post( $price ); ?></div>
			<?php endif; ?>
		</div>
		<div class="ai-search-card-content">
			<h4 class="ai-search-card-title">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
			</h4>
			<?php if ( ! empty( $address ) ) : ?>
				<div class="ai-search-card-address">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					<span><?php echo esc_html( $address ); ?></span>
				</div>
			<?php endif; ?>
			<div class="ai-search-card-meta">
				<?php if ( ! empty( $bedrooms ) ) : ?>
					<div class="ai-meta-item">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/></svg>
						<span><strong><?php echo esc_html( $bedrooms ); ?></strong> <?php esc_html_e( 'Beds', 'ai-search' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $bathrooms ) ) : ?>
					<div class="ai-meta-item">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h6a2 2 0 0 1 2 2v1H7V8a2 2 0 0 1 2-2zM4 11h16a1 1 0 0 1 1 1v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3a1 1 0 0 1 1-1zM6 19v2M18 19v2"/></svg>
						<span><strong><?php echo esc_html( $bathrooms ); ?></strong> <?php esc_html_e( 'Baths', 'ai-search' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $area_size ) ) : ?>
					<div class="ai-meta-item">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
						<span><strong><?php echo esc_html( $area_size ); ?></strong> <?php echo esc_html( $area_unit ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
