<?php
/**
 * OpenImmo Settings – verschlüsselte Plugin-Optionen.
 *
 * @package ImmoManager\OpenImmo
 */

namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet OpenImmo-Plugin-Optionen inkl. verschlüsselter Portal-Credentials.
 */
class Settings {

	public const OPTION_KEY = 'immo_manager_openimmo';

	/**
	 * Default-Struktur der Settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'enabled'   => false,
			'cron_time' => '03:00',
			'portals'   => array(
				'willhaben'      => self::portal_defaults(),
				'immoscout24_at' => self::portal_defaults(),
			),
		);
	}

	/**
	 * Default-Werte für ein einzelnes Portal.
	 *
	 * @return array
	 */
	public static function portal_defaults(): array {
		return array(
			'enabled'       => false,
			'sftp_host'     => '',
			'sftp_port'     => 22,
			'sftp_user'     => '',
			'sftp_password' => '',
			'remote_path'   => '/',
			'last_sync'     => '',
		);
	}

	/**
	 * Settings auslesen, Passwörter on-the-fly entschlüsseln.
	 *
	 * @return array
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = wp_parse_args( $stored, self::defaults() );

		if ( ! isset( $merged['portals'] ) || ! is_array( $merged['portals'] ) ) {
			$merged['portals'] = self::defaults()['portals'];
		}

		foreach ( $merged['portals'] as $key => $portal ) {
			$portal                     = wp_parse_args( (array) $portal, self::portal_defaults() );
			if ( ! empty( $portal['sftp_password'] ) ) {
				$portal['sftp_password'] = self::decrypt( $portal['sftp_password'] );
			}
			$merged['portals'][ $key ] = $portal;
		}

		return $merged;
	}

	/**
	 * Settings persistieren, Passwörter verschlüsseln.
	 *
	 * @param array $data Eingabedaten.
	 * @return void
	 */
	public static function save( array $data ): void {
		$sanitized = self::sanitize( $data );

		foreach ( $sanitized['portals'] as $key => $portal ) {
			if ( ! empty( $portal['sftp_password'] ) ) {
				$sanitized['portals'][ $key ]['sftp_password'] = self::encrypt( $portal['sftp_password'] );
			}
		}

		update_option( self::OPTION_KEY, $sanitized );
	}

	/**
	 * Skelett-Sanitization. Vollständige Validierung erfolgt in der AdminPage.
	 *
	 * @param array $data Eingabedaten.
	 * @return array
	 */
	private static function sanitize( array $data ): array {
		return wp_parse_args( $data, self::defaults() );
	}

	/**
	 * AES-256-CBC-Verschlüsselung mit Salt-abgeleitetem Schlüssel.
	 *
	 * @param string $plain Klartext.
	 * @return string base64-codierter IV+Cipher.
	 */
	private static function encrypt( string $plain ): string {
		$key    = self::derive_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * AES-256-CBC-Entschlüsselung.
	 *
	 * @param string $encoded base64-IV+Cipher.
	 * @return string Klartext (oder leer bei Fehler).
	 */
	private static function decrypt( string $encoded ): string {
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$key    = self::derive_key();
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Leitet einen 32-Byte-Schlüssel aus dem WP-Auth-Salt ab.
	 *
	 * @return string Binärer 32-Byte-Schlüssel.
	 */
	private static function derive_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
