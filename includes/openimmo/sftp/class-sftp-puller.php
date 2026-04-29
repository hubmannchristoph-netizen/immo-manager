<?php
/**
 * High-level SFTP-Pull-Workflow inkl. Lock.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

use ImmoManager\OpenImmo\Settings;
use ImmoManager\OpenImmo\SyncLog;

defined( 'ABSPATH' ) || exit;

/**
 * Pull-Workflow für ein Portal: Inbox listen, ZIPs holen, importieren, verschieben.
 */
class SftpPuller {

	private const LOCK_TRANSIENT_PREFIX = 'immo_oi_pull_lock_';
	private const LOCK_TTL_SECONDS      = 600;

	/**
	 * @return array{
	 *   status: string,
	 *   summary: string,
	 *   counts: array{pulled:int, imported:int, conflicts:int, errors:int},
	 *   sync_id: int
	 * }
	 */
	public function pull( string $portal_key ): array {
		$counts = array( 'pulled' => 0, 'imported' => 0, 'conflicts' => 0, 'errors' => 0 );
		$errors = array();

		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;

		if ( null === $portal || empty( $portal['enabled'] ) ) {
			return $this->dispatch_result( $portal_key, 'skipped', 'portal disabled', $counts, 0 );
		}
		if ( empty( $portal['inbox_path'] ) ) {
			return $this->dispatch_result( $portal_key, 'skipped', 'no inbox_path configured', $counts, 0 );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return $this->dispatch_result( $portal_key, 'error', 'sftp credentials incomplete', $counts, 0 );
		}

		if ( ! $this->acquire_lock( $portal_key ) ) {
			return $this->dispatch_result( $portal_key, 'skipped', 'lock held — another pull running', $counts, 0 );
		}

		$sync_id = SyncLog::start( $portal_key, 'pull' );
		if ( 0 === $sync_id ) {
			error_log( '[immo-manager openimmo] SyncLog::start failed for pull ' . $portal_key );
		}

		$upload_dir = wp_get_upload_dir();
		$stage_dir  = $upload_dir['basedir'] . '/openimmo/pull-staging/' . $portal_key . '-' . time();
		wp_mkdir_p( $stage_dir );

		$client = new SftpClient();
		try {
			if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'connect failed: ' . $client->last_error(), $counts, $errors );
			}
			if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'login failed: ' . $client->last_error(), $counts, $errors );
			}

			$inbox     = rtrim( $portal['inbox_path'], '/' );
			$processed = $inbox . '/processed';
			$error_dir = $inbox . '/error';

			if ( ! $client->mkdir_p( $inbox ) ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'inbox mkdir failed: ' . $client->last_error(), $counts, $errors );
			}
			$client->mkdir_p( $processed );  // best-effort
			$client->mkdir_p( $error_dir );  // best-effort

			$files = $client->list_directory( $inbox );
			$zips  = array();
			foreach ( $files as $f ) {
				if ( preg_match( '/\.zip$/i', $f ) ) {
					$zips[] = $f;
				}
			}

			if ( empty( $zips ) ) {
				return $this->dispatch_finish( $portal_key, $sync_id, 'skipped', 'no files in inbox', $counts, $errors );
			}

			$importer = \ImmoManager\Plugin::instance()->get_openimmo_import_service();

			foreach ( $zips as $filename ) {
				$remote_zip = $inbox . '/' . $filename;
				$local_zip  = $stage_dir . '/' . $filename;

				if ( ! $client->get( $remote_zip, $local_zip ) ) {
					++$counts['errors'];
					$errors[] = array( 'file' => $filename, 'message' => 'download failed: ' . $client->last_error() );
					continue;
				}
				++$counts['pulled'];

				$import = null;
				try {
					$import = $importer->run( $local_zip );
				} catch ( \Throwable $e ) {
					$import = array( 'status' => 'error', 'summary' => 'import threw: ' . $e->getMessage(), 'counts' => array() );
				}

				@unlink( $local_zip );  // phpcs:ignore WordPress.PHP.NoSilencedErrors

				if ( in_array( $import['status'], array( 'success', 'partial' ), true ) ) {
					++$counts['imported'];
					$counts['conflicts'] += isset( $import['counts']['conflicts'] ) ? (int) $import['counts']['conflicts'] : 0;
					$client->rename( $remote_zip, $processed . '/' . $filename );
					if ( '' !== $client->last_error() ) {
						error_log( '[immo-manager openimmo] processed-rename failed for ' . $filename . ': ' . $client->last_error() );
					}
				} else {
					++$counts['errors'];
					$errors[] = array(
						'file'    => $filename,
						'message' => isset( $import['summary'] ) ? (string) $import['summary'] : 'unknown import error',
					);
					$client->rename( $remote_zip, $error_dir . '/' . $filename );
					if ( '' !== $client->last_error() ) {
						error_log( '[immo-manager openimmo] error-rename failed for ' . $filename . ': ' . $client->last_error() );
					}
				}
			}

			if ( 0 === $counts['errors'] ) {
				$status = 'success';
			} elseif ( $counts['imported'] > 0 ) {
				$status = 'partial';
			} else {
				$status = 'error';
			}
			$summary = sprintf(
				'%d gepullt, %d importiert, %d Konflikte, %d Fehler',
				$counts['pulled'],
				$counts['imported'],
				$counts['conflicts'],
				$counts['errors']
			);
			return $this->dispatch_finish( $portal_key, $sync_id, $status, $summary, $counts, $errors );

		} catch ( \Throwable $e ) {
			return $this->dispatch_finish( $portal_key, $sync_id, 'error', 'unexpected: ' . $e->getMessage(), $counts, $errors );
		} finally {
			$client->disconnect();
			$this->rrmdir( $stage_dir );
			$this->release_lock( $portal_key );
		}
	}

	private function acquire_lock( string $portal_key ): bool {
		$key = self::LOCK_TRANSIENT_PREFIX . $portal_key;
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, time(), self::LOCK_TTL_SECONDS );
		return true;
	}

	private function release_lock( string $portal_key ): void {
		delete_transient( self::LOCK_TRANSIENT_PREFIX . $portal_key );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				@unlink( $path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Result-Helper für early-returns ohne Sync-Log.
	 */
	/**
	 * Wrapper um result(), triggert bei status='error' eine E-Mail.
	 */
	private function dispatch_result( string $portal_key, string $status, string $summary, array $counts, int $sync_id ): array {
		$result = $this->result( $status, $summary, $counts, $sync_id );
		if ( 'error' === $status ) {
			\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
				->notify_sync_error( $portal_key, 'pull', $summary, array( 'counts' => $counts ) );
		}
		return $result;
	}

	/**
	 * Wrapper um finish(), triggert bei status='error' eine E-Mail.
	 */
	private function dispatch_finish( string $portal_key, int $sync_id, string $status, string $summary, array $counts, array $errors ): array {
		$result = $this->finish( $sync_id, $status, $summary, $counts, $errors );
		if ( 'error' === $status ) {
			\ImmoManager\Plugin::instance()->get_openimmo_email_notifier()
				->notify_sync_error( $portal_key, 'pull', $summary, array(
					'counts' => $counts,
					'errors' => $errors,
				) );
		}
		return $result;
	}

	private function result( string $status, string $summary, array $counts, int $sync_id ): array {
		return array(
			'status'  => $status,
			'summary' => $summary,
			'counts'  => $counts,
			'sync_id' => $sync_id,
		);
	}

	/**
	 * Finish-Helper mit SyncLog-Update.
	 */
	private function finish( int $sync_id, string $status, string $summary, array $counts, array $errors ): array {
		if ( 0 !== $sync_id ) {
			SyncLog::finish(
				$sync_id,
				$status,
				$summary,
				array(
					'counts' => $counts,
					'errors' => $errors,
				),
				(int) $counts['imported']
			);
		}
		return array(
			'status'  => $status,
			'summary' => $summary,
			'counts'  => $counts,
			'sync_id' => $sync_id,
		);
	}
}
