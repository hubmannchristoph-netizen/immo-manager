<?php
/**
 * Template: [immo_detail] – Immobilien-Detailseite.
 * Layout: Slider-Hero + Sticky-Sidebar (minimal/CTA), darunter 2-spaltig.
 *
 * @var array  $property  Vollständiges Property-Array.
 * @var array  $similar   Ähnliche Immobilien.
 * @var string $api_base  REST-API-Basis-URL.
 * @var string $nonce     WP-REST-Nonce.
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$meta     = $property['meta'] ?? array();
$gallery  = $property['gallery'] ?? array();
$status   = $meta['status'] ?? 'available';
$mode     = $meta['mode'] ?? 'sale';
$has_map  = \ImmoManager\Settings::get( 'map_enabled', 1 ) && ! empty( $meta['lat'] ) && ! empty( $meta['lng'] );
$currency = \ImmoManager\Settings::get( 'currency_symbol', '€' );

// Galerie zusammenstellen: Featured Image + zusätzliche Galerie-Bilder
$all_images = array();
if ( $property['featured_image'] ) {
	$all_images[] = $property['featured_image'];
}
if ( ! empty( $gallery ) ) {
	$all_images = array_merge( $all_images, $gallery );
}

// Preis
$show_sale = in_array( $mode, array( 'sale', 'both' ), true ) && (float) $meta['price'] > 0;
$show_rent = in_array( $mode, array( 'rent', 'both' ), true ) && (float) $meta['rent'] > 0;

// Layout-Konfiguration (mit Fallback auf globale Einstellungen).
$detail_layout = ( ! empty( $meta['layout_type'] ) ) ? $meta['layout_type'] : \ImmoManager\Settings::get( 'default_detail_layout', 'standard' );
$gallery_type  = ( ! empty( $meta['gallery_type'] ) ) ? $meta['gallery_type'] : \ImmoManager\Settings::get( 'default_gallery_type', 'slider' );
$hero_type     = ( ! empty( $meta['hero_type'] ) ) ? $meta['hero_type'] : \ImmoManager\Settings::get( 'default_hero_type', 'full' );

// Key-Facts (rechte Spalte, kompakt)
$key_facts = array_filter( array(
	array( 'icon' => '🏠', 'label' => __( 'Typ',         'immo-manager' ), 'value' => $meta['property_type'] ?: null ),
	array( 'icon' => '📐', 'label' => __( 'Fläche',      'immo-manager' ), 'value' => $meta['area'] ? number_format_i18n( (float) $meta['area'], 0 ) . ' m²' : null ),
	array( 'icon' => '🛏️', 'label' => __( 'Zimmer',      'immo-manager' ), 'value' => $meta['rooms'] ?: null ),
	array( 'icon' => '🚿', 'label' => __( 'Badezimmer',  'immo-manager' ), 'value' => $meta['bathrooms'] ?: null ),
	array( 'icon' => '🏢', 'label' => __( 'Etage',       'immo-manager' ), 'value' => ( isset( $meta['floor'] ) && $meta['floor'] !== 0 ) ? $meta['floor'] : null ),
	array( 'icon' => '📅', 'label' => __( 'Baujahr',     'immo-manager' ), 'value' => $meta['built_year'] ?: null ),
	array( 'icon' => '⚡', 'label' => __( 'Energie',     'immo-manager' ), 'value' => $meta['energy_class'] ?: null ),
	array( 'icon' => '🔥', 'label' => __( 'Heizung',     'immo-manager' ), 'value' => $meta['heating'] ?: null ),
	array( 'icon' => '💰', 'label' => __( 'BK/Monat',    'immo-manager' ), 'value' => $meta['operating_costs'] ? number_format_i18n( (float) $meta['operating_costs'] ) . ' ' . $currency : null ),
	array( 'icon' => '📋', 'label' => __( 'Verfügbar ab', 'immo-manager' ), 'value' => $meta['available_from'] ? date_i18n( 'd.m.Y', strtotime( $meta['available_from'] ) ) : null ),
), fn( $f ) => $f['value'] !== null );
?>

<div class="immo-detail immo-layout-<?php echo esc_attr( $detail_layout ); ?> immo-hero-<?php echo esc_attr( $hero_type ); ?>"
	data-api-base="<?php echo esc_url( $api_base ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-property-id="<?php echo esc_attr( (string) $property['id'] ); ?>">

	<!-- ══════════════ HERO: Bild / Slider + Sticky CTA ══════════════ -->
	<div class="immo-detail-hero-layout">

		<!-- Bild-Bereich -->
		<?php if ( ! empty( $all_images ) ) : ?>
			<div class="immo-gallery-container immo-gallery-type-<?php echo esc_attr( $gallery_type ); ?>">
				
				<?php if ( 'slider' === $gallery_type ) : ?>
					<!-- SLIDER-ANSICHT -->
					<div class="immo-slider">
						<?php if ( $status !== 'available' ) : ?>
							<span class="immo-slider-badge status-<?php echo esc_attr( $status ); ?>">
								<?php $sl = array( 'reserved' => __( 'Reserviert', 'immo-manager' ), 'sold' => __( 'Verkauft', 'immo-manager' ), 'rented' => __( 'Vermietet', 'immo-manager' ) );
								echo esc_html( $sl[ $status ] ?? '' ); ?>
							</span>
						<?php endif; ?>

						<div class="immo-slider-main">
							<div class="immo-slider-track">
								<?php foreach ( $all_images as $i => $img ) : ?>
									<div class="immo-slide <?php echo 0 === $i ? 'active' : ''; ?>" aria-hidden="<?php echo 0 === $i ? 'false' : 'true'; ?>">
										<img src="<?php echo esc_url( $img['url_large'] ); ?>"
											data-full="<?php echo esc_url( $img['url'] ); ?>"
											alt="<?php echo esc_attr( $img['alt'] ?: ( $property['title'] ?? '' ) ); ?>"
											loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
											class="immo-slide-img">
									</div>
								<?php endforeach; ?>
							</div>
							<?php if ( count( $all_images ) > 1 ) : ?>
								<button class="immo-slider-nav immo-slider-prev" aria-label="<?php esc_attr_e( 'Vorheriges Bild', 'immo-manager' ); ?>">&#8249;</button>
								<button class="immo-slider-nav immo-slider-next" aria-label="<?php esc_attr_e( 'Nächstes Bild', 'immo-manager' ); ?>">&#8250;</button>
								<div class="immo-slider-counter"><span class="immo-slider-current">1</span> / <span><?php echo count( $all_images ); ?></span></div>
							<?php endif; ?>
							<button class="immo-slider-expand" aria-label="<?php esc_attr_e( 'Vollbild öffnen', 'immo-manager' ); ?>">⤢</button>
						</div>
						<?php if ( count( $all_images ) > 1 ) : ?>
							<div class="immo-slider-thumbs">
								<?php foreach ( $all_images as $i => $img ) : ?>
									<button class="immo-slider-thumb <?php echo 0 === $i ? 'active' : ''; ?>"
										data-index="<?php echo esc_attr( (string) $i ); ?>">
										<img src="<?php echo esc_url( $img['url_thumbnail'] ); ?>" alt="" loading="lazy" width="80" height="56">
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<!-- GRID-ANSICHT -->
					<div class="immo-grid-gallery">
						<?php foreach ( array_slice( $all_images, 0, 5 ) as $i => $img ) : ?>
							<div class="immo-grid-item immo-grid-item-<?php echo $i; ?>">
								<img src="<?php echo esc_url( $img['url_large'] ); ?>"
									data-full="<?php echo esc_url( $img['url'] ); ?>"
									alt="<?php echo esc_attr( $img['alt'] ?: ( $property['title'] ?? '' ) ); ?>"
									class="immo-grid-img">
								<?php if ( 4 === $i && count( $all_images ) > 5 ) : ?>
									<div class="immo-grid-overlay">
										<span>+<?php echo count( $all_images ) - 5; ?></span>
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
							<div class="immo-card-no-image">🏠</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- RECHTE SPALTE: Minimal + Anfragen-Fokus -->
		<aside class="immo-detail-sticky-sidebar">

			<!-- Titel + Ort -->
			<div class="immo-sticky-header">
				<h1 class="immo-detail-title"><?php echo esc_html( $property['title'] ?? '' ); ?></h1>
				<?php $loc = array_filter( array( trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ), $meta['region_state_label'] ?? '' ) );
				if ( $loc ) : ?>
					<p class="immo-detail-location">📍 <?php echo esc_html( implode( ', ', $loc ) ); ?></p>
				<?php endif; ?>
			</div>

			<!-- PREIS – sehr prominent -->
			<?php if ( $show_sale || $show_rent ) : ?>
				<div class="immo-price-hero">
					<?php if ( $show_sale ) : ?>
						<span class="immo-price-hero-label"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></span>
						<span class="immo-price-hero-value"><?php echo esc_html( $meta['price_formatted'] ); ?></span>
					<?php endif; ?>
					<?php if ( $show_rent ) : ?>
						<?php if ( $show_sale ) : ?><span class="immo-price-hero-alt"><?php endif; ?>
						<?php if ( ! $show_sale ) : ?>
							<span class="immo-price-hero-label"><?php esc_html_e( 'Miete/Monat', 'immo-manager' ); ?></span>
							<span class="immo-price-hero-value"><?php echo esc_html( $meta['rent_formatted'] ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'Miete: ', 'immo-manager' ); ?><?php echo esc_html( $meta['rent_formatted'] ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $meta['operating_costs'] ) : ?>
						<span class="immo-price-hero-note">+ <?php echo esc_html( number_format_i18n( (float) $meta['operating_costs'] ) . ' ' . $currency ); ?> <?php esc_html_e( 'BK/Monat', 'immo-manager' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- 3 wichtigste Fakten (Fläche, Zimmer, Etage) -->
			<ul class="immo-keyfacts immo-keyfacts--mini">
				<?php foreach ( array_slice( $key_facts, 0, 3 ) as $fact ) : ?>
					<li class="immo-keyfact">
						<span class="immo-keyfact-icon"><?php echo esc_html( $fact['icon'] ); ?></span>
						<span class="immo-keyfact-label"><?php echo esc_html( $fact['label'] ); ?></span>
						<span class="immo-keyfact-value"><?php echo esc_html( (string) $fact['value'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<!-- CTA – Anfrage + Anruf -->
			<div class="immo-sticky-cta">
				<?php if ( $meta['contact_name'] ) : ?>
					<div class="immo-sticky-agent" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
						<?php if ( ! empty( $meta['contact_image'] ) ) : ?>
							<img src="<?php echo esc_url( $meta['contact_image']['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $meta['contact_name'] ); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--immo-accent); box-shadow: var(--immo-shadow-sm);">
						<?php endif; ?>
						<div>
							<strong><?php echo esc_html( $meta['contact_name'] ); ?></strong>
							<?php if ( $meta['contact_phone'] ) : ?><br>
								<a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-contact-phone">📞 <?php echo esc_html( $meta['contact_phone'] ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( (bool) \ImmoManager\Settings::get( 'enable_inquiries', 1 ) ) : ?>
					<button type="button" class="immo-btn immo-btn-primary immo-btn-inquiry-open"
						data-property-id="<?php echo esc_attr( (string) $property['id'] ); ?>"
						data-agent-name="<?php echo esc_attr( $meta['contact_name'] ?? '' ); ?>"
						data-agent-email="<?php echo esc_attr( $meta['contact_email'] ?? '' ); ?>"
						data-agent-phone="<?php echo esc_attr( $meta['contact_phone'] ?? '' ); ?>">
						✉️ <?php esc_html_e( 'Anfrage senden', 'immo-manager' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( $meta['contact_phone'] ) : ?>
					<a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-btn immo-btn-call">📞 <?php esc_html_e( 'Jetzt anrufen', 'immo-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</aside>
	</div><!-- .immo-detail-hero-layout -->

	<!-- ══════════════ 2-SPALTIGER CONTENT-BEREICH ══════════════ -->
	<div class="immo-detail-twocol">

		<!-- LINKE SPALTE: Akkordeon-Beschreibungen -->
		<div class="immo-detail-content">

			<?php if ( ! empty( $property['description'] ) ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="true"><?php esc_html_e( 'Objektbeschreibung', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body"><div class="immo-detail-text"><?php echo wp_kses_post( $property['description'] ); ?></div></div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $meta['features_detail'] ) ) : ?>
				<div class="immo-accordion" id="immo-accordion-features">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Ausstattung', 'immo-manager' ); ?><span class="immo-accordion-badge"><?php echo count( $meta['features_detail'] ); ?></span><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<?php
						$gf = array(); $cats = \ImmoManager\Features::get_categories(); $all = \ImmoManager\Features::get_all();
						foreach ( $meta['features_detail'] as $f ) { $gf[ $all[ $f['key'] ]['category'] ?? 'indoor' ][] = $f; }
						foreach ( $gf as $cat => $items ) : ?>
							<div class="immo-features-group">
								<h4 class="immo-features-group-title"><?php echo esc_html( $cats[ $cat ] ?? $cat ); ?></h4>
								<ul class="immo-features-list">
									<?php foreach ( $items as $f ) : ?><li><span aria-hidden="true"><?php echo esc_html( $f['icon'] ); ?></span> <?php echo esc_html( $f['label'] ); ?></li><?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach;
						if ( ! empty( $meta['custom_features'] ) ) : ?><p class="immo-custom-features"><?php echo esc_html( $meta['custom_features'] ); ?></p><?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $meta['energy_class'] || $meta['heating'] ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Energie & Technik', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<div class="immo-detail-facts">
							<?php foreach ( array_filter( array(
								array( 'icon' => '⚡', 'label' => __( 'Energieklasse', 'immo-manager' ), 'value' => $meta['energy_class'] ?: null ),
								array( 'icon' => '📊', 'label' => __( 'HWB', 'immo-manager' ), 'value' => $meta['energy_hwb'] ? number_format_i18n( (float) $meta['energy_hwb'], 1 ) . ' kWh/m²a' : null ),
								array( 'icon' => '🔥', 'label' => __( 'Heizung', 'immo-manager' ), 'value' => $meta['heating'] ?: null ),
								array( 'icon' => '📅', 'label' => __( 'Baujahr', 'immo-manager' ), 'value' => $meta['built_year'] ?: null ),
								array( 'icon' => '🔨', 'label' => __( 'Saniert', 'immo-manager' ), 'value' => $meta['renovation_year'] ?: null ),
							), fn($f)=>$f['value']!==null) as $fact ) : ?>
								<div class="immo-fact"><span class="immo-fact-icon"><?php echo esc_html( $fact['icon'] ); ?></span><span class="immo-fact-label"><?php echo esc_html( $fact['label'] ); ?></span><span class="immo-fact-value"><?php echo esc_html( (string) $fact['value'] ); ?></span></div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $meta['operating_costs'] || $meta['deposit'] || $meta['commission'] ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Kosten & Konditionen', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<table class="immo-costs-table">
							<?php if ( $meta['operating_costs'] ) : ?><tr><td><?php esc_html_e( 'Betriebskosten/Monat', 'immo-manager' ); ?></td><td><?php echo esc_html( number_format_i18n( (float) $meta['operating_costs'] ) . ' ' . $currency ); ?></td></tr><?php endif; ?>
							<?php if ( $meta['deposit'] ) : ?><tr><td><?php esc_html_e( 'Kaution', 'immo-manager' ); ?></td><td><?php echo esc_html( number_format_i18n( (float) $meta['deposit'] ) . ' ' . $currency ); ?></td></tr><?php endif; ?>
							<?php if ( $meta['commission'] ) : ?><tr><td><?php esc_html_e( 'Provision', 'immo-manager' ); ?></td><td><?php echo esc_html( $meta['commission'] ); ?></td></tr><?php endif; ?>
							<?php if ( $meta['available_from'] ) : ?><tr><td><?php esc_html_e( 'Verfügbar ab', 'immo-manager' ); ?></td><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $meta['available_from'] ) ) ); ?></td></tr><?php endif; ?>
						</table>
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

			<?php if ( $has_map || $meta['address'] ) : ?>
				<div class="immo-accordion">
					<button class="immo-accordion-header" aria-expanded="false"><?php esc_html_e( 'Lage', 'immo-manager' ); ?><span class="immo-accordion-icon" aria-hidden="true"></span></button>
					<div class="immo-accordion-body" hidden>
						<?php if ( $meta['address'] ) : ?><p class="immo-detail-address">📍 <?php echo esc_html( implode( ', ', array_filter( array( $meta['address'], trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ) ) ) ) ); ?></p><?php endif; ?>
						<?php if ( $has_map ) : ?><div class="immo-map" id="immo-map-<?php echo esc_attr( (string) $property['id'] ); ?>" data-lat="<?php echo esc_attr( (string) $meta['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $meta['lng'] ); ?>" data-title="<?php echo esc_attr( $property['title'] ?? '' ); ?>" style="height:300px;border-radius:var(--immo-radius);margin-top:12px;"></div><?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// === Nebenkosten- & Finanzierungsrechner (am Ende der Akkordeons) ===
			// $show_sale (Zeile ~32) prüft bereits: mode in (sale|both) UND price > 0.
			if ( $show_sale ) {
				$calc_context = array(
					'base_price'      => (float) ( $meta['price'] ?? 0 ),
					'commission_free' => (bool) ( $meta['commission_free'] ?? false ),
					'units'           => array(),
				);
				include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/calculator.php';
			}
			?>
		</div><!-- .immo-detail-content -->

		<!-- RECHTE SPALTE: Objekt-Details auf einen Blick -->
		<aside class="immo-detail-info-sidebar">
			<?php if ( ! empty( $key_facts ) ) : ?>
				<div class="immo-info-box">
					<h3><?php esc_html_e( 'Details', 'immo-manager' ); ?></h3>
					<table class="immo-costs-table">
						<?php foreach ( $key_facts as $fact ) : ?>
							<tr>
								<td><?php echo esc_html( $fact['icon'] . ' ' . $fact['label'] ); ?></td>
								<td><strong><?php echo esc_html( (string) $fact['value'] ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endif; ?>

			<!-- Top Features -->
			<?php if ( ! empty( $meta['features_detail'] ) ) : ?>
				<div class="immo-info-box">
					<h3><?php esc_html_e( 'Ausstattung', 'immo-manager' ); ?></h3>
					<ul class="immo-info-features">
						<?php foreach ( array_slice( $meta['features_detail'], 0, 8 ) as $f ) : ?>
							<li><?php echo esc_html( $f['icon'] . ' ' . $f['label'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- Zugehöriges Bauprojekt -->
			<?php if ( ! empty( $meta['project'] ) ) : 
				$proj = $meta['project'];
				$proj_status_labels = array(
					'planning'  => __( 'In Planung', 'immo-manager' ),
					'building'  => __( 'In Bau', 'immo-manager' ),
					'completed' => __( 'Fertiggestellt', 'immo-manager' ),
				);
			?>
				<div class="immo-info-box">
					<h3><?php esc_html_e( 'Zugehöriges Bauprojekt', 'immo-manager' ); ?></h3>
					<?php if ( $proj['image'] ) : ?>
						<a href="<?php echo esc_url( $proj['permalink'] ); ?>" style="display:block; margin-bottom: 12px;">
							<img src="<?php echo esc_url( $proj['image'] ); ?>" alt="<?php echo esc_attr( $proj['title'] ); ?>" style="width: 100%; height: 140px; object-fit: cover; border-radius: var(--immo-radius-sm);">
						</a>
					<?php endif; ?>
					<p style="margin: 0 0 10px; font-size: 1.05em; line-height: 1.4;">
						<strong><a href="<?php echo esc_url( $proj['permalink'] ); ?>" style="color: inherit; text-decoration: none;"><?php echo esc_html( $proj['title'] ); ?></a></strong>
					</p>
					
					<?php if ( ! empty( $proj['description'] ) ) : ?>
						<div class="immo-proj-desc-wrapper" style="margin-bottom: 15px; font-size: 0.9em; color: var(--immo-text-muted); line-height: 1.5;">
							<div class="immo-proj-desc" style="overflow: hidden; transition: max-height 0.35s ease-out; max-height: 3em;">
								<?php echo wp_kses_post( wp_strip_all_tags( $proj['description'] ) ); ?>
							</div>
							<button type="button" class="immo-proj-desc-toggle" style="background: none; border: none; padding: 0; color: var(--immo-accent); cursor: pointer; font-size: 0.9em; margin-top: 4px; font-weight: 600; display: none;"><?php esc_html_e( 'Mehr lesen', 'immo-manager' ); ?> ↓</button>
						</div>
					<?php endif; ?>

					<ul class="immo-info-features" style="margin-bottom: 15px;">
						<?php if ( $proj['status'] ) : ?><li>🏗️ <?php echo esc_html( $proj_status_labels[ $proj['status'] ] ?? $proj['status'] ); ?></li><?php endif; ?>
						<?php if ( $proj['completion'] ) : ?><li>🏁 <?php esc_html_e( 'Fertigstellung:', 'immo-manager' ); ?> <?php echo esc_html( date_i18n( 'M Y', strtotime( $proj['completion'] ) ) ); ?></li><?php endif; ?>
						<?php if ( ! empty( $proj['total_units'] ) ) : ?><li>🏘️ <?php printf( esc_html__( '%1$d Einheiten (%2$d verfügbar)', 'immo-manager' ), (int) $proj['total_units'], (int) $proj['available_units'] ); ?></li><?php endif; ?>
					</ul>
					<a href="<?php echo esc_url( $proj['permalink'] ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm" style="width: 100%; text-align: center;">
						<?php esc_html_e( 'Zum Bauprojekt', 'immo-manager' ); ?> →
					</a>
				</div>
			<?php endif; ?>

			<!-- Kontakt nochmals -->
			<?php if ( $meta['contact_name'] || $meta['contact_email'] ) : ?>
				<div class="immo-info-box immo-info-box--cta">
					<h3><?php esc_html_e( 'Ihr Ansprechpartner', 'immo-manager' ); ?></h3>
					<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
						<?php if ( ! empty( $meta['contact_image'] ) ) : ?>
							<img src="<?php echo esc_url( $meta['contact_image']['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $meta['contact_name'] ); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--immo-accent); box-shadow: var(--immo-shadow-sm);">
						<?php endif; ?>
						<div>
							<?php if ( $meta['contact_name'] ) : ?><p style="margin: 0;"><strong><?php echo esc_html( $meta['contact_name'] ); ?></strong></p><?php endif; ?>
							<?php if ( $meta['contact_phone'] ) : ?><p style="margin: 0;"><a href="tel:<?php echo esc_attr( $meta['contact_phone'] ); ?>" class="immo-contact-phone">📞 <?php echo esc_html( $meta['contact_phone'] ); ?></a></p><?php endif; ?>
							<?php if ( $meta['contact_email'] ) : ?><p style="margin: 0; word-break:break-all"><a href="mailto:<?php echo esc_attr( $meta['contact_email'] ); ?>" style="color:var(--immo-accent)">✉️ <?php echo esc_html( $meta['contact_email'] ); ?></a></p><?php endif; ?>
						</div>
					</div>
					<?php if ( (bool) \ImmoManager\Settings::get( 'enable_inquiries', 1 ) ) : ?>
						<button type="button" class="immo-btn immo-btn-primary immo-btn-inquiry-open" style="width:100%;margin-top:8px"
							data-property-id="<?php echo esc_attr( (string) $property['id'] ); ?>"
							data-agent-name="<?php echo esc_attr( $meta['contact_name'] ?? '' ); ?>"
							data-agent-email="<?php echo esc_attr( $meta['contact_email'] ?? '' ); ?>"
							data-agent-phone="<?php echo esc_attr( $meta['contact_phone'] ?? '' ); ?>">
							✉️ <?php esc_html_e( 'Jetzt anfragen', 'immo-manager' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div><!-- .immo-detail-twocol -->

	<!-- Ähnliche Immobilien -->
	<?php if ( ! empty( $similar ) ) : ?>
		<div class="immo-similar">
			<h2><?php esc_html_e( 'Ähnliche Immobilien', 'immo-manager' ); ?></h2>
			<div class="immo-similar-grid" role="list">
				<?php foreach ( $similar as $property ) { include __DIR__ . '/parts/property-card.php'; } ?>
			</div>
		</div>
	<?php endif; ?>

</div><!-- .immo-detail -->

<!-- ══════ ANFRAGE-LIGHTBOX ══════ -->
<div class="immo-inquiry-lightbox" id="immo-inquiry-lightbox" hidden role="dialog" aria-modal="true" aria-labelledby="immo-inquiry-lightbox-title">
	<div class="immo-inquiry-lightbox-backdrop" id="immo-inquiry-backdrop"></div>
	<div class="immo-inquiry-lightbox-panel">
		<button class="immo-inquiry-lightbox-close" id="immo-inquiry-close" aria-label="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">✕</button>
		<h2 class="immo-inquiry-lightbox-title" id="immo-inquiry-lightbox-title"><?php esc_html_e( 'Anfrage senden', 'immo-manager' ); ?></h2>
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
		<div class="immo-inquiry-success" hidden role="alert"><span>✅</span><p><?php esc_html_e( 'Vielen Dank! Wir melden uns in Kürze.', 'immo-manager' ); ?></p></div>
		<div class="immo-inquiry-error" hidden role="alert"></div>
		<div class="immo-inquiry-form" data-property-id="<?php echo esc_attr( (string) $property['id'] ); ?>">
			<div class="immo-form-field"><label for="inq-lb-name"><?php esc_html_e( 'Ihr Name', 'immo-manager' ); ?> *</label><input type="text" id="inq-lb-name" name="inquirer_name" class="immo-input" required autocomplete="name"><span class="immo-field-error" hidden></span></div>
			<div class="immo-form-field"><label for="inq-lb-email"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?> *</label><input type="email" id="inq-lb-email" name="inquirer_email" class="immo-input" required autocomplete="email"><span class="immo-field-error" hidden></span></div>
			<div class="immo-form-field"><label for="inq-lb-phone"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></label><input type="tel" id="inq-lb-phone" name="inquirer_phone" class="immo-input" autocomplete="tel"></div>
			<div class="immo-form-field"><label for="inq-lb-msg"><?php esc_html_e( 'Nachricht', 'immo-manager' ); ?></label><textarea id="inq-lb-msg" name="inquirer_message" class="immo-input" rows="3" placeholder="<?php esc_attr_e( 'Ich interessiere mich für diese Immobilie…', 'immo-manager' ); ?>"></textarea></div>
			<div class="immo-form-field immo-form-field--checkbox"><label><input type="checkbox" name="consent" required><span><?php printf( esc_html__( 'Ich stimme der %s zu. *', 'immo-manager' ), '<a href="' . esc_url( get_privacy_policy_url() ?: '#' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'immo-manager' ) . '</a>' ); ?></span></label><span class="immo-field-error" hidden></span></div>
			<button type="button" class="immo-btn immo-btn-primary immo-inquiry-submit" style="width:100%"><?php esc_html_e( 'Anfrage absenden', 'immo-manager' ); ?> <span class="immo-btn-spinner" hidden>⟳</span></button>
			<p class="immo-form-note">* <?php esc_html_e( 'Pflichtfelder', 'immo-manager' ); ?></p>
		</div>
	</div>
</div>

<!-- Bild-Lightbox -->
<div class="immo-lightbox" id="immo-lightbox" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Bildergalerie', 'immo-manager' ); ?>">
	<button class="immo-lightbox-close" aria-label="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">✕</button>
	<button class="immo-lightbox-prev" aria-label="<?php esc_attr_e( 'Vorheriges', 'immo-manager' ); ?>">&#8249;</button>
	<button class="immo-lightbox-next" aria-label="<?php esc_attr_e( 'Nächstes', 'immo-manager' ); ?>">&#8250;</button>
	<div class="immo-lightbox-inner"><img src="" alt="" class="immo-lightbox-img"></div>
	<div class="immo-lightbox-counter"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const descToggles = document.querySelectorAll('.immo-proj-desc-toggle');
	descToggles.forEach(function(toggle) {
		const desc = toggle.previousElementSibling;
		if (!desc) return;

		// Prüfen, ob der Text länger als 2 Zeilen (3em) ist
		if (desc.scrollHeight > desc.clientHeight) {
			toggle.style.display = 'inline-block';
		}

		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			if (desc.style.maxHeight === '3em' || desc.style.maxHeight === '') {
				desc.style.maxHeight = desc.scrollHeight + 'px';
				this.innerHTML = '<?php esc_js( __( 'Weniger lesen', 'immo-manager' ) ); ?> ↑';
			} else {
				desc.style.maxHeight = '3em';
				this.innerHTML = '<?php esc_js( __( 'Mehr lesen', 'immo-manager' ) ); ?> ↓';
			}
		});
	});
});
</script>
