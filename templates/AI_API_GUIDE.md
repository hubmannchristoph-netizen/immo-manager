# Immo Manager – REST API für KI-Systeme

Diese Dokumentation richtet sich an KI-Systeme und automatisierte Agenten, die mit dem **Immo Manager** Plugin interagieren möchten, um Immobilieninformationen abzurufen, zu filtern oder Anfragen zu stellen. Das Plugin bietet eine robuste REST-API, die eine nahtlose Integration ermöglicht.

---

## 1. API-Grundlagen für KI-Interaktion

### Basis-URL
Alle API-Endpunkte sind unter dem Namespace `/wp-json/immo-manager/v1/` der WordPress-Installation erreichbar.

**Beispiel:** `https://your-wordpress-domain.com/wp-json/immo-manager/v1/properties`

### Authentifizierung
*   **GET-Endpunkte (Lesen):** Erfordern **keine Authentifizierung** und sind öffentlich zugänglich.
*   **POST-Endpunkte (Schreiben):** Für Aktionen wie das Einreichen von Anfragen (`/inquiries`) ist ein **API-Key erforderlich**, *falls im Plugin-Backend konfiguriert*.

    Der API-Key muss im HTTP-Header mitgesendet werden:
    `X-Immo-API-Key: DEIN_API_KEY_HIER`

    *Hinweis für die KI:* Der API-Key wird im Plugin-Backend unter `Immo Manager > Einstellungen > REST API & Headless-Betrieb` generiert. Er ist nach der Generierung nur einmalig vollständig sichtbar. Stelle sicher, dass dieser Key sicher gespeichert und verwendet wird.

### CORS (Cross-Origin Resource Sharing)
Wenn deine KI-Anwendung von einer anderen Domain als der WordPress-Installation aus auf die API zugreift, muss die Domain deiner KI-Anwendung in den Plugin-Einstellungen unter `Immo Manager > Einstellungen > REST API & Headless-Betrieb > Erlaubte Origins (CORS)` eingetragen werden. Alternativ kann `*` für den Zugriff von jeder Domain eingestellt werden (für Entwicklungsumgebungen).

---

## 2. Abrufen von Immobilien- und Projektdaten (GET-Endpunkte)

KI-Systeme können diese Endpunkte nutzen, um Immobilienportale zu erstellen, Suchagenten zu betreiben, dynamische Inhaltsgenerierung vorzunehmen oder Datenanalysen durchzuführen.

### `GET /properties`
Listet alle Immobilien. Ideal für die initiale Befüllung einer Datenbank oder die Filterung.

**Parameter für die KI:**
*   `page` (int): Seitenzahl (Standard: 1).
*   `per_page` (int): Immobilien pro Seite (Standard: 12, Max: 50).
*   `orderby` (string): Sortierung (`newest`, `price_asc`, `price_desc`, `area_desc`).
*   `status` (string): `available`, `reserved`, `sold`, `rented` (kommagetrennt möglich).
*   `mode` (string): `sale`, `rent`, `both`.
*   `type` (string): Immobilientyp (z.B. `Wohnung`, `Einfamilienhaus`, `Grundstück`).
*   `region_state` (string): Bundesland-Key (z.B. `wien`, `steiermark`).
*   `region_district` (string): Bezirks-Key (z.B. `1010`, `graz_stadt`).
*   `price_min`, `price_max` (number): Preisspanne.
*   `area_min`, `area_max` (number): Flächenspanne (in m²).
*   `rooms` (string): Zimmeranzahl (kommagetrennt, z.B. `2,3`).
*   `energy_class` (string): Energieklasse (z.B. `A`, `B`, `C`).
*   `project_id` (int): Zeigt nur Immobilien, die diesem Bauprojekt zugeordnet sind.

**KI-Anwendungsfall:** Ein KI-Agent könnte Immobilien abfragen, die "Verfügbar", "Zum Verkauf" stehen, in "Wien" liegen und "zwischen 300.000 und 500.000 EUR" kosten, um sie einem interessierten Nutzer vorzuschlagen.

**Beispiel-Anfrage (JavaScript `fetch`):**
```javascript
fetch('https://your-domain.com/wp-json/immo-manager/v1/properties?mode=sale&region_state=wien&price_max=500000')
  .then(response => response.json())
  .then(data => {
    // KI-Verarbeitung der Immobiliendaten
    console.log(data.properties); 
  });
```

### `GET /properties/{id}`
Ruft vollständige Details einer spezifischen Immobilie ab.

**KI-Anwendungsfall:** Eine KI kann diese Details nutzen, um Exposés zu generieren, FAQs zu beantworten oder Immobilienbeschreibungen zu paraphrasieren.

### `GET /projects`
Listet alle Bauprojekte.

### `GET /projects/{id}`
Ruft Details eines spezifischen Bauprojekts ab. Beinhaltet auch `unit_stats` für die Verfügbarkeit von Wohneinheiten.

### `GET /projects/{id}/units`
Listet alle Wohneinheiten eines Bauprojekts auf.

**Response Highlights für KI:** Wenn eine Wohneinheit mit einer eigenständigen Immobilie verknüpft ist, enthält die Response ein `property`-Objekt mit Quick-Info-Daten (Titel, Permalink, Bild, Preis, Typ, Fläche, Zimmer, Etage, Baujahr, Energieklasse). Dies erlaubt der KI, direkt auf die Details der verknüpften Immobilie zuzugreifen, ohne einen zusätzlichen API-Call.

**KI-Anwendungsfall:** Ein KI-Assistent kann die Verfügbarkeit von Einheiten prüfen und bei Bedarf auf das vollständige Exposé der verknüpften Einzelimmobilie verweisen.

---

## 3. Referenzdaten & Einstellungen für KI

Diese Endpunkte helfen der KI, das System besser zu verstehen und konsistente Ausgaben zu generieren.

### `GET /regions`
Liefert eine Liste aller österreichischen Bundesländer.

### `GET /regions/{state}/districts`
Liefert alle Bezirke eines spezifischen Bundeslandes.

**KI-Anwendungsfall:** KI kann diese Listen nutzen, um Standort-Filter in einer Benutzerschnittstelle zu befüllen oder Benutzereingaben zu validieren.

### `GET /features`
Gibt alle verfügbaren Ausstattungsmerkmale (z.B. `Balkon`, `Garage`) inklusive ihrer Kategorien und Icons zurück.

**KI-Anwendungsfall:** Eine KI kann diese Daten verwenden, um Immobilienbeschreibungen mit Icons anzureichern, Ausstattungsmerkmale zu filtern oder Nutzereingaben zu überprüfen.

### `GET /settings/public`
Stellt öffentliche Plugin-Einstellungen bereit (Währung, Trennzeichen, Farben, Karteneinstellungen).

**KI-Anwendungsfall:** Ermöglicht der KI, Finanzdaten korrekt zu formatieren oder visuelle Elemente (z.B. Primärfarbe für Buttons) in einer generierten UI zu verwenden.

---

## 4. Einreichen von Anfragen (POST-Endpunkt)

### `POST /inquiries`
Sendet eine neue Kontaktanfrage für eine Immobilie oder Wohneinheit.

**Anforderung für die KI:**
*   HTTP-Methode: `POST`
*   `Content-Type`: `application/json`
*   **Erforderlich:** `X-Immo-API-Key` im Header, falls im Backend aktiviert.

**Body-Payload (JSON) für die KI:**
| Feld              | Typ     | Erforderlich | Beschreibung                                     |
| :---------------- | :------ | :----------- | :----------------------------------------------- |
| `property_id`     | `int`   | Ja           | Die ID der angefragten Immobilie.                |
| `unit_id`         | `int`   | Nein         | Optional: Die ID der angefragten Wohneinheit (wenn es ein Bauprojekt ist). |
| `inquirer_name`   | `string`| Ja           | Name des Interessenten.                          |
| `inquirer_email`  | `string`| Ja           | E-Mail-Adresse des Interessenten.                |
| `inquirer_phone`  | `string`| Nein         | Telefonnummer des Interessenten.                 |
| `inquirer_message`| `string`| Nein         | Die Nachricht des Interessenten.                 |
| `consent`         | `boolean`| Ja           | Muss `true` sein, um Datenschutz-Einwilligung zu signalisieren. |

**KI-Anwendungsfall:** Eine Chatbot-KI könnte die Daten eines Nutzers sammeln und diese Anfrage dann automatisiert über diesen Endpunkt absenden, um einen Lead für den Makler zu generieren.

**Beispiel-Anfrage (cURL für KI-Backends):**
```bash
curl -X POST \
  https://your-domain.com/wp-json/immo-manager/v1/inquiries \
  -H 'Content-Type: application/json' \
  -H 'X-Immo-API-Key: DEIN_SICHERER_API_KEY_HIER' \
  -d '{
    "property_id": 123,
    "inquirer_name": "KI-Agent Bob",
    "inquirer_email": "bob.ai@example.com",
    "inquirer_phone": "+43123456789",
    "inquirer_message": "Als KI-Assistent habe ich großes Interesse an dieser Immobilie und würde gerne einen Besichtigungstermin vereinbaren. Bitte kontaktieren Sie mich.",
    "consent": true
  }'
```

---

## 5. Empfohlene KI-Anwendungen

*   **Intelligente Immobiliensuche:** KI-Agenten, die Benutzerpräferenzen lernen und Immobilien anhand von komplexen Kriterien dynamisch filtern und vorschlagen.
*   **Automatisierte Exposé-Erstellung:** Generierung von Kurz- oder Langexposés basierend auf den API-Details.
*   **Chatbots / Virtuelle Assistenten:** Beantwortung von Benutzerfragen zu Immobilien, Verfügbarkeit und Preisen.
*   **Lead-Generierung:** Automatische Erfassung von Interessentendaten und Einreichung als Anfrage an den Makler.
*   **Marktanalyse:** Sammeln von Preis-, Flächen- und Ausstattungsdaten für Trendanalysen.
*   **Content-Optimierung:** KI-basierte Überarbeitung oder Erweiterung von Immobilienbeschreibungen.

---

Diese Dokumentation dient als vollständige Referenz, um eine nahtlose und effektive Integration von KI-Systemen mit dem Immo Manager Plugin zu ermöglichen.