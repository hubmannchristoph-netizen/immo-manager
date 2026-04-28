<?php
/**
 * SFTP-Wrapper basierend auf phpseclib3.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Sftp;

defined( 'ABSPATH' ) || exit;

/**
 * Low-level SFTP-Client. Kein Wissen über Portale, Locks oder Retry.
 */
class SftpClient {

	/**
	 * @var \phpseclib3\Net\SFTP|null
	 */
	private $sftp = null;

	/**
	 * Letzte Fehlermeldung von phpseclib bzw. eigene Fehlerlage.
	 */
	private string $error = '';

	/**
	 * Verbindung aufbauen.
	 */
	public function connect( string $host, int $port = 22, int $timeout = 10 ): bool {
		try {
			$this->sftp = new \phpseclib3\Net\SFTP( $host, $port, $timeout );
			return true;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			$this->sftp  = null;
			return false;
		}
	}

	/**
	 * Login mit Username/Passwort.
	 */
	public function login( string $user, string $password ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->login( $user, $password );
			if ( ! $ok ) {
				$this->error = 'login rejected';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Verzeichnis rekursiv anlegen (analog `mkdir -p`).
	 */
	public function mkdir_p( string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			// phpseclib3 unterstützt Recursive direkt: mkdir( $dir, $mode = -1, $recursive = false ).
			$ok = $this->sftp->mkdir( $remote_path, -1, true );
			// `mkdir` gibt false zurück wenn das Verzeichnis bereits existiert — das ist OK.
			if ( ! $ok && ! $this->sftp->is_dir( $remote_path ) ) {
				$this->error = 'mkdir failed';
				return false;
			}
			return true;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Lokale Datei hochladen.
	 */
	public function put( string $local_path, string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		if ( ! file_exists( $local_path ) ) {
			$this->error = 'local file missing: ' . $local_path;
			return false;
		}
		try {
			$ok = $this->sftp->put( $remote_path, $local_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE );
			if ( ! $ok ) {
				$this->error = 'put failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Inline-Bytes als Datei schreiben (für Test-Connection).
	 */
	public function put_string( string $contents, string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->put( $remote_path, $contents );
			if ( ! $ok ) {
				$this->error = 'put_string failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Datei löschen.
	 */
	public function delete( string $remote_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->delete( $remote_path );
			if ( ! $ok ) {
				$this->error = 'delete failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Verbindung schließen.
	 */
	public function disconnect(): void {
		if ( null !== $this->sftp ) {
			try {
				$this->sftp->disconnect();
			} catch ( \Throwable $e ) {
				// Best-effort.
			}
			$this->sftp = null;
		}
	}

	/**
	 * Letzte Fehlermeldung (auch nach disconnect verfügbar).
	 */
	public function last_error(): string {
		return $this->error;
	}
}
