<?php
/**
 * Template: Bauprojekte-Archiv.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

get_header();

$rest    = \ImmoManager\Plugin::instance()->get_rest_api();
$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/projects' );
$request->set_query_params( array( 'per_page' => 12 ) );
$data     = $rest->get_projects( $request )->get_data();
$projects = $data['projects'] ?? array();
$currency = \ImmoManager\Settings::get( 'currency_symbol', '€' );
?>

	<div class="immo-archive-wrapper">
		<h1 class="immo-archive-title">
			<?php echo esc_html( post_type_archive_title( '', false ) ?: __( 'Bauprojekte', 'immo-manager' ) ); ?>
		</h1>

		<?php if ( empty( $projects ) ) : ?>
			<p class="immo-no-results"><?php esc_html_e( 'Keine Bauprojekte gefunden.', 'immo-manager' ); ?></p>
		<?php else : ?>
			<div class="immo-projects-grid immo-widget-grid immo-widget-cols-3">
				<?php foreach ( $projects as $proj ) :
					$meta   = $proj['meta'] ?? array();
					$img    = $proj['featured_image'] ?? null;
					$stats  = $proj['unit_stats'] ?? array();
					$status_labels = array(
						'planning'  => __( 'In Planung', 'immo-manager' ),
						'building'  => __( 'In Bau', 'immo-manager' ),
						'completed' => __( 'Fertiggestellt', 'immo-manager' ),
					);
					?>
					<article class="immo-property-card">
						<?php if ( $img ) : ?>
							<a href="<?php echo esc_url( $proj['permalink'] ?? '#' ); ?>" class="immo-card-link" tabindex="-1" aria-hidden="true">
								<div class="immo-card-image">
										<img
											src="<?php echo esc_url( $img['url_large'] ?? $img['url_medium'] ?? $img['url'] ); ?>"
											alt="<?php echo esc_attr( $img['alt'] ?: $proj['title'] ); ?>"
											loading="lazy"
											width="800"
											height="600"
										>
									<span class="immo-status-badge status-available">
										<?php echo esc_html( $status_labels[ $meta['project_status'] ?? '' ] ?? '' ); ?>
									</span>
								</div>
							</a>
						<?php endif; ?>
						<div class="immo-card-body">
							<h3 class="immo-card-title">
								<a href="<?php echo esc_url( $proj['permalink'] ?? '#' ); ?>"><?php echo esc_html( $proj['title'] ?? '' ); ?></a>
							</h3>
							<?php if ( $meta['city'] ) : ?>
								<p class="immo-card-location">📍 <?php echo esc_html( trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ) ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $stats['total'] ) ) : ?>
								<ul class="immo-card-facts">
									<li>🏘️ <?php printf( esc_html__( '%d Einheiten', 'immo-manager' ), (int) $stats['total'] ); ?></li>
									<?php
									$amin = (float) ( $stats['area_min'] ?? 0 );
									$amax = (float) ( $stats['area_max'] ?? 0 );
									if ( $amin > 0 && $amax > 0 ) :
										$area_label = ( abs( $amax - $amin ) < 0.5 )
											? number_format_i18n( $amin, 0 ) . ' m²'
											: number_format_i18n( $amin, 0 ) . ' – ' . number_format_i18n( $amax, 0 ) . ' m²';
									?>
										<li>📐 <?php echo esc_html( $area_label ); ?></li>
									<?php endif; ?>
									<?php if ( $stats['available'] ) : ?>
										<li>✅ <?php printf( esc_html__( '%d verfügbar', 'immo-manager' ), (int) $stats['available'] ); ?></li>
									<?php endif; ?>
								</ul>
							<?php endif; ?>
							<div class="immo-card-footer">
								<a href="<?php echo esc_url( $proj['permalink'] ?? '#' ); ?>" class="immo-btn immo-btn-primary immo-btn-sm">
									<?php esc_html_e( 'Projekt ansehen', 'immo-manager' ); ?>
								</a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

<?php get_footer(); ?>
