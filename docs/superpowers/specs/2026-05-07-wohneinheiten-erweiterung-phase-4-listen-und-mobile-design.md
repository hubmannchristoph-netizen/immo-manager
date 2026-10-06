# Wohneinheiten-Erweiterung — Phase 4: Listen-Anzeige & Mobile

**Status:** Implemented (2026-05-07)
**Datum:** 2026-05-07
**Voraussetzung:** Phasen 1–3 implementiert.
**Scope:** Die in Phase 1 angelegten Werte werden in der **Wohneinheits-Tabelle** sichtbar; Mobile-Breiten-Bug der Tabelle wird gefixt.

## Ziel

1. **Tabellen-Anreicherung:** Pro Zeile erscheinen die ausgefüllten Zusatz-Infos kompakt als Mini-Icon-Liste, damit der User Preisunterschiede zwischen Einheiten begründbar sieht (Manager + Client).
2. **Mobile-Bug-Fix:** Tabellen-Wrapper sprengt auf kleinen Viewports (~360–400px) das Layout. Das wird mit defensiven CSS-Patches behoben — Tabelle scrollt sauber innerhalb des Layouts statt das Layout zu sprengen.
3. **Design-Prinzip:** Tabelle bleibt Tabelle (keine Cards-Variante), wie vom User gewünscht.

## Out of Scope

- Cards-Layout statt Tabelle.
- Override-Preis-Eingabe-UI (Phase 3 Out-of-Scope; weiterhin DB-only).
- OpenImmo-Mapping der neuen Felder (Folge-Aufgabe).

## Lösungsstrategie

### A) Tabellen-Anreicherung

Pro Tabellen-Zeile wird unter der **Bezeichnungs-/Nr-Spalte** (Client) bzw. **Fläche-Spalte** (Manager) eine zweite Zeile mit kompakten Icon-Pills gerendert — nur für die Werte, die > 0 sind. Aussehen wie eine sekundäre Caption, kleinere Schrift, gedeckte Farbe.

**Pseudo-Markup (gleich für Manager + Client):**

```html
<td class="col-title">
  <strong>H1-3</strong>
  <span class="immo-unit-extras">
    <span class="immo-unit-extra" title="Balkon">🏔️ 8 m²</span>
    <span class="immo-unit-extra" title="Keller">🏚️ 5 m²</span>
    <span class="immo-unit-extra" title="Tiefgaragenplatz">🅿️ ×1</span>
  </span>
</td>
```

CSS:
```css
.immo-unit-extras { display: flex; flex-wrap: wrap; gap: 0.4em; margin-top: 0.3em; }
.immo-unit-extra  { font-size: 0.78rem; color: #6b7280; padding: 0.1em 0.45em; background: #f3f4f6; border-radius: 4px; }
```

### B) Mobile-Bug-Fix

**Diagnose-Strategie:** Auf statischer Code-Basis sind drei mögliche Ursachen identifiziert:

1. `.immo-detail-content` (Flex-Column-Container) hatte fehlendes `min-width:0` — wurde in der Vorab-Bug-Fix-Runde bereits gepatcht (`min-width:0`), reichte aber laut User noch nicht.
2. `.immo-accordion-body` und/oder `.immo-units-table-wrap` haben kein hartes `max-width:100%`.
3. `white-space: nowrap` auf `td`-Elementen in der Tabelle zwingt sie auf min-content-Breite — das ist OK, weil `.immo-units-table-wrap` `overflow-x:auto` hat — _solange_ die parents nicht zu breit werden.

**Fix-Pakete (defensive, zusätzlich zum bestehenden `min-width:0`):**

```css
/* Manager + Client: Tabellen-Container darf nie über sein Parent hinaus */
.immo-detail-content,
.immo-detail-content > .immo-accordion,
.immo-detail-content .immo-accordion-body,
.immo-detail-content .immo-units-table-wrap {
    max-width: 100%;
    min-width: 0;
}

/* Mobile: explizite Bündelung für sichere overflow-x:auto-Aktivierung */
@media (max-width: 768px) {
    .immo-units-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

Analog im Client-CSS für `.immo-project-singlecol .immo-units-list .immo-unit-table-wrapper`.

**Playwright-Diagnose:** _nicht_ in dieser Phase eingeplant, weil keine Live-URL vorhanden. Falls der defensive Patch das Problem nicht löst, kann der User die Live-URL nennen, dann wird per Playwright nachgemessen.

### C) Versions-Bump

Wohneinheiten-Erweiterung ist mit Phase 4 funktional komplett. Manager-Plugin geht von 1.1.0 → 1.2.0 (Minor-Version für die ganze Erweiterung), immo-client analog 1.1.0 → 1.2.0.

## Akzeptanzkriterien

1. Manager-Tabelle: Zeilen mit gesetzten Zusatzfeldern zeigen unter der Fläche eine Pills-Reihe mit den vorhandenen Extras (nur Werte > 0).
2. Client-Tabelle (Shortcode + Direct-Render): Zeilen mit Zusatzfeldern zeigen unter der Bezeichnung dieselben Pills.
3. Auf Mobile (Viewport 360–400px): Tabelle sprengt das Layout nicht mehr; horizontaler Scroll innerhalb des Wrappers funktioniert sauber.
4. Versions-Anzeige im WP-Plugin-Liste: Manager 1.2.0, Client 1.2.0.
5. Kein leeres Pills-Element wenn alle Zusatzfelder 0 sind.

## Offene Punkte

Keine.
