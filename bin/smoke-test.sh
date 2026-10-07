#!/usr/bin/env bash
# Smoke-Test: baut das Plugin-ZIP, installiert es in einer frischen WordPress-Instanz
# (Docker: wordpress:php8.2-apache + mariadb:11 + wordpress:cli-php8.2) und prüft
# Aktivierung, DB-Tabellen, Demo-Import, Frontend-Seiten, Shortcodes, REST-API,
# bedarfsgesteuertes Asset-Loading, Security-Fix (notify_email), Admin-Seiten,
# Datenerhalt bei Re-Installation/Uninstall und Opt-in-Löschung.
#
# Aufruf (Repo-Root):  bash bin/smoke-test.sh        (KEEP=1 lässt die Container laufen)
# Voraussetzungen:     Docker läuft, Port 8089 frei, python + curl im PATH.
set -u
export MSYS_NO_PATHCONV=1
# Repo-Root relativ zum Skript; fuer docker cp unter Git Bash in Windows-Notation.
REPO="$(cd "$(dirname "$0")/.." && pwd)"
command -v cygpath >/dev/null 2>&1 && REPO="$(cygpath -m "$REPO")"
VERSION="$(sed -n 's/^ \* Version: *//p' "$REPO/immo-manager.php" | tr -d '
 ')"
ZIP="immo-manager-$VERSION.zip"
echo "== ZIP bauen ($ZIP) =="
python "$REPO/bin/build-zip.py" || { echo "Build fehlgeschlagen"; exit 1; }
NET=immo-test
PORT=8089
PASS=0; FAIL=0
ok()   { echo "  [OK]   $1"; PASS=$((PASS+1)); }
fail() { echo "  [FAIL] $1"; FAIL=$((FAIL+1)); }
check() { # check <desc> <cmd...>
  local d="$1"; shift
  if "$@" >/dev/null 2>&1; then ok "$d"; else fail "$d"; fi
}

cleanup() {
  docker rm -f immo-wp immo-db >/dev/null 2>&1
  docker network rm $NET >/dev/null 2>&1
}
cleanup
docker network create $NET >/dev/null

echo "== Start MariaDB + WordPress =="
docker run -d --name immo-db --network $NET \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp \
  mariadb:11 >/dev/null
docker run -d --name immo-wp --network $NET -p $PORT:80 \
  -e WORDPRESS_DB_HOST=immo-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wordpress \
  -e WORDPRESS_DEBUG=1 \
  -e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false); define('SCRIPT_DEBUG', false);" \
  wordpress:php8.2-apache >/dev/null

WP() { docker run --rm --network $NET --volumes-from immo-wp --user 33:33 -e HOME=/tmp -e WORDPRESS_DB_HOST=immo-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wordpress -e WORDPRESS_DEBUG=1 -e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false);" wordpress:cli-php8.2 wp "$@"; }
export -f WP
export NET

echo "== Warte auf DB =="
for i in $(seq 1 40); do
  if docker exec immo-db mariadb -uwp -pwp -e "SELECT 1" wordpress >/dev/null 2>&1; then break; fi
  sleep 2
done
# wp-config wird vom WP-Entrypoint beim ersten Container-Start erzeugt
for i in $(seq 1 30); do
  if docker exec immo-wp test -f /var/www/html/wp-config.php; then break; fi
  sleep 2
done

echo "== WordPress installieren =="
WP core install --url=http://localhost:$PORT --title="Immo Test" --admin_user=admin --admin_password=admin123 --admin_email=admin@example.com --skip-email >/dev/null 2>&1 \
  && ok "wp core install" || fail "wp core install"
WP rewrite structure '/%postname%/' --hard >/dev/null 2>&1
WP option update timezone_string Europe/Vienna >/dev/null 2>&1

echo "== Plugin aus ZIP installieren + aktivieren =="
docker cp "$REPO/$ZIP" immo-wp:/var/www/html/wp-content/$ZIP
docker exec immo-wp chown 33:33 /var/www/html/wp-content/$ZIP
WP plugin install /var/www/html/wp-content/$ZIP --activate 2>&1 | tail -2
check "Plugin aktiv" bash -c "WP plugin list --status=active --field=name | tr -d '
' | grep -qx immo-manager"
WP plugin list --name=immo-manager --fields=name,status,version 2>/dev/null

# Settings-Option anlegen (wird sonst erst beim Speichern des Settings-Formulars geschrieben)
WP eval 'update_option("immo_manager_settings", ["items_per_page" => 9, "currency_symbol" => "EUR"]); echo "settings written
";' 2>&1 | tail -1
echo "== DB-Tabellen =="
TABLES=$(WP db tables --all-tables 2>/dev/null | tr -d '\r')
for t in wp_immo_units wp_immo_inquiries wp_immo_sync_log wp_immo_conflicts; do
  echo "$TABLES" | grep -qx "$t" && ok "Tabelle $t" || fail "Tabelle $t"
done
DBV="$(grep -o "DB_VERSION = '[0-9.]*'" "$REPO/includes/class-database.php" | grep -o "[0-9][0-9.]*[0-9]" | head -1)"
check "DB-Version Option = $DBV" bash -c "WP option get immo_manager_db_version | grep -q \"$DBV\""
check "Cron daily_sync geschedult" bash -c "WP cron event list --fields=hook | grep -q immo_manager_openimmo_daily_sync"

echo "== Demo-Daten importieren =="
WP eval 'add_filter("pre_http_request", function(){ return new WP_Error("blocked","no network in test"); }); $r = (new \ImmoManager\DemoData())->import(); echo json_encode($r);' 2>&1 | tail -1; echo
PROPS=$(WP post list --post_type=immo_mgr_property --post_status=publish --format=count 2>/dev/null | tr -d '\r')
PROJS=$(WP post list --post_type=immo_mgr_project --post_status=publish --format=count 2>/dev/null | tr -d '\r')
UNITS=$(WP db query "SELECT COUNT(*) FROM wp_immo_units" --skip-column-names 2>/dev/null | tr -d '\r')
echo "  Properties=$PROPS Projekte=$PROJS Units=$UNITS"
[ "${PROJS:-0}" -ge 2 ] && ok "Demo-Projekte vorhanden" || fail "Demo-Projekte vorhanden"
[ "${UNITS:-0}" -ge 1 ] && ok "Demo-Units vorhanden" || fail "Demo-Units vorhanden"
PID=$(WP post list --post_type=immo_mgr_project --post_status=publish --field=ID --posts_per_page=1 2>/dev/null | tr -d '\r' | head -1)
PSLUG=$(WP post get $PID --field=post_name 2>/dev/null | tr -d '\r')
echo "  Test-Projekt: ID=$PID slug=$PSLUG"

echo "== Test-Seiten anlegen =="
SC_PAGE=$(WP post create --post_type=page --post_status=publish --post_title="Widget Testseite" --post_content="[immo_projects title=\"Unsere Bauprojekte\" columns=\"2\"] [immo_units project=\"$PID\" title=\"Einheiten\"] [immo_list]" --porcelain 2>/dev/null | tr -d '\r')
PLAIN_PAGE=$(WP post create --post_type=page --post_status=publish --post_title="Plain" --post_content="<p>Nur Text ohne Plugin.</p>" --porcelain 2>/dev/null | tr -d '\r')
SC_URL=$(WP post get $SC_PAGE --field=url 2>/dev/null | tr -d '\r'); PLAIN_URL=$(WP post get $PLAIN_PAGE --field=url 2>/dev/null | tr -d '\r')
PROJ_URL=$(WP post get $PID --field=url 2>/dev/null | tr -d '\r')

fetch() { curl -s -o "$2" -w "%{http_code}" "$1"; }
T=$(mktemp -d); command -v cygpath >/dev/null && T=$(cygpath -m "$T")
echo "== Frontend =="
code=$(fetch "$SC_URL" $T/sc.html);        [ "$code" = 200 ] && ok "Shortcode-Seite HTTP 200" || fail "Shortcode-Seite HTTP $code"
grep -q 'immo-widget--projects' $T/sc.html && ok "[immo_projects] gerendert" || fail "[immo_projects] gerendert"
grep -q 'immo-project-card-item' $T/sc.html && ok "Projekt-Cards vorhanden" || fail "Projekt-Cards vorhanden"
grep -q 'immo-widget--units' $T/sc.html && grep -q 'immo-units-table' $T/sc.html && ok "[immo_units] Tabelle gerendert" || fail "[immo_units] Tabelle gerendert"
grep -q 'immo-list-container' $T/sc.html && ok "[immo_list] gerendert" || fail "[immo_list] gerendert"
grep -q 'public/css/frontend.css' $T/sc.html && ok "frontend.css auf Shortcode-Seite geladen" || fail "frontend.css auf Shortcode-Seite geladen"
grep -q 'public/vendor/leaflet/leaflet.js' $T/sc.html && ok "Leaflet lokal geladen" || fail "Leaflet lokal geladen"
grep -q 'unpkg.com' $T/sc.html && fail "kein unpkg.com-CDN" || ok "kein unpkg.com-CDN"
grep -q -- '--immo-primary' $T/sc.html && ok "Design-CSS-Variablen vorhanden" || fail "Design-CSS-Variablen vorhanden"
grep -q 'immoManager' $T/sc.html && ok "JS-Konfiguration (immoManager) vorhanden" || fail "JS-Konfiguration vorhanden"

code=$(fetch "$PLAIN_URL" $T/plain.html);  [ "$code" = 200 ] && ok "Plain-Seite HTTP 200" || fail "Plain-Seite HTTP $code"
grep -q 'public/css/frontend.css' $T/plain.html && fail "Plain-Seite laedt KEIN frontend.css" || ok "Plain-Seite laedt KEIN frontend.css"
grep -q 'leaflet' $T/plain.html && fail "Plain-Seite laedt KEIN Leaflet" || ok "Plain-Seite laedt KEIN Leaflet"
grep -q 'immo-global-search-lightbox' $T/plain.html && fail "Plain-Seite ohne Such-Lightbox-Markup" || ok "Plain-Seite ohne Such-Lightbox-Markup"
grep -q 'fonts.googleapis.com' $T/plain.html && fail "Plain-Seite ohne Google Fonts" || ok "Plain-Seite ohne Google Fonts"

code=$(fetch "http://localhost:$PORT/projekte/" $T/arch.html); [ "$code" = 200 ] && ok "Projekt-Archiv HTTP 200" || fail "Projekt-Archiv HTTP $code"
grep -q 'immo-project-card-item' $T/arch.html && ok "Archiv nutzt Card-Template" || fail "Archiv nutzt Card-Template"
code=$(fetch "$PROJ_URL" $T/single.html); [ "$code" = 200 ] && ok "Projekt-Einzelseite HTTP 200" || fail "Projekt-Einzelseite HTTP $code"
grep -q 'immo-units-table' $T/single.html && ok "Einzelseite zeigt Units-Tabelle" || fail "Einzelseite zeigt Units-Tabelle"
grep -q 'public/js/calculators.js' $T/single.html && ok "Rechner-JS auf Einzelseite" || fail "Rechner-JS auf Einzelseite"
code=$(fetch "http://localhost:$PORT/immobilien/" $T/parch.html); [ "$code" = 200 ] && ok "Immobilien-Archiv HTTP 200" || fail "Immobilien-Archiv HTTP $code"
grep -q "EEB" $T/parch.html && ok "Listing-Card zeigt Energieausweis (HWB/EEB)" || fail "Listing-Card zeigt Energieausweis (HWB/EEB)"
PROP_URL=$(WP post list --post_type=immo_mgr_property --post_status=publish --field=url --posts_per_page=1 --orderby=ID --order=ASC 2>/dev/null | tr -d '
' | head -1)
code=$(fetch "$PROP_URL" $T/psingle.html); [ "$code" = 200 ] && ok "Immobilien-Einzelseite HTTP 200" || fail "Immobilien-Einzelseite HTTP $code"
grep -q "Endenergiebedarf (EEB)" $T/psingle.html && ok "Detailseite zeigt Endenergiebedarf" || fail "Detailseite zeigt Endenergiebedarf"
grep -Eq '"name": *"EEB"' $T/psingle.html && ok "Schema.org enthält EEB" || fail "Schema.org enthält EEB"
# Betriebsnebenkosten (brutto): Demo-Property mit BK 220 / Heiz 85 / sonst 30 -> Summe 335
COSTS_SLUG=$(WP post list --post_type=immo_mgr_property --post_status=publish --field=post_name --meta_key=_immo_heating_costs --meta_value=85 2>/dev/null | tr -d '' | head -1)
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/properties/by-slug/$COSTS_SLUG" $T/costs.json)
python -c "import json,sys; m=json.load(open(sys.argv[1]))['meta']; assert m['operating_costs']==220 and m['heating_costs']==85 and m['other_costs']==30 and m['ancillary_costs_total']==335 and m['costs_gross'] is True and m['ancillary_costs_total_formatted'], m" $T/costs.json && ok "REST: Betriebsnebenkosten brutto + Summe 335" || fail "REST: Betriebsnebenkosten"
code=$(fetch "$(WP post list --post_type=immo_mgr_property --post_status=publish --field=url --meta_key=_immo_heating_costs --meta_value=85 2>/dev/null | tr -d '' | head -1)" $T/costs.html)
grep -q "Heizkosten/Monat (brutto)" $T/costs.html && grep -q "Nebenkosten gesamt/Monat (brutto)" $T/costs.html && ok "Manager-Detailseite: Kosten-Tabelle mit Heizkosten + Gesamtsumme" || fail "Manager-Detailseite: Kosten-Tabelle (HTTP $code)"

echo "== Preisregel: Wohneinheit einer Immobilie zuordnen =="
UNIT_PROP_ID=$(WP post list --post_type=immo_mgr_property --post_status=publish --field=ID --posts_per_page=1 --orderby=ID --order=ASC 2>/dev/null | tr -d '\r' | head -1)
UNIT_PROP_SLUG=$(WP post get $UNIT_PROP_ID --field=post_name 2>/dev/null | tr -d '\r')
FIRST_UNIT=$(WP db query "SELECT MIN(id) FROM wp_immo_units" --skip-column-names 2>/dev/null | tr -d '\r')
WP db query "UPDATE wp_immo_units SET property_id=$UNIT_PROP_ID, price=0, rent=0 WHERE id=$FIRST_UNIT" >/dev/null 2>&1
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/properties/by-slug/$UNIT_PROP_SLUG" $T/unitprop.json)
python -c "import json,sys; d=json.load(open(sys.argv[1])); m=d['meta']; assert d['unit_stats']['total']>=1, d['unit_stats']; assert m['price_formatted'] is None and m['has_units'] is True and 'Preisliste' in m['price_display'], (m['price_formatted'], m.get('has_units'), m.get('price_display'))" $T/unitprop.json && ok "REST: price_formatted null + has_units + price_display bei zugeordneten Units" || fail "REST Preisregel bei zugeordneten Units"
code=$(fetch "$(WP post get $UNIT_PROP_ID --field=url 2>/dev/null | tr -d '\r')" $T/unitprop.html); grep -q "siehe Preisliste" $T/unitprop.html && ok "Manager-Detailseite: siehe Preisliste" || fail "Manager-Detailseite: siehe Preisliste (HTTP $code)"
PROP_PRICE=$(python -c "import json,sys; print(int(json.load(open(sys.argv[1]))['meta']['price']))" $T/unitprop.json)
code=$(fetch "http://localhost:$PORT/immobilien/" $T/parch2.html); python - "$T/parch2.html" "$UNIT_PROP_ID" <<'PY'
import re,sys
html=open(sys.argv[1],encoding='utf-8').read(); pid=sys.argv[2]
m=re.search(r'data-property-id="%s".*?</article>' % pid, html, re.S)
card=m.group(0) if m else ''
ok = bool(card) and 'immo-card-price' not in card
sys.exit(0 if ok else 1)
PY
[ $? = 0 ] && ok "Manager-Card: kein Gesamtpreis bei Units ohne Preis" || fail "Manager-Card: kein Gesamtpreis bei Units ohne Preis"

echo "== REST-API =="
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/projects" $T/projects.json); [ "$code" = 200 ] && ok "GET /projects 200" || fail "GET /projects $code"
python -c "import json,sys; d=json.load(open(sys.argv[1])); assert d['projects'] and 'unit_stats' in d['projects'][0]; print('   projects:',len(d['projects']),'total units stats:',d['projects'][0]['unit_stats'])" $T/projects.json && ok "/projects Struktur" || fail "/projects Struktur"
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/projects/by-slug/$PSLUG/units?status=available,reserved" $T/units.json); [ "$code" = 200 ] && ok "GET /projects/by-slug/{slug}/units 200" || fail "GET units by slug $code"
python -c "import json,sys; d=json.load(open(sys.argv[1])); assert 'units' in d and 'stats' in d and d['stats']['total']>0; print('   units:',len(d['units']),'stats:',d['stats'])" $T/units.json && ok "/units Struktur" || fail "/units Struktur"
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/properties?per_page=5" $T/props.json); [ "$code" = 200 ] && ok "GET /properties 200" || fail "GET /properties $code"
python -c "import json,sys; d=json.load(open(sys.argv[1])); m=d['properties'][0]['meta']; assert 'energy_eeb' in m and 'energy_fgee' in m and 'energy_hwb' in m; assert any(p['meta']['energy_eeb']>0 for p in d['properties']); print('   energy:', [(p['meta']['energy_class'],p['meta']['energy_hwb'],p['meta']['energy_eeb'],p['meta']['energy_fgee']) for p in d['properties']])" $T/props.json && ok "REST liefert energy_eeb/energy_fgee" || fail "REST liefert energy_eeb/energy_fgee"
curl -s -D - -o /dev/null -H "Origin: https://kunde.example.com" "http://localhost:$PORT/wp-json/immo-manager/v1/projects" | grep -qi "Access-Control-Allow-Origin: \*" && ok "CORS-Header gesetzt" || fail "CORS-Header gesetzt"

echo "== Embed-Widget (headless, jsdom) =="
if [ -f "$REPO/bin/embed-test.cjs" ]; then
  if ! node -e "require('jsdom')" >/dev/null 2>&1; then
    echo "  jsdom fehlt – installiere temporär nach $T/node …"
    mkdir -p "$T/node" && ( cd "$T/node" && npm init -y >/dev/null 2>&1 && npm i jsdom --silent >/dev/null 2>&1 )
    export NODE_PATH="$T/node/node_modules"
  fi
  node "$REPO/bin/embed-test.cjs" "$REPO" "http://localhost:$PORT/wp-json/immo-manager/v1" > "$T/embed.log" 2>&1
  EMBED_RC=$?
  grep "FAIL\|EMBED RESULT\|Error" "$T/embed.log" | sed 's/^/  /'
  [ "$EMBED_RC" = "0" ] && ok "Embed-Widget-Tests bestanden" || { fail "Embed-Widget-Tests fehlgeschlagen"; tail -30 "$T/embed.log" | sed 's/^/    /'; }
else
  echo "  (bin/embed-test.cjs fehlt)"
fi
code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:$PORT/wp-content/plugins/immo-manager/public/embed/immo-embed.js"); [ "$code" = 200 ] && ok "immo-embed.js wird ausgeliefert" || fail "immo-embed.js HTTP $code"
code=$(fetch "http://localhost:$PORT/wp-content/plugins/immo-manager/public/embed/anleitung.html" $T/anleitung.html); [ "$code" = 200 ] && grep -q "Live-Demo" $T/anleitung.html && ok "Embed-Anleitung (anleitung.html) wird ausgeliefert" || fail "Embed-Anleitung HTTP $code"

echo "== Security: notify_email ohne API-Key wird ignoriert =="
WP eval '
add_filter("pre_wp_mail", function($null, $atts){ $GLOBALS["immo_mail_to"][] = $atts["to"]; return true; }, 10, 2);
$req = new WP_REST_Request("POST", "/immo-manager/v1/inquiries");
$req->set_header("content-type", "application/json");
$pid = get_posts(["post_type"=>"immo_mgr_property","fields"=>"ids","numberposts"=>1])[0];
$req->set_body(json_encode(["property_id"=>$pid,"inquirer_name"=>"Test\r\nBcc: evil@x","inquirer_email"=>"t@example.com","consent"=>true,"notify_email"=>"attacker@evil.example","skip_notifications"=>false]));
$res = rest_do_request($req);
echo "status=" . $res->get_status() . " to=" . json_encode($GLOBALS["immo_mail_to"] ?? []) . "\n";
' 2>&1 | tail -1 > $T/sec.txt; cat $T/sec.txt
grep -q "status=201" $T/sec.txt && ok "Anfrage anonym angelegt (201)" || fail "Anfrage anonym angelegt"
grep -q "attacker@evil.example" $T/sec.txt && fail "notify_email anonym NICHT uebernommen" || ok "notify_email anonym NICHT uebernommen"
INQ=$(WP db query "SELECT COUNT(*) FROM wp_immo_inquiries" --skip-column-names 2>/dev/null | tr -d '\r'); [ "${INQ:-0}" -ge 1 ] && ok "Anfrage in DB ($INQ)" || fail "Anfrage in DB"

echo "== Admin-Seiten (eingeloggt) =="
curl -s -c $T/cj -b $T/cj -o /dev/null "http://localhost:$PORT/wp-login.php"
curl -s -c $T/cj -b $T/cj -o /dev/null -d "log=admin&pwd=admin123&wp-submit=Log+In&redirect_to=http://localhost:$PORT/wp-admin/&testcookie=1" "http://localhost:$PORT/wp-login.php"
for page in "admin.php?page=immo-manager" "admin.php?page=immo-manager-settings" "admin.php?page=immo-help" "admin.php?page=immo-units" "admin.php?page=immo-inquiries" "edit.php?post_type=immo_mgr_project" "admin.php?page=immo-wizard&id=$PID"; do
  code=$(curl -s -b $T/cj -o $T/admin.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/$page")
  if [ "$code" = 200 ] && ! grep -qi "Fatal error\|Warning:\|Notice:" $T/admin.html; then ok "Admin $page"; else fail "Admin $page (HTTP $code)"; grep -io "Fatal error.*\|Warning:.*" $T/admin.html | head -2; fi
done
code=$(curl -s -b $T/cj -o $T/help.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/admin.php?page=immo-help")
grep -q "Snippet-Generator" $T/help.html && grep -q 'id="help-embed"' $T/help.html && grep -q "Webflow" $T/help.html && ok "Hilfe: Kapitel 12 Embed-Widgets mit Snippet-Generator" || fail "Hilfe: Kapitel 12 Embed-Widgets"
grep -q -- '--immo-primary' $T/admin.html && ok "Design-Variablen auf Admin-Wizard-Seite" || fail "Design-Variablen auf Admin-Wizard-Seite"
code=$(curl -s -b $T/cj -o $T/dash.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/index.php")
grep -q 'immo-manager-frontend\|public/css/frontend.css' $T/dash.html && fail "WP-Dashboard laedt KEIN Plugin-Frontend-CSS" || ok "WP-Dashboard laedt KEIN Plugin-Frontend-CSS"

echo "== Bauprojekte-Paket: Rollen-/Benutzer-Freischaltung =="
EDITOR_ID=$(WP user create redakteur redakteur@example.com --role=editor --user_pass=pw123456 --porcelain 2>/dev/null | tr -d '\r')
AUTHOR_ID=$(WP user create autor autor@example.com --role=author --user_pass=pw123456 --porcelain 2>/dev/null | tr -d '\r')
capcheck() { WP eval "echo user_can($1, '$2') ? 'yes' : 'no';" 2>/dev/null | tr -d '\r'; }
[ "$(capcheck $EDITOR_ID edit_immo_projects)" = "yes" ] && ok "Default (alle Rollen): Redakteur darf Bauprojekte" || fail "Default: Redakteur darf Bauprojekte"
[ "$(capcheck 1 edit_immo_projects)" = "yes" ] && ok "Admin darf Bauprojekte" || fail "Admin darf Bauprojekte"
WP eval '$s = get_option("immo_manager_settings", []); $s["projects_roles"] = ["author"]; update_option("immo_manager_settings", $s);' >/dev/null 2>&1
[ "$(capcheck $EDITOR_ID edit_immo_projects)" = "no" ] && ok "Nur Rolle author freigeschaltet: Redakteur gesperrt" || fail "Rollen-Freischaltung: Redakteur gesperrt"
[ "$(capcheck $EDITOR_ID edit_posts)" = "yes" ] && ok "Redakteur darf weiterhin Immobilien (edit_posts)" || fail "Redakteur edit_posts"
[ "$(capcheck $AUTHOR_ID edit_immo_projects)" = "yes" ] && ok "Autor (freigeschaltete Rolle) darf Bauprojekte" || fail "Autor darf Bauprojekte"
[ "$(capcheck 1 edit_immo_projects)" = "yes" ] && ok "Admin bleibt freigeschaltet" || fail "Admin bleibt freigeschaltet"
WP user meta update $EDITOR_ID immo_projects_access 1 >/dev/null 2>&1
[ "$(capcheck $EDITOR_ID edit_immo_projects)" = "yes" ] && ok "Benutzer-Freischaltung uebersteuert Rolle (frei)" || fail "Benutzer-Freischaltung (frei)"
WP user meta update $AUTHOR_ID immo_projects_access 0 >/dev/null 2>&1
[ "$(capcheck $AUTHOR_ID edit_immo_projects)" = "no" ] && ok "Benutzer-Sperre uebersteuert Rolle (gesperrt)" || fail "Benutzer-Sperre (gesperrt)"
# Redakteur ohne Paket: Admin-Menue ohne Bauprojekte, Edit-Screen verweigert
WP user meta delete $EDITOR_ID immo_projects_access >/dev/null 2>&1
curl -s -c $T/cj2 -b $T/cj2 -o /dev/null "http://localhost:$PORT/wp-login.php"
curl -s -c $T/cj2 -b $T/cj2 -o /dev/null -d "log=redakteur&pwd=pw123456&wp-submit=Log+In&redirect_to=http://localhost:$PORT/wp-admin/&testcookie=1" "http://localhost:$PORT/wp-login.php"
code=$(curl -s -b $T/cj2 -o $T/ed-menu.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/edit.php?post_type=immo_mgr_property")
grep -q "post_type=immo_mgr_property" $T/ed-menu.html && ok "Redakteur sieht Immobilien-Menue" || fail "Redakteur sieht Immobilien-Menue (HTTP $code)"
grep -q "edit.php?post_type=immo_mgr_project" $T/ed-menu.html && fail "Redakteur sieht KEIN Bauprojekte-Menue" || ok "Redakteur sieht KEIN Bauprojekte-Menue"
grep -q "page=immo-units" $T/ed-menu.html && fail "Redakteur sieht KEINE Wohneinheiten-Seite" || ok "Redakteur sieht KEINE Wohneinheiten-Seite"
code=$(curl -s -L -b $T/cj2 -o /dev/null -w "%{http_code}" "http://localhost:$PORT/wp-admin/post.php?post=$PID&action=edit")
[ "$code" = "403" ] && ok "Redakteur: Bauprojekt-Edit verweigert (403)" || fail "Redakteur: Bauprojekt-Edit verweigert (HTTP $code)"
code=$(curl -s -L -b $T/cj2 -o /dev/null -w "%{http_code}" "http://localhost:$PORT/wp-admin/admin.php?page=immo-wizard&id=$PID")
[ "$code" = "403" ] && ok "Redakteur: Admin-Wizard fuer Bauprojekt verweigert (403)" || fail "Redakteur: Admin-Wizard Bauprojekt (HTTP $code)"
code=$(curl -s -L -b $T/cj2 -o /dev/null -w "%{http_code}" "http://localhost:$PORT/wp-admin/edit.php?post_type=immo_mgr_project")
[ "$code" = "403" ] && ok "Redakteur: Bauprojekt-Liste verweigert (403)" || fail "Redakteur: Bauprojekt-Liste (HTTP $code)"
code=$(curl -s -b $T/cj2 -o $T/ed-wiz.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/admin.php?page=immo-wizard")
grep -q 'value="project"' $T/ed-wiz.html && fail "Redakteur: Wizard ohne Bauprojekt-Kachel" || ok "Redakteur: Wizard ohne Bauprojekt-Kachel"
WP eval '$s = get_option("immo_manager_settings", []); $s["projects_roles"] = ["*"]; update_option("immo_manager_settings", $s);' >/dev/null 2>&1

echo "== Bauprojekte-Paket global AUS: reine Immobilienverwaltung =="
WP eval '$s = get_option("immo_manager_settings", []); $s["enable_projects"] = 0; update_option("immo_manager_settings", $s); update_option("immo_flush_needed", 1);' >/dev/null 2>&1
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/projects" $T/p-off.json); [ "$code" = 404 ] && ok "REST /projects -> 404 bei deaktiviertem Paket" || fail "REST /projects bei Paket aus: HTTP $code"
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/properties?per_page=2" $T/pr-off.json); [ "$code" = 200 ] && ok "REST /properties weiterhin 200" || fail "REST /properties bei Paket aus: HTTP $code"
code=$(curl -s -L -o $T/arch-off.html -w "%{http_code} %{url_effective}" "http://localhost:$PORT/projekte/"); case "$code" in 404*) ok "Archiv /projekte/ -> 404";; *) fail "Archiv /projekte/ bei Paket aus: $code";; esac
code=$(curl -s -L -o $T/single-off.html -w "%{http_code} %{url_effective}" "$PROJ_URL"); case "$code" in 404*) ok "Bauprojekt-Einzelseite -> 404";; *) fail "Bauprojekt-Einzelseite bei Paket aus: $code";; esac
code=$(fetch "$SC_URL" $T/sc-off.html); [ "$code" = 200 ] && ! grep -q "immo-project-card-item" $T/sc-off.html && grep -q "immo-list-container" $T/sc-off.html && ok "[immo_projects]/[immo_units] leer, [immo_list] weiterhin da" || fail "Shortcodes bei Paket aus"
code=$(fetch "http://localhost:$PORT/immobilien/" $T/parch-off.html); [ "$code" = 200 ] && ok "Immobilien-Archiv weiterhin 200" || fail "Immobilien-Archiv bei Paket aus: HTTP $code"
code=$(curl -s -b $T/cj -o $T/admin-off.html -w "%{http_code}" "http://localhost:$PORT/wp-admin/admin.php?page=immo-manager")
grep -q "edit.php?post_type=immo_mgr_project" $T/admin-off.html && fail "Admin-Menue ohne Bauprojekte (Paket aus)" || ok "Admin-Menue ohne Bauprojekte (Paket aus)"
res=$(curl -s -L -b $T/cj -o $T/list-off.html -w "%{http_code} %{url_effective}" "http://localhost:$PORT/wp-admin/edit.php?post_type=immo_mgr_project"); case "$res" in 200*immo_projects_disabled*) ok "Bauprojekt-Liste bei Paket aus -> Weiterleitung zu Einstellungen mit Hinweis";; *) fail "Bauprojekt-Liste bei Paket aus: $res";; esac
grep -q "Bauprojekte-Paket ist deaktiviert" $T/list-off.html && ok "Hinweis-Notice angezeigt" || fail "Hinweis-Notice angezeigt"
PROJS_OFF=$(WP post list --post_type=immo_mgr_project --post_status=publish --format=count 2>/dev/null | tr -d '\r')
[ "$PROJS_OFF" = "$PROJS" ] && ok "Bauprojekt-Daten bleiben erhalten ($PROJS_OFF)" || fail "Bauprojekt-Daten bei Paket aus: $PROJS_OFF"
WP eval '$s = get_option("immo_manager_settings", []); $s["enable_projects"] = 1; update_option("immo_manager_settings", $s); update_option("immo_flush_needed", 1);' >/dev/null 2>&1
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/projects" $T/p-on.json); [ "$code" = 200 ] && ok "Paket wieder AN: REST /projects 200" || fail "Paket wieder an: HTTP $code"
code=$(fetch "http://localhost:$PORT/projekte/" $T/arch-on.html); [ "$code" = 200 ] && ok "Paket wieder AN: Archiv 200" || fail "Paket wieder an: Archiv HTTP $code"

echo "== ImmoClient (Schwester-Plugin, falls ../immo-client vorhanden) =="
CLIENT_DIR="$(dirname "$REPO")/immo-client"
if [ -f "$CLIENT_DIR/immo-client.php" ]; then
  docker cp "$CLIENT_DIR" immo-wp:/var/www/html/wp-content/plugins/immo-client >/dev/null 2>&1
  docker exec immo-wp sh -c 'rm -rf /var/www/html/wp-content/plugins/immo-client/.git; chown -R 33:33 /var/www/html/wp-content/plugins/immo-client'
  WP plugin activate immo-client 2>&1 | tail -1
  # Manager ueber den Docker-Netzwerknamen ansprechen – erreichbar aus dem Apache-Container
  # UND aus dem wp-cli-Container (dort waere "localhost" der CLI-Container selbst).
  WP option update immo_api_url "http://immo-wp" >/dev/null 2>&1
  WP option update immo_cache_duration 0 >/dev/null 2>&1
  WP rewrite flush --hard >/dev/null 2>&1
  CL_PAGE=$(WP post create --post_type=page --post_status=publish --post_title="Client Test" --post_content="[immo_list limit=\"6\"] [immo_project slug=\"$PSLUG\"] [immo_units project_slug=\"$PSLUG\"]" --porcelain 2>/dev/null | tr -d '\r')
  CL_URL=$(WP post get $CL_PAGE --field=url 2>/dev/null | tr -d '\r')
  code=$(fetch "$CL_URL" $T/client.html); [ "$code" = 200 ] && ok "Client: Shortcode-Seite HTTP 200" || fail "Client: Shortcode-Seite HTTP $code"
  # [immo_list] registrieren Manager UND Client (im Test gewinnt der Manager) -> Client-Liste direkt rendern.
  WP eval 'echo (new ImmoShortcodes())->render_list_shortcode(["limit" => 6]);' > $T/client-list.html 2>/dev/null
  grep -q "immo-item-grid" $T/client-list.html && grep -q "immo-card" $T/client-list.html && ok "Client: [immo_list] rendert Cards" || { fail "Client: [immo_list] rendert Cards"; echo "    eval output ($(wc -c < $T/client-list.html) bytes): $(head -c 300 $T/client-list.html)"; }
  grep -q "immo-card-energy" $T/client-list.html && grep -q "EEB" $T/client-list.html && ok "Client: Listing-Cards zeigen HWB/EEB" || fail "Client: Listing-Cards zeigen HWB/EEB"
  grep -q "Energieausweis" $T/client.html && ok "Client: Wohneinheiten-Karten zeigen Energieausweis" || fail "Client: Wohneinheiten-Karten zeigen Energieausweis"
  PSLUG_PROP=$(WP post list --post_type=immo_mgr_property --post_status=publish --field=post_name --posts_per_page=1 --orderby=ID --order=DESC 2>/dev/null | tr -d '\r' | head -1)
  code=$(curl -s -L -o $T/client-detail.html -w "%{http_code}" "http://localhost:$PORT/immobilie/$PSLUG_PROP/"); [ "$code" = 200 ] && ok "Client: Detailseite /immobilie/{slug} HTTP 200" || fail "Client: Detailseite HTTP $code"
  grep -q "Endenergiebedarf (EEB)\|fGEE (Altausweis)" $T/client-detail.html && ok "Client: Detailseite zeigt Endenergiebedarf" || fail "Client: Detailseite zeigt Endenergiebedarf"
  code=$(curl -s -L -o $T/client-costs.html -w "%{http_code}" "http://localhost:$PORT/immobilie/$COSTS_SLUG/")
  grep -q "Heizkosten / Monat (brutto)" $T/client-costs.html && grep -q "Nebenkosten gesamt / Monat (brutto)" $T/client-costs.html && ok "Client-Detailseite: Betriebsnebenkosten brutto + Summe" || fail "Client-Detailseite: Betriebsnebenkosten (HTTP $code)"
  code=$(curl -s -L -o $T/client-unitprop.html -w "%{http_code}" "http://localhost:$PORT/immobilie/$UNIT_PROP_SLUG/")
  grep -q "siehe Preisliste" $T/client-unitprop.html && ! grep -q "immo-price-value\">[0-9]" $T/client-unitprop.html && ok "Client-Detailseite: siehe Preisliste statt Gesamtpreis" || fail "Client-Detailseite: Preisregel (HTTP $code)"
  python - "$T/client-list.html" "$UNIT_PROP_SLUG" <<'PY'
import re,sys
html=open(sys.argv[1],encoding='utf-8').read(); slug=sys.argv[2]
cards=re.findall(r'<div class="immo-card".*?</div>\s*</div>', html, re.S)
card=[c for c in cards if slug in c]
sys.exit(0 if card and 'class="price"' not in card[0] else 1)
PY
  [ $? = 0 ] && ok "Client-Card: kein Gesamtpreis bei Units ohne Preis" || fail "Client-Card: kein Gesamtpreis bei Units ohne Preis"
  # Paket im Manager aus -> Client zeigt Hinweis statt "nicht gefunden"
  WP eval '$s = get_option("immo_manager_settings", []); $s["enable_projects"] = 0; update_option("immo_manager_settings", $s);' >/dev/null 2>&1
  WP transient delete --all >/dev/null 2>&1
  code=$(fetch "$CL_URL" $T/client-off.html); grep -q "Bauprojekte sind derzeit nicht verfügbar" $T/client-off.html && ok "Client: Hinweis bei deaktiviertem Bauprojekte-Paket" || fail "Client: Hinweis bei deaktiviertem Paket (HTTP $code)"
  WP eval '$s = get_option("immo_manager_settings", []); $s["enable_projects"] = 1; update_option("immo_manager_settings", $s);' >/dev/null 2>&1
  WP transient delete --all >/dev/null 2>&1
  WP plugin deactivate immo-client >/dev/null 2>&1
else
  echo "  (uebersprungen: $CLIENT_DIR nicht vorhanden)"
fi

echo "== Re-Installation (Update per ZIP) erhält Daten =="
WP plugin install /var/www/html/wp-content/$ZIP --force 2>&1 | tail -1
check "Plugin nach Re-Install aktiv" bash -c "WP plugin list --status=active --field=name | tr -d '
' | grep -qx immo-manager"
PROPS2=$(WP post list --post_type=immo_mgr_property --post_status=publish --format=count 2>/dev/null | tr -d '\r')
UNITS2=$(WP db query "SELECT COUNT(*) FROM wp_immo_units" --skip-column-names 2>/dev/null | tr -d '\r')
INQ2=$(WP db query "SELECT COUNT(*) FROM wp_immo_inquiries" --skip-column-names 2>/dev/null | tr -d '\r')
[ "$PROPS" = "$PROPS2" ] && [ "$UNITS" = "$UNITS2" ] && [ "$INQ" = "$INQ2" ] && ok "Daten unveraendert (props $PROPS2, units $UNITS2, inquiries $INQ2)" || fail "Daten veraendert! ($PROPS->$PROPS2, $UNITS->$UNITS2, $INQ->$INQ2)"

echo "== Deaktivieren + Loeschen OHNE Opt-in erhält Daten =="
WP plugin deactivate immo-manager >/dev/null 2>&1
WP plugin uninstall immo-manager 2>&1 | tail -1
TABLES=$(WP db tables --all-tables 2>/dev/null | tr -d '\r')
echo "$TABLES" | grep -qx wp_immo_units && ok "Tabelle wp_immo_units nach Uninstall erhalten" || fail "Tabelle wp_immo_units nach Uninstall erhalten"
UNITS3=$(WP db query "SELECT COUNT(*) FROM wp_immo_units" --skip-column-names 2>/dev/null | tr -d '\r')
[ "$UNITS" = "$UNITS3" ] && ok "Units-Zeilen erhalten ($UNITS3)" || fail "Units-Zeilen erhalten"
PROPS3=$(WP post list --post_type=immo_mgr_property --post_status=any --format=count 2>/dev/null | tr -d '\r')
[ "$PROPS" = "$PROPS3" ] && ok "Immobilien-Posts erhalten ($PROPS3)" || fail "Immobilien-Posts erhalten"
check "Settings-Option erhalten" bash -c "WP option get immo_manager_settings --format=json | grep -q items_per_page"
check "Cron-Events entfernt" bash -c "! WP cron event list --fields=hook | grep -q immo_manager_openimmo"

echo "== Re-Installation nach Uninstall: Daten wieder sichtbar =="
WP plugin install /var/www/html/wp-content/$ZIP --activate 2>&1 | tail -1
PROJS4=$(WP post list --post_type=immo_mgr_project --post_status=publish --format=count 2>/dev/null | tr -d '\r')
[ "$PROJS" = "$PROJS4" ] && ok "Projekte nach Re-Install sichtbar ($PROJS4)" || fail "Projekte nach Re-Install sichtbar"
code=$(fetch "http://localhost:$PORT/wp-json/immo-manager/v1/projects/$PID/units" $T/units2.json); [ "$code" = 200 ] && ok "Units-REST nach Re-Install" || fail "Units-REST nach Re-Install $code"

echo "== Uninstall MIT Opt-in loescht Daten =="
WP eval '$s = get_option("immo_manager_settings", []); $s["delete_data_on_uninstall"] = 1; update_option("immo_manager_settings", $s); echo "opt-in gesetzt\n";' 2>&1 | tail -1
WP plugin deactivate immo-manager >/dev/null 2>&1
WP plugin uninstall immo-manager 2>&1 | tail -1
TABLES=$(WP db tables --all-tables 2>/dev/null | tr -d '\r')
echo "$TABLES" | grep -qx wp_immo_units && fail "Tabelle wp_immo_units geloescht (Opt-in)" || ok "Tabelle wp_immo_units geloescht (Opt-in)"
PROPS5=$(WP post list --post_type=immo_mgr_property --post_status=any --format=count 2>/dev/null | tr -d '\r')
[ "${PROPS5:-0}" = "0" ] && ok "Immobilien geloescht (Opt-in)" || fail "Immobilien geloescht (Opt-in): $PROPS5"

echo "== PHP debug.log =="
docker exec immo-wp sh -c 'test -f /var/www/html/wp-content/debug.log && grep -i "immo" /var/www/html/wp-content/debug.log | grep -iv "pre_http_request\|blocked" | head -20 || echo "(kein debug.log / keine Plugin-Einträge)"'
n=$(docker exec immo-wp sh -c 'test -f /var/www/html/wp-content/debug.log && grep -ic "immo-manager" /var/www/html/wp-content/debug.log || echo 0' | tr -d '
')
[ "${n:-0}" -eq 0 ] 2>/dev/null && ok "keine PHP-Warnungen/Fehler aus immo-manager im debug.log" || fail "debug.log enthält $n Zeilen mit immo-manager"

echo
echo "RESULT: $PASS passed, $FAIL failed"
if [ "${KEEP:-0}" != "1" ]; then cleanup; fi
exit $FAIL
