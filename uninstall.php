<?php
/**
 * Uninstall-Handler für Immo Manager.
 *
 * Wird aufgerufen wenn der Nutzer das Plugin über WP-Admin löscht.
 * Entfernt: Custom Tables, Options, Post-Meta (optional), Transients.
 *
 * @package ImmoManager
 */

// Sicherheitscheck: Nur über WordPress-Uninstall aufrufen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Autoloader laden.
require_once __DIR__ . '/includes/Autoloader.php';
\ImmoManager\Autoloader::register();

// Custom DB-Tabellen löschen.
\ImmoManager\Database::uninstall();

// Plugin-Options entfernen.
$options = array(
	'immo_manager_version',
	'immo_manager_activated_at',
	'immo_manager_settings',
	'immo_manager_db_version',
	'immo_flush_needed',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Alle Transients mit immo_rl_ Prefix (Rate-Limiting) entfernen.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_immo_%' OR option_name LIKE '_transient_timeout_immo_%'"
);

// Optional: Custom Post Types entfernen (nur wenn gewünscht).
// Standardmäßig NICHT gelöscht, da Nutzer Daten behalten möchte.
// Kann aktiviert werden wenn Setting "delete_posts_on_uninstall" gesetzt.
$settings      = get_option( 'immo_manager_settings', array() );
$delete_posts  = ! empty( $settings['delete_posts_on_uninstall'] );

if ( $delete_posts ) {
	foreach ( array( 'immo_mgr_property', 'immo_mgr_project' ) as $post_type ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );
		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
