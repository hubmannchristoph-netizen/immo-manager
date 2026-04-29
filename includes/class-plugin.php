<?php
/**
 * Haupt Plugin-Klasse als Singleton.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton-Instanz.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * PostTypes-Handler.
	 *
	 * @var PostTypes|null
	 */
	private $post_types = null;

	/**
	 * AdminPages-Handler.
	 *
	 * @var AdminPages|null
	 */
	private $admin_pages = null;

	/**
	 * Settings-Handler.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Dashboard-Handler.
	 *
	 * @var Dashboard|null
	 */
	private $dashboard = null;

	/**
	 * Database-Handler.
	 *
	 * @var Database|null
	 */
	private $database = null;

	/**
	 * MetaFields-Handler.
	 *
	 * @var MetaFields|null
	 */
	private $meta_fields = null;

	/**
	 * Metaboxes-Handler.
	 *
	 * @var Metaboxes|null
	 */
	private $metaboxes = null;

	/**
	 * AdminAjax-Handler.
	 *
	 * @var AdminAjax|null
	 */
	private $admin_ajax = null;

	/**
	 * Shortcodes-Handler.
	 *
	 * @var Shortcodes|null
	 */
	private $shortcodes = null;

	/**
	 * Elementor-Integration.
	 *
	 * @var ElementorIntegration|null
	 */
	private $elementor = null;

	/**
	 * REST-API-Handler.
	 *
	 * @var RestApi|null
	 */
	private $rest_api = null;

	/**
	 * Wizard-Handler.
	 *
	 * @var Wizard|null
	 */
	private $wizard = null;

	/**
	 * DemoData-Handler.
	 *
	 * @var DemoData|null
	 */
	private $demo_data = null;

	/**
	 * Templates-Handler.
	 *
	 * @var Templates|null
	 */
	private $templates = null;

	/**
	 * Schema-Handler.
	 *
	 * @var Schema|null
	 */
	private $schema = null;

	/**
	 * OpenImmo Admin-Page.
	 *
	 * @var \ImmoManager\OpenImmo\AdminPage|null
	 */
	private $openimmo_admin_page = null;

	/**
	 * OpenImmo Cron-Scheduler.
	 *
	 * @var \ImmoManager\OpenImmo\CronScheduler|null
	 */
	private $openimmo_cron = null;

	/**
	 * OpenImmo Export-Service.
	 *
	 * @var \ImmoManager\OpenImmo\Export\ExportService|null
	 */
	private $openimmo_export_service = null;

	/**
	 * OpenImmo SFTP-Uploader.
	 *
	 * @var \ImmoManager\OpenImmo\Sftp\SftpUploader|null
	 */
	private $openimmo_sftp_uploader = null;

	/**
	 * OpenImmo Import-Service.
	 *
	 * @var \ImmoManager\OpenImmo\Import\ImportService|null
	 */
	private $openimmo_import_service = null;

	/**
	 * OpenImmo SFTP-Puller.
	 *
	 * @var \ImmoManager\OpenImmo\Sftp\SftpPuller|null
	 */
	private $openimmo_sftp_puller = null;

	/**
	 * OpenImmo Konflikte-Page.
	 *
	 * @var \ImmoManager\OpenImmo\ConflictsAdminPage|null
	 */
	private $openimmo_conflicts_page = null;

	/**
	 * Singleton-Instanz zurückgeben.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Privater Konstruktor (Singleton).
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Klonen verhindern.
	 */
	private function __clone() {}

	/**
	 * Deserialisierung verhindern.
	 *
	 * @throws \Exception Immer.
	 */
	public function __wakeup() {
		throw new \Exception( 'Singleton cannot be unserialized.' );
	}

	/**
	 * WordPress-Hooks registrieren.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'on_init' ), 10 );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		// REST API (immer, auch Frontend).
		add_action( 'init', array( $this, 'init_rest' ), 5 );

		// Shortcodes (immer, auch Frontend).
		add_action( 'init', array( $this, 'init_shortcodes' ), 10 );

		// Admin-Komponenten nur im Admin-Kontext.
		if ( is_admin() ) {
			add_action( 'init', array( $this, 'init_admin' ), 5 );
		}
	}

	/**
	 * REST API initialisieren.
	 *
	 * @return void
	 */
	public function init_rest(): void {
		$this->get_rest_api();
	}

	/**
	 * Shortcodes initialisieren.
	 *
	 * @return void
	 */
	public function init_shortcodes(): void {
		$this->get_shortcodes();
		$this->get_wizard();
		$this->get_templates();
		$this->get_elementor();
		$this->get_schema();
		$this->get_openimmo_cron();
	}

	/**
	 * Admin-Komponenten initialisieren.
	 *
	 * Wird nur im Admin-Kontext aufgerufen.
	 *
	 * @return void
	 */
	public function init_admin(): void {
		$this->get_admin_pages();
		$this->get_settings();
		$this->get_dashboard();
		$this->get_metaboxes();
		$this->get_admin_ajax();
		$this->get_demo_data();
		$this->get_openimmo_admin_page();
		$this->get_openimmo_conflicts_page();
	}

	/**
	 * Init-Callback: Post Types, Taxonomies & Meta-Felder registrieren.
	 *
	 * @return void
	 */
	public function on_init(): void {
		$this->get_post_types()->register();
		$this->get_meta_fields(); // Registriert Meta per register_post_meta().

		// Datenbank-Upgrade sicherstellen!
		$this->get_database()->maybe_upgrade();
	}

	/**
	 * Übersetzungsdateien laden.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'immo-manager',
			false,
			dirname( IMMO_MANAGER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Plugin aktivieren.
	 *
	 * Wird durch register_activation_hook aufgerufen.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Post Types zuerst registrieren, damit die Permalinks korrekt gesetzt werden.
		$this->get_post_types()->register();

		// Rewrite-Regeln flushen.
		flush_rewrite_rules();

		// Version in Options speichern.
		update_option( 'immo_manager_version', IMMO_MANAGER_VERSION );
		update_option( 'immo_manager_activated_at', current_time( 'mysql' ) );
	}

	/**
	 * Plugin deaktivieren.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * PostTypes-Handler abrufen (Lazy Loading).
	 *
	 * @return PostTypes
	 */
	public function get_post_types(): PostTypes {
		if ( null === $this->post_types ) {
			$this->post_types = new PostTypes();
		}
		return $this->post_types;
	}

	/**
	 * AdminPages-Handler abrufen (Lazy Loading).
	 *
	 * @return AdminPages
	 */
	public function get_admin_pages(): AdminPages {
		if ( null === $this->admin_pages ) {
			$this->admin_pages = new AdminPages();
		}
		return $this->admin_pages;
	}

	/**
	 * Settings-Handler abrufen (Lazy Loading).
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}
		return $this->settings;
	}

	/**
	 * Dashboard-Handler abrufen (Lazy Loading).
	 *
	 * @return Dashboard
	 */
	public function get_dashboard(): Dashboard {
		if ( null === $this->dashboard ) {
			$this->dashboard = new Dashboard();
		}
		return $this->dashboard;
	}

	/**
	 * Database-Handler abrufen (Lazy Loading).
	 *
	 * @return Database
	 */
	public function get_database(): Database {
		if ( null === $this->database ) {
			$this->database = new Database();
		}
		return $this->database;
	}

	/**
	 * MetaFields-Handler abrufen (Lazy Loading).
	 *
	 * @return MetaFields
	 */
	public function get_meta_fields(): MetaFields {
		if ( null === $this->meta_fields ) {
			$this->meta_fields = new MetaFields();
		}
		return $this->meta_fields;
	}

	/**
	 * Metaboxes-Handler abrufen (Lazy Loading).
	 *
	 * @return Metaboxes
	 */
	public function get_metaboxes(): Metaboxes {
		if ( null === $this->metaboxes ) {
			$this->metaboxes = new Metaboxes();
		}
		return $this->metaboxes;
	}

	/**
	 * AdminAjax-Handler abrufen (Lazy Loading).
	 *
	 * @return AdminAjax
	 */
	public function get_admin_ajax(): AdminAjax {
		if ( null === $this->admin_ajax ) {
			$this->admin_ajax = new AdminAjax();
		}
		return $this->admin_ajax;
	}

	/**
	 * Shortcodes-Handler abrufen (Lazy Loading).
	 *
	 * @return Shortcodes
	 */
	public function get_shortcodes(): Shortcodes {
		if ( null === $this->shortcodes ) {
			$this->shortcodes = new Shortcodes();
		}
		return $this->shortcodes;
	}

	/**
	 * Elementor-Integration abrufen.
	 *
	 * @return ElementorIntegration
	 */
	public function get_elementor(): ElementorIntegration {
		if ( null === $this->elementor ) {
			$this->elementor = new ElementorIntegration();
		}
		return $this->elementor;
	}

	/**
	 * REST-API-Handler abrufen (Lazy Loading).
	 *
	 * @return RestApi
	 */
	public function get_rest_api(): RestApi {
		if ( null === $this->rest_api ) {
			$this->rest_api = new RestApi();
		}
		return $this->rest_api;
	}

	/**
	 * Wizard-Handler abrufen (Lazy Loading).
	 *
	 * @return Wizard
	 */
	public function get_wizard(): Wizard {
		if ( null === $this->wizard ) {
			$this->wizard = new Wizard();
		}
		return $this->wizard;
	}

	/**
	 * DemoData-Handler abrufen (Lazy Loading).
	 *
	 * @return DemoData
	 */
	public function get_demo_data(): DemoData {
		if ( null === $this->demo_data ) {
			$this->demo_data = new DemoData();
		}
		return $this->demo_data;
	}

	/**
	 * Templates-Handler abrufen (Lazy Loading).
	 *
	 * @return Templates
	 */
	public function get_templates(): Templates {
		if ( null === $this->templates ) {
			$this->templates = new Templates();
		}
		return $this->templates;
	}

	/**
	 * Schema-Handler abrufen (Lazy Loading).
	 *
	 * @return Schema
	 */
	public function get_schema(): Schema {
		if ( null === $this->schema ) {
			$this->schema = new Schema();
		}
		return $this->schema;
	}

	/**
	 * OpenImmo Admin-Page abrufen (Lazy Loading).
	 *
	 * Sub-Namespace-Klassen werden manuell required, weil der bestehende
	 * Autoloader nur den Top-Level-Namespace ImmoManager unterstützt
	 * (analog zur Elementor-Integration).
	 *
	 * @return \ImmoManager\OpenImmo\AdminPage
	 */
	public function get_openimmo_admin_page(): \ImmoManager\OpenImmo\AdminPage {
		if ( null === $this->openimmo_admin_page ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-admin-page.php';
			$this->openimmo_admin_page = new \ImmoManager\OpenImmo\AdminPage();
		}
		return $this->openimmo_admin_page;
	}

	/**
	 * OpenImmo Cron-Scheduler abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\CronScheduler
	 */
	public function get_openimmo_cron(): \ImmoManager\OpenImmo\CronScheduler {
		if ( null === $this->openimmo_cron ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-cron-scheduler.php';
			$this->openimmo_cron = new \ImmoManager\OpenImmo\CronScheduler();
		}
		return $this->openimmo_cron;
	}

	/**
	 * OpenImmo Export-Service abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\Export\ExportService
	 */
	public function get_openimmo_export_service(): \ImmoManager\OpenImmo\Export\ExportService {
		if ( null === $this->openimmo_export_service ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/export/class-export-service.php';
			\ImmoManager\OpenImmo\Export\ExportService::require_dependencies();
			$this->openimmo_export_service = new \ImmoManager\OpenImmo\Export\ExportService();
		}
		return $this->openimmo_export_service;
	}

	/**
	 * OpenImmo SFTP-Uploader abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\Sftp\SftpUploader
	 */
	public function get_openimmo_sftp_uploader(): \ImmoManager\OpenImmo\Sftp\SftpUploader {
		if ( null === $this->openimmo_sftp_uploader ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-client.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-uploader.php';
			$this->openimmo_sftp_uploader = new \ImmoManager\OpenImmo\Sftp\SftpUploader();
		}
		return $this->openimmo_sftp_uploader;
	}

	/**
	 * OpenImmo Import-Service abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\Import\ImportService
	 */
	public function get_openimmo_import_service(): \ImmoManager\OpenImmo\Import\ImportService {
		if ( null === $this->openimmo_import_service ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/export/class-mapper.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-listing-dto.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-zip-extractor.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-mapper.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-image-importer.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-conflict-detector.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/import/class-import-service.php';
			$this->openimmo_import_service = new \ImmoManager\OpenImmo\Import\ImportService();
		}
		return $this->openimmo_import_service;
	}

	/**
	 * OpenImmo SFTP-Puller abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\Sftp\SftpPuller
	 */
	public function get_openimmo_sftp_puller(): \ImmoManager\OpenImmo\Sftp\SftpPuller {
		if ( null === $this->openimmo_sftp_puller ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-settings.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-sync-log.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-client.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/sftp/class-sftp-puller.php';
			$this->openimmo_sftp_puller = new \ImmoManager\OpenImmo\Sftp\SftpPuller();
		}
		return $this->openimmo_sftp_puller;
	}

	/**
	 * OpenImmo Konflikte-Page abrufen (Lazy Loading).
	 *
	 * @return \ImmoManager\OpenImmo\ConflictsAdminPage
	 */
	public function get_openimmo_conflicts_page(): \ImmoManager\OpenImmo\ConflictsAdminPage {
		if ( null === $this->openimmo_conflicts_page ) {
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts-resolver.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts-list-table.php';
			require_once IMMO_MANAGER_PLUGIN_DIR . 'includes/openimmo/class-conflicts-admin-page.php';
			$this->openimmo_conflicts_page = new \ImmoManager\OpenImmo\ConflictsAdminPage();
		}
		return $this->openimmo_conflicts_page;
	}
}
