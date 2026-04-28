<?php
/**
 * High-level SFTP-Upload-Workflow inkl. Retry und Lock.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

use ImmoManager\OpenImmo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Upload-Workflow für ein OpenImmo-ZIP zu einem Portal.
 */
class SftpUploader {

	private const LOCK_TRANSIENT_PREFIX = 'immo_oi_sftp_lock_';
	private const LOCK_TTL_SECONDS      = 600;
	private const RETRY_BACKOFFS        = array( 0, 5, 15 );

	/**
	 * Upload eines ZIPs ans SFTP des Portals.
	 *
	 * @return array{
	 *   status: string,
	 *   summary: string,
	 *   attempts: int,
	 *   remote_path: string,
	 *   error: string|null
	 * }
	 */
	public function upload( string $zip_path, string $portal_key ): array {
		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;

		if ( null === $portal || empty( $portal['enabled'] ) ) {
			return $this->result( 'error', 'portal disabled or unknown', 0, '', 'invalid_config' );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return $this->result( 'error', 'sftp credentials incomplete', 0, '', 'invalid_config' );
		}
		if ( ! file_exists( $zip_path ) ) {
			return $this->result( 'error', 'local zip missing', 0, '', 'local_zip_missing' );
		}

		if ( ! $this->acquire_lock( $portal_key ) ) {
			return $this->result( 'skipped', 'lock held — another upload running', 0, '', null );
		}

		$attempts = 0;
		$last_err = null;
		$ok       = false;
		$remote   = '';

		try {
			foreach ( self::RETRY_BACKOFFS as $backoff ) {
				if ( $backoff > 0 ) {
					sleep( $backoff );
				}
				++$attempts;
				$client = new SftpClient();
				$try    = $this->try_upload_once( $client, $portal, $zip_path );
				if ( $try['ok'] ) {
					$ok     = true;
					$remote = $try['remote_path'];
					break;
				}
				$last_err = $try['error'];
			}
		} finally {
			$this->release_lock( $portal_key );
		}

		if ( ! $ok ) {
			return $this->result( 'error', sprintf( 'upload failed after %d attempt(s)', $attempts ), $attempts, '', $last_err );
		}

		Settings::set_last_sync( $portal_key, current_time( 'mysql' ) );
		return $this->result( 'success', sprintf( 'uploaded after %d attempt(s)', $attempts ), $attempts, $remote, null );
	}

	/**
	 * Verbindungstest mit Testdatei + Cleanup.
	 *
	 * @return array{ ok: bool, message: string, mkdir: bool, write: bool }
	 */
	public function test_connection( string $portal_key ): array {
		$settings = Settings::get();
		$portal   = $settings['portals'][ $portal_key ] ?? null;
		if ( null === $portal ) {
			return array( 'ok' => false, 'message' => 'unknown portal', 'mkdir' => false, 'write' => false );
		}
		if ( empty( $portal['sftp_host'] ) || empty( $portal['sftp_user'] ) ) {
			return array( 'ok' => false, 'message' => 'credentials incomplete', 'mkdir' => false, 'write' => false );
		}

		$client = new SftpClient();
		if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
			return array( 'ok' => false, 'message' => 'connect failed: ' . $client->last_error(), 'mkdir' => false, 'write' => false );
		}
		if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'login failed: ' . $err, 'mkdir' => false, 'write' => false );
		}
		$mkdir_ok = $client->mkdir_p( $portal['remote_path'] );
		if ( ! $mkdir_ok ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'mkdir failed: ' . $err, 'mkdir' => false, 'write' => false );
		}

		$test_path = rtrim( $portal['remote_path'], '/' ) . '/immo-manager-test-' . time() . '.txt';
		$write_ok  = $client->put_string( 'immo-manager test ' . gmdate( 'c' ), $test_path );
		if ( ! $write_ok ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'message' => 'write failed: ' . $err, 'mkdir' => true, 'write' => false );
		}

		$client->delete( $test_path );  // best-effort
		$client->disconnect();

		return array(
			'ok'      => true,
			'message' => 'OK',
			'mkdir'   => true,
			'write'   => true,
		);
	}

	/**
	 * Ein Upload-Versuch.
	 *
	 * @return array{ ok: bool, error?: string, remote_path?: string }
	 */
	private function try_upload_once( SftpClient $client, array $portal, string $zip_path ): array {
		if ( ! $client->connect( $portal['sftp_host'], (int) $portal['sftp_port'] ) ) {
			return array( 'ok' => false, 'error' => 'connect failed: ' . $client->last_error() );
		}
		if ( ! $client->login( $portal['sftp_user'], $portal['sftp_password'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'login failed: ' . $err );
		}
		if ( ! $client->mkdir_p( $portal['remote_path'] ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'mkdir failed: ' . $err );
		}
		$remote = rtrim( $portal['remote_path'], '/' ) . '/' . basename( $zip_path );
		if ( ! $client->put( $zip_path, $remote ) ) {
			$err = $client->last_error();
			$client->disconnect();
			return array( 'ok' => false, 'error' => 'put failed: ' . $err );
		}
		$client->disconnect();
		return array( 'ok' => true, 'remote_path' => $remote );
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

	/**
	 * Hilfs-Konstruktor für Result-Arrays.
	 *
	 * @return array{ status: string, summary: string, attempts: int, remote_path: string, error: string|null }
	 */
	private function result( string $status, string $summary, int $attempts, string $remote_path, ?string $error ): array {
		return array(
			'status'      => $status,
			'summary'     => $summary,
			'attempts'    => $attempts,
			'remote_path' => $remote_path,
			'error'       => $error,
		);
	}
}
