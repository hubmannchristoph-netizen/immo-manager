<?php
/**
 * Uninstall-Handler für Immo Manager.
 *
 * Wird NUR aufgerufen, wenn der Nutzer das Plugin über WP-Admin → Plugins → „Löschen"
 * entfernt (nicht bei Deaktivierung, nicht bei Updates/ZIP-Upload).
 *
 * DATENSCHUTZ-PRINZIP: Standardmäßig bleiben ALLE Daten erhalten
 * (Immobilien, Bauprojekte, Wohneinheiten, Anfragen, Sync-Log, Einstellungen).
 * Nur wenn in den Einstellungen (Module → „Beim Löschen des Plugins alle Daten
 * entfernen") explizit zugestimmt wurde, werden Tabellen, Options und Posts gelöscht.
 *
 * Immer entfernt werden lediglich temporäre Artefakte: Cron-Events und
 * Rate-Limit-Transients.
 *
 * @package ImmoManager
 */

// Sicherheitscheck: Nur über WordPress-Uninstall aufrufen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Autoloader laden.
require_once __DIR__ . '/includes/Autoloader.php';
\ImmoManager\Autoloader::register();

global $wpdb;

// --- 1. Immer: Cron-Events entfernen (sonst laufen verwaiste Hooks weiter). ---
foreach ( array(
	'immo_manager_openimmo_daily_sync',
	'immo_manager_openimmo_hourly_pull',
	'immo_manager_openimmo_daily_cleanup',
) as $immo_hook ) {
	wp_clear_scheduled_hook( $immo_hook );
}

// --- 2. Immer: Rate-Limit-Transients (immo_rl_*) entfernen – rein temporär. ---
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_immo_rl_%' OR option_name LIKE '_transient_timeout_immo_rl_%'"
);

// --- 3. Opt-in prüfen: Ohne ausdrückliche Zustimmung bleiben alle Daten erhalten. ---
$immo_settings = get_option( 'immo_manager_settings', array() );
$immo_delete   = is_array( $immo_settings ) && ! empty( $immo_settings['delete_data_on_uninstall'] );

if ( ! $immo_delete ) {
	return;
}

// --- 4. Opt-in erteilt: Immobilien & Bauprojekte (inkl. Meta, Revisions) löschen. ---
foreach ( array( 'immo_mgr_property', 'immo_mgr_project' ) as $immo_post_type ) {
	$immo_post_ids = get_posts( array(
		'post_type'      => $immo_post_type,
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	) );
	foreach ( $immo_post_ids as $immo_post_id ) {
		wp_delete_post( (int) $immo_post_id, true );
	}
}

// --- 5. Custom Tables (Units, Inquiries, Sync-Log, Konflikte) löschen. ---
\ImmoManager\Database::uninstall();

// --- 6. Plugin-Options entfernen. ---
foreach ( array(
	'immo_manager_version',
	'immo_manager_activated_at',
	'immo_manager_settings',
	'immo_manager_db_version',
	'immo_manager_openimmo',
	'immo_flush_needed',
) as $immo_option ) {
	delete_option( $immo_option );
}

// --- 7. Restliche Plugin-Transients entfernen. ---
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_immo_%' OR option_name LIKE '_transient_timeout_immo_%'"
);
