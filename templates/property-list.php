<?php
/**
 * Template: [immo_list] – Listenansicht mit Off-Canvas Filter-Sidebar.
 *
 * Erwartete Variablen:
 * @var array<int, array>  $properties  Initiale Immobilien-Liste.
 * @var array<string, int> $pagination  Paginierungs-Daten.
 * @var string             $api_base    REST-API-Basis-URL.
 * @var string             $layout      grid | list.
 * @var string             $nonce       WP-REST-Nonce.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$states = \ImmoManager\Regions::get_states();
$currency = \ImmoManager\Settings::get( 'currency_symbol', '€' );
$total = $pagination['total'] ?? 0;
?>
<div class="immo-list-container" data-api-base="<?php echo esc_url( $api_base ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

	<!-- Header: Toggle + Sortierung + Ergebnis-Count -->
	<div class="immo-list-header">
		<button class="immo-filter-toggle" aria-expanded="false" aria-controls="immo-filter-sidebar">
			<span class="immo-icon">⚙</span>
			<span><?php esc_html_e( 'Filter', 'immo-manager' ); ?></span>
			<span class="immo-filter-badge" hidden>0</span>
		</button>

		<div class="immo-list-controls">
			<span class="immo-result-count">
				<?php printf(
					/* translators: %d: Anzahl Ergebnisse */
					esc_html( _n( '%d Immobilie', '%d Immobilien', $total, 'immo-manager' ) ),
					(int) $total
				); ?>
			</span>
			<select class="immo-sort-select" id="immo-sort" aria-label="<?php esc_attr_e( 'Sortierung', 'immo-manager' ); ?>">
				<option value="newest"><?php esc_html_e( 'Neueste zuerst', 'immo-manager' ); ?></option>
				<option value="price_asc"><?php esc_html_e( 'Preis aufsteigend', 'immo-manager' ); ?></option>
				<option value="price_desc"><?php esc_html_e( 'Preis absteigend', 'immo-manager' ); ?></option>
				<option value="area_desc"><?php esc_html_e( 'Größe absteigend', 'immo-manager' ); ?></option>
			</select>
			<div class="immo-layout-toggle">
				<button class="immo-layout-btn <?php echo 'grid' === $layout ? 'active' : ''; ?>" data-layout="grid" aria-label="<?php esc_attr_e( 'Gitter-Ansicht', 'immo-manager' ); ?>">⊞</button>
				<button class="immo-layout-btn <?php echo 'list' === $layout ? 'active' : ''; ?>" data-layout="list" aria-label="<?php esc_attr_e( 'Listen-Ansicht', 'immo-manager' ); ?>">☰</button>
			</div>
		</div>
	</div>

	<div class="immo-list-wrapper">
		<!-- Filter-Sidebar (Off-Canvas) -->
		<?php include __DIR__ . '/parts/filter-sidebar.php'; ?>

		<!-- Backdrop -->
		<div class="immo-filter-backdrop" id="immo-filter-backdrop" aria-hidden="true"></div>

		<!-- Property-Liste -->
		<div class="immo-properties-area">
			<!-- Aktive Filter Tags -->
			<div class="immo-active-filters" hidden>
				<span class="immo-active-filters-label"><?php esc_html_e( 'Aktive Filter:', 'immo-manager' ); ?></span>
				<div class="immo-filter-tags"></div>
				<button class="immo-filter-reset-all"><?php esc_html_e( 'Alle zurücksetzen', 'immo-manager' ); ?></button>
			</div>

			<!-- Loader -->
			<div class="immo-loader" id="immo-loader" hidden aria-live="polite">
				<div class="immo-spinner"></div>
				<span><?php esc_html_e( 'Lädt…', 'immo-manager' ); ?></span>
			</div>

			<!-- Properties Grid/List -->
			<div class="immo-properties-grid layout-<?php echo esc_attr( $layout ); ?>" id="immo-properties-grid" role="list">
				<?php if ( empty( $properties ) ) : ?>
					<p class="immo-no-results"><?php esc_html_e( 'Keine Immobilien gefunden.', 'immo-manager' ); ?></p>
				<?php else : ?>
					<?php foreach ( $properties as $property ) :
						include __DIR__ . '/parts/property-card.php';
					endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Paginierung -->
			<?php if ( ( $pagination['pages'] ?? 1 ) > 1 ) : ?>
				<nav class="immo-pagination" id="immo-pagination" aria-label="<?php esc_attr_e( 'Seitennavigation', 'immo-manager' ); ?>">
					<?php for ( $p = 1; $p <= (int) ( $pagination['pages'] ?? 1 ); $p++ ) : ?>
						<button class="immo-page-btn <?php echo (int) ( $pagination['current_page'] ?? 1 ) === $p ? 'active' : ''; ?>" data-page="<?php echo esc_attr( (string) $p ); ?>">
							<?php echo esc_html( (string) $p ); ?>
						</button>
					<?php endfor; ?>
				</nav>
			<?php endif; ?>
		</div><!-- .immo-properties-area -->
	</div><!-- .immo-list-wrapper -->
</div><!-- .immo-list-container -->
