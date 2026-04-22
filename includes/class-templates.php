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
}
