# Immo Manager

WordPress-Plugin für Immobilien-Management mit OpenImmo-Schnittstelle (willhaben.at + ImmobilienScout24.at).

## Voraussetzungen

- WordPress 5.9+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- OpenImmo-1.2.7-XSD-Datei in `vendor-schemas/openimmo-1.2.7/openimmo.xsd`

## Installation

1. Plugin-Verzeichnis nach `wp-content/plugins/` kopieren.
2. `vendor-schemas/openimmo-1.2.7/openimmo.xsd` von openimmo.de herunterladen und ablegen.
3. Plugin im WP-Admin aktivieren — DB-Tabellen werden automatisch angelegt.
4. Einstellungen unter „Immo Manager → OpenImmo" konfigurieren.

## Cron-Hooks

Das Plugin schedulet 3 Cron-Hooks beim Aktivieren:

| Hook | Frequenz | Zweck |
|---|---|---|
| `immo_manager_openimmo_daily_sync` | täglich (Settings: `cron_time`) | Export + SFTP-Push pro aktivem Portal |
| `immo_manager_openimmo_hourly_pull` | stündlich | SFTP-Inbox-Check pro Portal mit `inbox_path` |
| `immo_manager_openimmo_daily_cleanup` | täglich 04:00 | Retention-Cleanup |

**Wichtig:** Bei Code-Updates Plugin einmal deaktivieren + reaktivieren, damit neue Hooks registriert werden.

## OpenImmo-Settings

Submenü „Immo Manager → OpenImmo":

### Allgemein
- **Schnittstelle aktiv:** globaler Master-Switch.
- **Cron-Zeit:** Uhrzeit für den täglichen Push (HH:MM).
- **Notification-E-Mail:** für Fehler-Benachrichtigungen. Leer = WP-Admin-E-Mail.

### Pro Portal (willhaben + immoscout24_at)
- **Aktiv:** Portal-Switch.
- **SFTP-Verbindung:** Host, Port, User, Passwort (verschlüsselt gespeichert).
- **Remote-Verzeichnis:** Push-Pfad (z.B. `/upload/`).
- **Inbox-Pfad:** Pull-Pfad (z.B. `/inbox/`). Leer = kein Pull.
- **Anbieter-Daten:** anbieternr, firma, openimmo_anid, lizenzkennung, regi_id, technische E-Mail, Anbieter-E-Mail, Anbieter-Telefon, Impressum.
- **Buttons:** SFTP-Verbindung testen, Letztes ZIP jetzt hochladen, Inbox jetzt prüfen, Jetzt exportieren.

## Property-Backend

Pro Property-Edit-Seite gibt's eine Sidebar-Box „OpenImmo-Export":
- Checkbox „willhaben.at" — Property in willhaben-Export aufnehmen.
- Checkbox „ImmobilienScout24.at" — analog.

Nur Properties mit `Status=verfügbar` UND mind. einem aktivierten Portal werden tatsächlich exportiert.

## Konflikt-Queue

Submenü „Immo Manager → OpenImmo-Konflikte" zeigt alle Listings, bei denen ein Import einen bestehenden Datensatz ändern würde:
- **Bestand behalten:** Property unverändert lassen.
- **Eingehende übernehmen:** Property-Meta + Bilder werden ersetzt.
- **Bulk-Reject:** mehrere Konflikte gleichzeitig auf „Bestand behalten" setzen.

## Retention

Hardcoded Defaults im täglichen Cleanup-Cron:
- Lokale Export-ZIPs: 60 Tage
- SFTP `processed/` und `error/`: 30 Tage
- Verwaiste Attachments aus rejected Conflicts: 14 Tage
- Resolved Conflicts: 90 Tage

## Test-Workflow

1. **Verbindung testen:** OpenImmo-Settings → „SFTP-Verbindung testen" pro Portal.
2. **Property anlegen:** Status auf „verfügbar", Adresse + Preis + Bilder. Eines der Portal-Häkchen setzen.
3. **Export testen:** OpenImmo-Settings → „Jetzt exportieren". Notice zeigt ZIP-Pfad + Listings-Count.
4. **ZIP inspizieren:** `wp-content/uploads/openimmo/exports/<portal>/<filename>.zip` öffnen → enthält `openimmo.xml` und `images/`.
5. **XSD validieren:** `xmllint --schema vendor-schemas/openimmo-1.2.7/openimmo.xsd <(unzip -p .../openimmo.xml)`.
6. **Upload testen:** „Letztes ZIP jetzt hochladen" → Datei erscheint im Portal-SFTP unter `<remote_path>/`.
7. **Pull testen:** ZIP in Portal-Inbox legen, „Inbox jetzt prüfen" → ZIP wird heruntergeladen, importiert, in `<inbox>/processed/` verschoben.

## Datenbank-Tabellen

| Tabelle | Zweck |
|---|---|
| `wp_immo_units` | Wohneinheiten innerhalb von Bauprojekten |
| `wp_immo_inquiries` | Kontaktanfragen |
| `wp_immo_sync_log` | Log aller Push/Pull/Import-Läufe |
| `wp_immo_conflicts` | Konflikt-Queue |

DB-Version: 1.5.0. Migrations laufen automatisch beim Plugin-Load.

## Phasen-Status

- ✅ Phase 0 — Foundation (DB, Settings, Cron-Stub)
- ✅ Phase 1 — Export-Mapping (Plugin → OpenImmo XML 1.2.7)
- ✅ Phase 2 — SFTP-Push-Transport
- ✅ Phase 3 — Import-Parsing (XML → Plugin)
- ✅ Phase 4 — SFTP-Pull-Cron + manueller Upload
- ✅ Phase 5 — Konflikt-Behandlung (Approval-UI)
- ⏸️ Phase 6 — Portal-Anpassungen (aufgeschoben, wartet auf willhaben/IS24-Doku)
- ✅ Phase 7 — Polish (Retention, E-Mail, README)

## Bekannte Limitierungen

- Phase 6 ist offen. Aktuell wird ein generisches OpenImmo-1.2.7-XML ohne portal-spezifische Anpassungen erzeugt. Bei willhaben/IS24-Validierungs-Fehlern → Konflikt mit Provider klären, ggf. Mapper-Anpassung in `includes/openimmo/export/class-mapper.php`.
- SSH-Key-Authentifizierung wird (noch) nicht unterstützt — nur Username + Passwort.
- Heizungsart wird als Freitext im Element `sonstige_heizungsart` exportiert, nicht als Enum-Mapping (Phase 6 wird das verfeinern).
- Kontaktname-Splitting via Whitespace — Firmennamen werden ungenau in vorname/nachname getrennt.
- Bulk-Approve in der Konflikt-Queue ist bewusst nicht implementiert (nur Bulk-Reject).

## Spec- und Plan-Doku

Architektur und Design pro Phase: `docs/superpowers/specs/`. Implementierungspläne: `docs/superpowers/plans/`.
