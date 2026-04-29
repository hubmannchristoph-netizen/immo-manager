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
	 * Listet Dateien (keine Verzeichnisse) in einem Remote-Pfad.
	 *
	 * @return array<int,string> Filenamen ohne Pfad-Präfix; leer bei Fehler oder leerem Verzeichnis.
	 */
	public function list_directory( string $remote_path ): array {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return array();
		}
		try {
			$entries = $this->sftp->nlist( $remote_path );
			if ( ! is_array( $entries ) ) {
				return array();
			}
			$files = array();
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$full = rtrim( $remote_path, '/' ) . '/' . $entry;
				if ( $this->sftp->is_dir( $full ) ) {
					continue;
				}
				$files[] = $entry;
			}
			return $files;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return array();
		}
	}

	/**
	 * Lädt eine Remote-Datei lokal herunter.
	 */
	public function get( string $remote_path, string $local_path ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->get( $remote_path, $local_path );
			if ( false === $ok ) {
				$this->error = 'get failed';
			}
			return false !== $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Verschiebt/Renamed eine Remote-Datei.
	 */
	public function rename( string $from, string $to ): bool {
		if ( null === $this->sftp ) {
			$this->error = 'not connected';
			return false;
		}
		try {
			$ok = $this->sftp->rename( $from, $to );
			if ( ! $ok ) {
				$this->error = 'rename failed';
			}
			return (bool) $ok;
		} catch ( \Throwable $e ) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Letzte Fehlermeldung (auch nach disconnect verfügbar).
	 */
	public function last_error(): string {
		return $this->error;
	}
}
