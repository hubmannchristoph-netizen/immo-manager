<?php
/**
 * Retention-Cleanup: lokale ZIPs, SFTP-Verzeichnisse, verwaiste Attachments, alte Konflikte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;
use ImmoManager\OpenImmo\Sftp\SftpClient;

defined( 'ABSPATH' ) || exit;

class RetentionCleaner {

	private const RETENTION_DAYS_LOCAL_ZIPS         = 60;
	private const RETENTION_DAYS_SFTP_PROCESSED     = 30;
	private const RETENTION_DAYS_SFTP_ERROR         = 30;
	private const RETENTION_DAYS_ORPHAN_ATTACHMENTS = 14;
	private const RETENTION_DAYS_RESOLVED_CONFLICTS = 90;

	/**
	 * @return array{
	 *   local_zips: int,
	 *   sftp_dirs: int,
	 *   orphan_attachments: int,
	 *   resolved_conflicts: int
	 * }
	 */
	public function run_all(): array {
		$report = array(
			'local_zips'         => $this->cleanup_local_zips(),
			'sftp_dirs'          => $this->cleanup_sftp_directories(),
			'orphan_attachments' => $this->cleanup_orphaned_attachments(),
			'resolved_conflicts' => $this->cleanup_resolved_conflicts(),
		);
		$this->log( $report );
		return $report;
	}

	private function cleanup_local_zips(): int {
		$cutoff_ts = time() - ( self::RETENTION_DAYS_LOCAL_ZIPS * DAY_IN_SECONDS );
		$upload    = wp_get_upload_dir();
		$base      = $upload['basedir'] . '/openimmo/exports';
		if ( ! is_dir( $base ) ) {
			return 0;
		}
		$deleted = 0;
		$entries = scandir( $base );
		if ( ! is_array( $entries ) ) {
			return 0;
		}
		foreach ( $entries as $portal_dir ) {
			if ( '.' === $portal_dir || '..' === $portal_dir ) {
				continue;
			}
			$portal_path = $base . '/' . $portal_dir;
			if ( ! is_dir( $portal_path ) ) {
				continue;
			}
			$zips = glob( $portal_path . '/*.zip' );
			if ( ! is_array( $zips ) ) {
				continue;
			}
			foreach ( $zips as $zip ) {
				$mtime = filemtime( $zip );
				if ( false === $mtime || $mtime >= $cutoff_ts ) {
					continue;
				}
				if ( @unlink( $zip ) ) {  // phpcs:ignore WordPress.PHP.NoSilencedErrors
					++$deleted;
				}
			}
		}
		return $deleted;
	}

	private function cleanup_sftp_directories(): int {
		$settings = Settings::get();
		$deleted  = 0;
		foreach ( $settings['portals'] as $portal_key => $portal ) {
			if ( empty( $portal['enabled'] ) || empty( $portal['inbox_path'] ) ) {
				continue;
			}
			if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
				continue;
			}
			$client = new SftpClient();
			if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
				continue;
			}
			if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
				$client->disconnect();
				continue;
			}
			$inbox     = rtrim( $portal['inbox_path'], '/' );
			$processed = $inbox . '/processed';
			$error_dir = $inbox . '/error';
			$cutoff_ts = time() - ( self::RETENTION_DAYS_SFTP_PROCESSED * DAY_IN_SECONDS );
			$deleted  += $this->delete_old_in_sftp_dir( $client, $processed, $cutoff_ts );
			$deleted  += $this->delete_old_in_sftp_dir( $client, $error_dir, $cutoff_ts );
			$client->disconnect();
		}
		return $deleted;
	}

	private function delete_old_in_sftp_dir( SftpClient $client, string $remote_dir, int $cutoff_ts ): int {
		$files   = $client->list_directory( $remote_dir );
		$deleted = 0;
		foreach ( $files as $f ) {
			$path = $remote_dir . '/' . $f;
			$stat = $client->stat( $path );
			if ( null === $stat || empty( $stat['mtime'] ) ) {
				continue;
			}
			if ( $stat['mtime'] < $cutoff_ts ) {
				if ( $client->delete( $path ) ) {
					++$deleted;
				}
			}
		}
		return $deleted;
	}

	private function cleanup_orphaned_attachments(): int {
		$cutoff_iso = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS_ORPHAN_ATTACHMENTS * DAY_IN_SECONDS ) );
		$query      = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before'    => $cutoff_iso,
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_immo_openimmo_image_hash',
					'compare' => 'EXISTS',
				),
			),
		) );

		$deleted = 0;
		foreach ( $query->posts as $att_id ) {
			$res = wp_delete_attachment( (int) $att_id, true );
			if ( false !== $res && ! is_wp_error( $res ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	private function cleanup_resolved_conflicts(): int {
		global $wpdb;
		$table  = Database::conflicts_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS_RESOLVED_CONFLICTS * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'approved' AND resolved_at < %s",
				$cutoff
			)
		);
		return false === $result ? 0 : (int) $result;
	}

	private function log( array $report ): void {
		error_log( sprintf(
			'[immo-manager openimmo] retention cleanup: zips=%d, sftp=%d, orphans=%d, conflicts=%d',
			(int) $report['local_zips'],
			(int) $report['sftp_dirs'],
			(int) $report['orphan_attachments'],
			(int) $report['resolved_conflicts']
		) );
	}
}
