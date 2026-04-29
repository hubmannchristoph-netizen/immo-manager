# Finanzierungs- & Nebenkostenrechner — Design-Spec

**Datum:** 2026-04-28
**Status:** Approved — bereit für Implementierungsplan
**Branch:** feat/openimmo (oder neuer feat-Branch)
**Plugin:** immo-manager

---

## Ziel

Einen kombinierten **Nebenkosten- und Finanzierungsrechner** für Österreich auf der Property-Detailseite und der Bauprojekt-Seite einbauen. Die Rechner sind clientseitig (JS) und vollständig über die WordPress-Settings konfigurierbar. Sie ersetzen das frühere, im Git verlorene Feature und erweitern es um Sondertilgung und einen jährlichen Tilgungsplan.

## Nicht-Ziel

- Rechner für Mietverhältnisse (Mietnebenkosten / Strom-Schätzung) — nicht Teil dieses Specs.
- Server-seitige Berechnung über REST-API — nicht nötig, alles clientseitig.
- Speicherung der Rechner-Eingaben (z. B. pro User) — nicht im Scope.
- Mehrere Länder (DE/CH) — nur AT.

---

## Anforderungen — verbindlich (aus Brainstorming)

| # | Anforderung |
|---|---|
| R1 | Rechner wird auf Property-Detail- und Bauprojekt-Seite **als Akkordeon** eingebunden. |
| R2 | Bauprojekt: **ein** globales Akkordeon mit **Dropdown zur Unit-Auswahl** (nicht pro Card). |
| R3 | Finanzierungsrechner-Basis = **Kaufpreis + Nebenkosten** (gekoppelt). |
| R4 | Eigenkapital eingebbar in **€ und % umschaltbar**, Default 20 % (KIM-V). |
| R5 | Tilgungsplan als **jährliche Tabelle** (Jahr / Zinsen / Tilgung / Restschuld). |
| R6 | Sondertilgung pro Jahr eingebbar. |
| R7 | Settings „Volle“ Variante: alle Sätze + pro-Posten-Toggles + Notar-Pauschal-Modus + Finanz-Defaults + Tilgungsplan-Toggle. |
| R8 | Ein **gemeinsames** Akkordeon mit zwei Tabs (Nebenkosten / Finanzierung). |
| R9 | Provisionsfrei: globaler Settings-Toggle **plus** per-Property-Override (`_commission_free`). |
| R10 | Nur bei **Kauf** (`deal_type=sale`) UND `price > 0` rendern. |
| R11 | Berechnung **rein clientseitig** in JS, kein REST-Endpoint. |

---

## Architektur

### Komponentenkarte

```
includes/
├─ class-settings.php       [erweitert: Section "Rechner"]
├─ class-meta-fields.php    [erweitert: _commission_free Property-Meta]
├─ class-metaboxes.php      [erweitert: Checkbox "Provisionsfrei"]
├─ class-shortcodes.php     [erweitert: enqueue calculators.{js,css} + localize]
└─ class-rest-api.php       [erweitert: format_property liefert commission_free]

templates/parts/
└─ calculator.php           [NEU – Akkordeon mit zwei Tabs]

public/
├─ js/calculators.js        [NEU – Berechnung & DOM-Update]
└─ css/calculators.css      [NEU – Styling]

templates/
├─ property-detail.php             [erweitert]
└─ single-immo_mgr_project.php     [erweitert: Unit-Dropdown]
```

### Datenfluss

```
[Settings DB]
   │
   ▼
Settings::get_all()
   │
   ▼
class-shortcodes.php::enqueue_assets()
   │ wp_localize_script('immo-manager-calculators', 'immoManager.calc', {...})
   ▼
[window.immoManager.calc]   ◄── Defaults & globale Toggles
   │
   ▼
calculator.php (Akkordeon-Template, in Property/Project eingebunden)
   │ data-attributes: basePrice, commissionFree, units (JSON für Project)
   ▼
calculators.js
   │ liest Inputs + Settings, berechnet, schreibt Outputs
   ▼
[Live-Update DOM]
```

### Verantwortlichkeiten je Komponente

| Komponente | Tut | Tut nicht |
|---|---|---|
| `Settings` | Defaults speichern, Felder rendern, Sanitize | UI-Berechnung |
| `calculator.php` | HTML-Skeleton (Tabs, Inputs, Output-Boxen, Plan-Tabelle), Localize-Daten in DOM | Rechnen |
| `calculators.js` | Nebenkosten + Annuität + Tilgungsplan + Tab-Switch + Unit-Switch + DOM-Update + Currency-Formatting | DOM-Markup erzeugen (kommt aus PHP) |
| `calculators.css` | Layout, Tab-Buttons, Plan-Tabelle, Mobile | Logik |
| `Metaboxes`/Wizard | Property-Override „Provisionsfrei“ | Settings-Defaults |

---

## Settings-Schema

### Neue Defaults in `Settings::get_defaults()`

```php
// === RECHNER ===
'calc_enable_costs'           => 1,        // Nebenkostenrechner aktiv
'calc_enable_financing'       => 1,        // Finanzierungsrechner aktiv

// Nebenkosten-Sätze (in %)
'calc_grest_rate'             => 3.5,      // Grunderwerbsteuer
'calc_grundbuch_rate'         => 1.1,      // Grundbucheintragung
'calc_notar_rate'             => 2.5,      // Notar/Treuhänder
'calc_provision_rate'         => 3.0,      // Maklerprovision
'calc_ust_rate'               => 20.0,     // USt auf Provision

// Pro-Posten-Toggles (global)
'calc_show_grest'             => 1,
'calc_show_grundbuch'         => 1,
'calc_show_notar'             => 1,
'calc_show_provision'         => 1,        // global, per Property überschreibbar
'calc_show_ust_on_provision'  => 1,

// Notar-Modus
'calc_notar_mode'             => 'percent',// 'percent' | 'flat'
'calc_notar_flat'             => 1500,     // Pauschal-€

// Finanzierung
'calc_default_equity_pct'     => 20,       // % vom Kaufpreis
'calc_default_interest_rate'  => 3.5,      // % p.a.
'calc_default_term_years'     => 25,       // Jahre
'calc_default_extra_payment'  => 0,        // €/Jahr Sondertilgung
'calc_show_amortization_table'=> 1,        // Tilgungsplan-Tabelle ein/aus
```

### Sanitize-Regeln (in `Settings::sanitize()`)

| Feld | Caps |
|---|---|
| `calc_*_rate` | 0.0 – 50.0 (float, 2 Dezimalen) |
| `calc_ust_rate` | 0.0 – 30.0 |
| `calc_default_equity_pct` | 0 – 100 (int) |
| `calc_default_interest_rate` | 0.0 – 20.0 |
| `calc_default_term_years` | 1 – 50 (int) |
| `calc_default_extra_payment` | 0 – 1.000.000 (int €) |
| `calc_notar_flat` | 0 – 100.000 (int €) |
| `calc_notar_mode` | enum `percent` \| `flat` |
| Alle `calc_show_*`, `calc_enable_*` | 0/1 |

### Localize-Payload `immoManager.calc`

```js
window.immoManager.calc = {
  enabled: { costs: true, financing: true },
  rates:   { grest: 3.5, grundbuch: 1.1, notar: 2.5, provision: 3.0, ust: 20.0 },
  show:    { grest: true, grundbuch: true, notar: true, provision: true, ust: true },
  notar:   { mode: 'percent', flat: 1500 },
  finance: { equityPct: 20, interestRate: 3.5, termYears: 25, extraPayment: 0,
             showAmortization: true },
  currency:{ symbol: '€', position: 'after', decimals: 0,
             thousands: '.', decimal: ',' },
  i18n:    { /* DE-Labels für JS-Output */ },
};
```

---

## Property-Meta — neuer Override

```php
'_commission_free' => bool   // 0/1
```

- Sichtbar im Wizard Schritt 4 (Preis) als Checkbox „Provisionsfrei“ — nur bei `deal_type=sale`.
- Gleicher Toggle in der Property-Metabox.
- REST-API liefert `meta.commission_free` aus `format_property()`.
- Wirkung: Provision (und USt-auf-Provision) wird nicht in den Nebenkosten-Posten aufgenommen, auch wenn der globale Settings-Toggle `calc_show_provision = 1` ist.

---

## UI-Layout

### Property-Detailseite

Akkordeon wird zwischen „Ausstattung" und „Lage" eingefügt — nur bei `deal_type=sale` UND `price > 0`.

```
┌─────────────────────────────────────────────────────────────┐
│  💰 Finanzierungs- & Nebenkostenrechner            [▼ ▲]   │
├─────────────────────────────────────────────────────────────┤
│   ┌──────────────┬──────────────────┐                       │
│   │ Nebenkosten  │   Finanzierung   │                       │
│   └──────────────┴──────────────────┘                       │
│   [TAB-INHALT WECHSELT JE NACH AUSWAHL]                    │
│   Hinweis: "Unverbindliche Schätzung – keine Beratung."    │
└─────────────────────────────────────────────────────────────┘
```

### Tab 1 — Nebenkostenrechner

```
Kaufpreis:  [ 350.000  ] €

────────────────────────────────────
Grunderwerbsteuer        3,5 %    12.250 €
Grundbucheintragung      1,1 %     3.850 €
Notar/Treuhand           2,5 %     8.750 €
Maklerprovision          3,0 %    10.500 €
+ USt auf Provision     20,0 %     2.100 €
────────────────────────────────────
Nebenkosten gesamt              37.450 €
Gesamtkosten (inkl. Kauf)      387.450 €
```

- Kaufpreis-Input ist editierbar (User-Durchspielen). Default = Property-Preis.
- Posten mit Settings-Toggle `false` werden ausgeblendet.
- Provision ausgeblendet bei `_commission_free = true`.
- Im Notar-Modus `flat` zeigt die Zeile keinen Prozentwert, nur den Pauschal-€-Betrag.

### Tab 2 — Finanzierungsrechner

```
Finanzierungsbedarf (Kauf + Nebenkosten):    387.450 €  [auto, read-only]

Eigenkapital:    [○ €]  [● %]                 ← Toggle-Pills
                 [   20  ] %    →   77.490 €  [berechnete Anzeige]

Kreditsumme:                                   309.960 €  [auto]
Zinssatz:        [  3,5  ] % p.a.
Laufzeit:        [   25  ] Jahre
Sondertilgung:   [    0  ] €/Jahr

────────────────────────────────────
   Monatliche Rate           1.551,42 €
   Gesamt-Zinsen            155.467 €
   Gesamtaufwand            465.427 €
   Tilgung-Ende             2051
────────────────────────────────────

[▼ Tilgungsplan anzeigen]    ← Sub-Akkordeon (nur wenn Setting aktiv)

   Jahr │ Zinsen │ Tilgung │ Restschuld
   2026 │ 10.732 │  7.881  │ 302.079
   2027 │ 10.450 │  8.163  │ 293.916
   ...
```

### Bauprojekt-Seite

Identisches Akkordeon, aber zusätzlich Unit-Dropdown ganz oben:

```
┌────────────────────────────────────────────────────────────┐
│  💰 Finanzierungs- & Nebenkostenrechner            [▼ ▲]  │
├────────────────────────────────────────────────────────────┤
│  Wohneinheit:  [ Top 3 — 85 m² — 425.000 € ▾ ]            │
│  ────────────────────────────────────────────              │
│  [Tabs Nebenkosten / Finanzierung wie oben]                │
└────────────────────────────────────────────────────────────┘
```

- Dropdown listet nur Kauf-Units (`unit.price > 0`).
- Auswahl ändert sofort den Kaufpreis in beiden Tabs.
- Akkordeon erscheint nur, wenn ≥1 Kauf-Unit existiert.

---

## Berechnungs-Logik

### Nebenkosten

```js
function calcCosts(price, settings, propertyOverrides) {
  const items = [];
  const r = settings.rates;
  const s = settings.show;

  if (s.grest)     items.push({ key:'grest',    label:'Grunderwerbsteuer',  rate:r.grest,    amount: price * r.grest    / 100 });
  if (s.grundbuch) items.push({ key:'grundbuch',label:'Grundbucheintragung',rate:r.grundbuch,amount: price * r.grundbuch / 100 });

  if (s.notar) {
    const notarAmount = settings.notar.mode === 'flat'
      ? settings.notar.flat
      : price * r.notar / 100;
    items.push({ key:'notar', label:'Notar/Treuhand',
                 rate: settings.notar.mode === 'flat' ? null : r.notar,
                 amount: notarAmount });
  }

  // Provision: globaler Toggle UND nicht "_commission_free" an der Property
  if (s.provision && !propertyOverrides.commissionFree) {
    const provision = price * r.provision / 100;
    items.push({ key:'provision', label:'Maklerprovision', rate:r.provision, amount: provision });
    if (s.ust) {
      items.push({ key:'ust', label:'USt auf Provision', rate:r.ust,
                   amount: provision * r.ust / 100 });
    }
  }

  const total = items.reduce((a, i) => a + i.amount, 0);
  return { items, total, grandTotal: price + total };
}
```

### Finanzierung — Annuitätenformel

```
m = (K · i) / (1 - (1 + i)^(-n))

K = Kreditsumme
i = monatlicher Zinssatz = Jahreszins / 12 / 100
n = Anzahl Monate = Laufzeit · 12
m = monatliche Rate
```

**Edge Case Zinssatz 0 %:** `m = K / n` (lineare Tilgung).

**Sondertilgung:** Am Ende jedes Jahres wird `extraPayment` auf die Restschuld angerechnet (verkürzt Laufzeit). Monatsrate bleibt gleich.

### Tilgungsplan (jährlich aggregiert)

```js
function buildAmortization(K, annualRate, termYears, extraPerYear) {
  const i = annualRate / 12 / 100;
  const n = termYears * 12;
  const m = i === 0 ? K / n : (K * i) / (1 - Math.pow(1 + i, -n));

  const rows = [];
  let balance = K;
  let totalInterest = 0;

  for (let year = 1; year <= termYears; year++) {
    let yearInterest = 0;
    let yearPrincipal = 0;
    for (let month = 1; month <= 12; month++) {
      if (balance <= 0.01) break;
      const interestPart = balance * i;
      let principalPart = m - interestPart;
      if (principalPart > balance) principalPart = balance;
      balance -= principalPart;
      yearInterest += interestPart;
      yearPrincipal += principalPart;
    }
    if (extraPerYear > 0 && balance > 0) {
      const extra = Math.min(extraPerYear, balance);
      balance -= extra;
      yearPrincipal += extra;
    }
    totalInterest += yearInterest;
    rows.push({
      year: new Date().getFullYear() + year - 1,
      interest: yearInterest,
      principal: yearPrincipal,
      balance: Math.max(0, balance),
    });
    if (balance <= 0.01) break;
  }
  return { rate: m, rows, totalInterest, totalPayments: K + totalInterest };
}
```

---

## Edge Cases

| Fall | Verhalten |
|---|---|
| `price === 0` oder fehlt | Akkordeon wird **nicht gerendert** (PHP-Bedingung). |
| `deal_type === 'rent'` | Akkordeon wird **nicht gerendert**. |
| `_commission_free === true` | Provision + USt-auf-Provision ausgeblendet. |
| Settings-Toggle `calc_show_X = 0` | Posten X global ausgeblendet. |
| `calc_enable_costs = 0` AND `calc_enable_financing = 0` | Akkordeon wird **nicht gerendert**. |
| Nur einer der beiden Rechner aktiv | Tab-Bar entfällt; nur der eine Inhalt sichtbar. |
| Eigenkapital ≥ Finanzierungsbedarf | Kreditsumme = 0; „Keine Finanzierung nötig". |
| Zinssatz 0 % | Lineare Tilgung statt Annuität. |
| Sondertilgung > Restschuld | Wird auf Restschuld gekappt. |
| Bauprojekt mit 0 Kauf-Units | Akkordeon wird **nicht gerendert**. |
| User editiert Kaufpreis im Tab „Nebenkosten" | Finanzierungstab nutzt automatisch den neuen Wert. |
| Settings inkonsistent (z. B. `notar_mode='flat'` + `notar_flat=0`) | Posten zeigt 0 €, kein Crash. |

### Input-Validierung (clientseitig)

- `min=0` für alle Zahlen-Inputs.
- Eigenkapital % gekappt 0–100.
- Zinssatz gekappt 0–20.
- Laufzeit gekappt 1–50 Jahre.
- Bei Invalid: letzter gültiger Wert behalten, Feld kurz hervorgehoben.

---

## Phasen-Plan (Übersicht — Detail kommt im Implementierungsplan)

| Phase | Inhalt | Akzeptanz |
|---|---|---|
| **1 — Settings-Fundament** | Defaults, Section „Rechner", Felder, Sanitize, Notar-Modus-Toggle | Settings-Seite zeigt neue Sektion; Werte speichern + Reload OK |
| **2 — Property-Meta `_commission_free`** | Meta-Key, Wizard Schritt 4 + Metabox, REST-Output | Im Wizard ankreuzbar, REST liefert `meta.commission_free` |
| **3 — Calculator-Template & Assets-Skeleton** | `calculator.php`, `calculators.css`, `calculators.js` (nur Tab-Switch) | Akkordeon rendert leer; Tabs schalten um |
| **4 — Nebenkosten-Logik** | `calcCosts()`, Posten-Toggles, Notar-Modus, Provisionsfrei-Override | Eingabe Kaufpreis → korrekte Posten + Summen live |
| **5 — Finanzierungs-Logik** | `buildAmortization()`, EK €/% Toggle, Annuität + 0%-Sonderfall, Sondertilgung, Plan-Tabelle | Rate, Gesamtzinsen, Endjahr, Plan-Tabelle korrekt |
| **6 — Property-Detail-Integration** | Akkordeon einhängen, enqueue-Bedingung, Render-Conditions | Live auf Test-Kauf-Property; kein Akkordeon bei Miete |
| **7 — Bauprojekt-Integration** | Unit-Dropdown, JS reagiert auf Unit-Wechsel | Dropdown nur Kauf-Units; Wechsel → Preis + Berechnung neu |
| **8 — Polish & Edge Cases** | Disclaimer, Currency-Formatting, Validierungs-Caps, Mobile-Check | Alle Edge Cases verifiziert |

---

## Akzeptanz-Kriterien (gesamt)

1. Auf einer Kauf-Property mit Preis erscheint das Akkordeon zwischen „Ausstattung" und „Lage".
2. Bei Miete oder Preis = 0 erscheint kein Akkordeon.
3. Beide Tabs zeigen korrekt berechnete Werte gemäß den AT-Default-Sätzen.
4. Eigenkapital lässt sich zwischen € und % umschalten und beeinflusst die Kreditsumme korrekt.
5. Sondertilgung verkürzt Laufzeit und reduziert Gesamtzinsen sichtbar.
6. Tilgungsplan-Tabelle zeigt korrekte Jahres-Aggregate; letzte Restschuld = 0.
7. In den Settings sind alle Sätze, Toggles, Notar-Modus und Finanz-Defaults persistent änderbar.
8. Im Property-Wizard/Metabox-„Provisionsfrei" ankreuzbar; ausgekreuzt blendet Provision korrekt im Rechner aus.
9. Auf Bauprojekt-Seite mit ≥1 Kauf-Unit erscheint das Akkordeon mit Unit-Dropdown; Unit-Wechsel aktualisiert beide Tabs sofort.
10. Currency-Formatting folgt den globalen Settings (Position, Dezimalen, Trennzeichen).

---

## Out of Scope

- Mietnebenkosten-Rechner (BK + Strom/Gas-Schätzung).
- Rechner für DE/CH-Sätze.
- Speichern der User-Eingaben pro Session/User.
- Export der Tilgungstabelle als PDF/CSV.
- Rechner über Shortcode auf beliebigen Seiten.
