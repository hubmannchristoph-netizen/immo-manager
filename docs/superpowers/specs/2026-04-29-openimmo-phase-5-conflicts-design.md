# OpenImmo Phase 5 – Konflikt-Behandlung (Approval-UI) Design

**Datum:** 2026-04-29
**Branch:** `feat/openimmo`
**Phase:** 5 von 7 (Phase 0/1/2/3/4 abgeschlossen)
**Status:** Spec — Implementierungs-Plan folgt nach Approval

---

## Ziel

WP-Admin-Page für die `wp_immo_conflicts`-Queue (Phase 0). Pro Eintrag Diff-Anzeige (current vs. incoming), Approve/Reject-Buttons. Bei Approve werden Property-Meta + Featured/Galerie aus den eingehenden Daten übernommen. Bei Reject bleibt das Property unverändert. Plus Badge im Menü mit Anzahl pending Konflikte und Bulk-Reject-Aktion in der Liste.

## Anforderungen (User-Entscheidungen aus Brainstorming)

| # | Punkt | Wahl |
|---|---|---|
| 1 | UI-Standort | Eigenes Submenü „Immo Manager → OpenImmo-Konflikte" |
| 2 | Layout | WP-List-Table + Detail-Page via `?conflict_id=N` |
| 3 | Resolution-Granularität | Nur whole-conflict (`keep_local` / `take_import`) |
| 4 | Bulk-Actions | Nur Bulk-Reject (`keep_local`) — kein Bulk-Approve |
| 5 | Pending-Attachments | Featured + Galerie replace (incoming wins) |
| 6 | Notifications | Badge im Menü-Eintrag (Anzahl pending) |

## Architektur & Datenfluss

```
[Submenü-Klick]
       │
       ▼
[ConflictsAdminPage::render()]
       │
       ├─ ?conflict_id=N → render_detail( N )
       └─ sonst          → render_list()
       │
       ▼
[Detail-View Form-Submit]
       │
       ▼
[ConflictsAdminPage::maybe_handle_submit()]
       │
       ├─ Capability + Nonce check
       │
       ▼
[ConflictsResolver::resolve( $id, $resolution )]
       │
       ├─ resolution = 'keep_local':
       │     └─ Conflicts::resolve( $id, 'keep_local', $user_id )
       │     (Property bleibt unverändert)
       │
       └─ resolution = 'take_import':
             ├─ Property-Existenz prüfen (sonst auto-reject)
             ├─ Source-JSON parsen
             ├─ Foreach _immo_*-Felder → update_post_meta
             ├─ Pending-Attachments verknüpfen:
             │     ├─ alle bisherigen Galerie-Attachments post_parent=0
             │     ├─ TITELBILD → set_post_thumbnail
             │     └─ BILD → wp_update_post( post_parent=$post_id )
             └─ Conflicts::resolve( $id, 'take_import', $user_id )
       │
       ▼
   Redirect mit success-Notice
```

### Klassen

| Klasse | Verantwortung |
|---|---|
| `ConflictsAdminPage` | Submenü-Registration (mit Badge), Routing (Liste/Detail), Form-Handler (single + bulk) |
| `ConflictsListTable extends \WP_List_Table` | Spalten + Bulk-Action `bulk_reject` + Pagination |
| `ConflictsResolver` | Business-Logic: `resolve( $id, $resolution ): bool` |
| `Conflicts` (Phase 0, **erweitert**) | + `pending_count(): int` für Badge |

### Submenü-Routing

```
?page=immo-manager-openimmo-conflicts                  → Liste
?page=immo-manager-openimmo-conflicts&conflict_id=42   → Detail
```

Beide über `ConflictsAdminPage::render()` mit internem Switch.

## Klassen-Schnittstellen

### `ConflictsAdminPage`

```php
namespace ImmoManager\OpenImmo;

class ConflictsAdminPage {

	public const PAGE_SLUG = 'immo-manager-openimmo-conflicts';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init', array( $this, 'maybe_handle_submit' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_bulk_submit' ) );
	}

	public function register_submenu(): void;
	public function render(): void;
	public function maybe_handle_submit(): void;
	public function maybe_handle_bulk_submit(): void;

	private function render_list(): void;
	private function render_detail( int $conflict_id ): void;
	private function menu_label_with_badge(): string;
}
```

### `ConflictsListTable`

```php
namespace ImmoManager\OpenImmo;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ConflictsListTable extends \WP_List_Table {

	public function get_columns(): array;
	public function get_bulk_actions(): array;       // nur 'bulk_reject'
	public function prepare_items(): void;
	public function column_default( $item, $column_name );
	public function column_cb( $item ): string;
	public function column_property( $item ): string;
	public function column_changed_fields( $item ): string;
	public function no_items(): void;
}
```

**Spalten:** `cb`, `property`, `portal`, `changed_fields`, `created_at`.

**Bulk-Action:** nur `bulk_reject` → setzt `keep_local` für alle ausgewählten.

**Pagination:** 20 pro Seite, sortiert nach `created_at DESC`, nur `status='pending'`.

### `ConflictsResolver`

```php
namespace ImmoManager\OpenImmo;

use ImmoManager\PostTypes;

class ConflictsResolver {

	/**
	 * @param string $resolution 'keep_local' | 'take_import'
	 */
	public function resolve( int $conflict_id, string $resolution ): bool;

	private function fetch( int $conflict_id ): ?array;
	private function apply_import( int $post_id, string $source_json ): void;
	private function attach_pending_images( int $post_id, array $pending_attachments ): void;
}
```

**`apply_import()`-Flow:**
1. `$source = json_decode( $source_json, true )` — null-Check.
2. Foreach `_immo_*`-Schlüssel: `update_post_meta()` (analog Phase-3-Konvention „incoming wins where present").
3. `_immo_openimmo_pending_attachment_ids` aus `$source` parsen → `attach_pending_images()`.

**`attach_pending_images()`:**
1. Bestehende Galerie-Attachments via `get_attached_media('image', $post_id)` holen → alle `post_parent=0` setzen.
2. Foreach Pending mit `gruppe='TITELBILD'` (erstes nehmen) → `set_post_thumbnail($post_id, $att_id)`.
3. Foreach Pending mit `gruppe='BILD'` → `wp_update_post(['ID'=>$att_id, 'post_parent'=>$post_id])`.

### `Conflicts::pending_count()` (Phase-0-Erweiterung)

```php
public static function pending_count(): int {
	global $wpdb;
	$table = Database::conflicts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
}
```

## UI-Layout

### Liste

```
┌─────────────────────────────────────────────────────────────────┐
│ OpenImmo-Konflikte                                              │
├─────────────────────────────────────────────────────────────────┤
│ [Bulk-Aktion ▾] [Anwenden]                                      │
├─────────────────────────────────────────────────────────────────┤
│ ☐ │ Property            │ Portal │ Geänderte Felder    │ Erstellt│
├───┼─────────────────────┼────────┼─────────────────────┼─────────│
│ ☐ │ Wohnung Mariahilfer │ import │ _immo_address,      │ vor 2 h │
│   │ #wp-123             │        │ _immo_price,        │         │
│   │ Anzeigen │ Behalten │        │ ... (3 mehr)        │         │
│   │ │ Übernehmen        │        │                     │         │
└─────────────────────────────────────────────────────────────────┘
```

**Property-Spalte:** Titel-Link auf Detail + WP-Row-Actions „Anzeigen | Bestand behalten | Eingehende übernehmen". Resolution-Links sind `wp_nonce_url`-GET-Links.

**Bulk-Dropdown:** nur „Bestand behalten" (Frage 4).

**No-Items:** „Keine pending Konflikte. 🎉"

### Detail

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Zurück zur Liste                                              │
│ Konflikt #42                                                     │
│ Property: Wohnung Mariahilfer #wp-123  [Im Editor öffnen ↗]     │
│ Portal: import     Erstellt: vor 2 Stunden     Status: pending   │
├─────────────────────────────────────────────────────────────────┤
│ ┌──────────────────┬──────────────────┬──────────────────────┐ │
│ │ Feld             │ Bestand          │ Eingehend            │ │
│ ├──────────────────┼──────────────────┼──────────────────────┤ │
│ │ _immo_address    │ Mariahilfer 1    │ Mariahilfer Str. 1   │ │
│ │ _immo_price      │ 350000           │ 365000               │ │
│ │ _immo_features   │ balkon, garten   │ balkon, garten,      │ │
│ │                  │                  │ aufzug               │ │
│ └──────────────────┴──────────────────┴──────────────────────┘ │
│                                                                  │
│ Eingehende Bilder: 5 Attachments (1× TITELBILD, 4× BILD)        │
│                                                                  │
│ [Bestand behalten]   [Eingehende Daten übernehmen]              │
│  (Property unverändert)  (mit confirm() onclick)                 │
└─────────────────────────────────────────────────────────────────┘
```

**Diff-Tabelle:** Pro Feld eine Zeile, Werte via `esc_html`. Arrays via `implode(', ', ...)`. Lange Strings (>200 Zeichen) in `<details><summary>`.

**Approve/Reject:** Zwei Form-Buttons (POST mit Nonce). Confirm-Dialog auf „Eingehende übernehmen".

### Menü-Badge

`menu_label_with_badge()` returnt `'OpenImmo-Konflikte <span class="awaiting-mod count-3"><span class="pending-count">3</span></span>'` wenn pending > 0, sonst nur Label. Standard-WP-Pattern wie bei Comments-Pending-Count.

### Form-Handling-Patterns

| Action | Methode |
|---|---|
| Single Resolve (Detail-Form POST) | `<form method="post">` mit `wp_nonce_field( 'immo_openimmo_resolve_' . $id )` |
| Single Resolve (Row-Action GET) | `wp_nonce_url( admin_url(...&resolution=take_import), 'immo_openimmo_resolve_' . $id )` |
| Bulk Reject | WP-Standard `bulk-conflicts`-Nonce über `WP_List_Table` |

## Sicherheit & Edge-Cases

### Sicherheits-Pattern

| Action | Nonce-Action | Validierung |
|---|---|---|
| Single Resolve (POST) | `immo_openimmo_resolve_{id}` | `wp_verify_nonce( $_POST['_wpnonce'], ... )` |
| Single Resolve (GET) | `immo_openimmo_resolve_{id}` | `check_admin_referer( ... )` |
| Bulk Reject | `bulk-conflicts` | `check_admin_referer( 'bulk-conflicts' )` |

Capability `manage_options` Gate vor jedem Schreibvorgang.

### Edge-Cases

| Edge-Case | Behandlung |
|---|---|
| Conflict bereits resolved (Race-Condition) | `Resolver::resolve()` prüft `status === 'pending'`, returnt false → Notice „Bereits bearbeitet" |
| Property gelöscht | Vor `apply_import()` `get_post()` prüfen → auto-reject mit `keep_local`, Notice „Property nicht vorhanden" |
| Pending-Att-ID zeigt auf gelöschtes Attachment | In `attach_pending_images()` skip-on-not-found, kein Abbruch |
| Pending-Att-IDs invalides JSON | `json_decode` returnt null → loggen, Bilder skip, Meta-Update läuft trotzdem |
| Source-Data invalides JSON | Early return aus `apply_import()` ohne Änderungen, Conflict bleibt pending |
| Bulk-Reject leere Auswahl | silent return |

### Plugin-Wiring

```php
private $openimmo_conflicts_page = null;

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

Im bestehenden Init-Pattern (analog `get_openimmo_admin_page()`) im Plugin-Singleton-Init triggern, damit Constructor-Hooks registriert werden.

## Manuelle Verifikation

| Test | Vorgehen | Erwartet |
|---|---|---|
| PHP-Lint | alle neuen/modifizierten Dateien | „No syntax errors" |
| Plugin-Reaktivierung | Deaktivieren → reaktivieren | Kein Fatal |
| Submenü sichtbar | WP-Admin öffnen | „OpenImmo-Konflikte" mit Badge wenn pending |
| Badge | manuell pending Conflict in DB anlegen | Badge zeigt Anzahl |
| No items | 0 pending | Liste „Keine pending Konflikte. 🎉" |
| Liste mit Konflikten | 3 pending | List-Table mit 3 Zeilen |
| Detail-View | Klick „Anzeigen" | Diff-Tabelle aller `conflict_fields` |
| Diff-Array-Feld | Conflict mit `_immo_features`-Diff | Komma-Liste angezeigt |
| Lange Strings | `_immo_address` >200 Zeichen | `<details>`-Zusammenklapper |
| „Bestand behalten" Detail | Klick + Bestätigung | Conflict approved/keep_local; Property unverändert; Notice |
| „Eingehende übernehmen" Detail | Klick + Bestätigung | Property-Meta + Featured + Galerie ersetzt; Conflict approved/take_import |
| Featured-Replace | Property hatte A, Conflict bringt B → Approve | Featured ist B; A unverknüpft |
| Galerie-Replace | Galerie [X,Y] → [P,Q,R] | Galerie zeigt P,Q,R; X,Y haben post_parent=0 |
| Row-Actions | Klick auf „Bestand behalten" in Liste | gleicher Effekt wie Detail-Approve |
| Bulk-Reject | 5 Konflikte ankreuzen, Bulk-Aktion „Bestand behalten" | 5 Conflicts approved/keep_local; Notice „5 abgelehnt" |
| Bulk leer | keine Auswahl | silent |
| Race | Tab1 öffnet Detail, Tab2 löst auf, Tab1 klickt | Notice „Bereits bearbeitet" |
| Property gelöscht | Property löschen, dann Approve | auto-reject, Notice „Property nicht vorhanden" |
| Pending-Att-IDs invalid | Conflict mit kaputt-encoded IDs → take_import | Meta-Update läuft, Bilder werden skipped, kein Fatal |
| Capability | als Editor | „not allowed" |
| Nonce | direkter URL ohne Nonce | nicht ausgeführt |

## Out-of-Scope für Phase 5

- ❌ Pro-Feld-Merge — Phase 7 falls real benötigt
- ❌ Bulk-Approve — bewusst ausgeschlossen
- ❌ E-Mail-Notifications bei neuen Konflikten — Phase 7
- ❌ Admin-Notices außerhalb der Konflikt-Page — bewusst ausgeschlossen
- ❌ Auto-Cleanup alter resolved Conflicts — Phase 7
- ❌ Auto-Cleanup verwaister Attachments nach Reject — Phase 7
- ❌ Filter / Suche auf der Konflikt-Liste — Phase 7
- ❌ Sortierung außer `created_at DESC` — Phase 7
- ❌ History-View „resolved Conflicts" — Phase 7

## Datei-Übersicht

| Aktion | Datei |
|---|---|
| Create | `includes/openimmo/class-conflicts-admin-page.php` |
| Create | `includes/openimmo/class-conflicts-list-table.php` |
| Create | `includes/openimmo/class-conflicts-resolver.php` |
| Modify | `includes/openimmo/class-conflicts.php` (+ `pending_count()`) |
| Modify | `includes/class-plugin.php` (Lazy-Getter + Init-Trigger) |

5 Dateien — 3 neu, 2 modifiziert.

## Phasen-Kontext

Spec für `docs/superpowers/plans/2026-04-29-openimmo-phase-5.md`. Plan folgt nach User-Approval via `superpowers:writing-plans`.

Phasen-Übersicht:
- ✅ Phase 0 — Foundation
- ✅ Phase 1 — Export-Mapping
- ✅ Phase 2 — SFTP-Push-Transport
- ✅ Phase 3 — Import-Parsing
- ✅ Phase 4 — Import-Transport (SFTP-Pull)
- 👉 **Phase 5 — Konflikt-Behandlung (diese Spec)**
- ⬜ Phase 6 — Portal-Anpassungen / Adapter-Pattern
- ⬜ Phase 7 — Polish (Retention, E-Mail, README)
