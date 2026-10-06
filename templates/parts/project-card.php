<?php
/**
 * Template-Part: Bauprojekt-Card.
 *
 * Gemeinsames Markup für das Projekt-Archiv, den Shortcode [immo_projects]
 * und das Elementor-Widget „Bauprojekte".
 *
 * Erwartete Variable:
 * @var array<string, mixed> $project Formatiertes Projekt-Array aus RestApi::format_project().
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$meta      = $project['meta'] ?? array();
$img       = $project['featured_image'] ?? null;
$stats     = $project['unit_stats'] ?? array();
$status    = (string) ( $meta['project_status'] ?? '' );
$permalink = $project['permalink'] ?? '#';
$location  = trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) );

$status_labels = array(
	'planning'  => __( 'In Planung', 'immo-manager' ),
	'building'  => __( 'In Bau', 'immo-manager' ),
	'completed' => __( 'Fertiggestellt', 'immo-manager' ),
);

$area_label = '';
$amin       = (float) ( $stats['area_min'] ?? 0 );
$amax       = (float) ( $stats['area_max'] ?? 0 );
if ( $amin > 0 && $amax > 0 ) {
	$area_label = ( abs( $amax - $amin ) < 0.5 )
		? number_format_i18n( $amin, 0 ) . ' m²'
		: number_format_i18n( $amin, 0 ) . ' – ' . number_format_i18n( $amax, 0 ) . ' m²';
}
?>
<article class="immo-property-card immo-project-card-item" role="listitem" data-project-id="<?php echo esc_attr( (string) ( $project['id'] ?? 0 ) ); ?>">
	<a href="<?php echo esc_url( $permalink ); ?>" class="immo-card-link" tabindex="-1" aria-hidden="true">
		<div class="immo-card-image">
			<?php if ( $img ) : ?>
				<img
					src="<?php echo esc_url( $img['url_large'] ?? $img['url_medium'] ?? $img['url'] ); ?>"
					alt="<?php echo esc_attr( $img['alt'] ?: ( $project['title'] ?? '' ) ); ?>"
					loading="lazy"
					width="800"
					height="600"
				>
			<?php else : ?>
				<div class="immo-card-no-image">🏗️</div>
			<?php endif; ?>
			<?php if ( $status ) : ?>
				<span class="immo-status-badge status-available immo-project-status-<?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
				</span>
			<?php endif; ?>
		</div>
	</a>

	<div class="immo-card-body">
		<h3 class="immo-card-title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $project['title'] ?? '' ); ?></a>
		</h3>

		<?php if ( $location ) : ?>
			<p class="immo-card-location">
				<span aria-hidden="true">📍</span>
				<?php echo esc_html( $location ); ?>
				<?php if ( ! empty( $meta['region_state_label'] ) ) : ?>
					<span class="immo-card-region">, <?php echo esc_html( $meta['region_state_label'] ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $stats['total'] ) ) : ?>
			<ul class="immo-card-facts" aria-label="<?php esc_attr_e( 'Eckdaten', 'immo-manager' ); ?>">
				<li>
					<span aria-hidden="true">🏘️</span>
					<?php
					printf(
						/* translators: %d: Anzahl Wohneinheiten */
						esc_html( _n( '%d Einheit', '%d Einheiten', (int) $stats['total'], 'immo-manager' ) ),
						(int) $stats['total']
					);
					?>
				</li>
				<?php if ( $area_label ) : ?>
					<li><span aria-hidden="true">📐</span> <?php echo esc_html( $area_label ); ?></li>
				<?php endif; ?>
				<?php if ( ! empty( $stats['available'] ) ) : ?>
					<li>
						<span aria-hidden="true">✅</span>
						<?php
						printf(
							/* translators: %d: Anzahl verfügbarer Wohneinheiten */
							esc_html__( '%d verfügbar', 'immo-manager' ),
							(int) $stats['available']
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<div class="immo-card-footer">
			<a href="<?php echo esc_url( $permalink ); ?>" class="immo-btn immo-btn-primary immo-btn-sm">
				<?php esc_html_e( 'Projekt ansehen', 'immo-manager' ); ?>
			</a>
		</div>
	</div>
</article>
