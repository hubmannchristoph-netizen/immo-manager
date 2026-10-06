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
	 * Alle Shortcode-Tags des Plugins (für die bedarfsgesteuerte Asset-Erkennung).
	 */
	public const SHORTCODES = array(
		'immo_list',
		'immo_detail',
		'immo_detail_page',
		'immo_latest',
		'immo_featured',
		'immo_count',
		'immo_search',
		'immo_projects',
		'immo_units',
	);

	/**
	 * Wurden die Asset-Handles bereits registriert?
	 *
	 * @var bool
	 */
	private $assets_registered = false;

	/**
	 * Wurde die JS-Konfiguration (wp_localize_script) bereits angehängt?
	 *
	 * @var bool
	 */
	private $localized = false;

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
		// Bauprojekte & Wohneinheiten.
		add_shortcode( 'immo_projects', array( $this, 'render_projects' ) );
		add_shortcode( 'immo_units',    array( $this, 'render_units' ) );

		// Assets: zuerst nur registrieren, dann bedarfsgesteuert laden
		// (CPT-Seiten, Seiten mit Plugin-Shortcodes, Elementor-Widgets).
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
		add_filter( 'wp_resource_hints', array( $this, 'add_resource_hints' ), 10, 2 );

		// Design-Style als Body-Klasse (Frontend + Admin).
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
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

		$this->enqueue_assets();

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

		// Assets nachladen (Detailseite braucht zusätzlich die Rechner).
		$this->enqueue_assets();
		$this->enqueue_calculator_assets();

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
	 * Alle Asset-Handles registrieren (ohne sie zu laden).
	 *
	 * Idempotent – kann mehrfach aufgerufen werden.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( $this->assets_registered ) {
			return;
		}
		$this->assets_registered = true;

		$fonts_handle = $this->register_google_fonts();
		$style_deps   = $fonts_handle ? array( $fonts_handle ) : array();

		// Styles.
		wp_register_style(
			'immo-manager-frontend',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/frontend.css',
			$style_deps,
			IMMO_MANAGER_VERSION
		);
		// Design-System (CSS Custom Properties) direkt an das Haupt-Stylesheet hängen –
		// wird dadurch genau dort ausgegeben, wo das Stylesheet geladen wird.
		wp_add_inline_style( 'immo-manager-frontend', $this->get_design_css() );

		wp_register_style(
			'immo-manager-cf-badge',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/commission-free-badge.css',
			array( 'immo-manager-frontend' ),
			IMMO_MANAGER_VERSION
		);
		wp_register_style(
			'immo-manager-wizard',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/wizard.css',
			array( 'immo-manager-frontend' ),
			IMMO_MANAGER_VERSION
		);
		wp_register_style(
			'immo-manager-calculators',
			IMMO_MANAGER_PLUGIN_URL . 'public/css/calculators.css',
			array( 'immo-manager-frontend' ),
			IMMO_MANAGER_VERSION
		);

		// Scripts (filters.js ist Vanilla-JS ohne jQuery-Abhängigkeit).
		wp_register_script(
			'immo-manager-filters',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/filters.js',
			array(),
			IMMO_MANAGER_VERSION,
			true
		);
		wp_register_script(
			'immo-manager-frontend',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/frontend.js',
			array( 'jquery' ),
			IMMO_MANAGER_VERSION,
			true
		);
		wp_register_script(
			'immo-manager-wizard',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/wizard.js',
			array( 'jquery', 'immo-manager-filters' ),
			IMMO_MANAGER_VERSION,
			true
		);
		wp_register_script(
			'immo-manager-calculators',
			IMMO_MANAGER_PLUGIN_URL . 'public/js/calculators.js',
			array( 'immo-manager-filters' ),
			IMMO_MANAGER_VERSION,
			true
		);

		// Leaflet lokal gebündelt (kein CDN-Request an unpkg.com → DSGVO, Offline-fähig,
		// kein Single-Point-of-Failure). Handle nur belegen, wenn kein anderes Plugin
		// Leaflet bereits registriert hat.
		if ( ! wp_style_is( 'leaflet', 'registered' ) ) {
			wp_register_style( 'leaflet', IMMO_MANAGER_PLUGIN_URL . 'public/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		}
		if ( ! wp_script_is( 'leaflet', 'registered' ) ) {
			wp_register_script( 'leaflet', IMMO_MANAGER_PLUGIN_URL . 'public/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		}
	}

	/**
	 * Frontend-Assets bedarfsgesteuert laden (Hook: wp_enqueue_scripts).
	 *
	 * Lädt nur auf Seiten, die das Plugin tatsächlich darstellen:
	 * - CPT-Einzel-/Archivseiten (Immobilien, Bauprojekte)
	 * - Seiten/Beiträge, deren Inhalt einen Plugin-Shortcode enthält
	 * - alles, was der Filter `immo_manager_load_frontend_assets` erzwingt
	 *
	 * Shortcodes/Elementor-Widgets, die anderswo gerendert werden (Widget-Areas,
	 * Theme-Templates, Blöcke), laden die Assets zusätzlich zur Render-Zeit nach.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$cpts = array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT );
		$load = is_singular( $cpts ) || is_post_type_archive( $cpts ) || is_tax( array( PostTypes::TAX_CATEGORY, PostTypes::TAX_LOCATION, PostTypes::TAX_AGENT ) );
		$wizard = false;

		if ( ! $load && is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				$load   = $this->content_has_shortcode( (string) $post->post_content );
				$wizard = has_shortcode( (string) $post->post_content, 'immo_wizard' );
			}
		}

		/**
		 * Erzwingt (oder unterbindet) das Laden der Frontend-Assets auf der aktuellen Seite.
		 *
		 * @param bool $load Ob die Assets geladen werden sollen.
		 */
		$load = (bool) apply_filters( 'immo_manager_load_frontend_assets', $load || $wizard );

		if ( $load ) {
			$this->enqueue_assets();
		}
		if ( $wizard ) {
			$this->enqueue_wizard_assets();
		}
	}

	/**
	 * Enthält der Inhalt einen der Plugin-Shortcodes?
	 *
	 * @param string $content Post-Content.
	 *
	 * @return bool
	 */
	private function content_has_shortcode( string $content ): bool {
		if ( '' === $content || false === strpos( $content, '[immo_' ) ) {
			return false;
		}
		foreach ( self::SHORTCODES as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Frontend-Basis-Assets laden (CSS, Filter-/Frontend-JS, Leaflet, Rechner).
	 *
	 * Idempotent – darf von Templates, Shortcodes und Widgets beliebig oft
	 * aufgerufen werden. Wird zur Render-Zeit aufgerufen, landen Styles im Footer
	 * (WordPress druckt nachgeladene Styles via print_late_styles()).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$this->register_assets();

		wp_enqueue_style( 'immo-manager-frontend' );
		// „Provisionsfrei"-Badge — überall verfügbar, da auch in Listings genutzt.
		wp_enqueue_style( 'immo-manager-cf-badge' );

		wp_enqueue_script( 'immo-manager-filters' );
		wp_enqueue_script( 'immo-manager-frontend' );

		// Leaflet für Kartendarstellung.
		if ( Settings::get( 'map_enabled', 1 ) ) {
			wp_enqueue_style( 'leaflet' );
			wp_enqueue_script( 'leaflet' );
		}

		// Rechner – auf Detailseiten (CPT-Single oder [immo_detail]).
		if ( is_singular( array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ) ) ) {
			$this->enqueue_calculator_assets();
		}

		$this->localize();
	}

	/**
	 * Zusätzliche Assets für den Eingabe-Wizard ([immo_wizard] bzw. Admin-Wizard-Seite).
	 *
	 * @return void
	 */
	public function enqueue_wizard_assets(): void {
		$this->enqueue_assets();
		wp_enqueue_style( 'immo-manager-wizard' );
		wp_enqueue_script( 'immo-manager-wizard' );
	}

	/**
	 * Zusätzliche Assets für Nebenkosten-/Finanzierungsrechner.
	 *
	 * @return void
	 */
	public function enqueue_calculator_assets(): void {
		$this->register_assets();
		wp_enqueue_style( 'immo-manager-calculators' );
		wp_enqueue_script( 'immo-manager-calculators' );
	}

	/**
	 * JS-Konfiguration (window.immoManager) einmalig anhängen.
	 *
	 * @return void
	 */
	private function localize(): void {
		if ( $this->localized ) {
			return;
		}
		$this->localized = true;

		$localize_data = array(
			'apiBase'        => rest_url( RestApi::NAMESPACE ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'mapEnabled'     => (bool) Settings::get( 'map_enabled', 1 ),
			'mapTileUrl'     => Settings::get( 'map_tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' ),
			'mapAttrib'      => Settings::get( 'map_attribution', '' ),
			'mapDefaultLat'  => (float) Settings::get( 'map_default_lat', 47.5162 ),
			'mapDefaultLng'  => (float) Settings::get( 'map_default_lng', 14.5501 ),
			'mapDefaultZoom' => (int) Settings::get( 'map_default_zoom', 7 ),
		);

		// Rechner-Konfiguration ist klein und wird immer mitgegeben, damit auch
		// [immo_detail] auf normalen Seiten die Rechner initialisieren kann.
		$localize_data['calc'] = array(
			'enabled'  => array(
				'costs'     => (bool) Settings::get( 'calc_enable_costs', 1 ),
				'financing' => (bool) Settings::get( 'calc_enable_financing', 1 ),
			),
			'rates'    => array(
				'grest'     => (float) Settings::get( 'calc_grest_rate', 3.5 ),
				'grundbuch' => (float) Settings::get( 'calc_grundbuch_rate', 1.1 ),
				'notar'     => (float) Settings::get( 'calc_notar_rate', 2.5 ),
				'provision' => (float) Settings::get( 'calc_provision_rate', 3.0 ),
				'ust'       => (float) Settings::get( 'calc_ust_rate', 20.0 ),
			),
			'show'     => array(
				'grest'     => (bool) Settings::get( 'calc_show_grest', 1 ),
				'grundbuch' => (bool) Settings::get( 'calc_show_grundbuch', 1 ),
				'notar'     => (bool) Settings::get( 'calc_show_notar', 1 ),
				'provision' => (bool) Settings::get( 'calc_show_provision', 1 ),
				'ust'       => (bool) Settings::get( 'calc_show_ust_on_provision', 1 ),
			),
			'notar'    => array(
				'mode' => (string) Settings::get( 'calc_notar_mode', 'percent' ),
				'flat' => (int)    Settings::get( 'calc_notar_flat', 1500 ),
			),
			'finance'  => array(
				'equityPct'        => (int)   Settings::get( 'calc_default_equity_pct', 20 ),
				'interestRate'     => (float) Settings::get( 'calc_default_interest_rate', 3.5 ),
				'termYears'        => (int)   Settings::get( 'calc_default_term_years', 25 ),
				'extraPayment'     => (int)   Settings::get( 'calc_default_extra_payment', 0 ),
				'showAmortization' => (bool)  Settings::get( 'calc_show_amortization_table', 1 ),
			),
			'currency' => array(
				'symbol'    => (string) Settings::get( 'currency_symbol', '€' ),
				'position'  => (string) Settings::get( 'currency_position', 'after' ),
				'decimals'  => (int)    Settings::get( 'price_decimals', 0 ),
				'thousands' => (string) Settings::get( 'thousands_separator', '.' ),
				'decimal'   => (string) Settings::get( 'decimal_separator', ',' ),
			),
			'i18n'     => array(
				'noFinancing' => __( 'Keine Finanzierung nötig (Eigenkapital ≥ Bedarf).', 'immo-manager' ),
				'showTable'   => __( 'Tilgungsplan anzeigen', 'immo-manager' ),
				'hideTable'   => __( 'Tilgungsplan ausblenden', 'immo-manager' ),
			),
		);

		$localize_data['i18n'] = array(
			'loading'         => __( 'Lädt…', 'immo-manager' ),
			'noResults'       => __( 'Keine Immobilien gefunden.', 'immo-manager' ),
			'filterToggle'    => __( 'Filter', 'immo-manager' ),
			'filterClose'     => __( 'Schließen', 'immo-manager' ),
			'filterReset'     => __( 'Zurücksetzen', 'immo-manager' ),
			'activeFilters'   => __( 'Aktive Filter', 'immo-manager' ),
			'results'         => __( 'Ergebnisse', 'immo-manager' ),
			'district'        => __( '— Bezirk —', 'immo-manager' ),
			'inquirySent'     => __( 'Anfrage gesendet! Wir melden uns in Kürze.', 'immo-manager' ),
			'inquiryError'    => __( 'Fehler beim Senden. Bitte versuche es erneut.', 'immo-manager' ),
			'required'        => __( 'Pflichtfeld.', 'immo-manager' ),
			'invalidEmail'    => __( 'Gültige E-Mail-Adresse erforderlich.', 'immo-manager' ),
			'consentRequired' => __( 'Bitte bestätige die Datenschutzerklärung.', 'immo-manager' ),
			'copied'          => __( 'Kopiert!', 'immo-manager' ),
		);

		wp_localize_script(
			'immo-manager-filters',
			'immoManager',
			$localize_data
		);
	}

	/**
	 * Preconnect-Hints für Google Fonts – nur wenn die Fonts tatsächlich geladen werden.
	 *
	 * @param array<int, mixed> $urls          Bisherige Hints.
	 * @param string            $relation_type Hint-Typ (preconnect, dns-prefetch, …).
	 *
	 * @return array<int, mixed>
	 */
	public function add_resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}
		if ( ! wp_style_is( 'immo-manager-fonts', 'registered' ) || ! wp_style_is( 'immo-manager-frontend', 'enqueued' ) ) {
			return $urls;
		}
		$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		return $urls;
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

		$this->enqueue_assets();

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

		$this->enqueue_assets();

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

		$this->enqueue_assets();

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

		$this->enqueue_assets();

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

	// =========================================================================
	// [immo_projects] – Bauprojekte-Liste
	// =========================================================================

	/**
	 * [immo_projects] rendern.
	 *
	 * Attribute:
	 * - count   : Anzahl Projekte (1–50, default 12)
	 * - status  : planning | building | completed (kommagetrennt möglich, leer = alle)
	 * - columns : 1–4 Spalten im Grid (default 3)
	 * - layout  : grid | list | slider (default grid)
	 * - title   : Optionale Überschrift
	 * - link    : Optionale „Alle Bauprojekte"-URL (default: Projekt-Archiv)
	 *
	 * @param array<string, mixed>|string $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_projects( $atts ): string {
		$atts = shortcode_atts( array(
			'count'   => 12,
			'status'  => '',
			'columns' => 3,
			'layout'  => 'grid',
			'title'   => '',
			'link'    => '',
		), (array) $atts, 'immo_projects' );

		$this->enqueue_assets();

		$rest    = Plugin::instance()->get_rest_api();
		$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/projects' );
		$request->set_query_params( array_filter( array(
			'per_page' => max( 1, min( 50, (int) $atts['count'] ) ),
			'status'   => sanitize_text_field( (string) $atts['status'] ),
		) ) );
		$projects = $rest->get_projects( $request )->get_data()['projects'] ?? array();

		$layout  = in_array( $atts['layout'], array( 'grid', 'list', 'slider' ), true ) ? $atts['layout'] : 'grid';
		$columns = max( 1, min( 4, (int) $atts['columns'] ) );

		switch ( $layout ) {
			case 'list':
				$wrapper_class = 'immo-widget-list-layout';
				break;
			case 'slider':
				$wrapper_class = 'immo-list-slider columns-' . $columns;
				break;
			default:
				$wrapper_class = 'immo-widget-grid immo-widget-cols-' . $columns;
		}

		ob_start();
		?>
		<div class="immo-widget immo-widget--projects immo-widget--<?php echo esc_attr( $layout ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="immo-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $projects ) ) : ?>
				<p class="immo-widget-empty"><?php esc_html_e( 'Keine Bauprojekte gefunden.', 'immo-manager' ); ?></p>
			<?php else : ?>
				<div class="immo-projects-grid <?php echo esc_attr( $wrapper_class ); ?>" role="list">
					<?php foreach ( $projects as $project ) :
						include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/project-card.php';
					endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			$more_link = $atts['link'] ? $atts['link'] : get_post_type_archive_link( PostTypes::POST_TYPE_PROJECT );
			if ( $atts['link'] && $more_link ) : ?>
				<p class="immo-widget-more">
					<a href="<?php echo esc_url( $more_link ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm">
						<?php esc_html_e( 'Alle Bauprojekte ansehen', 'immo-manager' ); ?> →
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// =========================================================================
	// [immo_units] – Wohneinheiten eines Bauprojekts
	// =========================================================================

	/**
	 * [immo_units] rendern.
	 *
	 * Attribute:
	 * - project    : Projekt-ID oder Slug (leer = aktuelles Bauprojekt auf dessen Einzelseite)
	 * - status     : available | reserved | sold | rented (kommagetrennt, leer = alle)
	 * - orderby    : unit_number | floor | area | price | rooms | status (default unit_number)
	 * - limit      : Maximale Anzahl (0 = alle)
	 * - title      : Optionale Überschrift
	 * - show_stats : 1|0 – Status-Zusammenfassung über der Tabelle (default 1)
	 *
	 * @param array<string, mixed>|string $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_units( $atts ): string {
		$atts = shortcode_atts( array(
			'project'    => '',
			'status'     => '',
			'orderby'    => 'unit_number',
			'limit'      => 0,
			'title'      => '',
			'show_stats' => 1,
		), (array) $atts, 'immo_units' );

		$this->enqueue_assets();

		$project_id = $this->resolve_project_id( (string) $atts['project'] );
		if ( ! $project_id ) {
			return '<p class="immo-no-results">' . esc_html__( 'Kein Bauprojekt angegeben oder gefunden.', 'immo-manager' ) . '</p>';
		}

		$rest = Plugin::instance()->get_rest_api();
		$req  = new \WP_REST_Request( 'GET', "/immo-manager/v1/projects/{$project_id}/units" );
		$req->set_url_params( array( 'id' => $project_id ) );
		$req->set_query_params( array_filter( array(
			'status'  => sanitize_text_field( (string) $atts['status'] ),
			'orderby' => sanitize_key( (string) $atts['orderby'] ),
			'limit'   => max( 0, (int) $atts['limit'] ),
		) ) );
		$data       = $rest->get_project_units( $req )->get_data();
		$units      = $data['units'] ?? array();
		$unit_stats = $data['stats'] ?? array();

		$status_labels = array(
			'available' => __( 'Verfügbar', 'immo-manager' ),
			'reserved'  => __( 'Reserviert', 'immo-manager' ),
			'sold'      => __( 'Verkauft', 'immo-manager' ),
			'rented'    => __( 'Vermietet', 'immo-manager' ),
		);
		$status_class = array(
			'available' => 'status-available',
			'reserved'  => 'status-reserved',
			'sold'      => 'status-sold',
			'rented'    => 'status-sold',
		);

		ob_start();
		?>
		<div class="immo-widget immo-widget--units" data-project-id="<?php echo esc_attr( (string) $project_id ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="immo-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( $atts['show_stats'] && ! empty( $unit_stats['total'] ) ) : ?>
				<div class="immo-units-filter" aria-label="<?php esc_attr_e( 'Verfügbarkeit', 'immo-manager' ); ?>">
					<span class="immo-units-filter-pill is-active">
						<?php esc_html_e( 'Gesamt', 'immo-manager' ); ?>
						<span class="immo-units-filter-count"><?php echo (int) $unit_stats['total']; ?></span>
					</span>
					<?php foreach ( $status_labels as $st_key => $st_label ) :
						if ( empty( $unit_stats[ $st_key ] ) ) {
							continue;
						} ?>
						<span class="immo-units-filter-pill <?php echo esc_attr( $status_class[ $st_key ] ); ?>">
							<?php echo esc_html( $st_label ); ?>
							<span class="immo-units-filter-count"><?php echo (int) $unit_stats[ $st_key ]; ?></span>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $units ) ) : ?>
				<p class="immo-widget-empty"><?php esc_html_e( 'Keine Wohneinheiten gefunden.', 'immo-manager' ); ?></p>
			<?php else :
				include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/units-table.php';
			endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Bauprojekt-Referenz (ID oder Slug) in eine veröffentlichte Projekt-ID auflösen.
	 *
	 * Leer → aktuelles Bauprojekt, wenn wir auf dessen Einzelseite sind.
	 *
	 * @param string $ref ID, Slug oder ''.
	 *
	 * @return int 0 wenn nicht gefunden.
	 */
	private function resolve_project_id( string $ref ): int {
		$ref = trim( $ref );

		if ( '' === $ref ) {
			$current = get_queried_object_id();
			return ( $current && PostTypes::POST_TYPE_PROJECT === get_post_type( $current ) ) ? (int) $current : 0;
		}

		if ( ctype_digit( $ref ) ) {
			$post = get_post( (int) $ref );
			return ( $post && PostTypes::POST_TYPE_PROJECT === $post->post_type && 'publish' === $post->post_status ) ? (int) $post->ID : 0;
		}

		$found = get_posts( array(
			'name'           => sanitize_title( $ref ),
			'post_type'      => PostTypes::POST_TYPE_PROJECT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		return $found ? (int) $found[0] : 0;
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
	public function print_design_css(): void {
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

		echo ':root{';
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

	}

	/**
	 * Design-System-CSS (Custom Properties + Style-Overrides) als String.
	 *
	 * Wird via wp_add_inline_style() an das Frontend- bzw. Admin-Stylesheet gehängt.
	 *
	 * @return string
	 */
	public function get_design_css(): string {
		ob_start();
		$this->print_design_css();
		return (string) ob_get_clean();
	}

	/**
	 * Body-Klasse mit dem aktiven Design-Style (Frontend).
	 *
	 * @param array<int, string> $classes Klassen.
	 *
	 * @return array<int, string>
	 */
	public function add_body_class( array $classes ): array {
		$classes[] = 'immo-style-' . sanitize_html_class( (string) Settings::get( 'design_style', 'expressive' ) );
		return $classes;
	}

	/**
	 * Body-Klasse mit dem aktiven Design-Style (Admin).
	 *
	 * @param string $classes Klassen-String.
	 *
	 * @return string
	 */
	public function add_admin_body_class( string $classes ): string {
		return $classes . ' immo-style-' . sanitize_html_class( (string) Settings::get( 'design_style', 'expressive' ) );
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
	 * Google-Fonts-Stylesheet registrieren (falls aktiviert und benötigt).
	 *
	 * Gibt den Handle zurück, der als Dependency des Frontend-Stylesheets dient,
	 * oder '' wenn keine externen Fonts geladen werden (System-Font-Stacks).
	 *
	 * @return string
	 */
	private function register_google_fonts(): string {
		if ( ! Settings::get( 'load_google_fonts', 1 ) ) {
			return '';
		}

		$needed = array_unique( array(
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
		if ( ! $families ) {
			return '';
		}

		$url = 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
		wp_register_style( 'immo-manager-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		return 'immo-manager-fonts';
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
