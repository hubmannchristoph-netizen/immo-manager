<?php
/**
 * PSR-4 Autoloader für den ImmoManager-Namespace.
 *
 * Unterstützt sowohl den WordPress-Naming-Convention (class-{kebab-case}.php)
 * als auch PSR-4-Standard-Dateinamen ({StudlyCase}.php).
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Namespace-Präfix, für das dieser Autoloader zuständig ist.
	 */
	private const NAMESPACE_PREFIX = 'ImmoManager\\';

	/**
	 * Basisverzeichnis für die Klassen.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Autoloader registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::$base_dir = __DIR__ . '/';
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Klasse per Autoloader laden.
	 *
	 * @param string $class_name Voll qualifizierter Klassenname.
	 *
	 * @return void
	 */
	public static function autoload( string $class_name ): void {
		// Nur Klassen aus unserem Namespace behandeln.
		if ( strncmp( self::NAMESPACE_PREFIX, $class_name, strlen( self::NAMESPACE_PREFIX ) ) !== 0 ) {
			return;
		}

		// Namespace-Präfix entfernen.
		$relative_class = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );

		// Sub-Namespaces werden auf kleingeschriebene Unterordner gemappt:
		// ImmoManager\OpenImmo\Export\ExportService -> openimmo/export/class-export-service.php
		// ImmoManager\Elementor\ProjectsWidget        -> elementor/class-projects-widget.php
		$parts       = explode( '\\', $relative_class );
		$class_short = (string) array_pop( $parts );
		$sub_dir     = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';

		// Erst: WordPress-Naming-Convention (class-post-types.php).
		$wp_style_file = self::$base_dir . $sub_dir . 'class-' . self::camel_to_kebab( $class_short ) . '.php';
		if ( is_readable( $wp_style_file ) ) {
			require_once $wp_style_file;
			return;
		}

		// Fallback: PSR-4-Standard (PostTypes.php bzw. OpenImmo/Export/ExportService.php).
		$psr4_file = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( is_readable( $psr4_file ) ) {
			require_once $psr4_file;
		}
	}

	/**
	 * CamelCase in kebab-case umwandeln.
	 *
	 * Beispiel: "PostTypes" -> "post-types"
	 *
	 * @param string $input Der umzuwandelnde String.
	 *
	 * @return string Kebab-case-String.
	 */
	private static function camel_to_kebab( string $input ): string {
		// Einen Bindestrich vor jedem Großbuchstaben einfügen, der nicht am Anfang steht.
		$result = preg_replace( '/(?<!^)[A-Z]/', '-$0', $input );

		return strtolower( (string) $result );
	}
}
