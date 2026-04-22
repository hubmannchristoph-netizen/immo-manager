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
}
