<?php
/**
 * Sendet Sync-Fehler-Notifications per E-Mail mit Throttling.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

defined( 'ABSPATH' ) || exit;

class EmailNotifier {

	private const TRANSIENT_PREFIX = 'immo_oi_mail_sent_';
	private const TRANSIENT_TTL    = DAY_IN_SECONDS;

	/**
	 * @return bool true wenn versendet, false wenn throttled oder Fehler.
	 */
	public function notify_sync_error( string $portal, string $direction, string $summary, array $details = array() ): bool {
		$key = self::TRANSIENT_PREFIX . $portal . '_' . $direction;
		if ( get_transient( $key ) ) {
			return false;
		}

		$to      = $this->recipient();
		$subject = sprintf(
			'[%s] OpenImmo-Sync-Fehler: %s (%s)',
			get_bloginfo( 'name' ),
			$portal,
			$direction
		);
		$body    = $this->build_body( $portal, $direction, $summary, $details );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( $sent ) {
			set_transient( $key, time(), self::TRANSIENT_TTL );
		}
		return (bool) $sent;
	}

	private function recipient(): string {
		$settings = Settings::get();
		$email    = (string) ( $settings['notification_email'] ?? '' );
		if ( '' === $email || ! is_email( $email ) ) {
			return (string) get_option( 'admin_email' );
		}
		return $email;
	}

	private function build_body( string $portal, string $direction, string $summary, array $details ): string {
		$lines = array(
			'Site:      ' . site_url(),
			'Portal:    ' . $portal,
			'Direction: ' . $direction,
			'Time:      ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'',
			'Summary:',
			$summary,
			'',
			'Details:',
			(string) wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'',
			'— Immo Manager',
		);
		return implode( "\n", $lines );
	}
}
