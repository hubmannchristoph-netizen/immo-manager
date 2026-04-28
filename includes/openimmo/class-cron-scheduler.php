<?php
/**
 * OpenImmo Cron Scheduler – täglicher Sync-Hook.
 *
 * @package ImmoManager\OpenImmo
 */

namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert und behandelt den täglichen OpenImmo-Sync.
 */
class CronScheduler {

	public const HOOK_DAILY_SYNC = 'immo_manager_openimmo_daily_sync';

	/**
	 * Konstruktor – Hook-Callback registrieren.
	 */
	public function __construct() {
		add_action( self::HOOK_DAILY_SYNC, array( $this, 'run_daily_sync' ) );
	}

	/**
	 * Auf Plugin-Aktivierung – Daily-Hook scheduled.
	 *
	 * @return void
	 */
	public static function on_activation(): void {
		if ( wp_next_scheduled( self::HOOK_DAILY_SYNC ) ) {
			return;
		}

		$settings = Settings::get();
		$parts    = array_pad( explode( ':', (string) $settings['cron_time'] ), 2, '0' );
		$hour     = max( 0, min( 23, (int) $parts[0] ) );
		$minute   = max( 0, min( 59, (int) $parts[1] ) );

		$first_run = strtotime( sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ) );
		if ( false === $first_run ) {
			$first_run = time() + DAY_IN_SECONDS;
		}

		wp_schedule_event( $first_run, 'daily', self::HOOK_DAILY_SYNC );
	}

	/**
	 * Auf Plugin-Deaktivierung – Hook entfernen.
	 *
	 * @return void
	 */
	public static function on_deactivation(): void {
		$timestamp = wp_next_scheduled( self::HOOK_DAILY_SYNC );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_DAILY_SYNC );
		}
		wp_clear_scheduled_hook( self::HOOK_DAILY_SYNC );
	}

	/**
	 * Daily-Sync-Callback. Führt den Export für alle aktiven Portale durch.
	 *
	 * @return void
	 */
	public function run_daily_sync(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Klasse laden — Sub-Namespace, Autoloader greift hier nicht.
		require_once dirname( __FILE__ ) . '/export/class-export-service.php';
		\ImmoManager\OpenImmo\Export\ExportService::require_dependencies();

		$service = new \ImmoManager\OpenImmo\Export\ExportService();
		foreach ( $settings['portals'] as $portal_key => $portal ) {
			if ( ! empty( $portal['enabled'] ) ) {
				$service->run( $portal_key );
			}
		}
	}
}
