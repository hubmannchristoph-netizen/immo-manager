<?php
/**
 * Template: Einzelnes Bauprojekt – neu formatiert.
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

get_header();

$post_id = get_the_ID();
$post    = get_post( $post_id );

if ( ! $post || 'publish' !== $post->post_status ) {
	echo '<p>' . esc_html__( 'Dieses Bauprojekt ist nicht mehr verfügbar.', 'immo-manager' ) . '</p>';
	get_footer();
	return;
}

$rest       = \ImmoManager\Plugin::instance()->get_rest_api();
$req        = new \WP_REST_Request( 'GET', "/immo-manager/v1/projects/{$post_id}" );
$req->set_url_params( array( 'id' => $post_id ) );
$project    = $rest->get_project( $req )->get_data();

$ureq       = new \WP_REST_Request( 'GET', "/immo-manager/v1/projects/{$post_id}/units" );
$ureq->set_url_params( array( 'id' => $post_id ) );
$udata      = $rest->get_project_units( $ureq )->get_data();
$units      = $udata['units'] ?? array();
$unit_stats = $udata['stats'] ?? array();

$meta     = $project['meta'] ?? array();
$currency = \ImmoManager\Settings::get( 'currency_symbol', '€' );
$api_base = rest_url( \ImmoManager\RestApi::NAMESPACE );
$nonce    = wp_create_nonce( 'wp_rest' );
$has_map  = \ImmoManager\Settings::get( 'map_enabled', 1 ) && ! empty( $meta['lat'] ) && ! empty( $meta['lng'] );

$proj_status_labels = array(
	'planning'  => __( 'In Planung', 'immo-manager' ),
	'building'  => __( 'In Bau', 'immo-manager' ),
	'completed' => __( 'Fertiggestellt', 'immo-manager' ),
);
$unit_status_labels = array(
	'available' => __( 'Verfügbar', 'immo-manager' ),
	'reserved'  => __( 'Reserviert', 'immo-manager' ),
	'sold'      => __( 'Verkauft', 'immo-manager' ),
	'rented'    => __( 'Vermietet', 'immo-manager' ),
);
$unit_status_class = array(
	'available' => 'status-available',
	'reserved'  => 'status-reserved',
	'sold'      => 'status-sold',
	'rented'    => 'status-sold',
);

$gallery  = $project['gallery'] ?? array();
$all_imgs = array();
if ( $project['featured_image'] ) {
	$all_imgs[] = $project['featured_image'];
}
if ( ! empty( $gallery ) ) {
	$all_imgs = array_merge( $all_imgs, $gallery );
}
// Layout-Konfiguration (mit Fallback auf globale Einstellungen).
$detail_layout = ( ! empty( $meta['layout_type'] ) ) ? $meta['layout_type'] : \ImmoManager\Settings::get( 'default_detail_layout', 'standard' );
$gallery_type  = ( ! empty( $meta['gallery_type'] ) ) ? $meta['gallery_type'] : \ImmoManager\Settings::get( 'default_gallery_type', 'slider' );
$hero_type     = ( ! empty( $meta['hero_type'] ) ) ? $meta['hero_type'] : \ImmoManager\Settings::get( 'default_hero_type', 'full' );
?>

<div class="immo-single-wrapper">
<div class="immo-detail immo-layout-<?php echo esc_attr( $detail_layout ); ?> immo-hero-<?php echo esc_attr( $hero_type ); ?>" data-api-base="<?php echo esc_url( $api_base ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

	<!-- HERO: Slider + Sticky Sidebar -->
	<div class="immo-detail-hero-layout">

		<!-- Bild-Bereich -->
		<?php if ( ! empty( $all_imgs ) ) : ?>
			<div class="immo-gallery-container immo-gallery-type-<?php echo esc_attr( $gallery_type ); ?>">
				
				<?php if ( 'slider' === $gallery_type ) : ?>
					<!-- SLIDER-ANSICHT -->
					<div class="immo-slider">
						<span class="immo-slider-badge status-available">
							<?php echo esc_html( $proj_status_labels[ $meta['project_status'] ?? '' ] ?? '' ); ?>
						</span>
						<div class="immo-slider-main">
							<div class="immo-slider-track">
								<?php foreach ( $all_imgs as $i => $img ) : ?>
									<div class="immo-slide <?php echo 0 === $i ? 'active' : ''; ?>" aria-hidden="<?php echo 0 === $i ? 'false' : 'true'; ?>">
										<img src="<?php echo esc_url( $img['url_large'] ); ?>"
											data-full="<?php echo esc_url( $img['url'] ); ?>"
											alt="<?php echo esc_attr( $img['alt'] ?: $project['title'] ); ?>"
											loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
											class="immo-slide-img">
									</div>
								<?php endforeach; ?>
							</div>
							<?php if ( count( $all_imgs ) > 1 ) : ?>
								<button class="immo-slider-nav immo-slider-prev" aria-label="<?php esc_attr_e( 'Vorheriges', 'immo-manager' ); ?>">&#8249;</button>
								<button class="immo-slider-nav immo-slider-next" aria-label="<?php esc_attr_e( 'Nächstes', 'immo-manager' ); ?>">&#8250;</button>
								<div class="immo-slider-counter">
									<span class="immo-slider-current">1</span> / <span><?php echo count( $all_imgs ); ?></span>
								</div>
							<?php endif; ?>
							<button class="immo-slider-expand" aria-label="<?php esc_attr_e( 'Vollbild', 'immo-manager' ); ?>">⤢</button>
						</div>
						<?php if ( count( $all_imgs ) > 1 ) : ?>
							<div class="immo-slider-thumbs">
								<?php foreach ( $all_imgs as $i => $img ) : ?>
									<button class="immo-slider-thumb <?php echo 0 === $i ? 'active' : ''; ?>" data-index="<?php echo esc_attr( (string) $i ); ?>">
										<img src="<?php echo esc_url( $img['url_thumbnail'] ); ?>" alt="" loading="lazy" width="80" height="56">
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<!-- GRID-ANSICHT -->
					<div class="immo-grid-gallery">
						<?php foreach ( array_slice( $all_imgs, 0, 5 ) as $i => $img ) : ?>
							<div class="immo-grid-item immo-grid-item-<?php echo $i; ?>">
								<img src="<?php echo esc_url( $img['url_large'] ); ?>"
									data-full="<?php echo esc_url( $img['url'] ); ?>"
									alt="<?php echo esc_attr( $img['alt'] ?: ( $project['title'] ?? '' ) ); ?>"
									class="immo-grid-img">
								<?php if ( 4 === $i && count( $all_imgs ) > 5 ) : ?>
									<div class="immo-grid-overlay">
										<span>+<?php echo count( $all_imgs ) - 5; ?></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>
		<?php else : ?>
			<!-- Platzhalter für fehlende Bilder -->
			<div class="immo-slider immo-slider-placeholder">
				<div class="immo-slider-main">
					<div class="immo-slider-track">
						<div class="immo-slide active" aria-hidden="false">
							<div class="immo-card-no-image">🏗️</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- RECHTE SPALTE: Projekt-Info + CTA -->
		<aside class="immo-detail-sticky-sidebar">
			<div class="immo-sticky-header">
				<h1 class="immo-detail-title"><?php echo esc_html( $project['title'] ?? '' ); ?></h1>
				<?php if ( $meta['city'] ) : ?>
					<p class="immo-detail-location">📍 <?php echo esc_html( trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) . ', ' . ( $meta['region_state_label'] ?? '' ) ) ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Projekt-Status Kacheln -->
			<?php if ( ! empty( $unit_stats['total'] ) ) : ?>
				<div class="immo-proj-stats">
					<?php foreach ( array( 'available' => '✅', 'reserved' => '⏳', 'sold' => '🔴', 'rented' => '📋', 'total' => '🏘️' ) as $key => $icon ) :
						if ( empty( $unit_stats[ $key ] ) ) { continue; }
						?>
						<div class="immo-proj-stat">
							<span><?php echo esc_html( $icon ); ?></span>
							<strong><?php echo (int) $unit_stats[ $key ]; ?></strong>
							<small><?php echo esc_html( $unit_status_labels[ $key ] ?? __( 'Gesamt', 'immo-manager' ) ); ?></small>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Fertigstellung -->
			<?php if ( $meta['project_completion'] || $meta['project_start_date'] ) : ?>
				<ul class="immo-keyfacts">
					<?php if ( $meta['project_start_date'] ) : ?>
						<li class="immo-keyfact">
							<span class="immo-keyfact-icon">🏗️</span>
							<span class="immo-keyfact-label"><?php esc_html_e( 'Baubeginn', 'immo-manager' ); ?></span>
							<span class="immo-keyfact-value"><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $meta['project_start_date'] ) ) ); ?></span>
						</li>
					<?php endif; ?>
					<?php if ( $meta['project_completion'] ) : ?>
						<li class="immo-keyfact">
							<span class="immo-keyfact-icon">🏁</span>
							<span class="immo-keyfact-label"><?php esc_html_e( 'Fertigstellung', 'immo-manager' ); ?></span>
							<span class="immo-keyfact-value"><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $meta['project_completion'] ) ) ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>

			<!-- CTA -->
			<div class="immo-sticky-cta">
				<?php if ( $meta['contact_name'] ) : ?>
					<div class="immo-sticky-agent" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
						<?php if ( ! empty( $meta['contact_image'] ) ) : ?>
							<img src="<?php echo esc_url( $meta['contact_image']['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $meta['contact_name'] ); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--immo-accent); box-shadow: var(--immo-shadow-sm);">
						<?php endif; ?>
						<div>
							<strong><?php echo esc_html( $meta['contact_name'] ); ?></strong>
							<?php if ( $meta['contact_phone'] ) : ?>
								<br><a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-contact-phone">📞 <?php echo esc_html( $meta['contact_phone'] ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
				<button type="button" class="immo-btn immo-btn-primary immo-btn-inquiry-open"
					data-property-id="<?php echo esc_attr( (string) $post_id ); ?>"
					data-property-title="<?php echo esc_attr( $project['title'] ?? '' ); ?>"
					data-agent-name="<?php echo esc_attr( $meta['contact_name'] ?? '' ); ?>"
					data-agent-email="<?php echo esc_attr( $meta['contact_email'] ?? '' ); ?>"
					data-agent-phone="<?php echo esc_attr( $meta['contact_phone'] ?? '' ); ?>">
					✉️ <?php esc_html_e( 'Anfrage senden', 'immo-manager' ); ?>
				</button>
				<?php if ( $meta['contact_phone'] ) : ?>
					<a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-btn immo-btn-call">
						📞 <?php esc_html_e( 'Jetzt anrufen', 'immo-manager' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</aside>
	</div><!-- .immo-detail-hero-layout -->

	<!-- ZWEISPALTIGER CONTENT-BEREICH -->
	<div class="immo-detail-twocol">

		<!-- LINKE SPALTE: Akkordeon -->
		<div class="immo-detail-content">

			<?php if ( ! empty( $project['description'] ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="true"><?php esc_html_e( 'Projektbeschreibung', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body"><div class="immo-detail-text"><?php echo wp_kses_post( $project['description'] ); ?></div></div>
				</div>
			<?php endif; ?>

			<!-- Wohneinheiten-Tabelle -->
			<?php if ( ! empty( $units ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="true">
						<?php esc_html_e( 'Wohneinheiten', 'immo-manager' ); ?>
						<span class="immo-accordion-badge"><?php echo count( $units ); ?></span>
						<span class="immo-accordion-icon" aria-hidden="true"></span>
					</button>
					<div class="immo-accordion-body">
						<div class="immo-units-grid">
							<?php foreach ( $units as $unit ) :
								$sc = $unit_status_class[ $unit['status'] ] ?? '';
								$sl = $unit_status_labels[ $unit['status'] ] ?? $unit['status'];
								$price_display = '';
								if ( (float) $unit['price'] > 0 ) { $price_display = $unit['price_formatted']; }
								elseif ( (float) $unit['rent'] > 0 ) { $price_display = $unit['rent_formatted'] . '/Mo'; }
								?>
								<div class="immo-unit-card">
									<?php if ( ! empty( $unit['floor_plan'] ) ) : ?>
										<div class="immo-unit-card-img">
											<button type="button" class="immo-unit-image-trigger" data-full="<?php echo esc_url( $unit['floor_plan']['url'] ); ?>" data-alt="<?php printf( esc_attr__( 'Grundriss %s', 'immo-manager' ), $unit['unit_number'] ); ?>" aria-label="<?php esc_attr_e( 'Wohneinheit-Bild vergrößern', 'immo-manager' ); ?>">
										<img src="<?php echo esc_url( $unit['floor_plan']['url_thumbnail'] ); ?>"
												alt="<?php printf( esc_attr__( 'Grundriss %s', 'immo-manager' ), $unit['unit_number'] ); ?>"
												loading="lazy">
											</button>
										</div>
									<?php endif; ?>
									<div class="immo-unit-card-body">
										<div class="immo-unit-card-header">
											<strong class="immo-unit-number"><?php echo esc_html( $unit['unit_number'] ); ?></strong>
											<span class="immo-unit-status-pill <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $sl ); ?></span>
										</div>
										<ul class="immo-unit-facts">
											<?php if ( $unit['floor'] ) : ?><li>🏢 <?php printf( esc_html__( '%d. Etage', 'immo-manager' ), (int) $unit['floor'] ); ?></li><?php endif; ?>
											<?php if ( $unit['area'] ) : ?><li>📐 <?php echo esc_html( number_format_i18n( (float) $unit['area'], 0 ) . ' m²' ); ?></li><?php endif; ?>
											<?php if ( $unit['rooms'] ) : ?><li>🛏️ <?php echo esc_html( $unit['rooms'] ); ?> Zi.</li><?php endif; ?>
										</ul>
										<?php if ( $price_display ) : ?>
											<p class="immo-unit-price"><?php echo esc_html( $price_display ); ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $unit['property'] ) ) : ?>
											<button type="button" class="immo-btn immo-btn-secondary immo-quick-info-btn" style="width: 100%; margin-top: 15px; padding: 0.4rem; font-size: 0.85em; display: flex; justify-content: center; gap: 6px; align-items: center;" data-property="<?php echo esc_attr( wp_json_encode( $unit['property'] ) ); ?>">
												🔍 <?php esc_html_e( 'Immobilien-Details', 'immo-manager' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Ausstattung -->
			<?php $feat = $meta['features_detail'] ?? array(); if ( ! empty( $feat ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Gemeinschafts-Ausstattung', 'immo-manager' ); ?><span class="immo-accordion-badge"><?php echo count( $feat ); ?></span><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<ul class="immo-features-list">
							<?php foreach ( $feat as $f ) : ?>
								<li><span aria-hidden="true"><?php echo esc_html( $f['icon'] ); ?></span> <?php echo esc_html( $f['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $meta['documents'] ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Dokumente & Exposé', 'immo-manager' ); ?><span class="immo-accordion-badge"><?php echo count( $meta['documents'] ); ?></span><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<ul class="immo-documents-list" style="list-style: none; padding: 0; margin: 0;">
							<?php foreach ( $meta['documents'] as $doc ) : ?>
								<li style="margin-bottom: 12px; border: 1px solid var(--immo-border); border-radius: var(--immo-radius-sm); padding: 10px;">
									<a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--immo-text);">
										<span style="font-size: 1.5em; color: var(--immo-accent);">📄</span>
										<span style="font-weight: 500; font-size: 0.95em; word-break: break-all; flex: 1;"><?php echo esc_html( $doc['title'] ); ?></span>
										<span style="font-size: 0.85em; color: var(--immo-accent);"><?php esc_html_e( 'Ansehen', 'immo-manager' ); ?> &rarr;</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $meta['video_url'] ) || ! empty( $meta['video_file_url'] ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Video / Virtuelle Tour', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden style="padding: 1.5rem;">
						<?php if ( ! empty( $meta['video_url'] ) ) : ?>
							<div class="immo-video-embed" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: var(--immo-radius-sm); background: #000;">
								<?php 
								$embed = wp_oembed_get( $meta['video_url'], array( 'width' => 800 ) );
								if ( $embed ) {
									// Make iframe responsive
									echo str_replace( '<iframe', '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"', $embed );
								} else {
									echo '<a href="' . esc_url( $meta['video_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Video ansehen', 'immo-manager' ) . '</a>';
								}
								?>
							</div>
						<?php elseif ( ! empty( $meta['video_file_url'] ) ) : ?>
							<video controls style="width: 100%; max-height: 500px; border-radius: var(--immo-radius-sm); background: #000;">
								<source src="<?php echo esc_url( $meta['video_file_url'] ); ?>">
								<?php esc_html_e( 'Dein Browser unterstützt das Video-Tag nicht.', 'immo-manager' ); ?>
							</video>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Lage -->
			<?php if ( $has_map || $meta['address'] ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Lage & Karte', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<?php if ( $meta['address'] ) : ?>
							<p class="immo-detail-address">📍 <?php echo esc_html( implode( ', ', array_filter( array( $meta['address'], trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ) ) ) ) ); ?></p>
						<?php endif; ?>
						<?php if ( $has_map ) : ?>
							<div class="immo-map" id="immo-map-<?php echo esc_attr( (string) $post_id ); ?>"
								data-lat="<?php echo esc_attr( (string) $meta['lat'] ); ?>"
								data-lng="<?php echo esc_attr( (string) $meta['lng'] ); ?>"
								data-title="<?php echo esc_attr( $project['title'] ); ?>"
								style="height:300px;border-radius:var(--immo-radius);margin-top:12px;">
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// === Nebenkosten- & Finanzierungsrechner (am Ende der Akkordeons) ===
			$calc_units = array();
			foreach ( $units as $u ) {
				if ( ! empty( $u['price'] ) && (float) $u['price'] > 0 ) {
					$prop = $u['property'] ?? array();
					$calc_units[] = array(
						'id'              => (int) $u['id'],
						'price'           => (float) $u['price'],
						'commission_free' => (bool) ( $prop['commission_free'] ?? false ),
						'label'           => sprintf(
							/* translators: 1: Wohneinheits-Nummer, 2: Fläche in m², 3: formatierter Preis */
							__( '%1$s — %2$s m² — %3$s', 'immo-manager' ),
							esc_html( (string) $u['unit_number'] ),
							number_format_i18n( (float) $u['area'], 0 ),
							esc_html( (string) $u['price_formatted'] )
						),
					);
				}
			}
			if ( ! empty( $calc_units ) ) {
				$first = $calc_units[0];
				$calc_context = array(
					'base_price'      => $first['price'],
					'commission_free' => $first['commission_free'],
					'units'           => $calc_units,
				);
				include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/calculator.php';
			}
			?>
		</div><!-- .immo-detail-content -->

		<!-- RECHTE SPALTE: Info-Box -->
		<aside class="immo-detail-info-sidebar">
			<div class="immo-info-box">
				<h3><?php esc_html_e( 'Projekt-Details', 'immo-manager' ); ?></h3>
				<table class="immo-costs-table">
					<?php if ( $meta['project_status'] ) : ?><tr><td><?php esc_html_e( 'Status', 'immo-manager' ); ?></td><td><?php echo esc_html( $proj_status_labels[ $meta['project_status'] ] ?? '' ); ?></td></tr><?php endif; ?>
					<?php if ( $meta['city'] ) : ?><tr><td><?php esc_html_e( 'Standort', 'immo-manager' ); ?></td><td><?php echo esc_html( trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ) ); ?></td></tr><?php endif; ?>
					<?php if ( ! empty( $unit_stats['total'] ) ) : ?><tr><td><?php esc_html_e( 'Einheiten', 'immo-manager' ); ?></td><td><?php echo (int) $unit_stats['total']; ?></td></tr><?php endif; ?>
					<?php if ( ! empty( $unit_stats['available'] ) ) : ?><tr><td><?php esc_html_e( 'Verfügbar', 'immo-manager' ); ?></td><td><span class="immo-unit-status-pill status-available"><?php echo (int) $unit_stats['available']; ?></span></td></tr><?php endif; ?>
					<?php if ( $meta['project_completion'] ) : ?><tr><td><?php esc_html_e( 'Fertigstellung', 'immo-manager' ); ?></td><td><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $meta['project_completion'] ) ) ); ?></td></tr><?php endif; ?>
				</table>
			</div>

			<?php if ( $meta['contact_name'] || $meta['contact_email'] ) : ?>
				<div class="immo-info-box">
					<h3><?php esc_html_e( 'Kontakt', 'immo-manager' ); ?></h3>
					<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
						<?php if ( ! empty( $meta['contact_image'] ) ) : ?>
							<img src="<?php echo esc_url( $meta['contact_image']['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $meta['contact_name'] ); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--immo-accent); box-shadow: var(--immo-shadow-sm);">
						<?php endif; ?>
						<div>
							<?php if ( $meta['contact_name'] ) : ?><p style="margin: 0;"><strong><?php echo esc_html( $meta['contact_name'] ); ?></strong></p><?php endif; ?>
							<?php if ( $meta['contact_phone'] ) : ?><p style="margin: 0;"><a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-contact-phone">📞 <?php echo esc_html( $meta['contact_phone'] ); ?></a></p><?php endif; ?>
							<?php if ( $meta['contact_email'] ) : ?><p style="margin: 0; word-break:break-all"><a href="mailto:<?php echo esc_attr( $meta['contact_email'] ); ?>" style="color:var(--immo-accent);">✉️ <?php echo esc_html( $meta['contact_email'] ); ?></a></p><?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</aside>
	</div><!-- .immo-detail-twocol -->

</div><!-- .immo-detail -->
</div><!-- .immo-single-wrapper -->

<!-- Anfrage-Lightbox -->
<div class="immo-inquiry-lightbox" id="immo-inquiry-lightbox" hidden role="dialog" aria-modal="true">
	<div class="immo-inquiry-lightbox-backdrop" id="immo-inquiry-backdrop"></div>
	<div class="immo-inquiry-lightbox-panel">
		<button class="immo-inquiry-lightbox-close" id="immo-inquiry-close" aria-label="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">✕</button>
		<h2 class="immo-inquiry-lightbox-title"><?php esc_html_e( 'Anfrage senden', 'immo-manager' ); ?></h2>
		<?php if ( $meta['contact_name'] || $meta['contact_email'] || $meta['contact_phone'] ) : ?>
			<div class="immo-inquiry-agent" style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px;">
				<?php if ( ! empty( $meta['contact_image'] ) ) : ?>
					<img src="<?php echo esc_url( $meta['contact_image']['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $meta['contact_name'] ); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--immo-accent); box-shadow: var(--immo-shadow-sm);">
				<?php endif; ?>
				<div class="immo-inquiry-agent-info">
					<?php if ( $meta['contact_name'] ) : ?><strong style="display: block; font-size: 1.1em; color: #111827;"><?php echo esc_html( $meta['contact_name'] ); ?></strong><?php endif; ?>
					<?php if ( $meta['contact_phone'] ) : ?><a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" style="color: #4b5563; text-decoration: none; font-size: 0.9em; display: inline-block; margin-top: 4px;">📞 <?php echo esc_html( $meta['contact_phone'] ); ?></a><br><?php endif; ?>
					<?php if ( $meta['contact_email'] ) : ?><a href="mailto:<?php echo esc_attr( $meta['contact_email'] ); ?>" style="color: #4b5563; text-decoration: none; font-size: 0.9em; display: inline-block; margin-top: 2px;">✉️ <?php echo esc_html( $meta['contact_email'] ); ?></a><?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<div class="immo-inquiry-success" hidden role="alert"><span>✅</span><p><?php esc_html_e( 'Vielen Dank! Ihre Anfrage wurde gesendet.', 'immo-manager' ); ?></p></div>
		<div class="immo-inquiry-error" hidden role="alert"></div>
		<div class="immo-inquiry-form" data-property-id="<?php echo esc_attr( (string) $post_id ); ?>">
			<div class="immo-form-field"><label for="inq-p-name"><?php esc_html_e( 'Ihr Name', 'immo-manager' ); ?> *</label><input type="text" id="inq-p-name" name="inquirer_name" class="immo-input" required><span class="immo-field-error" hidden></span></div>
			<div class="immo-form-field"><label for="inq-p-email"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?> *</label><input type="email" id="inq-p-email" name="inquirer_email" class="immo-input" required><span class="immo-field-error" hidden></span></div>
			<div class="immo-form-field"><label for="inq-p-phone"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></label><input type="tel" id="inq-p-phone" name="inquirer_phone" class="immo-input"></div>
		<?php if ( ! empty( $units ) ) : ?>
			<div class="immo-form-field"><label for="inq-p-unit"><?php esc_html_e( 'Bevorzugte Wohneinheit', 'immo-manager' ); ?></label><select id="inq-p-unit" name="unit_id" class="immo-input">
				<option value=""><?php esc_html_e( 'Keine Auswahl', 'immo-manager' ); ?></option>
				<?php foreach ( $units as $unit ) : ?>
					<option value="<?php echo esc_attr( (string) $unit['id'] ); ?>"><?php echo esc_html( $unit['unit_number'] ); ?><?php echo $unit['status'] ? ' – ' . esc_html( $unit_status_labels[ $unit['status'] ] ?? $unit['status'] ) : ''; ?></option>
				<?php endforeach; ?>
			</select></div>
		<?php endif; ?>
			<div class="immo-form-field"><label for="inq-p-msg"><?php esc_html_e( 'Nachricht', 'immo-manager' ); ?></label><textarea id="inq-p-msg" name="inquirer_message" class="immo-input" rows="3"></textarea></div>
			<div class="immo-form-field immo-form-field--checkbox"><label><input type="checkbox" name="consent" required><span><?php printf( esc_html__( 'Ich stimme der %s zu. *', 'immo-manager' ), '<a href="' . esc_url( get_privacy_policy_url() ?: '#' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'immo-manager' ) . '</a>' ); ?></span></label><span class="immo-field-error" hidden></span></div>
			<button type="button" class="immo-btn immo-btn-primary immo-inquiry-submit" style="width:100%"><?php esc_html_e( 'Absenden', 'immo-manager' ); ?> <span class="immo-btn-spinner" hidden>⟳</span></button>
		</div>
	</div>
</div>

<!-- Bild-Lightbox -->
<div class="immo-lightbox" id="immo-lightbox" hidden role="dialog" aria-modal="true">
	<button class="immo-lightbox-close">✕</button>
	<button class="immo-lightbox-prev">&#8249;</button>
	<button class="immo-lightbox-next">&#8250;</button>
	<div class="immo-lightbox-inner"><img src="" alt="" class="immo-lightbox-img"></div>
	<div class="immo-lightbox-counter"></div>
</div>

<!-- Quick-Info Lightbox für verknüpfte Immobilien -->
<div class="immo-inquiry-lightbox" id="immo-quick-info-lightbox" hidden role="dialog" aria-modal="true">
	<div class="immo-inquiry-lightbox-backdrop" id="immo-quick-info-backdrop"></div>
	<div class="immo-inquiry-lightbox-panel" style="max-width: 450px; text-align: center; padding: 2rem;">
		<button class="immo-inquiry-lightbox-close" id="immo-quick-info-close" aria-label="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">✕</button>
		<h2 class="immo-inquiry-lightbox-title" style="margin-bottom: 1.5rem; font-size: 1.4em;"><?php esc_html_e( 'Quick-Info zur Immobilie', 'immo-manager' ); ?></h2>
		
		<img id="immo-qi-img" src="" alt="" style="width: 100%; height: 220px; object-fit: cover; border-radius: 8px; margin-bottom: 1.25rem; display: none; box-shadow: var(--immo-shadow-sm);">
		
		<h3 id="immo-qi-title" style="font-size: 1.2em; margin-bottom: 1rem; color: #111827; line-height: 1.4;"></h3>
		
		<div id="immo-qi-facts" style="display: flex; justify-content: center; gap: 1.25rem; margin-bottom: 1.5rem; color: #4b5563; flex-wrap: wrap; font-size: 0.95em;">
			<span id="immo-qi-type"></span>
			<span id="immo-qi-area"></span>
			<span id="immo-qi-rooms"></span>
			<span id="immo-qi-floor"></span>
			<span id="immo-qi-built"></span>
			<span id="immo-qi-energy"></span>
		</div>
		
		<strong id="immo-qi-price" style="color: var(--immo-accent); font-size: 1.4em; display: block; margin-bottom: 1.5rem;"></strong>

		<a id="immo-qi-link" href="#" class="immo-btn immo-btn-primary" style="width: 100%; box-sizing: border-box; padding: 0.6rem 1rem; font-size: 0.95em;">
			<?php esc_html_e( 'Zum vollständigen Exposé', 'immo-manager' ); ?> →
		</a>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const qiBtns = document.querySelectorAll('.immo-quick-info-btn');
	const qiLightbox = document.getElementById('immo-quick-info-lightbox');
	const qiCloseBtns = document.querySelectorAll('#immo-quick-info-close, #immo-quick-info-backdrop');
	
	qiBtns.forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const data = JSON.parse(this.getAttribute('data-property'));
			document.getElementById('immo-qi-title').textContent = data.title;
			document.getElementById('immo-qi-link').href = data.permalink;
			document.getElementById('immo-qi-img').src = data.image || '';
			document.getElementById('immo-qi-img').style.display = data.image ? 'block' : 'none';

			document.getElementById('immo-qi-type').innerHTML = data.type ? '🏠 ' + data.type : '';
			document.getElementById('immo-qi-area').innerHTML = data.area > 0 ? '📐 ' + data.area + ' m²' : '';
			document.getElementById('immo-qi-rooms').innerHTML = data.rooms > 0 ? '🛏️ ' + data.rooms + ' <?php echo esc_js( __( 'Zi.', 'immo-manager' ) ); ?>' : '';
			
			const floorVal = data.floor;
			if (floorVal !== null) {
				const floorText = floorVal === 0 ? '<?php echo esc_js( __( 'EG', 'immo-manager' ) ); ?>' : floorVal + '. <?php echo esc_js( __( 'Etage', 'immo-manager' ) ); ?>';
				document.getElementById('immo-qi-floor').innerHTML = '🏢 ' + floorText;
			} else {
				document.getElementById('immo-qi-floor').innerHTML = '';
			}

			document.getElementById('immo-qi-built').innerHTML = data.built_year > 0 ? '📅 ' + data.built_year : '';
			document.getElementById('immo-qi-energy').innerHTML = data.energy_class ? '⚡ ' + data.energy_class : '';
			document.getElementById('immo-qi-price').innerHTML = data.price ? data.price : (data.rent ? data.rent + '/Mo' : '');

			qiLightbox.hidden = false;
			document.querySelectorAll('#immo-qi-facts span').forEach(span => { span.style.display = span.innerHTML.trim() ? 'inline-block' : 'none'; });
		});
	});

	qiCloseBtns.forEach(btn => {
		btn.addEventListener('click', e => { e.preventDefault(); qiLightbox.hidden = true; });
	});
});
</script>

<?php get_footer(); ?>
