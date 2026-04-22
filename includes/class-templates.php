<?php
/**
 * Automatische Template-Steuerung für CPT-Seiten.
 *
 * Greift in die WordPress-Template-Hierarchie ein und lädt
 * Plugin-eigene Templates für Archiv- und Einzelseiten,
 * ohne das Theme zu erfordern.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Templates
 */
class Templates {

	/**
	 * Konstruktor – Template-Hooks registrieren.
	 */
	public function __construct() {
		add_filter( 'template_include', array( $this, 'override_template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_search_lightbox' ) );
	}

	/**
	 * Template-Override via template_include-Filter.
	 *
	 * Reihenfolge der Prüfung (Theme gewinnt immer):
	 * 1. Theme hat spezifisches Template? → Theme-Template nutzen
	 * 2. Sonst → Plugin-Template
	 *
	 * @param string $template Vom Theme ermittelter Template-Pfad.
	 *
	 * @return string Finaler Template-Pfad.
	 */
	public function override_template( string $template ): string {

		// Archiv: /immobilien/
		if ( is_post_type_archive( PostTypes::POST_TYPE_PROPERTY ) ) {
			return $this->locate( 'archive-immo_mgr_property.php', $template );
		}

		// Einzelseite: /immobilien/mein-objekt/
		if ( is_singular( PostTypes::POST_TYPE_PROPERTY ) ) {
			return $this->locate( 'single-immo_mgr_property.php', $template );
		}

		// Archiv: /projekte/
		if ( is_post_type_archive( PostTypes::POST_TYPE_PROJECT ) ) {
			return $this->locate( 'archive-immo_mgr_project.php', $template );
		}

		// Einzelseite: /projekte/mein-projekt/
		if ( is_singular( PostTypes::POST_TYPE_PROJECT ) ) {
			return $this->locate( 'single-immo_mgr_project.php', $template );
		}

		return $template;
	}

	/**
	 * Template-Pfad ermitteln.
	 *
	 * Reihenfolge:
	 * 1. {child-theme}/immo-manager/{name}
	 * 2. {parent-theme}/immo-manager/{name}
	 * 3. Plugin-eigenes Template
	 * 4. Aktuelles Template als Fallback
	 *
	 * @param string $name     Dateiname des Templates.
	 * @param string $fallback Aktuelles Template (Fallback).
	 *
	 * @return string Templatepfad.
	 */
	private function locate( string $name, string $fallback ): string {
		// Theme-Override-Möglichkeit.
		$theme_file = locate_template( array(
			'immo-manager/' . $name,
			$name,
		) );
		if ( $theme_file ) {
			return $theme_file;
		}

		// Plugin-Template.
		$plugin_file = IMMO_MANAGER_PLUGIN_DIR . 'templates/' . $name;
		if ( file_exists( $plugin_file ) ) {
			return $plugin_file;
		}

		return $fallback;
	}

	/**
	 * Frontend-Assets auf CPT-Seiten laden.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if (
			is_singular( array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ) ) ||
			is_post_type_archive( array( PostTypes::POST_TYPE_PROPERTY, PostTypes::POST_TYPE_PROJECT ) )
		) {
			// Shortcodes-Assets werden normalerweise nur auf Shortcode-Seiten geladen.
			// Da wir hier eigene Templates nutzen, laden wir sie manuell.
			Plugin::instance()->get_shortcodes()->enqueue_assets();
		}
	}

	/**
	 * Globale Search Lightbox im Footer rendern.
	 *
	 * @return void
	 */
	public function render_search_lightbox(): void {
		?>
		<div class="immo-lightbox" id="immo-global-search-lightbox" hidden>
			<div class="immo-lightbox-overlay"></div>
			<div class="immo-lightbox-content immo-search-lightbox-content">
				<button type="button" class="immo-lightbox-close" aria-label="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">&times;</button>
				<h3><?php esc_html_e( 'Immobilien suchen', 'immo-manager' ); ?></h3>
				
				<form action="<?php echo esc_url( get_post_type_archive_link( PostTypes::POST_TYPE_PROPERTY ) ); ?>" method="get" class="immo-global-search-form">
					<div class="immo-form-field">
						<label><?php esc_html_e( 'Ort, PLZ oder Stichwort', 'immo-manager' ); ?></label>
						<input type="text" name="immo_search" class="immo-search-autocomplete" autocomplete="off" placeholder="<?php esc_attr_e( 'Suchen...', 'immo-manager' ); ?>">
						<div class="immo-search-autocomplete-results"></div>
					</div>
					<div class="immo-form-field">
						<label><?php esc_html_e( 'Vermarktungsart', 'immo-manager' ); ?></label>
						<select name="mode">
							<option value=""><?php esc_html_e( 'Alle', 'immo-manager' ); ?></option>
							<option value="sale"><?php esc_html_e( 'Kauf', 'immo-manager' ); ?></option>
							<option value="rent"><?php esc_html_e( 'Miete', 'immo-manager' ); ?></option>
						</select>
					</div>
					<button type="submit" class="immo-btn immo-btn-primary" style="width: 100%; margin-top: 10px;">
						<?php esc_html_e( 'Suchen', 'immo-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}
