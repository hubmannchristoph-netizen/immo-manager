<?php
/**
 * Custom Post Types und Taxonomies für Immo Manager.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class PostTypes
 */
class PostTypes {

	/**
	 * Post-Type-Slug für Immobilien.
	 */
	public const POST_TYPE_PROPERTY = 'immo_mgr_property';

	/**
	 * Post-Type-Slug für Bauprojekte.
	 */
	public const POST_TYPE_PROJECT = 'immo_mgr_project';

	/**
	 * Taxonomy-Slug für Immobilientyp (Wohnung, Haus, Grund …).
	 */
	public const TAX_CATEGORY = 'immo_category';

	/**
	 * Taxonomy-Slug für Region (Bundesland/Bezirk).
	 */
	public const TAX_LOCATION = 'immo_location';

	/**
	 * Taxonomy-Slug für Agenten.
	 */
	public const TAX_AGENT = 'immo_agent';

	/**
	 * Alle Post Types und Taxonomies registrieren.
	 *
	 * Wichtig: Taxonomies nach den Post Types registrieren, da sie sich auf diese beziehen.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_property_post_type();
		$this->register_project_post_type();
		$this->register_category_taxonomy();
		$this->register_location_taxonomy();
		$this->register_agent_taxonomy();

		// Row Actions für die Liste hinzufügen.
		add_filter( 'post_row_actions', array( $this, 'add_edit_row_actions' ), 10, 2 );

		// Custom Columns für Immobilien und Projekte.
		add_filter( 'manage_' . self::POST_TYPE_PROPERTY . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_filter( 'manage_' . self::POST_TYPE_PROJECT . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE_PROPERTY . '_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
		add_action( 'manage_' . self::POST_TYPE_PROJECT . '_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
	}

	/**
	 * Spalten in der Listenansicht erweitern.
	 *
	 * @param array<string, string> $columns Spalten.
	 *
	 * @return array<string, string> Modifizierte Spalten.
	 */
	public function add_custom_columns( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			// Die neue Spalte nach dem Titel einfügen.
			if ( 'title' === $key ) {
				$new_columns['immo_editor_mode'] = __( 'Editor-Modus', 'immo-manager' );
			}
		}
		return $new_columns;
	}

	/**
	 * Inhalt der Custom-Spalten rendern.
	 *
	 * @param string $column  Spalten-Key.
	 * @param int    $post_id Post-ID.
	 *
	 * @return void
	 */
	public function render_custom_column( string $column, int $post_id ): void {
		if ( 'immo_editor_mode' !== $column ) {
			return;
		}

		$edit_url    = get_edit_post_link( $post_id );
		$classic_url = add_query_arg( 'mode', 'classic', $edit_url );

		echo '<div class="immo-quick-edit-links">';
		printf(
			'<a href="%s" class="button button-small immo-btn-wizard" title="%s">✨ %s</a>',
			esc_url( $edit_url ),
			esc_attr__( 'Mit dem geführten Wizard bearbeiten', 'immo-manager' ),
			__( 'Wizard', 'immo-manager' )
		);
		printf(
			'<a href="%s" class="button button-small" title="%s">⚙️ %s</a>',
			esc_url( $classic_url ),
			esc_attr__( 'WordPress Standard-Editor öffnen', 'immo-manager' ),
			__( 'Standard', 'immo-manager' )
		);
		echo '</div>';
	}

	/**
	 * Fügt "Wizard Bearbeiten" und "Standard WordPress Editor" Links zur Liste hinzu.
	 *
	 * @param array<string, string> $actions Aktionen.
	 * @param \WP_Post              $post    Post.
	 *
	 * @return array<string, string> Modifizierte Aktionen.
	 */
	public function add_edit_row_actions( array $actions, \WP_Post $post ): array {
		if ( ! in_array( $post->post_type, array( self::POST_TYPE_PROPERTY, self::POST_TYPE_PROJECT ), true ) ) {
			return $actions;
		}

		$edit_url = get_edit_post_link( $post->ID );
		if ( ! $edit_url ) {
			return $actions;
		}

		// Den Standard-"Bearbeiten" Link umbenennen in "Wizard (Empfohlen)".
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( $edit_url ),
				esc_attr__( 'Mit dem Wizard bearbeiten', 'immo-manager' ),
				__( 'Bearbeiten (Wizard)', 'immo-manager' )
			);
		}

		// Neuen Link für den klassischen WordPress Editor hinzufügen.
		$classic_url = add_query_arg( 'mode', 'classic', $edit_url );
		$actions['classic_edit'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $classic_url ),
			esc_attr__( 'Mit dem WordPress Standard-Editor bearbeiten', 'immo-manager' ),
			__( 'Standard-Editor', 'immo-manager' )
		);

		return $actions;
	}

	/**
	 * Post Type "Immobilie" registrieren.
	 *
	 * @return void
	 */
	private function register_property_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Immobilien', 'post type general name', 'immo-manager' ),
			'singular_name'         => _x( 'Immobilie', 'post type singular name', 'immo-manager' ),
			'menu_name'             => _x( 'Immobilien', 'admin menu', 'immo-manager' ),
			'name_admin_bar'        => _x( 'Immobilie', 'add new on admin bar', 'immo-manager' ),
			'add_new'               => _x( 'Neu hinzufügen', 'property', 'immo-manager' ),
			'add_new_item'          => __( 'Neue Immobilie hinzufügen', 'immo-manager' ),
			'new_item'              => __( 'Neue Immobilie', 'immo-manager' ),
			'edit_item'             => __( 'Immobilie bearbeiten', 'immo-manager' ),
			'view_item'             => __( 'Immobilie ansehen', 'immo-manager' ),
			'view_items'            => __( 'Immobilien ansehen', 'immo-manager' ),
			'all_items'             => __( 'Alle Immobilien', 'immo-manager' ),
			'search_items'          => __( 'Immobilien suchen', 'immo-manager' ),
			'not_found'             => __( 'Keine Immobilien gefunden.', 'immo-manager' ),
			'not_found_in_trash'    => __( 'Keine Immobilien im Papierkorb.', 'immo-manager' ),
			'featured_image'        => __( 'Titelbild der Immobilie', 'immo-manager' ),
			'set_featured_image'    => __( 'Titelbild festlegen', 'immo-manager' ),
			'remove_featured_image' => __( 'Titelbild entfernen', 'immo-manager' ),
			'use_featured_image'    => __( 'Als Titelbild verwenden', 'immo-manager' ),
			'archives'              => __( 'Immobilien-Archiv', 'immo-manager' ),
			'insert_into_item'      => __( 'In Immobilie einfügen', 'immo-manager' ),
			'uploaded_to_this_item' => __( 'Zu dieser Immobilie hochgeladen', 'immo-manager' ),
			'filter_items_list'     => __( 'Immobilienliste filtern', 'immo-manager' ),
			'items_list_navigation' => __( 'Navigation Immobilienliste', 'immo-manager' ),
			'items_list'            => __( 'Immobilienliste', 'immo-manager' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Immobilien für Verkauf und Vermietung', 'immo-manager' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'immo-manager', // Submenu unter Immo Manager.
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'immo-properties',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'immobilien',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-building',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
		);

		register_post_type( self::POST_TYPE_PROPERTY, $args );
	}

	/**
	 * Post Type "Bauprojekt" registrieren.
	 *
	 * @return void
	 */
	private function register_project_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Bauprojekte', 'post type general name', 'immo-manager' ),
			'singular_name'         => _x( 'Bauprojekt', 'post type singular name', 'immo-manager' ),
			'menu_name'             => _x( 'Bauprojekte', 'admin menu', 'immo-manager' ),
			'name_admin_bar'        => _x( 'Bauprojekt', 'add new on admin bar', 'immo-manager' ),
			'add_new'               => _x( 'Neu hinzufügen', 'project', 'immo-manager' ),
			'add_new_item'          => __( 'Neues Bauprojekt hinzufügen', 'immo-manager' ),
			'new_item'              => __( 'Neues Bauprojekt', 'immo-manager' ),
			'edit_item'             => __( 'Bauprojekt bearbeiten', 'immo-manager' ),
			'view_item'             => __( 'Bauprojekt ansehen', 'immo-manager' ),
			'view_items'            => __( 'Bauprojekte ansehen', 'immo-manager' ),
			'all_items'             => __( 'Alle Bauprojekte', 'immo-manager' ),
			'search_items'          => __( 'Bauprojekte suchen', 'immo-manager' ),
			'not_found'             => __( 'Keine Bauprojekte gefunden.', 'immo-manager' ),
			'not_found_in_trash'    => __( 'Keine Bauprojekte im Papierkorb.', 'immo-manager' ),
			'featured_image'        => __( 'Titelbild des Projekts', 'immo-manager' ),
			'set_featured_image'    => __( 'Titelbild festlegen', 'immo-manager' ),
			'remove_featured_image' => __( 'Titelbild entfernen', 'immo-manager' ),
			'use_featured_image'    => __( 'Als Titelbild verwenden', 'immo-manager' ),
			'archives'              => __( 'Bauprojekt-Archiv', 'immo-manager' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Bauprojekte mit mehreren Wohneinheiten', 'immo-manager' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'immo-manager', // Submenu unter Immo Manager.
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'immo-projects',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'projekte',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 21,
			'menu_icon'          => 'dashicons-admin-multisite',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
		);

		register_post_type( self::POST_TYPE_PROJECT, $args );
	}

	/**
	 * Taxonomy "Immobilientyp" registrieren.
	 *
	 * Nicht hierarchisch (ähnlich wie Tags) – z. B. Wohnung, Haus, Gewerbe.
	 *
	 * @return void
	 */
	private function register_category_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Immobilientypen', 'taxonomy general name', 'immo-manager' ),
			'singular_name'     => _x( 'Immobilientyp', 'taxonomy singular name', 'immo-manager' ),
			'search_items'      => __( 'Typen suchen', 'immo-manager' ),
			'all_items'         => __( 'Alle Typen', 'immo-manager' ),
			'edit_item'         => __( 'Typ bearbeiten', 'immo-manager' ),
			'update_item'       => __( 'Typ aktualisieren', 'immo-manager' ),
			'add_new_item'      => __( 'Neuen Typ hinzufügen', 'immo-manager' ),
			'new_item_name'     => __( 'Name des neuen Typs', 'immo-manager' ),
			'menu_name'         => __( 'Typen', 'immo-manager' ),
			'not_found'         => __( 'Keine Typen gefunden.', 'immo-manager' ),
			'back_to_items'     => __( '← Zurück zu den Typen', 'immo-manager' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'immobilientyp' ),
		);

		register_taxonomy( self::TAX_CATEGORY, array( self::POST_TYPE_PROPERTY ), $args );
	}

	/**
	 * Taxonomy "Region" registrieren.
	 *
	 * Hierarchisch, damit Bundesländer Bezirke enthalten können.
	 *
	 * @return void
	 */
	private function register_location_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Regionen', 'taxonomy general name', 'immo-manager' ),
			'singular_name'     => _x( 'Region', 'taxonomy singular name', 'immo-manager' ),
			'search_items'      => __( 'Regionen suchen', 'immo-manager' ),
			'all_items'         => __( 'Alle Regionen', 'immo-manager' ),
			'parent_item'       => __( 'Übergeordnete Region', 'immo-manager' ),
			'parent_item_colon' => __( 'Übergeordnete Region:', 'immo-manager' ),
			'edit_item'         => __( 'Region bearbeiten', 'immo-manager' ),
			'update_item'       => __( 'Region aktualisieren', 'immo-manager' ),
			'add_new_item'      => __( 'Neue Region hinzufügen', 'immo-manager' ),
			'new_item_name'     => __( 'Name der neuen Region', 'immo-manager' ),
			'menu_name'         => __( 'Regionen', 'immo-manager' ),
			'not_found'         => __( 'Keine Regionen gefunden.', 'immo-manager' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'region' ),
		);

		register_taxonomy(
			self::TAX_LOCATION,
			array( self::POST_TYPE_PROPERTY, self::POST_TYPE_PROJECT ),
			$args
		);
	}

	/**
	 * Taxonomy "Agent" registrieren.
	 *
	 * @return void
	 */
	private function register_agent_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Agenten', 'taxonomy general name', 'immo-manager' ),
			'singular_name'     => _x( 'Agent', 'taxonomy singular name', 'immo-manager' ),
			'search_items'      => __( 'Agenten suchen', 'immo-manager' ),
			'all_items'         => __( 'Alle Agenten', 'immo-manager' ),
			'edit_item'         => __( 'Agent bearbeiten', 'immo-manager' ),
			'update_item'       => __( 'Agent aktualisieren', 'immo-manager' ),
			'add_new_item'      => __( 'Neuen Agent hinzufügen', 'immo-manager' ),
			'new_item_name'     => __( 'Name des neuen Agenten', 'immo-manager' ),
			'menu_name'         => __( 'Agenten', 'immo-manager' ),
			'not_found'         => __( 'Keine Agenten gefunden.', 'immo-manager' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'agent' ),
		);

		register_taxonomy(
			self::TAX_AGENT,
			array( self::POST_TYPE_PROPERTY, self::POST_TYPE_PROJECT ),
			$args
		);
	}
}
