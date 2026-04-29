<?php
/**
 * Plugin Name:       Immo Manager
 * Plugin URI:        https://example.com/immo-manager
 * Description:       Professionelle Immobilienverwaltung für Österreich – Verkauf, Vermietung und Bauprojekte mit Wohneinheiten.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Christoph
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       immo-manager
 * Domain Path:       /languages
 *
 * @package ImmoManager
 */

// Direkten Zugriff verhindern
defined( 'ABSPATH' ) || exit;

/**
 * Plugin-Konstanten definieren
 */
define( 'IMMO_MANAGER_VERSION', '1.0.0' );
define( 'IMMO_MANAGER_PLUGIN_FILE', __FILE__ );
define( 'IMMO_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMMO_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IMMO_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'IMMO_MANAGER_MIN_PHP', '7.4' );
define( 'IMMO_MANAGER_MIN_WP', '5.9' );

// Composer-Autoloader (Vendor-Dependencies wie phpseclib für SFTP).
// Conditional, damit das Plugin auch ohne installiertes vendor/ nicht fatal-fehlschlaegt.
$immo_manager_composer_autoload = IMMO_MANAGER_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $immo_manager_composer_autoload ) ) {
	require_once $immo_manager_composer_autoload;
}
unset( $immo_manager_composer_autoload );

/**
 * Mindestanforderungen prüfen, bevor Plugin geladen wird
 *
 * @return bool True wenn Anforderungen erfüllt, sonst false
 */
function immo_manager_check_requirements() {
	global $wp_version;

	$errors = array();

	if ( version_compare( PHP_VERSION, IMMO_MANAGER_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Erforderliche PHP-Version, 2: Aktuelle PHP-Version */
			esc_html__( 'Immo Manager benötigt PHP %1$s oder neuer. Du verwendest aktuell PHP %2$s.', 'immo-manager' ),
			IMMO_MANAGER_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( $wp_version, IMMO_MANAGER_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Erforderliche WP-Version, 2: Aktuelle WP-Version */
			esc_html__( 'Immo Manager benötigt WordPress %1$s oder neuer. Du verwendest aktuell WordPress %2$s.', 'immo-manager' ),
			IMMO_MANAGER_MIN_WP,
			$wp_version
		);
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			static function () use ( $errors ) {
				echo '<div class="notice notice-error"><p><strong>Immo Manager:</strong></p><ul>';
				foreach ( $errors as $error ) {
					echo '<li>' . esc_html( $error ) . '</li>';
				}
				echo '</ul></div>';
			}
		);
		return false;
	}

	return true;
}

/**
 * Plugin initialisieren
 */
function immo_manager_init() {
	if ( ! immo_manager_check_requirements() ) {
		return;
	}

	// Autoloader laden
	require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/Autoloader.php';
	\ImmoManager\Autoloader::register();

	// Plugin-Singleton starten
	\ImmoManager\Plugin::instance();
}

// Plugin starten, sobald alle Plugins geladen sind
add_action( 'plugins_loaded', 'immo_manager_init', 10 );

/**
 * Activation Hook – Standalone-Funktion, damit er auch ohne geladenes Plugin funktioniert
 */
register_activation_hook(
	__FILE__,
	static function () {
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/Autoloader.php';
		\ImmoManager\Autoloader::register();
		// Datenbank-Tabellen anlegen.
		\ImmoManager\Database::install();
		// Post Types registrieren & Permalinks flushen.
		\ImmoManager\Plugin::instance()->activate();

		// OpenImmo: täglichen Sync-Cron schedulen.
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-cron-scheduler.php';
		\ImmoManager\OpenImmo\CronScheduler::on_activation();
	}
);

/**
 * Deactivation Hook
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/Autoloader.php';
		\ImmoManager\Autoloader::register();
		\ImmoManager\Plugin::instance()->deactivate();

		// OpenImmo: Cron-Hook entfernen.
		require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-cron-scheduler.php';
		\ImmoManager\OpenImmo\CronScheduler::on_deactivation();
	}
);
