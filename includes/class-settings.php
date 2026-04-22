<?php
/**
 * Plugin-Einstellungen via WordPress Settings API.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Verwaltet die Plugin-Optionen (Währung, Farben, Features, Kontakt, …)
 * mit der WordPress Settings API.
 */
class Settings {

	/**
	 * Option-Key, unter dem alle Settings als Array gespeichert werden.
	 */
	public const OPTION_NAME = 'immo_manager_settings';

	/**
	 * Menu-Slug der Settings-Seite.
	 */
	public const MENU_SLUG = 'immo-manager-settings';

	/**
	 * Settings-Gruppe (für register_setting / settings_fields).
	 */
	public const SETTINGS_GROUP = 'immo_manager_settings_group';

	/**
	 * Required Capability für Settings-Zugriff.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Konstruktor registriert Hooks.
	 */
	public function __construct() {
		// Submenü NACH dem Top-Level-Menü registrieren.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'flush_caches' ) );
	}

	/**
	 * Standard-Einstellungen.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			// Allgemein.
			'currency'            => 'EUR',
			'currency_symbol'     => '€',
			'currency_position'   => 'after',
			'price_decimals'      => 0,
			'thousands_separator' => '.',
			'decimal_separator'   => ',',

			// === DESIGN: Layout-Stil (Haupt-Option) ===
			'design_style'        => 'expressive', // glassmorphism | expressive | minimal | classic

			// === DESIGN: Farbpalette ===
			'primary_color'       => '#6750A4',
			'secondary_color'     => '#625B71',
			'accent_color'        => '#7D5260',
			'background_color'    => '#FFFBFE',
			'surface_color'       => '#FFFFFF',
			'text_color'          => '#1C1B1F',
			'text_muted_color'    => '#605D66',
			'border_color'        => '#E7E0EC',

			// Status-Farben.
			'status_available_color' => '#2E7D32',
			'status_reserved_color'  => '#ED6C02',
			'status_sold_color'      => '#D32F2F',

			// === DESIGN: Typography ===
			'font_family_heading' => 'inter',    // inter | poppins | roboto | dm-sans | system | custom
			'font_family_body'    => 'inter',
			'font_family_custom'  => '',
			'font_size_base'      => 16,         // 14-20 px
			'heading_weight'      => 700,        // 400|500|600|700|800|900

			// === DESIGN: Layout & Spacing ===
			'border_radius'       => 16,         // 0-32 px
			'shadow_intensity'    => 'medium',   // none | soft | medium | strong
			'spacing_scale'       => 'normal',   // compact | normal | spacious
			'card_aspect_ratio'   => '4/3',      // 4/3 | 16/9 | 1/1 | 3/2

			// === DESIGN: Effekte ===
			'hover_effect'        => 'lift',     // none | lift | glow | scale
			'image_hover'         => 'zoom',     // none | zoom | fade | brightness
			'transition_speed'    => 'normal',   // slow | normal | fast
			'backdrop_blur'       => 12,         // 0-40 px (für Glassmorphism)

			'default_view'        => 'grid',
			'items_per_page'      => 12,

			// === NEU: Standard-Layouts für Detailseite ===
			'default_detail_layout'  => 'standard', // standard | compact
			'default_gallery_type'   => 'slider',   // slider | grid
			'default_hero_type'      => 'full',     // full | contained

			// Features.
			'enable_wizard'       => 1,
			'enable_filter'       => 1,
			'enable_projects'     => 1,
			'enable_inquiries'    => 1,
			// Kontakt.
			'contact_email'       => '',
			'sender_name'         => '',
			'agent_name'          => '',
			'agent_email'         => '',
			'agent_phone'         => '',
			'agent_image_id'      => 0,
			'email_logo_id'       => 0,
			'admin_notifications' => 1,
			// Integrationen (REST API / Headless).
			'cors_allowed_origins' => '*',
			'api_key_hash'         => '',
			'api_key_preview'      => '',
			'api_rate_limit'       => 60,
			// Karten.
			'map_enabled'         => 1,
			'map_tile_url'        => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			'map_attribution'     => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			'map_default_lat'     => 47.5162,
			'map_default_lng'     => 14.5501,
			'map_default_zoom'    => 7,
		);
	}

	/**
	 * Einen Setting-Wert abrufen.
	 *
	 * @param string $key     Option-Key.
	 * @param mixed  $default Fallback-Wert.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();
		$merged   = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

		if ( array_key_exists( $key, $merged ) ) {
			return $merged[ $key ];
		}

		return $default;
	}

	/**
	 * Alle Settings abrufen.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Settings-Submenü registrieren.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminPages::MENU_SLUG,
			__( 'Einstellungen – Immo Manager', 'immo-manager' ),
			__( 'Einstellungen', 'immo-manager' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Settings registrieren (Sections & Fields).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		$this->register_general_section();
		$this->register_design_section();
		$this->register_features_section();
		$this->register_contact_section();
		$this->register_api_section();
		$this->register_integrations_section();
	}

	/**
	 * Section: Allgemein.
	 *
	 * @return void
	 */
	private function register_general_section(): void {
		$section = 'immo_general';

		add_settings_section(
			$section,
			__( 'Allgemein', 'immo-manager' ),
			function () {
				echo '<p>' . esc_html__( 'Grundlegende Einstellungen für Währung und Zahlenformat.', 'immo-manager' ) . '</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'currency',
			__( 'Währung', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'currency',
				'options' => array(
					'EUR' => __( 'Euro (€)', 'immo-manager' ),
					'CHF' => __( 'Schweizer Franken (CHF)', 'immo-manager' ),
					'USD' => __( 'US-Dollar ($)', 'immo-manager' ),
					'GBP' => __( 'Britisches Pfund (£)', 'immo-manager' ),
				),
			)
		);

		add_settings_field(
			'currency_symbol',
			__( 'Währungs-Symbol', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'   => 'currency_symbol',
				'class' => 'small-text',
			)
		);

		add_settings_field(
			'currency_position',
			__( 'Position des Symbols', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'currency_position',
				'options' => array(
					'before' => __( 'Vor dem Betrag (€ 100)', 'immo-manager' ),
					'after'  => __( 'Nach dem Betrag (100 €)', 'immo-manager' ),
				),
			)
		);

		add_settings_field(
			'price_decimals',
			__( 'Dezimalstellen', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'   => 'price_decimals',
				'min'   => 0,
				'max'   => 4,
				'class' => 'small-text',
			)
		);

		add_settings_field(
			'thousands_separator',
			__( 'Tausender-Trennzeichen', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'       => 'thousands_separator',
				'maxlength' => 1,
				'class'     => 'small-text',
			)
		);

		add_settings_field(
			'decimal_separator',
			__( 'Dezimal-Trennzeichen', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'       => 'decimal_separator',
				'maxlength' => 1,
				'class'     => 'small-text',
			)
		);
	}

	/**
	 * Section: Design.
	 *
	 * @return void
	 */
	private function register_design_section(): void {
		$section = 'immo_design';

		add_settings_section(
			$section,
			__( '🎨 Design & Layout', 'immo-manager' ),
			function () {
				echo '<p>' . esc_html__( 'Wähle einen vordefinierten Design-Stil oder passe jede Farbe, Schrift und Form individuell an.', 'immo-manager' ) . '</p>';
			},
			self::MENU_SLUG
		);

		// === Layout-Stil (Haupt-Option) ===
		add_settings_field(
			'design_style',
			__( 'Design-Stil', 'immo-manager' ),
			array( $this, 'render_design_style_field' ),
			self::MENU_SLUG,
			$section,
			array( 'key' => 'design_style' )
		);

		// === Farbpalette ===
		$color_fields = array(
			'primary_color'          => __( 'Primärfarbe', 'immo-manager' ),
			'secondary_color'        => __( 'Sekundärfarbe', 'immo-manager' ),
			'accent_color'           => __( 'Akzentfarbe', 'immo-manager' ),
			'background_color'       => __( 'Hintergrund', 'immo-manager' ),
			'surface_color'          => __( 'Oberflächen (Cards)', 'immo-manager' ),
			'text_color'             => __( 'Text', 'immo-manager' ),
			'text_muted_color'       => __( 'Text gedämpft', 'immo-manager' ),
			'border_color'           => __( 'Rahmen', 'immo-manager' ),
			'status_available_color' => __( 'Status „Verfügbar"', 'immo-manager' ),
			'status_reserved_color'  => __( 'Status „Reserviert"', 'immo-manager' ),
			'status_sold_color'      => __( 'Status „Verkauft/Vermietet"', 'immo-manager' ),
		);
		foreach ( $color_fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_color_field' ),
				self::MENU_SLUG,
				$section,
				array( 'key' => $key )
			);
		}

		// === Typography ===
		add_settings_field(
			'font_family_heading',
			__( 'Überschriften-Schriftart', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'font_family_heading',
				'options' => array(
					'inter'    => 'Inter (modern, Sans-Serif)',
					'poppins'  => 'Poppins (geometrisch)',
					'roboto'   => 'Roboto (Material Design)',
					'dm-sans'  => 'DM Sans (expressive)',
					'playfair' => 'Playfair Display (elegant, Serif)',
					'system'   => __( 'System-Schriftart', 'immo-manager' ),
					'custom'   => __( 'Benutzerdefiniert', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'font_family_body',
			__( 'Text-Schriftart', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'font_family_body',
				'options' => array(
					'inter'   => 'Inter',
					'poppins' => 'Poppins',
					'roboto'  => 'Roboto',
					'dm-sans' => 'DM Sans',
					'system'  => __( 'System-Schriftart', 'immo-manager' ),
					'custom'  => __( 'Benutzerdefiniert', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'font_family_custom',
			__( 'Custom CSS-Font-Stack', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'font_family_custom',
				'class'       => 'regular-text',
				'placeholder' => '"Merriweather", Georgia, serif',
				'description' => __( 'Nur aktiv wenn Schriftart = "Benutzerdefiniert". Font muss via Theme oder externem Service geladen sein.', 'immo-manager' ),
			)
		);
		add_settings_field(
			'font_size_base',
			__( 'Basis-Schriftgröße (px)', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array( 'key' => 'font_size_base', 'min' => 12, 'max' => 22, 'step' => 1, 'class' => 'small-text' )
		);
		add_settings_field(
			'heading_weight',
			__( 'Überschriften-Gewicht', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'heading_weight',
				'options' => array(
					'400' => '400 – Regular',
					'500' => '500 – Medium',
					'600' => '600 – Semibold',
					'700' => '700 – Bold',
					'800' => '800 – Extrabold',
					'900' => '900 – Black (Expressive)',
				),
			)
		);

		// === Layout & Spacing ===
		add_settings_field(
			'border_radius',
			__( 'Rundungsradius (px)', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'border_radius',
				'min'         => 0, 'max' => 40, 'step' => 1,
				'class'       => 'small-text',
				'description' => __( '0 = eckig, 8 = klassisch, 16 = weich, 24+ = sehr rund', 'immo-manager' ),
			)
		);
		add_settings_field(
			'shadow_intensity',
			__( 'Schatten-Intensität', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'shadow_intensity',
				'options' => array(
					'none'   => __( 'Keine', 'immo-manager' ),
					'soft'   => __( 'Sanft', 'immo-manager' ),
					'medium' => __( 'Mittel', 'immo-manager' ),
					'strong' => __( 'Stark', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'spacing_scale',
			__( 'Abstände', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'spacing_scale',
				'options' => array(
					'compact'   => __( 'Kompakt (dicht)', 'immo-manager' ),
					'normal'    => __( 'Normal', 'immo-manager' ),
					'spacious'  => __( 'Großzügig (Expressive)', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'card_aspect_ratio',
			__( 'Bild-Seitenverhältnis (Cards)', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'card_aspect_ratio',
				'options' => array(
					'4/3'  => '4:3 – klassisch',
					'16/9' => '16:9 – breit',
					'3/2'  => '3:2 – leicht breit',
					'1/1'  => '1:1 – quadratisch',
				),
			)
		);

		// === Effekte ===
		add_settings_field(
			'hover_effect',
			__( 'Card-Hover-Effekt', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'hover_effect',
				'options' => array(
					'none'  => __( 'Kein Effekt', 'immo-manager' ),
					'lift'  => __( 'Anheben (Lift)', 'immo-manager' ),
					'glow'  => __( 'Leuchten (Glow)', 'immo-manager' ),
					'scale' => __( 'Vergrößern (Scale)', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'image_hover',
			__( 'Bild-Hover-Effekt', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'image_hover',
				'options' => array(
					'none'       => __( 'Kein Effekt', 'immo-manager' ),
					'zoom'       => __( 'Zoom', 'immo-manager' ),
					'fade'       => __( 'Abdunkeln', 'immo-manager' ),
					'brightness' => __( 'Aufhellen', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'transition_speed',
			__( 'Animationsgeschwindigkeit', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'transition_speed',
				'options' => array(
					'slow'   => __( 'Langsam (0.5s)', 'immo-manager' ),
					'normal' => __( 'Normal (0.25s)', 'immo-manager' ),
					'fast'   => __( 'Schnell (0.15s)', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'backdrop_blur',
			__( 'Glasmorphism Blur (px)', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'backdrop_blur',
				'min'         => 0, 'max' => 40, 'step' => 1,
				'class'       => 'small-text',
				'description' => __( 'Nur aktiv wenn Design-Stil = Glassmorphism. 0 = kein Blur.', 'immo-manager' ),
			)
		);

		// === Allgemein ===
		add_settings_field(
			'default_view',
			__( 'Standard-Listenansicht', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'default_view',
				'options' => array(
					'grid'    => __( 'Grid (Kacheln)', 'immo-manager' ),
					'list'    => __( 'Liste', 'immo-manager' ),
					'masonry' => __( 'Masonry', 'immo-manager' ),
				),
			)
		);
		add_settings_field(
			'items_per_page',
			__( 'Einträge pro Seite', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array( 'key' => 'items_per_page', 'min' => 1, 'max' => 100, 'class' => 'small-text' )
		);

		// === NEU: Detailseiten-Konfiguration ===
		add_settings_field(
			'default_detail_layout',
			__( 'Standard Detail-Layout', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'default_detail_layout',
				'options' => array(
					'standard' => __( 'Standard (Hero-Slider + Sidebar)', 'immo-manager' ),
					'compact'  => __( 'Kompakt (Fokus auf Inhalt)', 'immo-manager' ),
				),
			)
		);

		add_settings_field(
			'default_gallery_type',
			__( 'Standard Galerie-Typ', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'default_gallery_type',
				'options' => array(
					'slider' => __( 'Slider (Blättern)', 'immo-manager' ),
					'grid'   => __( 'Raster (Grid)', 'immo-manager' ),
				),
			)
		);

		add_settings_field(
			'default_hero_type',
			__( 'Standard Hauptbild-Breite', 'immo-manager' ),
			array( $this, 'render_select_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'     => 'default_hero_type',
				'options' => array(
					'full'      => __( 'Volle Breite (Edge-to-Edge)', 'immo-manager' ),
					'contained' => __( 'Eingefasst (Container)', 'immo-manager' ),
				),
			)
		);
	}

	/**
	 * Design-Stil als visueller Radio-Picker (mit Vorschau).
	 *
	 * @param array<string, mixed> $args Args.
	 *
	 * @return void
	 */
	public function render_design_style_field( array $args ): void {
		$key     = $args['key'];
		$current = self::get( $key, 'expressive' );
		$styles  = array(
			'expressive' => array(
				'label' => __( 'Expressive Design', 'immo-manager' ),
				'desc'  => __( 'Material-You inspiriert. Große Typografie, sanfte runde Formen, kräftige Farben. Ideal für moderne, einladende Websites.', 'immo-manager' ),
				'icon'  => '✨',
			),
			'glassmorphism' => array(
				'label' => __( 'Glassmorphism', 'immo-manager' ),
				'desc'  => __( 'Frosted-Glas-Effekt mit Backdrop-Blur. Transparente Oberflächen, weiche Schatten, premium Look. Benötigt farbigen Hintergrund zur vollen Wirkung.', 'immo-manager' ),
				'icon'  => '🔮',
			),
			'minimal' => array(
				'label' => __( 'Minimal', 'immo-manager' ),
				'desc'  => __( 'Reduziertes Design ohne Schnörkel. Viel Weißraum, dezente Rahmen, klare Typografie. Professionell und zeitlos.', 'immo-manager' ),
				'icon'  => '○',
			),
			'classic' => array(
				'label' => __( 'Classic', 'immo-manager' ),
				'desc'  => __( 'Traditionelles Real-Estate-Layout mit klaren Cards und subtilen Schatten. Konservativ und vertraut.', 'immo-manager' ),
				'icon'  => '▢',
			),
		);
		?>
		<div class="immo-design-style-picker">
			<?php foreach ( $styles as $val => $style ) : ?>
				<label class="immo-design-style-option <?php echo $current === $val ? 'selected' : ''; ?>">
					<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $val ); ?>" <?php checked( $current, $val ); ?>>
					<span class="immo-style-icon"><?php echo esc_html( $style['icon'] ); ?></span>
					<span class="immo-style-info">
						<span class="immo-style-label"><?php echo esc_html( $style['label'] ); ?></span>
						<span class="immo-style-desc"><?php echo esc_html( $style['desc'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description">
			<?php esc_html_e( 'Der Design-Stil definiert die Grundästhetik. Farben, Typografie und andere Optionen unten können pro Stil angepasst werden.', 'immo-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Text-Feld rendern (generisch).
	 *
	 * @param array<string, mixed> $args Args.
	 *
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$key         = $args['key'];
		$value       = self::get( $key, '' );
		$class       = $args['class'] ?? 'regular-text';
		$placeholder = $args['placeholder'] ?? '';
		$description = $args['description'] ?? '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="%4$s" placeholder="%5$s">',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			esc_attr( $placeholder )
		);
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Section: Features.
	 *
	 * @return void
	 */
	private function register_features_section(): void {
		$section = 'immo_features';

		add_settings_section(
			$section,
			__( 'Funktionen', 'immo-manager' ),
			function () {
				echo '<p>' . esc_html__( 'Einzelne Plugin-Funktionen aktivieren oder deaktivieren.', 'immo-manager' ) . '</p>';
			},
			self::MENU_SLUG
		);

		$toggles = array(
			'enable_wizard'    => __( 'Wizard für Immobilien-Eingabe aktivieren', 'immo-manager' ),
			'enable_filter'    => __( 'Filter-Sidebar im Frontend aktivieren', 'immo-manager' ),
			'enable_projects'  => __( 'Bauprojekte mit Wohneinheiten aktivieren', 'immo-manager' ),
			'enable_inquiries' => __( 'Anfragen-System aktivieren', 'immo-manager' ),
		);

		foreach ( $toggles as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_checkbox_field' ),
				self::MENU_SLUG,
				$section,
				array(
					'key'   => $key,
					'label' => $label,
				)
			);
		}
	}

	/**
	 * Section: Kontakt/E-Mail.
	 *
	 * @return void
	 */
	private function register_contact_section(): void {
		$section = 'immo_contact';

		add_settings_section(
			$section,
			__( 'Kontakt & Benachrichtigungen', 'immo-manager' ),
			function () {
				echo '<p>' . esc_html__( 'E-Mail-Einstellungen für Anfragen und Benachrichtigungen.', 'immo-manager' ) . '</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'contact_email',
			__( 'Kontakt-E-Mail', 'immo-manager' ),
			array( $this, 'render_email_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'contact_email',
				'description' => __( 'Standard-Empfänger für Anfragen. Leer lassen für Admin-E-Mail.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'sender_name',
			__( 'Absender-Name', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'sender_name',
				'description' => __( 'Name im "Von:"-Feld der E-Mails. Leer lassen für Site-Namen.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'agent_name',
			__( 'Standard-Makler Name', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'agent_name',
				'description' => __( 'Wird beim Anlegen neuer Immobilien oder Bauprojekte im Wizard vorausgefüllt.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'agent_email',
			__( 'Standard-Makler E-Mail', 'immo-manager' ),
			array( $this, 'render_email_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'agent_email',
				'description' => __( 'Wird beim Anlegen neuer Immobilien oder Bauprojekte im Wizard vorausgefüllt.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'agent_phone',
			__( 'Standard-Makler Telefon', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'agent_phone',
				'description' => __( 'Wird beim Anlegen neuer Immobilien oder Bauprojekte im Wizard vorausgefüllt.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'agent_image_id',
			__( 'Standard-Makler Foto', 'immo-manager' ),
			array( $this, 'render_image_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'agent_image_id',
				'description' => __( 'Portrait des Maklers für ein vertrauenswürdiges Erscheinungsbild. Wird im Wizard vorausgefüllt.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'email_logo_id',
			__( 'E-Mail Logo', 'immo-manager' ),
			array( $this, 'render_image_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'email_logo_id',
				'description' => __( 'Logo für HTML-E-Mails (z. B. Admin-Benachrichtigungen bei Anfragen).', 'immo-manager' ),
			)
		);

		add_settings_field(
			'admin_notifications',
			__( 'Admin-Benachrichtigung bei neuen Anfragen', 'immo-manager' ),
			array( $this, 'render_checkbox_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'   => 'admin_notifications',
				'label' => __( 'Administrator per E-Mail benachrichtigen', 'immo-manager' ),
			)
		);
	}

	/**
	 * Section: REST API & Headless.
	 *
	 * @return void
	 */
	private function register_api_section(): void {
		$section = 'immo_api';

		add_settings_section(
			$section,
			__( 'REST API & Headless-Betrieb', 'immo-manager' ),
			function () {
				echo '<p>' . wp_kses(
					__( 'Ermöglicht externen Webseiten den Zugriff auf Immobilien-Daten über die REST API (<code>/wp-json/immo-manager/v1/</code>). Lesende Endpunkte sind öffentlich, schreibende erfordern den API-Key.', 'immo-manager' ),
					array( 'code' => array() )
				) . '</p>';
			},
			self::MENU_SLUG
		);

		// CORS Origins.
		add_settings_field(
			'cors_allowed_origins',
			__( 'Erlaubte Origins (CORS)', 'immo-manager' ),
			array( $this, 'render_textarea_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'cors_allowed_origins',
				'rows'        => 3,
				'description' => __( '* = alle Domains (empfohlen für öffentliche APIs). Oder eine Domain pro Zeile, z. B. https://meine-seite.at', 'immo-manager' ),
			)
		);

		// API Key Generator.
		add_settings_field(
			'api_key',
			__( 'API-Key (für schreibende Endpunkte)', 'immo-manager' ),
			array( $this, 'render_api_key_field' ),
			self::MENU_SLUG,
			$section
		);

		// Rate Limit.
		add_settings_field(
			'api_rate_limit',
			__( 'Rate Limit (Requests/Min.)', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'api_rate_limit',
				'min'         => 0,
				'max'         => 600,
				'step'        => 1,
				'class'       => 'small-text',
				'description' => __( '0 = kein Limit. Empfohlen: 60. Gilt für POST /inquiries.', 'immo-manager' ),
			)
		);
	}

	/**
	 * Textarea-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_textarea_field( array $args ): void {
		$key         = $args['key'];
		$value       = self::get( $key, '' );
		$rows        = (int) ( $args['rows'] ?? 3 );
		$description = $args['description'] ?? '';

		printf(
			'<textarea id="%1$s" name="%2$s[%1$s]" rows="%3$d" class="large-text code">%4$s</textarea>',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			$rows,
			esc_textarea( (string) $value )
		);

		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * API-Key Feld rendern (mit Generator-Button).
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$preview = self::get( 'api_key_preview', '' );
		$has_key = ! empty( $preview );

		echo '<div class="immo-api-key-field">';
		if ( $has_key ) {
			echo '<code>' . esc_html( $preview ) . '…</code> ';
			echo '<span class="description">' . esc_html__( '(nur die ersten 8 Zeichen sichtbar)', 'immo-manager' ) . '</span><br><br>';
		} else {
			echo '<span class="description">' . esc_html__( 'Noch kein API-Key generiert.', 'immo-manager' ) . '</span><br><br>';
		}
		printf(
			'<button type="button" class="button immo-generate-api-key" data-nonce="%s">%s</button>',
			esc_attr( wp_create_nonce( 'immo_generate_api_key' ) ),
			$has_key
				? esc_html__( 'Neuen API-Key generieren (invalidiert alten)', 'immo-manager' )
				: esc_html__( 'API-Key generieren', 'immo-manager' )
		);
		echo '<span class="immo-api-key-new" style="display:none; margin-left:12px;">';
		echo '<strong>' . esc_html__( 'Neuer Key:', 'immo-manager' ) . '</strong> ';
		echo '<code class="immo-api-key-value"></code> ';
		echo '<button type="button" class="button button-small immo-copy-api-key">' . esc_html__( 'Kopieren', 'immo-manager' ) . '</button>';
		echo '<p class="description" style="color:#b71c1c;">' . esc_html__( 'Jetzt kopieren – der vollständige Key wird nur einmalig angezeigt!', 'immo-manager' ) . '</p>';
		echo '</span>';
		echo '</div>';
		echo '<p class="description">' . wp_kses(
			__( 'Header für schreibende Requests: <code>X-Immo-API-Key: ihr-key</code>', 'immo-manager' ),
			array( 'code' => array() )
		) . '</p>';
	}

	/**
	 * Section: Karten (OpenStreetMap).
	 *
	 * @return void
	 */
	private function register_integrations_section(): void {
		$section = 'immo_maps';

		add_settings_section(
			$section,
			__( 'Karten (OpenStreetMap)', 'immo-manager' ),
			function () {
				echo '<p>' . wp_kses(
					__( 'Das Plugin nutzt <strong>OpenStreetMap</strong> via <a href="https://leafletjs.com/" target="_blank" rel="noopener">Leaflet.js</a> für die Kartendarstellung – kostenfrei und ohne API-Key.', 'immo-manager' ),
					array(
						'strong' => array(),
						'a'      => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				) . '</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'map_enabled',
			__( 'Karten anzeigen', 'immo-manager' ),
			array( $this, 'render_checkbox_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'   => 'map_enabled',
				'label' => __( 'Kartendarstellung im Frontend aktivieren', 'immo-manager' ),
			)
		);

		add_settings_field(
			'map_tile_url',
			__( 'Tile-Server-URL', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'map_tile_url',
				'class'       => 'large-text code',
				'description' => __( 'URL zum Tile-Server. Platzhalter: {s} (Subdomain), {z} (Zoom), {x}/{y} (Kachel). Standard: OpenStreetMap.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'map_attribution',
			__( 'Attribution (Quellenangabe)', 'immo-manager' ),
			array( $this, 'render_text_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'map_attribution',
				'class'       => 'large-text',
				'description' => __( 'Muss laut OSM-Lizenz sichtbar sein. HTML erlaubt (z. B. Links).', 'immo-manager' ),
			)
		);

		add_settings_field(
			'map_default_lat',
			__( 'Standard-Latitude', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'map_default_lat',
				'min'         => -90,
				'max'         => 90,
				'step'        => '0.0001',
				'class'       => 'regular-text',
				'description' => __( 'Breitengrad für die Standardansicht. Österreich-Zentrum ≈ 47.5162.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'map_default_lng',
			__( 'Standard-Longitude', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'map_default_lng',
				'min'         => -180,
				'max'         => 180,
				'step'        => '0.0001',
				'class'       => 'regular-text',
				'description' => __( 'Längengrad für die Standardansicht. Österreich-Zentrum ≈ 14.5501.', 'immo-manager' ),
			)
		);

		add_settings_field(
			'map_default_zoom',
			__( 'Standard-Zoom', 'immo-manager' ),
			array( $this, 'render_number_field' ),
			self::MENU_SLUG,
			$section,
			array(
				'key'         => 'map_default_zoom',
				'min'         => 1,
				'max'         => 19,
				'step'        => 1,
				'class'       => 'small-text',
				'description' => __( 'Zoom-Level (1 = Weltkarte, 19 = Häuserebene). Empfohlen für Österreich: 7.', 'immo-manager' ),
			)
		);
	}

	// =========================================================================
	// Field Renderers
	// =========================================================================

	/**
	/**
	 * E-Mail-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_email_field( array $args ): void {
		$key         = $args['key'];
		$value       = self::get( $key, '' );
		$description = $args['description'] ?? '';

		printf(
			'<input type="email" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value )
		);

		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Nummer-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$key         = $args['key'];
		$value       = self::get( $key, 0 );
		$min         = $args['min'] ?? 0;
		$max         = $args['max'] ?? '';
		$step        = $args['step'] ?? '';
		$class       = $args['class'] ?? 'small-text';
		$description = $args['description'] ?? '';

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s"%6$s class="%7$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			'' !== (string) $step ? ' step="' . esc_attr( (string) $step ) . '"' : '',
			esc_attr( $class )
		);

		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Select-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$key     = $args['key'];
		$value   = self::get( $key, '' );
		$options = $args['options'] ?? array();

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME )
		);

		foreach ( $options as $option_value => $option_label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';
	}

	/**
	 * Checkbox-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$key   = $args['key'];
		$value = (int) self::get( $key, 0 );

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			checked( $value, 1, false ),
			esc_html( $args['label'] ?? '' )
		);
	}

	/**
	 * Bild-Feld (Media Uploader) rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_image_field( array $args ): void {
		$key   = $args['key'];
		$value = (int) self::get( $key, 0 );
		$desc  = $args['description'] ?? '';
		$url   = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';

		echo '<div class="immo-image-preview-wrapper" style="margin-bottom: 10px;">';
		echo '<img src="' . esc_url( $url ) . '" style="max-width: 100px; height: auto; border-radius: 4px; ' . ( $url ? 'display: block;' : 'display: none;' ) . '" class="immo-image-preview" />';
		echo '</div>';
		printf( '<input type="hidden" id="%1$s" name="%2$s[%1$s]" value="%3$d" class="immo-image-id" />', esc_attr( $key ), esc_attr( self::OPTION_NAME ), $value );
		echo '<button type="button" class="button immo-upload-image-btn">' . esc_html__( 'Bild auswählen', 'immo-manager' ) . '</button> ';
		echo '<button type="button" class="button immo-remove-image-btn" ' . ( $value ? '' : 'style="display:none;"' ) . '>' . esc_html__( 'Entfernen', 'immo-manager' ) . '</button>';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		?>
		<script>
		jQuery(document).ready(function($){
			var frame;
			$('#<?php echo esc_js( $key ); ?>').siblings('.immo-upload-image-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				if ( frame ) { frame.open(); return; }
				frame = wp.media({
					title: '<?php esc_js( __( 'Bild auswählen', 'immo-manager' ) ); ?>',
					button: { text: '<?php esc_js( __( 'Verwenden', 'immo-manager' ) ); ?>' },
					multiple: false
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#<?php echo esc_js( $key ); ?>').val(attachment.id);
					btn.siblings('.immo-image-preview-wrapper').find('.immo-image-preview').attr('src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
					btn.siblings('.immo-remove-image-btn').show();
				});
				frame.open();
			});
			$('#<?php echo esc_js( $key ); ?>').siblings('.immo-remove-image-btn').on('click', function(e) {
				e.preventDefault();
				$('#<?php echo esc_js( $key ); ?>').val('0');
				$(this).siblings('.immo-image-preview-wrapper').find('.immo-image-preview').attr('src', '').hide();
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Farb-Feld rendern.
	 *
	 * @param array<string, mixed> $args Feld-Argumente.
	 *
	 * @return void
	 */
	public function render_color_field( array $args ): void {
		$key   = $args['key'];
		$value = self::get( $key, '' );

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="immo-color-picker" data-default-color="%4$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value ),
			esc_attr( (string) ( self::get_defaults()[ $key ] ?? '' ) )
		);
	}

	// =========================================================================
	// Sanitization
	// =========================================================================

	/**
	 * Alle Input-Werte sanitizen.
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 *
	 * @return array<string, mixed> Bereinigte Werte.
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::get_defaults();
		}

		$defaults  = self::get_defaults();
		$sanitized = array();

		// Währung.
		$sanitized['currency'] = in_array(
			(string) ( $input['currency'] ?? '' ),
			array( 'EUR', 'CHF', 'USD', 'GBP' ),
			true
		) ? (string) $input['currency'] : $defaults['currency'];

		$sanitized['currency_symbol'] = sanitize_text_field(
			(string) ( $input['currency_symbol'] ?? $defaults['currency_symbol'] )
		);

		$sanitized['currency_position'] = in_array(
			(string) ( $input['currency_position'] ?? '' ),
			array( 'before', 'after' ),
			true
		) ? (string) $input['currency_position'] : $defaults['currency_position'];

		$sanitized['price_decimals']      = max( 0, min( 4, (int) ( $input['price_decimals'] ?? $defaults['price_decimals'] ) ) );
		$sanitized['thousands_separator'] = substr( sanitize_text_field( (string) ( $input['thousands_separator'] ?? $defaults['thousands_separator'] ) ), 0, 1 );
		$sanitized['decimal_separator']   = substr( sanitize_text_field( (string) ( $input['decimal_separator'] ?? $defaults['decimal_separator'] ) ), 0, 1 );

		// === DESIGN: Layout-Stil ===
		$valid_styles = array( 'glassmorphism', 'expressive', 'minimal', 'classic' );
		$sanitized['design_style'] = in_array( (string) ( $input['design_style'] ?? '' ), $valid_styles, true )
			? (string) $input['design_style']
			: $defaults['design_style'];

		// === DESIGN: Alle Farben ===
		$color_keys = array(
			'primary_color', 'secondary_color', 'accent_color',
			'background_color', 'surface_color', 'text_color',
			'text_muted_color', 'border_color',
			'status_available_color', 'status_reserved_color', 'status_sold_color',
		);
		foreach ( $color_keys as $color_key ) {
			$hex                     = sanitize_hex_color( (string) ( $input[ $color_key ] ?? '' ) );
			$sanitized[ $color_key ] = $hex ? $hex : $defaults[ $color_key ];
		}

		// === DESIGN: Typography ===
		$valid_fonts = array( 'inter', 'poppins', 'roboto', 'dm-sans', 'playfair', 'system', 'custom' );
		foreach ( array( 'font_family_heading', 'font_family_body' ) as $font_key ) {
			$sanitized[ $font_key ] = in_array( (string) ( $input[ $font_key ] ?? '' ), $valid_fonts, true )
				? (string) $input[ $font_key ]
				: $defaults[ $font_key ];
		}
		// Custom font stack: nur harmlose Zeichen zulassen.
		$custom_font = (string) ( $input['font_family_custom'] ?? '' );
		$sanitized['font_family_custom'] = preg_match( '/^[a-zA-Z0-9\s,\-_\'\"]+$/', $custom_font ) ? $custom_font : '';

		$sanitized['font_size_base'] = max( 12, min( 22, (int) ( $input['font_size_base'] ?? $defaults['font_size_base'] ) ) );
		$valid_weights = array( 400, 500, 600, 700, 800, 900 );
		$weight        = (int) ( $input['heading_weight'] ?? $defaults['heading_weight'] );
		$sanitized['heading_weight'] = in_array( $weight, $valid_weights, true ) ? $weight : $defaults['heading_weight'];

		// === DESIGN: Layout ===
		$sanitized['border_radius']   = max( 0, min( 40, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) );
		$sanitized['backdrop_blur']   = max( 0, min( 40, (int) ( $input['backdrop_blur'] ?? $defaults['backdrop_blur'] ) ) );

		$sanitized['shadow_intensity'] = in_array( (string) ( $input['shadow_intensity'] ?? '' ), array( 'none', 'soft', 'medium', 'strong' ), true )
			? (string) $input['shadow_intensity']
			: $defaults['shadow_intensity'];

		$sanitized['spacing_scale'] = in_array( (string) ( $input['spacing_scale'] ?? '' ), array( 'compact', 'normal', 'spacious' ), true )
			? (string) $input['spacing_scale']
			: $defaults['spacing_scale'];

		$sanitized['card_aspect_ratio'] = in_array( (string) ( $input['card_aspect_ratio'] ?? '' ), array( '4/3', '16/9', '3/2', '1/1' ), true )
			? (string) $input['card_aspect_ratio']
			: $defaults['card_aspect_ratio'];

		// === DESIGN: Effekte ===
		$sanitized['hover_effect'] = in_array( (string) ( $input['hover_effect'] ?? '' ), array( 'none', 'lift', 'glow', 'scale' ), true )
			? (string) $input['hover_effect']
			: $defaults['hover_effect'];

		$sanitized['image_hover'] = in_array( (string) ( $input['image_hover'] ?? '' ), array( 'none', 'zoom', 'fade', 'brightness' ), true )
			? (string) $input['image_hover']
			: $defaults['image_hover'];

		$sanitized['transition_speed'] = in_array( (string) ( $input['transition_speed'] ?? '' ), array( 'slow', 'normal', 'fast' ), true )
			? (string) $input['transition_speed']
			: $defaults['transition_speed'];

		// === DESIGN: Allgemein ===
		$sanitized['default_view']   = in_array(
			(string) ( $input['default_view'] ?? '' ),
			array( 'grid', 'list', 'masonry' ),
			true
		) ? (string) $input['default_view'] : $defaults['default_view'];
		$sanitized['items_per_page'] = max( 1, min( 100, (int) ( $input['items_per_page'] ?? $defaults['items_per_page'] ) ) );

		// === NEU: Detailseiten-Konfiguration ===
		$sanitized['default_detail_layout'] = in_array( (string) ( $input['default_detail_layout'] ?? '' ), array( 'standard', 'compact' ), true )
			? (string) $input['default_detail_layout']
			: $defaults['default_detail_layout'];

		$sanitized['default_gallery_type'] = in_array( (string) ( $input['default_gallery_type'] ?? '' ), array( 'slider', 'grid' ), true )
			? (string) $input['default_gallery_type']
			: $defaults['default_gallery_type'];

		$sanitized['default_hero_type'] = in_array( (string) ( $input['default_hero_type'] ?? '' ), array( 'full', 'contained' ), true )
			? (string) $input['default_hero_type']
			: $defaults['default_hero_type'];

		// Feature-Toggles (Checkboxes).
		foreach ( array( 'enable_wizard', 'enable_filter', 'enable_projects', 'enable_inquiries', 'admin_notifications', 'map_enabled' ) as $toggle ) {
			$sanitized[ $toggle ] = ! empty( $input[ $toggle ] ) ? 1 : 0;
		}

		// Kontakt.
		$email                      = sanitize_email( (string) ( $input['contact_email'] ?? '' ) );
		$sanitized['contact_email'] = $email ? $email : '';
		$sanitized['sender_name']   = sanitize_text_field( (string) ( $input['sender_name'] ?? '' ) );
		$agent_email                = sanitize_email( (string) ( $input['agent_email'] ?? '' ) );
		$sanitized['agent_email']   = $agent_email ? $agent_email : '';
		$sanitized['agent_name']    = sanitize_text_field( (string) ( $input['agent_name'] ?? '' ) );
		$sanitized['agent_phone']   = sanitize_text_field( (string) ( $input['agent_phone'] ?? '' ) );
		$sanitized['agent_image_id']= absint( $input['agent_image_id'] ?? 0 );
		$sanitized['email_logo_id'] = absint( $input['email_logo_id'] ?? 0 );

		// Karten (OpenStreetMap).
		$tile_url                   = trim( (string) ( $input['map_tile_url'] ?? '' ) );
		$sanitized['map_tile_url']  = $tile_url ? sanitize_text_field( $tile_url ) : $defaults['map_tile_url'];

		$sanitized['map_attribution'] = wp_kses(
			(string) ( $input['map_attribution'] ?? $defaults['map_attribution'] ),
			array(
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
				'strong' => array(),
				'em'     => array(),
				'span'   => array( 'class' => array() ),
			)
		);

		$lat = isset( $input['map_default_lat'] ) ? (float) $input['map_default_lat'] : $defaults['map_default_lat'];
		$lng = isset( $input['map_default_lng'] ) ? (float) $input['map_default_lng'] : $defaults['map_default_lng'];

		$sanitized['map_default_lat']  = max( -90.0, min( 90.0, $lat ) );
		$sanitized['map_default_lng']  = max( -180.0, min( 180.0, $lng ) );
		$sanitized['map_default_zoom'] = max( 1, min( 19, (int) ( $input['map_default_zoom'] ?? $defaults['map_default_zoom'] ) ) );

		// REST API / CORS.
		$cors_raw  = trim( (string) ( $input['cors_allowed_origins'] ?? '*' ) );
		// Jede Zeile ist entweder * oder eine valide URL.
		$cors_lines = array_filter( array_map( 'trim', explode( "\n", $cors_raw ) ) );
		$cors_clean = array();
		foreach ( $cors_lines as $line ) {
			if ( '*' === $line ) {
				$cors_clean = array( '*' );
				break;
			}
			$url = esc_url_raw( $line );
			if ( $url ) {
				$cors_clean[] = $url;
			}
		}
		$sanitized['cors_allowed_origins'] = $cors_clean ? implode( "\n", $cors_clean ) : '*';
		$sanitized['api_rate_limit']        = max( 0, min( 600, (int) ( $input['api_rate_limit'] ?? $defaults['api_rate_limit'] ) ) );

		// api_key_hash und api_key_preview werden via AJAX-Handler gesetzt,
		// nicht über das normale Settings-Formular.
		$sanitized['api_key_hash']    = self::get( 'api_key_hash', '' );
		$sanitized['api_key_preview'] = self::get( 'api_key_preview', '' );

		return $sanitized;
	}

	/**
	 * Settings-Seite rendern.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Du hast keine Berechtigung, diese Seite aufzurufen.', 'immo-manager' ),
				403
			);
		}

		$template = IMMO_MANAGER_PLUGIN_DIR . 'templates/admin/settings-page.php';
		if ( is_readable( $template ) ) {
			include $template;
		}
	}

	/**
	 * Leert alle gängigen Caches (Object & Page Caches) nach dem Speichern der Einstellungen.
	 * So werden aktualisierte Farben, Kontaktinfos oder API-Regeln sofort überall aktiv.
	 *
	 * @return void
	 */
	public function flush_caches(): void {
		// 1. WordPress Object Cache (Redis/Memcached) leeren
		wp_cache_flush();

		// 2. WP Rocket Cache leeren
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 3. LiteSpeed Cache leeren
		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
			\LiteSpeed_Cache_API::purge_all();
		}

		// 4. W3 Total Cache leeren
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 5. WP Super Cache leeren
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// 6. Autoptimize leeren
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}
	}
}
