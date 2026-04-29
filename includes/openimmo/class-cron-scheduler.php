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

	public const HOOK_DAILY_SYNC    = 'immo_manager_openimmo_daily_sync';
	public const HOOK_HOURLY_PULL   = 'immo_manager_openimmo_hourly_pull';
	public const HOOK_DAILY_CLEANUP = 'immo_manager_openimmo_daily_cleanup';

	/**
	 * Konstruktor – Hook-Callbacks registrieren.
	 */
	public function __construct() {
		add_action( self::HOOK_DAILY_SYNC,    array( $this, 'run_daily_sync' ) );
		add_action( self::HOOK_HOURLY_PULL,   array( $this, 'run_hourly_pull' ) );
		add_action( self::HOOK_DAILY_CLEANUP, array( $this, 'run_daily_cleanup' ) );
	}

	/**
	 * Auf Plugin-Aktivierung – Daily-Hook scheduled.
	 *
	 * @return void
	 */
	public static function on_activation(): void {
		if ( ! wp_next_scheduled( self::HOOK_DAILY_SYNC ) ) {
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

		if ( ! wp_next_scheduled( self::HOOK_HOURLY_PULL ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::HOOK_HOURLY_PULL );
		}

		if ( ! wp_next_scheduled( self::HOOK_DAILY_CLEANUP ) ) {
			$first = strtotime( 'tomorrow 04:00:00' );
			if ( false === $first ) {
				$first = time() + DAY_IN_SECONDS;
			}
			wp_schedule_event( $first, 'daily', self::HOOK_DAILY_CLEANUP );
		}
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

		$timestamp_pull = wp_next_scheduled( self::HOOK_HOURLY_PULL );
		if ( $timestamp_pull ) {
			wp_unschedule_event( $timestamp_pull, self::HOOK_HOURLY_PULL );
		}
		wp_clear_scheduled_hook( self::HOOK_HOURLY_PULL );

		$timestamp_cleanup = wp_next_scheduled( self::HOOK_DAILY_CLEANUP );
		if ( $timestamp_cleanup ) {
			wp_unschedule_event( $timestamp_cleanup, self::HOOK_DAILY_CLEANUP );
		}
		wp_clear_scheduled_hook( self::HOOK_DAILY_CLEANUP );
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

		$service = \ImmoManager\Plugin::instance()->get_openimmo_export_service();
		foreach ( $settings['portals'] as $portal_key => $portal ) {
			if ( ! empty( $portal['enabled'] ) ) {
				$service->run( $portal_key );
			}
		}
	}

	/**
	 * Hourly-Pull-Callback. Holt eingehende ZIPs für jedes aktive Portal mit inbox_path.
	 *
	 * @return void
	 */
	public function run_hourly_pull(): void {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$puller = \ImmoManager\Plugin::instance()->get_openimmo_sftp_puller();
		foreach ( $settings['portals'] as $portal_key => $portal ) {
			if ( ! empty( $portal['enabled'] ) && ! empty( $portal['inbox_path'] ) ) {
				$puller->pull( $portal_key );
			}
		}
	}

	/**
	 * Daily-Cleanup-Callback. Räumt lokale ZIPs, SFTP-Dirs, verwaiste Attachments und alte Conflicts auf.
	 *
	 * @return void
	 */
	public function run_daily_cleanup(): void {
		\ImmoManager\Plugin::instance()->get_openimmo_retention_cleaner()->run_all();
	}
}
