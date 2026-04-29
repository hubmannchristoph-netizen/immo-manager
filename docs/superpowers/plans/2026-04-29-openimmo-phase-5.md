# OpenImmo Phase 5 – Konflikt-Behandlung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** WP-Admin-Page für die `wp_immo_conflicts`-Queue. Pro Eintrag Diff-Anzeige, Approve/Reject. Bei Approve werden Property-Meta + Featured/Galerie aus den eingehenden Daten übernommen. Plus Badge mit pending count im Menü und Bulk-Reject in der Liste.

**Architecture:** Klar getrennte Layer: `ConflictsListTable extends \WP_List_Table` für Liste, `ConflictsAdminPage` für Submenü-Routing und Form-Handler, `ConflictsResolver` für Business-Logic. `Conflicts` (Phase 0) bekommt `pending_count()`-Erweiterung.

**Tech Stack:** WordPress 5.9+, PHP 7.4+, `\WP_List_Table`, `wp_nonce_*`, Standard-WP-Admin-Pattern (kein AJAX, kein eigenes JS/CSS).

**Spec:** `docs/superpowers/specs/2026-04-29-openimmo-phase-5-conflicts-design.md`

**Phasen-Kontext:** Phase 5 von 7. Phase 0/1/2/3/4 abgeschlossen.

**Hinweis zu Tests:** Wie bisher manuelle Verifikation: PHP-Lint, Plugin-Reaktivierung, UI-Smoke-Tests.

---

## File Structure

| Aktion | Datei | Verantwortung |
|---|---|---|
| Modify | `includes/openimmo/class-conflicts.php` | + `pending_count()` |
| Create | `includes/openimmo/class-conflicts-resolver.php` | Business-Logic für Resolve |
| Create | `includes/openimmo/class-conflicts-list-table.php` | WP_List_Table-Subklasse |
| Create | `includes/openimmo/class-conflicts-admin-page.php` | Submenü, Routing, Render, Form-Handler |
| Modify | `includes/class-plugin.php` | Lazy-Getter + Init-Trigger |

---

## Task 1: Conflicts::pending_count() + ConflictsResolver

**Files:**
- Modify: `includes/openimmo/class-conflicts.php`
- Create: `includes/openimmo/class-conflicts-resolver.php`

**Ziel:** Foundation. Phase-0-`Conflicts` bekommt `pending_count()` (für Badge), neue `ConflictsResolver`-Klasse für Business-Logic.

- [ ] **Step 1: Conflicts::pending_count() ergänzen**

In `includes/openimmo/class-conflicts.php`, vor der schließenden `}` der Klasse:

```php
/**
 * Anzahl pending Konflikte (für Menü-Badge).
 */
public static function pending_count(): int {
	global $wpdb;
	$table = Database::conflicts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
}
```

- [ ] **Step 2: ConflictsResolver anlegen**

`includes/openimmo/class-conflicts-resolver.php`:

```php
<?php
/**
 * Resolver für OpenImmo-Konflikte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;
use ImmoManager\PostTypes;

defined( 'ABSPATH' ) || exit;

class ConflictsResolver {

	/**
	 * Löst einen Konflikt auf.
	 *
	 * @param int    $conflict_id  Conflict-ID.
	 * @param string $resolution   'keep_local' | 'take_import'.
	 */
	public function resolve( int $conflict_id, string $resolution ): bool {
		$conflict = $this->fetch( $conflict_id );
		if ( null === $conflict || 'pending' !== $conflict['status'] ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( 'keep_local' === $resolution ) {
			return Conflicts::resolve( $conflict_id, 'keep_local', $user_id );
		}

		if ( 'take_import' === $resolution ) {
			$post_id = (int) $conflict['post_id'];
			$post    = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || PostTypes::POST_TYPE_PROPERTY !== $post->post_type ) {
				// Property gelöscht — auto-reject.
				Conflicts::resolve( $conflict_id, 'keep_local', $user_id );
				return false;
			}

			$this->apply_import( $post_id, (string) $conflict['source_data'] );
			return Conflicts::resolve( $conflict_id, 'take_import', $user_id );
		}

		return false;
	}

	/**
	 * Conflict-Row holen.
	 */
	private function fetch( int $conflict_id ): ?array {
		global $wpdb;
		$table = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conflict_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Source-Data auf Property anwenden (Meta + Bilder).
	 */
	private function apply_import( int $post_id, string $source_json ): void {
		$source = json_decode( $source_json, true );
		if ( ! is_array( $source ) ) {
			return;
		}

		// Meta-Felder updaten — incoming wins where present (analog Phase 3).
		foreach ( $source as $key => $value ) {
			if ( 0 !== strpos( (string) $key, '_immo_' ) ) {
				continue;
			}
			if ( '_immo_openimmo_pending_attachment_ids' === $key ) {
				continue;  // wird unten separat verarbeitet
			}
			if ( '' === $value || null === $value
				|| ( is_array( $value ) && empty( $value ) ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}

		// Pending-Attachments verknüpfen.
		if ( ! empty( $source['_immo_openimmo_pending_attachment_ids'] ) ) {
			$pending = json_decode( (string) $source['_immo_openimmo_pending_attachment_ids'], true );
			if ( is_array( $pending ) ) {
				$this->attach_pending_images( $post_id, $pending );
			}
		}
	}

	/**
	 * Pending-Attachments dem Property zuordnen.
	 *
	 * @param array<int,array{att_id:int, gruppe:string}> $pending
	 */
	private function attach_pending_images( int $post_id, array $pending ): void {
		// 1. Bestehende Galerie-Attachments ent-verknüpfen (post_parent=0).
		$existing = get_attached_media( 'image', $post_id );
		foreach ( $existing as $att ) {
			if ( ! $att instanceof \WP_Post ) {
				continue;
			}
			wp_update_post( array( 'ID' => (int) $att->ID, 'post_parent' => 0 ) );
		}

		// 2. TITELBILD setzen (erstes Pending mit gruppe='TITELBILD').
		foreach ( $pending as $p ) {
			$att_id = isset( $p['att_id'] ) ? (int) $p['att_id'] : 0;
			$gruppe = isset( $p['gruppe'] ) ? (string) $p['gruppe'] : 'BILD';
			if ( 'TITELBILD' === $gruppe && $att_id > 0 && get_post( $att_id ) instanceof \WP_Post ) {
				set_post_thumbnail( $post_id, $att_id );
				break;
			}
		}

		// 3. Galerie-Attachments verknüpfen.
		foreach ( $pending as $p ) {
			$att_id = isset( $p['att_id'] ) ? (int) $p['att_id'] : 0;
			$gruppe = isset( $p['gruppe'] ) ? (string) $p['gruppe'] : 'BILD';
			if ( 'TITELBILD' === $gruppe ) {
				continue;
			}
			if ( $att_id > 0 && get_post( $att_id ) instanceof \WP_Post ) {
				wp_update_post( array( 'ID' => $att_id, 'post_parent' => $post_id ) );
			}
		}
	}
}
```

- [ ] **Step 3: PHP-Lint**

```bash
php -l includes/openimmo/class-conflicts.php
php -l includes/openimmo/class-conflicts-resolver.php
```

**Erwartet:** „No syntax errors detected".

- [ ] **Step 4: Commit**

```bash
git add includes/openimmo/class-conflicts.php includes/openimmo/class-conflicts-resolver.php
git commit -m "feat(openimmo): add Conflicts::pending_count and ConflictsResolver"
```

---

## Task 2: ConflictsListTable

**Files:**
- Create: `includes/openimmo/class-conflicts-list-table.php`

**Ziel:** WP_List_Table-Subklasse mit Spalten, Bulk-Action `bulk_reject`, Pagination.

- [ ] **Step 1: ConflictsListTable anlegen**

`includes/openimmo/class-conflicts-list-table.php`:

```php
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
		$title      = $post instanceof \WP_Post ? $post->post_title : sprintf( __( '(post #%d gelöscht)', 'immo-manager' ), $post_id );
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
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/class-conflicts-list-table.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/class-conflicts-list-table.php
git commit -m "feat(openimmo): add ConflictsListTable"
```

---

## Task 3: ConflictsAdminPage

**Files:**
- Create: `includes/openimmo/class-conflicts-admin-page.php`

**Ziel:** Submenü-Registrierung mit Badge, Routing zwischen Liste und Detail, Form-Handler für Single-Approve/Reject und Bulk-Reject.

- [ ] **Step 1: ConflictsAdminPage anlegen**

`includes/openimmo/class-conflicts-admin-page.php`:

```php
<?php
/**
 * Admin-Page für die OpenImmo-Konflikt-Queue.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo;

use ImmoManager\Database;

defined( 'ABSPATH' ) || exit;

class ConflictsAdminPage {

	public const PAGE_SLUG = 'immo-manager-openimmo-conflicts';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init', array( $this, 'maybe_handle_submit' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_bulk_submit' ) );
	}

	public function register_submenu(): void {
		add_submenu_page(
			'immo-manager',
			__( 'OpenImmo-Konflikte', 'immo-manager' ),
			$this->menu_label_with_badge(),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'immo-manager' ) );
		}

		$conflict_id = isset( $_GET['conflict_id'] ) ? (int) $_GET['conflict_id'] : 0;
		if ( $conflict_id > 0 ) {
			$this->render_detail( $conflict_id );
			return;
		}
		$this->render_list();
	}

	/**
	 * Form-Handler für Single-Approve/Reject (POST oder GET-Link).
	 */
	public function maybe_handle_submit(): void {
		$is_post = isset( $_POST['immo_openimmo_resolve_action'] );
		$is_get  = isset( $_GET['page'], $_GET['conflict_id'], $_GET['resolution'] )
			&& self::PAGE_SLUG === $_GET['page'];

		if ( ! $is_post && ! $is_get ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$conflict_id = (int) ( $is_post ? ( $_POST['conflict_id'] ?? 0 ) : ( $_GET['conflict_id'] ?? 0 ) );
		$resolution  = sanitize_key( wp_unslash( (string) ( $is_post ? ( $_POST['resolution'] ?? '' ) : ( $_GET['resolution'] ?? '' ) ) ) );

		if ( $conflict_id <= 0 || ! in_array( $resolution, array( 'keep_local', 'take_import' ), true ) ) {
			return;
		}

		check_admin_referer( 'immo_openimmo_resolve_' . $conflict_id );

		$resolver = new ConflictsResolver();
		$ok       = $resolver->resolve( $conflict_id, $resolution );

		$message = $ok
			? ( 'take_import' === $resolution
				? __( 'Eingehende Daten wurden übernommen.', 'immo-manager' )
				: __( 'Bestand wurde behalten.', 'immo-manager' ) )
			: __( 'Konflikt konnte nicht aufgelöst werden (möglicherweise bereits bearbeitet oder Property nicht vorhanden).', 'immo-manager' );

		$redirect = add_query_arg(
			array(
				'page'             => self::PAGE_SLUG,
				'immo_resolve_msg' => rawurlencode( $message ),
				'immo_resolve_ok'  => $ok ? '1' : '0',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Form-Handler für Bulk-Reject.
	 */
	public function maybe_handle_bulk_submit(): void {
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( 'bulk_reject' !== $action ) {
			$action_2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
			if ( 'bulk_reject' !== $action_2 ) {
				return;
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'bulk-conflicts' );

		$ids = isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] )
			? array_map( 'intval', wp_unslash( $_REQUEST['ids'] ) )
			: array();
		$ids = array_filter( $ids, static function ( $id ) {
			return $id > 0;
		} );

		if ( empty( $ids ) ) {
			return;
		}

		$resolver = new ConflictsResolver();
		$count    = 0;
		foreach ( $ids as $id ) {
			if ( $resolver->resolve( (int) $id, 'keep_local' ) ) {
				++$count;
			}
		}

		$message  = sprintf(
			/* translators: %d: anzahl */
			__( '%d Konflikte abgelehnt.', 'immo-manager' ),
			$count
		);
		$redirect = add_query_arg(
			array(
				'page'             => self::PAGE_SLUG,
				'immo_resolve_msg' => rawurlencode( $message ),
				'immo_resolve_ok'  => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private function render_list(): void {
		$table = new ConflictsListTable();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenImmo-Konflikte', 'immo-manager' ); ?></h1>
			<?php $this->render_resolve_notice(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function render_detail( int $conflict_id ): void {
		global $wpdb;
		$tbl = Database::conflicts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conflict = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $conflict_id ), ARRAY_A );

		if ( ! is_array( $conflict ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'OpenImmo-Konflikt', 'immo-manager' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'Konflikt nicht gefunden.', 'immo-manager' ); ?></p></div>
				<p><a href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Zurück zur Liste', 'immo-manager' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$post_id = (int) $conflict['post_id'];
		$post    = get_post( $post_id );
		$source  = json_decode( (string) $conflict['source_data'], true );
		$local   = json_decode( (string) $conflict['local_data'], true );
		$fields  = json_decode( (string) $conflict['conflict_fields'], true );
		if ( ! is_array( $source ) ) { $source = array(); }
		if ( ! is_array( $local ) )  { $local  = array(); }
		if ( ! is_array( $fields ) ) { $fields = array(); }

		$pending_atts = isset( $source['_immo_openimmo_pending_attachment_ids'] )
			? json_decode( (string) $source['_immo_openimmo_pending_attachment_ids'], true )
			: array();
		if ( ! is_array( $pending_atts ) ) { $pending_atts = array(); }
		$titel_count = 0;
		$bild_count  = 0;
		foreach ( $pending_atts as $a ) {
			if ( ( $a['gruppe'] ?? '' ) === 'TITELBILD' ) { ++$titel_count; } else { ++$bild_count; }
		}

		$ts = strtotime( (string) $conflict['created_at'] );
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>">← <?php esc_html_e( 'Zurück zur Liste', 'immo-manager' ); ?></a></p>
			<h1><?php
				/* translators: %d: conflict id */
				echo esc_html( sprintf( __( 'Konflikt #%d', 'immo-manager' ), $conflict_id ) );
			?></h1>
			<?php $this->render_resolve_notice(); ?>
			<p>
				<strong><?php esc_html_e( 'Property:', 'immo-manager' ); ?></strong>
				<?php
				if ( $post instanceof \WP_Post ) {
					printf(
						'%s &nbsp; <a href="%s" target="_blank">[%s ↗]</a>',
						esc_html( $post->post_title ),
						esc_url( get_edit_post_link( $post->ID ) ),
						esc_html__( 'Im Editor öffnen', 'immo-manager' )
					);
				} else {
					/* translators: %d: post id */
					echo esc_html( sprintf( __( '#%d (gelöscht)', 'immo-manager' ), $post_id ) );
				}
				?>
				<br>
				<strong><?php esc_html_e( 'Portal:', 'immo-manager' ); ?></strong> <?php echo esc_html( (string) $conflict['portal'] ); ?>
				&nbsp;|&nbsp; <strong><?php esc_html_e( 'Erstellt:', 'immo-manager' ); ?></strong>
				<?php echo $ts ? esc_html( sprintf( __( 'vor %s', 'immo-manager' ), human_time_diff( $ts, time() ) ) ) : '—'; ?>
				&nbsp;|&nbsp; <strong><?php esc_html_e( 'Status:', 'immo-manager' ); ?></strong> <?php echo esc_html( (string) $conflict['status'] ); ?>
			</p>

			<h2><?php
				/* translators: %d: anzahl */
				echo esc_html( sprintf( __( 'Geänderte Felder (%d)', 'immo-manager' ), count( $fields ) ) );
			?></h2>

			<?php if ( empty( $fields ) ) : ?>
				<p><em><?php esc_html_e( 'Keine Diff-Felder gespeichert.', 'immo-manager' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feld', 'immo-manager' ); ?></th>
							<th><?php esc_html_e( 'Bestand', 'immo-manager' ); ?></th>
							<th><?php esc_html_e( 'Eingehend', 'immo-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $f ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $f ); ?></code></td>
								<td><?php echo $this->format_value( $local[ $f ] ?? '' ); ?></td>
								<td><?php echo $this->format_value( $source[ $f ] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $pending_atts ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Eingehende Bilder:', 'immo-manager' ); ?></strong>
					<?php
					/* translators: 1: total, 2: titelbild count, 3: bild count */
					echo esc_html( sprintf( __( '%1$d Attachments (%2$d× TITELBILD, %3$d× BILD)', 'immo-manager' ), count( $pending_atts ), $titel_count, $bild_count ) );
					?>
				</p>
			<?php endif; ?>

			<?php if ( 'pending' === (string) $conflict['status'] ) : ?>
				<form method="post" style="margin-top: 20px;">
					<?php wp_nonce_field( 'immo_openimmo_resolve_' . $conflict_id ); ?>
					<input type="hidden" name="immo_openimmo_resolve_action" value="1">
					<input type="hidden" name="conflict_id" value="<?php echo (int) $conflict_id; ?>">
					<button type="submit" name="resolution" value="keep_local" class="button button-secondary">
						<?php esc_html_e( 'Bestand behalten', 'immo-manager' ); ?>
					</button>
					<button type="submit" name="resolution" value="take_import" class="button button-primary"
							onclick="return confirm('<?php echo esc_js( __( 'Wirklich übernehmen? Property-Daten werden überschrieben.', 'immo-manager' ) ); ?>');">
						<?php esc_html_e( 'Eingehende Daten übernehmen', 'immo-manager' ); ?>
					</button>
				</form>
			<?php else : ?>
				<p><em><?php
					/* translators: %s: resolution */
					echo esc_html( sprintf( __( 'Bereits aufgelöst (%s).', 'immo-manager' ), (string) $conflict['resolution'] ) );
				?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Wert für die Diff-Tabelle formatieren.
	 */
	private function format_value( $value ): string {
		if ( is_array( $value ) ) {
			$str = implode( ', ', array_map( 'strval', $value ) );
		} else {
			$str = (string) $value;
		}
		if ( strlen( $str ) > 200 ) {
			return '<details><summary>' . esc_html( substr( $str, 0, 200 ) ) . '…</summary>' . esc_html( $str ) . '</details>';
		}
		return esc_html( $str );
	}

	/**
	 * Notice nach Resolve-Aktion (Query-Param).
	 */
	private function render_resolve_notice(): void {
		if ( empty( $_GET['immo_resolve_msg'] ) ) {
			return;
		}
		$msg   = sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['immo_resolve_msg'] ) ) );
		$ok    = ! empty( $_GET['immo_resolve_ok'] ) && '1' === (string) $_GET['immo_resolve_ok'];
		$class = $ok ? 'notice notice-success' : 'notice notice-error';
		printf(
			'<div class="%s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $msg )
		);
	}

	private function menu_label_with_badge(): string {
		$count = Conflicts::pending_count();
		if ( $count <= 0 ) {
			return __( 'OpenImmo-Konflikte', 'immo-manager' );
		}
		return sprintf(
			'%s <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
			esc_html__( 'OpenImmo-Konflikte', 'immo-manager' ),
			$count,
			$count
		);
	}
}
```

- [ ] **Step 2: PHP-Lint**

```bash
php -l includes/openimmo/class-conflicts-admin-page.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/openimmo/class-conflicts-admin-page.php
git commit -m "feat(openimmo): add ConflictsAdminPage with list, detail and form-handler"
```

---

## Task 4: Plugin-Lazy-Getter + Init-Trigger

**Files:**
- Modify: `includes/class-plugin.php`

**Ziel:** Lazy-Getter `get_openimmo_conflicts_page()` und Init-Aufruf, damit Hooks registriert werden.

- [ ] **Step 1: Property hinzufügen**

In `includes/class-plugin.php`, neben anderen `$openimmo_*`-Properties:

```php
/**
 * OpenImmo Konflikte-Page.
 *
 * @var \ImmoManager\OpenImmo\ConflictsAdminPage|null
 */
private $openimmo_conflicts_page = null;
```

- [ ] **Step 2: Getter implementieren**

Vor der schließenden `}` der Klasse:

```php
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
```

- [ ] **Step 3: Init-Trigger im Init-Pattern**

Finde die Methode/Stelle, wo `get_openimmo_admin_page()` aufgerufen wird (z.B. in `init_shortcodes()` oder vergleichbar — analog Phase 0). Dort daneben:

```php
$this->get_openimmo_conflicts_page();
```

Falls die Stelle nicht eindeutig ist: zumindest sicherstellen, dass die Methode im selben Hook aufgerufen wird wie `get_openimmo_admin_page()`. Schau dir bitte an, wo Phase 0 die `AdminPage` triggert — der Init-Trigger für die Konflikte-Page muss am selben Point eingebaut werden.

- [ ] **Step 4: PHP-Lint**

```bash
php -l includes/class-plugin.php
```

- [ ] **Step 5: Commit**

```bash
git add includes/class-plugin.php
git commit -m "feat(openimmo): wire ConflictsAdminPage into plugin singleton"
```

---

## Task 5: Akzeptanz + Memory-Update

**Files:** keine

- [ ] **Step 1: Verifikation**

- [ ] PHP-Lint sauber für alle Dateien
- [ ] Plugin-Reaktivierung — kein Fatal
- [ ] Submenü „OpenImmo-Konflikte" sichtbar (mit Badge wenn pending Konflikte da sind)
- [ ] Badge zeigt korrekte Anzahl (manuell pending Conflict in DB anlegen, Badge-Count prüfen)
- [ ] No-Items-Text bei leerer Queue
- [ ] Liste mit Konflikten zeigt alle Spalten
- [ ] Detail-View via Klick „Anzeigen" funktioniert
- [ ] Diff-Tabelle zeigt Bestand vs. Eingehend pro Feld
- [ ] Array-Felder als Komma-Liste, lange Strings via `<details>`
- [ ] „Bestand behalten" auf Detail: Conflict 'approved'/'keep_local', Property unverändert, Notice
- [ ] „Eingehende übernehmen" auf Detail: Property-Meta updated, Featured + Galerie ersetzt, Conflict 'approved'/'take_import'
- [ ] Featured-Replace: alter Featured bleibt in Library, neuer ist gesetzt
- [ ] Galerie-Replace: alte Attachments haben `post_parent=0`, neue haben `post_parent=$post_id`
- [ ] Row-Actions in Liste: „Bestand behalten" und „Eingehende übernehmen" funktionieren (mit confirm() für letzteres)
- [ ] Bulk-Reject: 5 Konflikte ankreuzen, Bulk-Aktion → 5 Conflicts approved/keep_local
- [ ] Bulk leer: silent
- [ ] Race-Test: Conflict in zwei Tabs öffnen, in einem auflösen, im anderen klicken → Notice
- [ ] Property gelöscht: Property löschen, Conflict approven → auto-reject
- [ ] Capability-Schutz: Editor sieht keine „Sorry, you are not allowed"
- [ ] Nonce-Schutz: direkter URL ohne Nonce → keine Aktion

- [ ] **Step 2: Memory-Update**

In `~/.claude/projects/.../memory/project_openimmo.md` Phase-5-Status auf „erledigt" setzen, Hinweis auf Phase 6 (Portal-Anpassungen).

- [ ] **Step 3: Bereitschaftsmeldung**

Ergebnis an User: „Phase 5 fertig. Bereit für Phase 6 (Portal-Anpassungen)? Erst nach OK weiter."

---

## Self-Review (vom Plan-Autor durchgeführt)

**Spec-Coverage-Check:**

| Spec-Anforderung | Abgedeckt in |
|---|---|
| `Conflicts::pending_count()` | T1 |
| `ConflictsResolver` mit `resolve()` + `apply_import()` + `attach_pending_images()` | T1 |
| Race-Condition + Property-gelöscht-Schutz | T1 (Resolver-Pre-Checks) |
| `ConflictsListTable` mit Spalten + Bulk-Action | T2 |
| Liste: Property-Spalte mit Row-Actions | T2 |
| `no_items()` und `column_changed_fields()` | T2 |
| `ConflictsAdminPage` Submenü + Routing | T3 |
| Badge im Menü-Label | T3 (`menu_label_with_badge`) |
| Detail-View mit Diff-Tabelle | T3 (`render_detail`) |
| Single-POST-Form mit Nonce | T3 (`maybe_handle_submit` POST-Pfad) |
| GET-Row-Action mit `wp_nonce_url` | T3 (`maybe_handle_submit` GET-Pfad + Liste in T2) |
| Bulk-Submit-Handler | T3 (`maybe_handle_bulk_submit`) |
| Notice nach Resolve | T3 (`render_resolve_notice`) |
| Format-Value (Arrays + lange Strings) | T3 (`format_value`) |
| Plugin-Lazy-Getter + Init-Trigger | T4 |
| Manuelle Verifikation | T5 |
| Out-of-Scope-Liste | nicht implementiert (korrekt) |

**Placeholder-Scan:** Keine TBD/TODO. Alle Code-Blöcke vollständig.

**Type-Konsistenz:**
- `ConflictsAdminPage::PAGE_SLUG` konsistent zwischen Definition (T3) und Konsumation in `ConflictsListTable::column_property` (T2).
- `ConflictsResolver::resolve(int, string): bool` Signatur konsistent zwischen Definition (T1) und Konsumation in `maybe_handle_submit` / `maybe_handle_bulk_submit` (T3).
- `Conflicts::pending_count()` Definition (T1) ↔ Konsumation in `menu_label_with_badge()` (T3).

**Bekannte Vereinfachungen:**
- T4 Step 3 weist den Engineer an, den Init-Trigger an der existing-Phase-0-Stelle einzubauen — die exakte Methode hängt von Phase-0-Code ab und ist im Plan bewusst flexibel beschrieben.

Plan ist konsistent.
