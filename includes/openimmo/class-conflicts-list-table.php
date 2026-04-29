<?php
/**
 * WP_List_Table für die OpenImmo-Konflikt-Queue.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ConflictsListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'conflict',
			'plural'   => 'conflicts',
			'ajax'     => false,
		) );
	}

	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox">',
			'property'       => __( 'Property', 'immo-manager' ),
			'portal'         => __( 'Portal', 'immo-manager' ),
			'changed_fields' => __( 'Geänderte Felder', 'immo-manager' ),
			'created_at'     => __( 'Erstellt', 'immo-manager' ),
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'bulk_reject' => __( 'Bestand behalten', 'immo-manager' ),
		);
	}

	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		global $wpdb;
		$table = Database::conflicts_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$this->items = is_array( $rows ) ? $rows : array();

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
		);

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		) );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'portal':
				return esc_html( (string) ( $item['portal'] ?? '' ) );
			case 'created_at':
				$ts = strtotime( (string) ( $item['created_at'] ?? '' ) );
				return false === $ts
					? '—'
					: esc_html( sprintf(
						/* translators: %s: human-readable time difference */
						__( 'vor %s', 'immo-manager' ),
						human_time_diff( $ts, time() )
					) );
			default:
				return '—';
		}
	}

	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%d">',
			(int) ( $item['id'] ?? 0 )
		);
	}

	public function column_property( $item ): string {
		$post_id    = (int) ( $item['post_id'] ?? 0 );
		$post       = get_post( $post_id );
		$title      = $post instanceof \WP_Post
			? $post->post_title
			: sprintf(
				/* translators: %d: post id */
				__( '(post #%d gelöscht)', 'immo-manager' ),
				$post_id
			);
		$detail_url = add_query_arg(
			array( 'page' => ConflictsAdminPage::PAGE_SLUG, 'conflict_id' => (int) $item['id'] ),
			admin_url( 'admin.php' )
		);
		$keep_url   = wp_nonce_url(
			add_query_arg(
				array(
					'page'        => ConflictsAdminPage::PAGE_SLUG,
					'conflict_id' => (int) $item['id'],
					'resolution'  => 'keep_local',
				),
				admin_url( 'admin.php' )
			),
			'immo_openimmo_resolve_' . (int) $item['id']
		);
		$take_url   = wp_nonce_url(
			add_query_arg(
				array(
					'page'        => ConflictsAdminPage::PAGE_SLUG,
					'conflict_id' => (int) $item['id'],
					'resolution'  => 'take_import',
				),
				admin_url( 'admin.php' )
			),
			'immo_openimmo_resolve_' . (int) $item['id']
		);

		$row_actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $detail_url ), esc_html__( 'Anzeigen', 'immo-manager' ) ),
			'keep' => sprintf( '<a href="%s">%s</a>', esc_url( $keep_url ), esc_html__( 'Bestand behalten', 'immo-manager' ) ),
			'take' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $take_url ),
				esc_js( __( 'Wirklich übernehmen? Property-Daten werden überschrieben.', 'immo-manager' ) ),
				esc_html__( 'Eingehende übernehmen', 'immo-manager' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong><br><small>#%d</small>%s',
			esc_url( $detail_url ),
			esc_html( $title ),
			$post_id,
			$this->row_actions( $row_actions )
		);
	}

	public function column_changed_fields( $item ): string {
		$fields = json_decode( (string) ( $item['conflict_fields'] ?? '[]' ), true );
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return '—';
		}
		$count = count( $fields );
		$shown = array_slice( $fields, 0, 3 );
		$out   = esc_html( implode( ', ', $shown ) );
		if ( $count > 3 ) {
			$out .= ' ' . sprintf(
				/* translators: %d: number of additional fields */
				esc_html__( '... (%d mehr)', 'immo-manager' ),
				$count - 3
			);
		}
		return $out;
	}

	public function no_items(): void {
		esc_html_e( 'Keine pending Konflikte. 🎉', 'immo-manager' );
	}
}
