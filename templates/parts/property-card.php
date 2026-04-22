<?php
/**
 * Template-Part: Einzelne Property-Card (für Grid & List-Layout).
 *
 * Erwartete Variable:
 * @var array<string, mixed> $property  Formatiertes Property-Array aus der REST API.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$meta         = $property['meta'] ?? array();
$status       = $meta['status'] ?? 'available';
$mode         = $meta['mode'] ?? 'sale';
$price        = (float) ( $meta['price'] ?? 0 );
$rent         = (float) ( $meta['rent'] ?? 0 );
$display_price = $mode === 'rent' && $rent > 0
	? ( $meta['rent_formatted'] ?? '' )
	: ( $meta['price_formatted'] ?? '' );
$price_suffix = $mode === 'rent' ? ' / ' . __( 'Monat', 'immo-manager' ) : '';

$status_labels = array(
	'available' => array( 'label' => __( 'Verfügbar', 'immo-manager' ), 'class' => 'available' ),
	'reserved'  => array( 'label' => __( 'Reserviert', 'immo-manager' ), 'class' => 'reserved' ),
	'sold'      => array( 'label' => __( 'Verkauft', 'immo-manager' ),   'class' => 'sold' ),
	'rented'    => array( 'label' => __( 'Vermietet', 'immo-manager' ),  'class' => 'sold' ),
);
$status_info = $status_labels[ $status ] ?? $status_labels['available'];

$top_features    = array_slice( $meta['features_detail'] ?? array(), 0, 3 );
$location_parts  = array_filter( array( $meta['postal_code'] ?? '', $meta['city'] ?? '' ) );
$location        = implode( ' ', $location_parts );
$image           = $property['featured_image'] ?? null;
?>
<article class="immo-property-card" role="listitem" data-property-id="<?php echo esc_attr( (string) $property['id'] ); ?>">
	<a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>" class="immo-card-link" tabindex="-1" aria-hidden="true">
		<div class="immo-card-image">
			<?php if ( $image ) : ?>
				<img
					src="<?php echo esc_url( $image['url_large'] ?? $image['url_medium'] ?? $image['url'] ); ?>"
					alt="<?php echo esc_attr( $image['alt'] ?: $property['title'] ); ?>"
					loading="lazy"
					width="800"
					height="600"
				>
			<?php else : ?>
				<div class="immo-card-no-image">🏠</div>
			<?php endif; ?>
			<span class="immo-status-badge status-<?php echo esc_attr( $status_info['class'] ); ?>">
				<?php echo esc_html( $status_info['label'] ); ?>
			</span>
			<?php if ( $meta['property_type'] ) : ?>
				<span class="immo-type-badge"><?php echo esc_html( $meta['property_type'] ); ?></span>
			<?php endif; ?>
		</div>
	</a>

	<div class="immo-card-body">
		<h3 class="immo-card-title">
			<a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>">
				<?php echo esc_html( $property['title'] ?? '' ); ?>
			</a>
		</h3>

		<?php if ( $location ) : ?>
			<p class="immo-card-location">
				<span aria-hidden="true">📍</span>
				<?php echo esc_html( $location ); ?>
				<?php if ( $meta['region_state_label'] ) : ?>
					<span class="immo-card-region">, <?php echo esc_html( $meta['region_state_label'] ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $display_price ) : ?>
			<p class="immo-card-price">
				<strong><?php echo esc_html( $display_price . $price_suffix ); ?></strong>
			</p>
		<?php endif; ?>

		<ul class="immo-card-facts" aria-label="<?php esc_attr_e( 'Eckdaten', 'immo-manager' ); ?>">
			<?php if ( $meta['rooms'] ) : ?>
				<li><span aria-hidden="true">🛏️</span> <?php echo esc_html( $meta['rooms'] . ' ' . _n( 'Zimmer', 'Zimmer', (int) $meta['rooms'], 'immo-manager' ) ); ?></li>
			<?php endif; ?>
			<?php if ( $meta['area'] ) : ?>
				<li><span aria-hidden="true">📐</span> <?php echo esc_html( number_format_i18n( (float) $meta['area'], 0 ) . ' m²' ); ?></li>
			<?php endif; ?>
			<?php if ( $meta['energy_class'] ) : ?>
				<li><span aria-hidden="true">⚡</span> <?php echo esc_html( $meta['energy_class'] ); ?></li>
			<?php endif; ?>
		</ul>

		<?php if ( ! empty( $top_features ) ) : ?>
			<ul class="immo-card-features" aria-label="<?php esc_attr_e( 'Ausstattung', 'immo-manager' ); ?>">
				<?php foreach ( $top_features as $feature ) : ?>
					<li title="<?php echo esc_attr( $feature['label'] ); ?>">
						<span aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></span>
						<span class="sr-only"><?php echo esc_html( $feature['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="immo-card-footer">
			<a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>" class="immo-btn immo-btn-primary immo-btn-sm">
				<?php esc_html_e( 'Details ansehen', 'immo-manager' ); ?>
			</a>
		</div>
	</div>
</article>
