<?php
/**
 * Frontend-Shortcodes für Immo Manager.
 *
 * [immo_list]  – Listenansicht mit AJAX-Filter-Sidebar
 * [immo_detail id="X"] – Detailseite einer Immobilie
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcodes
 */
class Shortcodes {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_shortcode( 'immo_list',   array( $this, 'render_list' ) );
		add_shortcode( 'immo_detail', array( $this, 'render_detail' ) );
		// Widget-Shortcodes.
		add_shortcode( 'immo_latest',     array( $this, 'render_latest' ) );
		add_shortcode( 'immo_featured',   array( $this, 'render_featured' ) );
		add_shortcode( 'immo_count',      array( $this, 'render_count' ) );
		add_shortcode( 'immo_search',     array( $this, 'render_search' ) );
		// URL-basierte Detailseite (Single-Page-App Modus).
		add_shortcode( 'immo_detail_page', array( $this, 'render_detail_page' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'inject_css_vars' ), 20 );
		add_action( 'admin_head', array( $this, 'inject_css_vars' ), 20 );
	}

	// =========================================================================
	// [immo_list]
	// =========================================================================

	/**
	 * [immo_list] rendern.
	 *
	 * Attribute:
	 * - status   : Komma-getrennte Status-Filter (default: "available")
	 * - mode     : sale | rent | both
	 * - per_page : Anzahl pro Seite (default aus Settings)
	 * - orderby  : newest | price_asc | price_desc | area_desc
	 * - layout   : grid | list
	 *
	 * @param array<string, mixed>|string $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_list( $atts ): string {
		$atts = shortcode_atts( array(
			'status'   => 'available',
			'mode'     => '',
			'per_page' => (int) Settings::get( 'items_per_page', 12 ),
			'orderby'  => 'newest',
			'layout'   => Settings::get( 'default_view', 'grid' ),
		), (array) $atts, 'immo_list' );

		// Initiale Properties laden.
		$rest     = Plugin::instance()->get_rest_api();
		$request  = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );
		$page     = isset( $_GET['immo_page'] ) ? max( 1, absint( wp_unslash( $_GET['immo_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request->set_query_params( array_filter( array(
			'status'   => $atts['status'],
			'mode'     => $atts['mode'],
			'per_page' => (int) $atts['per_page'],
			'orderby'  => $atts['orderby'],
			'page'     => $page,
		) ) );
		$response   = $rest->get_properties( $request );
		$data       = $response->get_data();
		$properties = $data['properties'] ?? array();
		$pagination = $data['pagination'] ?? array();

		// Template laden.
		ob_start();
		$api_base  = rest_url( RestApi::NAMESPACE );
		$layout    = in_array( $atts['layout'], array( 'grid', 'list' ), true ) ? $atts['layout'] : 'grid';
		$nonce     = wp_create_nonce( 'wp_rest' );
		include IMMO_MANAGER_PLUGIN_DIR . 'templates/property-list.php';
		return ob_get_clean();
	}

	// =========================================================================
	// [immo_detail]
	// =========================================================================

	/**
	 * [immo_detail id="X"] rendern.
	 *
	 * @param array<string, mixed>|string $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_detail( $atts ): string {
		$atts = shortcode_atts( array(
			'id' => 0,
		), (array) $atts, 'immo_detail' );

		$id = (int) $atts['id'];

		// Fallback: ID aus URL-Parameter (für dynamische Seiten).
		if ( ! $id && isset( $_GET['immo_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id = absint( $_GET['immo_id'] );
		}

		if ( ! $id ) {
			return '<p>' . esc_html__( 'Keine Immobilien-ID angegeben.', 'immo-manager' ) . '</p>';
		}

		$post = get_post( $id );
		if ( ! $post || PostTypes::POST_TYPE_PROPERTY !== $post->post_type || 'publish' !== $post->post_status ) {
			return '<p>' . esc_html__( 'Immobilie nicht gefunden.', 'immo-manager' ) . '</p>';
		}

		$rest     = Plugin::instance()->get_rest_api();
		$property = $rest->format_property( $post, true );

		// Ähnliche Immobilien.
		$similar_req = new \WP_REST_Request( 'GET', "/immo-manager/v1/properties/{$id}/similar" );
		$similar_req->set_query_params( array( 'limit' => 3 ) );
		$similar = $rest->get_similar( $similar_req )->get_data()['properties'] ?? array();

		ob_start();
		$api_base = rest_url( RestApi::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );
		include IMMO_MANAGER_PLUGIN_DIR . 'templates/property-detail.php';
		return ob_get_clean();
	}

	// =========================================================================
	// Assets & CSS-Variablen
	// =========================================================================

	/**
	 * Frontend-Assets enqueuen.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'immo-manager-frontend',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			IMMO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'immo-manager-filters',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/filters.js',
			array( 'jquery' ),
			IMMO_MANAGER_VERSION,
			true
		);

		wp_enqueue_script(
			'immo-manager-frontend',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/frontend.js',
			array( 'jquery' ),
			IMMO_MANAGER_VERSION,
			true
		);

		// Wizard-Assets.
		wp_enqueue_style(
			'immo-manager-wizard',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/wizard.css',
			array( 'immo-manager-frontend' ),
			IMMO_MANAGER_VERSION
		);
		wp_enqueue_script(
			'immo-manager-wizard',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/wizard.js',
			array( 'jquery', 'immo-manager-filters' ),
			IMMO_MANAGER_VERSION,
			true
		);

		// Leaflet für Kartendarstellung.
		if ( Settings::get( 'map_enabled', 1 ) ) {
			wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
			wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
		}

		// Konfiguration für JS.
		wp_localize_script(
			'immo-manager-filters',
			'immoManager',
			array(
				'apiBase'      => rest_url( RestApi::NAMESPACE ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'mapEnabled'   => (bool) Settings::get( 'map_enabled', 1 ),
				'mapTileUrl'   => Settings::get( 'map_tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' ),
				'mapAttrib'    => Settings::get( 'map_attribution', '' ),
				'mapDefaultLat' => (float) Settings::get( 'map_default_lat', 47.5162 ),
				'mapDefaultLng' => (float) Settings::get( 'map_default_lng', 14.5501 ),
				'mapDefaultZoom' => (int) Settings::get( 'map_default_zoom', 7 ),
				'i18n'         => array(
					'loading'       => __( 'Lädt…', 'immo-manager' ),
					'noResults'     => __( 'Keine Immobilien gefunden.', 'immo-manager' ),
					'filterToggle'  => __( 'Filter', 'immo-manager' ),
					'filterClose'   => __( 'Schließen', 'immo-manager' ),
					'filterReset'   => __( 'Zurücksetzen', 'immo-manager' ),
					'activeFilters' => __( 'Aktive Filter', 'immo-manager' ),
					'results'       => __( 'Ergebnisse', 'immo-manager' ),
					'district'      => __( '— Bezirk —', 'immo-manager' ),
					'inquirySent'   => __( 'Anfrage gesendet! Wir melden uns in Kürze.', 'immo-manager' ),
					'inquiryError'  => __( 'Fehler beim Senden. Bitte versuche es erneut.', 'immo-manager' ),
					'required'      => __( 'Pflichtfeld.', 'immo-manager' ),
					'invalidEmail'  => __( 'Gültige E-Mail-Adresse erforderlich.', 'immo-manager' ),
					'consentRequired' => __( 'Bitte bestätige die Datenschutzerklärung.', 'immo-manager' ),
					'copied'        => __( 'Kopiert!', 'immo-manager' ),
				),
			)
		);
	}

	// =========================================================================
	// [immo_latest] – Neueste Immobilien als kompaktes Widget
	// =========================================================================

	/**
	 * [immo_latest] – Neueste Immobilien.
	 *
	 * Attribute:
	 * - count    : Anzahl (default 3)
	 * - status   : Komma-getrennt (default "available")
	 * - mode     : sale | rent | both | "" (alle)
	 * - layout   : card | minimal | list
	 * - title    : Widget-Titel (leer = kein Titel)
	 * - link     : URL der Listenseite für "Alle anzeigen"-Link
	 * - columns  : 1 | 2 | 3 (default 3)
	 *
	 * @param array<string, mixed>|string $atts Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_latest( $atts ): string {
		$atts = shortcode_atts( array(
			'count'   => 3,
			'status'  => 'available',
			'mode'    => '',
			'layout'  => 'card',
			'title'   => '',
			'link'    => '',
			'columns' => 3,
		), (array) $atts, 'immo_latest' );

		$rest     = Plugin::instance()->get_rest_api();
		$request  = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );
		$request->set_query_params( array_filter( array(
			'status'   => $atts['status'],
			'mode'     => $atts['mode'],
			'per_page' => max( 1, min( 12, (int) $atts['count'] ) ),
			'orderby'  => 'newest',
		) ) );
		$data       = $rest->get_properties( $request )->get_data();
		$properties = $data['properties'] ?? array();

		ob_start();
		$layout  = in_array( $atts['layout'], array( 'card', 'minimal', 'list' ), true ) ? $atts['layout'] : 'card';
		$columns = max( 1, min( 4, (int) $atts['columns'] ) );
		?>
		<div class="immo-widget immo-widget--latest immo-widget--<?php echo esc_attr( $layout ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="immo-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $properties ) ) : ?>
				<p class="immo-widget-empty"><?php esc_html_e( 'Keine Immobilien gefunden.', 'immo-manager' ); ?></p>
			<?php elseif ( 'minimal' === $layout ) : ?>
				<ul class="immo-widget-list">
					<?php foreach ( $properties as $p ) :
						$meta = $p['meta'] ?? array();
						$price = $meta['mode'] === 'rent' ? ( $meta['rent_formatted'] ?? '' ) : ( $meta['price_formatted'] ?? '' );
						?>
						<li class="immo-widget-item">
							<a href="<?php echo esc_url( $p['permalink'] ?? '#' ); ?>" class="immo-widget-item-link">
								<span class="immo-widget-item-title"><?php echo esc_html( $p['title'] ?? '' ); ?></span>
								<?php if ( $meta['city'] ) : ?>
									<span class="immo-widget-item-loc">📍 <?php echo esc_html( $meta['city'] ); ?></span>
								<?php endif; ?>
								<?php if ( $price ) : ?>
									<span class="immo-widget-item-price"><?php echo esc_html( $price ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( 'list' === $layout ) : ?>
				<div class="immo-widget-list-layout">
					<?php foreach ( $properties as $property ) :
						include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/property-card.php';
					endforeach; ?>
				</div>
			<?php else : // card ?>
				<div class="immo-widget-grid immo-widget-cols-<?php echo esc_attr( (string) $columns ); ?>">
					<?php foreach ( $properties as $property ) :
						include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/property-card.php';
					endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $atts['link'] ) : ?>
				<p class="immo-widget-more">
					<a href="<?php echo esc_url( $atts['link'] ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm">
						<?php esc_html_e( 'Alle Immobilien ansehen', 'immo-manager' ); ?> →
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [immo_featured] – Einzelne hervorgehobene Immobilie.
	 *
	 * Attribute:
	 * - id     : Post-ID (leer = neueste verfügbare)
	 * - layout : hero | compact
	 *
	 * @param array<string, mixed>|string $atts Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_featured( $atts ): string {
		$atts = shortcode_atts( array(
			'id'     => 0,
			'layout' => 'hero',
		), (array) $atts, 'immo_featured' );

		$id   = absint( $atts['id'] );
		$rest = Plugin::instance()->get_rest_api();

		if ( $id ) {
			$request  = new \WP_REST_Request( 'GET', "/immo-manager/v1/properties/{$id}" );
			$request->set_url_params( array( 'id' => $id ) );
			$response = $rest->get_property( $request );
			if ( is_wp_error( $response ) ) {
				return '';
			}
			$property = $response->get_data();
		} else {
			$req = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );
			$req->set_query_params( array( 'status' => 'available', 'per_page' => 1, 'orderby' => 'newest' ) );
			$data  = $rest->get_properties( $req )->get_data();
			$items = $data['properties'] ?? array();
			if ( empty( $items ) ) { return ''; }
			$property = $items[0];
		}

		$meta    = $property['meta'] ?? array();
		$img     = $property['featured_image'] ?? null;
		$price   = $meta['mode'] === 'rent' ? ( $meta['rent_formatted'] ?? '' ) : ( $meta['price_formatted'] ?? '' );
		$layout  = $atts['layout'] === 'compact' ? 'compact' : 'hero';

		ob_start();
		?>
		<div class="immo-widget immo-widget--featured immo-widget--<?php echo esc_attr( $layout ); ?>">
			<?php if ( $img ) : ?>
				<div class="immo-featured-image">
					<img src="<?php echo esc_url( $img['url_medium'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ?: $property['title'] ); ?>" loading="lazy">
				</div>
			<?php endif; ?>
			<div class="immo-featured-body">
				<h3 class="immo-featured-title">
					<a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>"><?php echo esc_html( $property['title'] ?? '' ); ?></a>
				</h3>
				<?php if ( $meta['city'] ) : ?>
					<p class="immo-featured-loc">📍 <?php echo esc_html( trim( ( $meta['postal_code'] ?? '' ) . ' ' . ( $meta['city'] ?? '' ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $price ) : ?>
					<p class="immo-featured-price"><strong><?php echo esc_html( $price ); ?></strong></p>
				<?php endif; ?>
				<div class="immo-featured-facts">
					<?php if ( $meta['rooms'] ) : ?><span>🛏️ <?php echo esc_html( (string) $meta['rooms'] ); ?></span><?php endif; ?>
					<?php if ( $meta['area'] ) : ?><span>📐 <?php echo esc_html( number_format_i18n( (float) $meta['area'] ) . ' m²' ); ?></span><?php endif; ?>
					<?php if ( $meta['energy_class'] ) : ?><span>⚡ <?php echo esc_html( $meta['energy_class'] ); ?></span><?php endif; ?>
				</div>
				<a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>" class="immo-btn immo-btn-primary immo-btn-sm">
					<?php esc_html_e( 'Details ansehen', 'immo-manager' ); ?> →
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [immo_count] – Zähler-Widget.
	 *
	 * Attribute:
	 * - status  : available | reserved | sold | rented | all
	 * - label   : Text nach der Zahl
	 * - mode    : sale | rent | "" (alle)
	 *
	 * Beispiel: [immo_count status="available" label="Immobilien verfügbar"]
	 *
	 * @param array<string, mixed>|string $atts Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_count( $atts ): string {
		$atts = shortcode_atts( array(
			'status' => 'all',
			'label'  => __( 'Immobilien', 'immo-manager' ),
			'mode'   => '',
		), (array) $atts, 'immo_count' );

		$rest    = Plugin::instance()->get_rest_api();
		$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );
		$params  = array( 'per_page' => 1 );
		if ( $atts['status'] !== 'all' ) {
			$params['status'] = $atts['status'];
		}
		if ( $atts['mode'] ) {
			$params['mode'] = $atts['mode'];
		}
		$request->set_query_params( $params );
		$pagination = $rest->get_properties( $request )->get_data()['pagination'] ?? array();
		$total      = (int) ( $pagination['total'] ?? 0 );

		return '<span class="immo-widget immo-widget--count"><span class="immo-count-number">' . esc_html( number_format_i18n( $total ) ) . '</span> <span class="immo-count-label">' . esc_html( $atts['label'] ) . '</span></span>';
	}

	/**
	 * [immo_search] – Schnell-Suchformular.
	 *
	 * Attribute:
	 * - action  : URL der Listenseite (Pflicht)
	 * - title   : Widget-Titel
	 * - fields  : Komma-getrennt: type,mode,region,price (default alle)
	 *
	 * @param array<string, mixed>|string $atts Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_search( $atts ): string {
		$atts = shortcode_atts( array(
			'action' => '',
			'title'  => __( 'Immobilie suchen', 'immo-manager' ),
			'fields' => 'type,mode,region,price',
		), (array) $atts, 'immo_search' );

		$fields  = array_map( 'trim', explode( ',', $atts['fields'] ) );
		$states  = Regions::get_states();
		$action  = $atts['action'] ?: get_permalink();
		$currency = Settings::get( 'currency_symbol', '€' );

		ob_start();
		?>
		<div class="immo-widget immo-widget--search">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="immo-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<form class="immo-search-form" method="get" action="<?php echo esc_url( $action ); ?>">
				<?php if ( in_array( 'type', $fields, true ) ) : ?>
					<div class="immo-search-field">
						<label><?php esc_html_e( 'Immobilientyp', 'immo-manager' ); ?></label>
						<select name="type">
							<option value=""><?php esc_html_e( '— Alle Typen —', 'immo-manager' ); ?></option>
							<?php foreach ( array( 'wohnung' => __( 'Wohnung', 'immo-manager' ), 'einfamilienhaus' => __( 'Einfamilienhaus', 'immo-manager' ), 'grundstück' => __( 'Grundstück', 'immo-manager' ), 'gewerbe' => __( 'Gewerbe', 'immo-manager' ) ) as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<?php if ( in_array( 'mode', $fields, true ) ) : ?>
					<div class="immo-search-field">
						<label><?php esc_html_e( 'Angebot', 'immo-manager' ); ?></label>
						<select name="mode">
							<option value=""><?php esc_html_e( '— Kaufen & Mieten —', 'immo-manager' ); ?></option>
							<option value="sale"><?php esc_html_e( 'Kaufen', 'immo-manager' ); ?></option>
							<option value="rent"><?php esc_html_e( 'Mieten', 'immo-manager' ); ?></option>
						</select>
					</div>
				<?php endif; ?>
				<?php if ( in_array( 'region', $fields, true ) ) : ?>
					<div class="immo-search-field">
						<label><?php esc_html_e( 'Bundesland', 'immo-manager' ); ?></label>
						<select name="region_state">
							<option value=""><?php esc_html_e( '— Alle Regionen —', 'immo-manager' ); ?></option>
							<?php foreach ( $states as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<?php if ( in_array( 'price', $fields, true ) ) : ?>
					<div class="immo-search-field immo-search-field--price">
						<label><?php esc_html_e( 'Max. Preis', 'immo-manager' ); ?> (<?php echo esc_html( $currency ); ?>)</label>
						<input type="number" name="price_max" min="0" step="10000" placeholder="<?php esc_attr_e( 'Keine Begrenzung', 'immo-manager' ); ?>">
					</div>
				<?php endif; ?>
				<button type="submit" class="immo-btn immo-btn-primary">
					🔍 <?php esc_html_e( 'Suchen', 'immo-manager' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	// =========================================================================
	// [immo_detail_page] – URL-Parameter-basierte Detailseite
	// =========================================================================

	/**
	 * [immo_detail_page] – Dynamische Detailseite via URL-Parameter.
	 *
	 * Zeigt entweder:
	 * - Eine Detailseite wenn ?immo_id=X oder ?immo_slug=xyz in der URL
	 * - Einen Fallback-Inhalt (leer oder via Attribut) wenn kein Parameter
	 *
	 * Attribute:
	 * - fallback_url : URL auf die weitergeleitet wird wenn kein Parameter → Liste
	 * - show_back    : 1/0 – "Zurück zur Liste"-Link anzeigen (default 1)
	 * - back_url     : URL des "Zurück"-Links (default: Referrer)
	 * - back_label   : Text des "Zurück"-Links
	 *
	 * Verwendung (SPA-Modus):
	 *   Lege eine WordPress-Seite "/immobilien/" mit [immo_list] an.
	 *   Lege eine Seite "/immobilie/" mit [immo_detail_page] an.
	 *   Die Listenseite verlinkt dann auf /immobilie/?immo_id=42
	 *
	 * @param array<string, mixed>|string $atts Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_detail_page( $atts ): string {
		$atts = shortcode_atts( array(
			'fallback_url' => '',
			'show_back'    => 1,
			'back_url'     => '',
			'back_label'   => __( '← Zurück zur Übersicht', 'immo-manager' ),
		), (array) $atts, 'immo_detail_page' );

		// ID aus URL-Parameter auslesen.
		$id   = 0;
		$slug = '';

		if ( isset( $_GET['immo_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$id = absint( $_GET['immo_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( isset( $_GET['immo_slug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$slug = sanitize_title( wp_unslash( $_GET['immo_slug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		// Slug in ID auflösen.
		if ( ! $id && $slug ) {
			$found = get_posts( array(
				'post_type'   => PostTypes::POST_TYPE_PROPERTY,
				'name'        => $slug,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			) );
			$id = $found ? (int) $found[0] : 0;
		}

		// Kein Parameter → Weiterleitung oder Hinweis.
		if ( ! $id ) {
			if ( $atts['fallback_url'] ) {
				wp_safe_redirect( esc_url_raw( $atts['fallback_url'] ) );
				exit;
			}
			return '<p class="immo-no-results">' . esc_html__( 'Keine Immobilie ausgewählt. Bitte wähle eine Immobilie aus der Liste.', 'immo-manager' ) . '</p>';
		}

		$back_url = $atts['back_url'] ?: ( wp_get_referer() ?: home_url() );

		ob_start();
		if ( $atts['show_back'] ) : ?>
			<div class="immo-detail-back">
				<a href="<?php echo esc_url( $back_url ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm">
					<?php echo esc_html( $atts['back_label'] ); ?>
				</a>
			</div>
		<?php endif;

		// Normalen [immo_detail] Shortcode nutzen.
		echo $this->render_detail( array( 'id' => $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return ob_get_clean();
	}

	/**
	 * CSS Custom Properties + Design-Stil in <head> injizieren.
	 *
	 * Generiert das komplette Design-System aus den Einstellungen:
	 * Farben, Typografie, Spacing, Schatten, Hover-Effekte,
	 * und fügt die body-Klasse `immo-style-{style}` hinzu.
	 *
	 * @return void
	 */
	public function inject_css_vars(): void {
		$design_style = Settings::get( 'design_style', 'expressive' );

		// Farben.
		$primary   = Settings::get( 'primary_color', '#6750A4' );
		$secondary = Settings::get( 'secondary_color', '#625B71' );
		$accent    = Settings::get( 'accent_color', '#7D5260' );
		$bg        = Settings::get( 'background_color', '#FFFBFE' );
		$surface   = Settings::get( 'surface_color', '#FFFFFF' );
		$text      = Settings::get( 'text_color', '#1C1B1F' );
		$text_muted = Settings::get( 'text_muted_color', '#605D66' );
		$border    = Settings::get( 'border_color', '#E7E0EC' );
		$status_a  = Settings::get( 'status_available_color', '#2E7D32' );
		$status_r  = Settings::get( 'status_reserved_color', '#ED6C02' );
		$status_s  = Settings::get( 'status_sold_color', '#D32F2F' );

		// Typography.
		$font_heading = $this->resolve_font_family( Settings::get( 'font_family_heading', 'inter' ) );
		$font_body    = $this->resolve_font_family( Settings::get( 'font_family_body', 'inter' ) );
		$font_size    = (int) Settings::get( 'font_size_base', 16 );
		$heading_weight = (int) Settings::get( 'heading_weight', 700 );

		// Layout.
		$radius     = (int) Settings::get( 'border_radius', 16 );
		$blur       = (int) Settings::get( 'backdrop_blur', 12 );
		$shadow     = (string) Settings::get( 'shadow_intensity', 'medium' );
		$spacing    = (string) Settings::get( 'spacing_scale', 'normal' );
		$aspect     = (string) Settings::get( 'card_aspect_ratio', '4/3' );

		// Effekte.
		$speed     = (string) Settings::get( 'transition_speed', 'normal' );

		// Spacing-Scale.
		$spacing_vals = array(
			'compact'  => array( 'xs' => '4px',  'sm' => '8px',  'md' => '12px', 'lg' => '16px', 'xl' => '24px' ),
			'normal'   => array( 'xs' => '6px',  'sm' => '12px', 'md' => '16px', 'lg' => '24px', 'xl' => '36px' ),
			'spacious' => array( 'xs' => '8px',  'sm' => '16px', 'md' => '24px', 'lg' => '36px', 'xl' => '56px' ),
		);
		$sp = $spacing_vals[ $spacing ] ?? $spacing_vals['normal'];

		// Shadows pro Intensität.
		$shadow_vals = array(
			'none'   => array(
				'sm' => 'none',
				'md' => 'none',
				'lg' => 'none',
			),
			'soft'   => array(
				'sm' => '0 1px 2px rgba(0,0,0,.04)',
				'md' => '0 2px 8px rgba(0,0,0,.06)',
				'lg' => '0 8px 24px rgba(0,0,0,.08)',
			),
			'medium' => array(
				'sm' => '0 1px 3px rgba(0,0,0,.06)',
				'md' => '0 4px 14px rgba(0,0,0,.08)',
				'lg' => '0 12px 32px rgba(0,0,0,.12)',
			),
			'strong' => array(
				'sm' => '0 2px 6px rgba(0,0,0,.10)',
				'md' => '0 8px 24px rgba(0,0,0,.14)',
				'lg' => '0 20px 48px rgba(0,0,0,.20)',
			),
		);
		$sh = $shadow_vals[ $shadow ] ?? $shadow_vals['medium'];

		// Transition speeds.
		$speed_vals = array(
			'slow'   => '0.5s',
			'normal' => '0.25s',
			'fast'   => '0.15s',
		);
		$sp_val = $speed_vals[ $speed ] ?? $speed_vals['normal'];

		// Content-Tint für Glassmorphism (Hintergrund transparent).
		$surface_glass = $this->hex_to_rgba( $surface, 0.65 );
		$border_glass  = $this->hex_to_rgba( $primary, 0.15 );

		// Primary / Accent als RGBA für transparente Akzente.
		$primary_rgba_20 = $this->hex_to_rgba( $primary, 0.2 );
		$primary_rgba_30 = $this->hex_to_rgba( $primary, 0.3 );

		echo '<style id="immo-manager-design-system">:root{';
		// Farben.
		printf( '--immo-primary:%s;', esc_attr( $primary ) );
		printf( '--immo-secondary:%s;', esc_attr( $secondary ) );
		printf( '--immo-accent:%s;', esc_attr( $accent ) );
		printf( '--immo-bg:%s;', esc_attr( $bg ) );
		printf( '--immo-surface:%s;', esc_attr( $surface ) );
		printf( '--immo-surface-glass:%s;', esc_attr( $surface_glass ) );
		printf( '--immo-text:%s;', esc_attr( $text ) );
		printf( '--immo-text-muted:%s;', esc_attr( $text_muted ) );
		printf( '--immo-border:%s;', esc_attr( $border ) );
		printf( '--immo-border-glass:%s;', esc_attr( $border_glass ) );
		printf( '--immo-status-available:%s;', esc_attr( $status_a ) );
		printf( '--immo-status-reserved:%s;', esc_attr( $status_r ) );
		printf( '--immo-status-sold:%s;', esc_attr( $status_s ) );
		printf( '--immo-primary-20:%s;', esc_attr( $primary_rgba_20 ) );
		printf( '--immo-primary-30:%s;', esc_attr( $primary_rgba_30 ) );
		// Typography.
		printf( '--immo-font-heading:%s;', esc_attr( $font_heading ) );
		printf( '--immo-font-body:%s;', esc_attr( $font_body ) );
		printf( '--immo-font-size:%dpx;', $font_size );
		printf( '--immo-heading-weight:%d;', $heading_weight );
		// Layout.
		printf( '--immo-radius:%dpx;', $radius );
		printf( '--immo-radius-sm:%dpx;', max( 2, (int) ( $radius * 0.5 ) ) );
		printf( '--immo-radius-lg:%dpx;', (int) ( $radius * 1.3 ) );
		printf( '--immo-blur:%dpx;', $blur );
		printf( '--immo-aspect-ratio:%s;', esc_attr( $aspect ) );
		// Spacing.
		foreach ( $sp as $k => $v ) {
			printf( '--immo-space-%s:%s;', esc_attr( $k ), esc_attr( $v ) );
		}
		// Shadows.
		foreach ( $sh as $k => $v ) {
			printf( '--immo-shadow-%s:%s;', esc_attr( $k ), esc_attr( $v ) );
		}
		// Transition.
		printf( '--immo-transition:%s ease;', esc_attr( $sp_val ) );
		printf( '--immo-transition-slow:%s ease;', esc_attr( $speed_vals['slow'] ) );
		echo '}';

		// Design-Style-spezifische Overrides.
		$this->inject_style_specific_css( $design_style );

		echo '</style>' . "\n";

		// Google Fonts laden wenn nötig.
		$this->maybe_enqueue_google_fonts();

		// Body-Klasse via Filter hinzufügen.
		add_filter( 'body_class', function ( $classes ) use ( $design_style ) {
			$classes[] = 'immo-style-' . $design_style;
			return $classes;
		} );
		add_filter( 'admin_body_class', function ( $classes ) use ( $design_style ) {
			return $classes . ' immo-style-' . $design_style;
		} );
	}

	/**
	 * Design-Stil-spezifische CSS-Regeln ausgeben.
	 *
	 * @param string $style Design-Stil.
	 *
	 * @return void
	 */
	private function inject_style_specific_css( string $style ): void {
		$hover_effect = (string) Settings::get( 'hover_effect', 'lift' );
		$image_hover  = (string) Settings::get( 'image_hover', 'zoom' );

		switch ( $style ) {
			case 'glassmorphism':
				echo '
					.immo-style-glassmorphism .immo-property-card,
					.immo-style-glassmorphism .immo-contact-card,
					.immo-style-glassmorphism .immo-inquiry-card,
					.immo-style-glassmorphism .immo-filter-sidebar,
					.immo-style-glassmorphism .immo-widget--featured {
						background: var(--immo-surface-glass);
						backdrop-filter: blur(var(--immo-blur)) saturate(1.4);
						-webkit-backdrop-filter: blur(var(--immo-blur)) saturate(1.4);
						border: 1px solid var(--immo-border-glass);
						box-shadow: var(--immo-shadow-md), inset 0 1px 0 rgba(255,255,255,0.5);
					}
					.immo-style-glassmorphism .immo-btn-primary {
						background: var(--immo-primary);
						box-shadow: 0 4px 16px var(--immo-primary-30);
					}
					.immo-style-glassmorphism .immo-status-badge {
						backdrop-filter: blur(8px);
						background: rgba(255,255,255,0.75);
						color: var(--immo-text);
						border: 1px solid var(--immo-border-glass);
					}
				';
				break;

			case 'expressive':
				echo '
					.immo-style-expressive .immo-property-card {
						border: none;
						box-shadow: var(--immo-shadow-md);
					}
					.immo-style-expressive .immo-card-title { font-weight: 800; letter-spacing: -0.02em; }
					.immo-style-expressive .immo-card-price { font-size: 1.25rem; font-weight: 800; }
					.immo-style-expressive .immo-btn {
						border-radius: 999px;
						font-weight: 600;
						letter-spacing: 0.01em;
					}
					.immo-style-expressive .immo-btn-primary {
						background: var(--immo-primary);
						box-shadow: 0 2px 8px var(--immo-primary-20);
					}
					.immo-style-expressive .immo-status-badge { font-weight: 700; }
					.immo-style-expressive .immo-filter-btn-option span { border-radius: 999px; }
				';
				break;

			case 'minimal':
				echo '
					.immo-style-minimal .immo-property-card {
						background: var(--immo-surface);
						border: 1px solid var(--immo-border);
						box-shadow: none;
					}
					.immo-style-minimal .immo-property-card:hover { border-color: var(--immo-primary); }
					.immo-style-minimal .immo-btn { border-radius: 4px; }
					.immo-style-minimal .immo-card-title { font-weight: 500; }
					.immo-style-minimal .immo-card-price { color: var(--immo-text); }
				';
				break;

			case 'classic':
			default:
				echo '
					.immo-style-classic .immo-property-card {
						background: var(--immo-surface);
						border: 1px solid var(--immo-border);
						box-shadow: var(--immo-shadow-sm);
					}
					.immo-style-classic .immo-card-title { font-weight: 600; }
				';
				break;
		}

		// Hover-Effekte.
		$hover_css = array(
			'none'  => '',
			'lift'  => '.immo-property-card:hover { transform: translateY(-4px); box-shadow: var(--immo-shadow-lg); }',
			'glow'  => '.immo-property-card:hover { box-shadow: 0 0 0 2px var(--immo-primary), var(--immo-shadow-lg); }',
			'scale' => '.immo-property-card:hover { transform: scale(1.02); box-shadow: var(--immo-shadow-lg); }',
		);
		echo $hover_css[ $hover_effect ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Image-Hover-Effekte.
		$image_css = array(
			'none'       => '',
			'zoom'       => '.immo-property-card:hover .immo-card-image img { transform: scale(1.08); }',
			'fade'       => '.immo-property-card:hover .immo-card-image img { filter: brightness(0.85); }',
			'brightness' => '.immo-property-card:hover .immo-card-image img { filter: brightness(1.08); }',
		);
		echo $image_css[ $image_hover ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Schriftart-Key zu CSS-Font-Stack auflösen.
	 *
	 * @param string $key Font-Key aus Settings.
	 *
	 * @return string CSS-Wert für font-family.
	 */
	private function resolve_font_family( string $key ): string {
		$stacks = array(
			'inter'    => '"Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
			'poppins'  => '"Poppins", system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
			'roboto'   => '"Roboto", system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
			'dm-sans'  => '"DM Sans", system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
			'playfair' => '"Playfair Display", Georgia, "Times New Roman", serif',
			'system'   => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
		);
		if ( 'custom' === $key ) {
			$custom = Settings::get( 'font_family_custom', '' );
			return $custom ?: $stacks['system'];
		}
		return $stacks[ $key ] ?? $stacks['inter'];
	}

	/**
	 * Google Fonts laden wenn die Schriftart dies erfordert.
	 *
	 * @return void
	 */
	private function maybe_enqueue_google_fonts(): void {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$needed  = array_unique( array(
			Settings::get( 'font_family_heading', 'inter' ),
			Settings::get( 'font_family_body', 'inter' ),
		) );

		$google_fonts = array(
			'inter'    => 'Inter:wght@400;500;600;700;800;900',
			'poppins'  => 'Poppins:wght@400;500;600;700;800;900',
			'roboto'   => 'Roboto:wght@400;500;700;900',
			'dm-sans'  => 'DM+Sans:wght@400;500;700',
			'playfair' => 'Playfair+Display:wght@400;600;700;800;900',
		);

		$families = array();
		foreach ( $needed as $font ) {
			if ( isset( $google_fonts[ $font ] ) ) {
				$families[] = 'family=' . $google_fonts[ $font ];
			}
		}
		if ( $families ) {
			$url = 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
			printf( '<link rel="preconnect" href="https://fonts.googleapis.com">' );
			printf( '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' );
			printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( $url ) );
		}
		$enqueued = true;
	}

	/**
	 * Hex-Farbe in RGBA umwandeln (für transparente Overlays).
	 *
	 * @param string $hex   Hex-Code (#RRGGBB).
	 * @param float  $alpha Alpha (0-1).
	 *
	 * @return string rgba(r,g,b,a)
	 */
	private function hex_to_rgba( string $hex, float $alpha ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( strlen( $hex ) !== 6 ) {
			return 'rgba(255,255,255,' . $alpha . ')';
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return sprintf( 'rgba(%d,%d,%d,%.2f)', $r, $g, $b, $alpha );
	}
}
