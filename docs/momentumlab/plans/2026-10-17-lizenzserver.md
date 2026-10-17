# Lizenzserver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use momentumlab:subagent-driven-development (recommended) or momentumlab:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** WordPress-Plugin `mlab-license-server`, das auf momentumlab.ch Lizenzen verwaltet und den Core-Plugins beim Kunden Katalog, Update-Metadaten und Downloads aus GitHub-Releases liefert.

**Architecture:** Reine Logik (Host-Normalisierung, Schlüssel, Signatur, Freischaltung, readme-Parser) liegt in WordPress-freien Klassen und wird mit PHPUnit getestet. Darum herum: eigene Tabellen über `$wpdb`, ein GitHub-Client mit Transient-Cache, REST-Routen `mlab-ls/v1` und Admin-Seiten mit `WP_List_Table`. Downloads werden nicht durchgestreamt: der Server holt bei GitHub die kurzlebige, vorsignierte Asset-URL und leitet mit 302 dorthin weiter.

**Tech Stack:** PHP 8.0+, WordPress 6.4+, PHPUnit 9, PHPCS mit WordPress Coding Standards, Plugin Update Checker v5 (Selbst-Update), GitHub REST API.

**Spec:** `docs/momentumlab/specs/2026-10-17-core-und-lizenzserver-design.md` (Repo mlab-core)

**Repo:** `momentumlab-ch/mlab-license-server`, Branch `feature/license-server`.

## Global Constraints

- Header: `Requires at least: 6.4`, `Requires PHP: 8.0`, `Text Domain: mlab-license-server`, `Update URI: https://github.com/momentumlab-ch/mlab-license-server/`, Lizenz GPL-2.0-or-later.
- Präfix `mlab_ls_` (Funktionen, Optionen, Tabellen `{$wpdb->prefix}mlab_ls_*`, Hooks), `MLAB_LS_` (Konstanten), Namespace `MomentumLab\LicenseServer`.
- Jede PHP-Datei beginnt mit `defined( 'ABSPATH' ) || exit;` (Testdateien ausgenommen; der Test-Bootstrap definiert `ABSPATH`).
- Funktionen unter ca. 30 Zeilen, höchstens zwei Ebenen Einrückung, keine verschachtelten Ternären.
- Texte übersetzbar, Textdomain `mlab-license-server`, Deutsch als Quelle. UI-Texte ohne Fachjargon, Fehlermeldungen sagen, was zu tun ist.
- Secrets nur als Konstanten in `wp-config.php`: `MLAB_LS_GITHUB_TOKEN`, `MLAB_LS_SIGNING_KEY`. Fehlt eine, zeigt der Admin einen Hinweis mit dem Namen der Konstante und die REST-Routen antworten mit 503.
- Rechte: alle Admin-Seiten und -Aktionen `manage_options` plus Nonce. Alle REST-Routen mit eigenem `permission_callback`.
- Datenbank nur über `$wpdb->prepare()` bzw. `$wpdb->insert/update/delete`.
- Lizenzschlüssel nur als Hash gespeichert. Klartext nie loggen, nie in URLs.
- Server-Timeouts gegenüber GitHub: 10 Sekunden. Release-Infos 3600 Sekunden gecacht.
- Keine Hinweise auf Claude oder KI in Code, Kommentaren, readme, Changelog, Commits, PR.
- Git-Identität `MomentumLab Dev <support@momentumlab.ch>`. Commit-Format `vX.Y.Z — <type>: <beschreibung>`, Version dieses Plans `v0.1.0`. Nie auf `main` pushen ausser dem leeren Initial-Commit (Task 1). Chris merged.
- Build grün vor jedem Commit: `composer test` und `composer lint` ohne Fehler.

## Review Focus

- Freischaltung mit `expires_at` genau jetzt oder in der Vergangenheit → nicht freigeschaltet; `null` → unbefristet freigeschaltet. (Test in Task 3)
- Gesperrte Lizenz mit gültigen Freischaltungen → nichts freigeschaltet ausser Gratis-Produkten. (Test in Task 3)
- Manipulierte, abgelaufene oder für einen anderen Slug ausgestellte Download-Signatur → 403, kein Redirect. (Test in Task 2)
- Website-URLs `https://www.Kunde.ch/`, `http://kunde.ch/blog` → derselbe Host `kunde.ch`; `kunde.local`, `localhost:8080`, `staging.kunde.ch` → zählen nicht zum Limit. (Test in Task 2)
- GitHub nicht erreichbar oder Release ohne `<slug>.zip` → `/catalog` liefert das Produkt ohne Version, `/update` antwortet 404 mit verständlicher Meldung statt 500. (Test in Task 5)

---

### Task 1: Gerüst, Tooling, Release

**Files:**
- Create: `mlab-license-server.php`, `inc/options.php`, `inc/updater.php`, `readme.txt`, `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/HeaderTest.php`, `.distignore`, `.gitignore`, `.github/workflows/release.yml`, `.github/workflows/ci.yml`, `languages/.gitkeep`
- Copy: `lib/plugin-update-checker/` aus dem Skill `wordpress-development`

**Interfaces:**
- Produces: Konstanten `MLAB_LS_VERSION` (`'0.1.0'`), `MLAB_LS_FILE`, `MLAB_LS_DIR`; Funktion `mlab_ls_option( string $key ): mixed` in `inc/options.php` mit Defaults `signature_ttl` = 600, `rate_limit_max` = 10, `rate_limit_window` = 900, `release_cache_ttl` = 3600, `default_max_sites` = 1. Die Hauptdatei lädt alle Dateien aus `inc/` per `require_once` in fester Reihenfolge.

- [ ] **Step 1:** Initial-Commit auf `main` mit nur `.gitignore` (`vendor/`, `*.zip`), pushen; danach Branch `feature/license-server` anlegen. Grund: das Repo ist leer, ein PR braucht eine Basis.
- [ ] **Step 2:** `composer.json` mit `require-dev`: `phpunit/phpunit:^9.6`, `wp-coding-standards/wpcs:^3.1`, `dealerdirect/phpcodesniffer-composer-installer`; Scripts `test` = `phpunit`, `lint` = `phpcs`. `composer install`.
- [ ] **Step 3: Test schreiben** `tests/HeaderTest.php::test_header_version_matches_constant` liest `mlab-license-server.php`, extrahiert `Version:` aus dem Header und `define( 'MLAB_LS_VERSION', '…' )` per Regex und erwartet beide `'0.1.0'`; zweiter Test `test_readme_stable_tag_matches` erwartet `Stable tag: 0.1.0` in `readme.txt`.
- [ ] **Step 4:** `composer test` → FAIL (Dateien fehlen).
- [ ] **Step 5:** Hauptdatei, `inc/options.php`, `readme.txt` (Deutsch, Changelog-Eintrag «0.1.0 – Erste Version: Lizenzen, Plugin-Katalog, Updates und Downloads für MomentumLab Core.»), `phpcs.xml.dist` (Vorlage, `{{TEXT_DOMAIN}}` = `mlab-license-server`), `.distignore` (Vorlage plus `phpunit.xml.dist`, `vendor`), `release.yml` (Vorlage unverändert), `inc/updater.php` (Vorlage: `mlab_ls`, `MLAB_LS`, Slug `mlab-license-server`, Repo-URL oben, `mlab-license-server.php`, `private` = `true`). `ci.yml`: bei Push und PR `composer install`, `composer lint`, `composer test` auf PHP 8.0 und 8.3.
- [ ] **Step 6:** `composer test` und `composer lint` → PASS, 0 Fehler.
- [ ] **Step 7: Commit** `v0.1.0 — chore: plugin scaffold, tooling and release workflow`

### Task 2: Sicherheits-Bausteine (Host, Schlüssel, Signatur)

**Files:**
- Create: `inc/class-host.php`, `inc/class-license-key.php`, `inc/class-signature.php`
- Test: `tests/HostTest.php`, `tests/LicenseKeyTest.php`, `tests/SignatureTest.php`

**Interfaces:**
- Produces:
  - `Host::normalize( string $url ): string` — Kleinbuchstaben, ohne Schema, Port, Pfad, `www.`; leerer oder ungültiger Input → `''`.
  - `Host::is_dev( string $host ): bool` — `true` für `localhost`, IPs, `*.local`, `*.test`, `*.localhost`, `staging.*`, `dev.*`.
  - `LicenseKey::generate(): string` — Format `MLAB-XXXX-XXXX-XXXX-XXXX`, Zeichen `A-Z2-9` ohne `O`, `I`, `0`, `1`, aus `random_int`.
  - `LicenseKey::hash( string $key ): string` — `hash( 'sha256', strtoupper( trim( $key ) ) )`.
  - `LicenseKey::hint( string $key ): string` — letzte 4 Zeichen.
  - `Signature::create( string $slug, int $license_id, int $expires, string $secret ): string` — `base64url( "$slug|$license_id|$expires" ) . '.' . base64url( hmac_sha256 )`.
  - `Signature::verify( string $token, string $slug, string $secret, int $now ): ?int` — Lizenz-ID oder `null`; Vergleich mit `hash_equals`.

- [ ] **Step 1: Tests schreiben**
  - `HostTest::test_normalize` mit Datenprovider: `https://www.Kunde.ch/` → `kunde.ch`; `http://kunde.ch/blog` → `kunde.ch`; `kunde.ch:8443` → `kunde.ch`; `''` → `''`; `not a url` → `''`.
  - `HostTest::test_is_dev`: `localhost`, `127.0.0.1`, `kunde.local`, `kunde.test`, `staging.kunde.ch`, `dev.kunde.ch` → `true`; `kunde.ch`, `shop.kunde.ch` → `false`.
  - `LicenseKeyTest::test_generate_format` erwartet Regex `/^MLAB(-[A-HJ-NP-Z2-9]{4}){4}$/`, und zwei Aufrufe sind verschieden.
  - `LicenseKeyTest::test_hash_ignores_case_and_spaces`: `hash(' mlab-abcd-… ')` === `hash('MLAB-ABCD-…')`.
  - `SignatureTest`: gültig → Lizenz-ID 7; `$now` = `$expires` → `null`; anderer Slug → `null`; ein Zeichen im Token verändert → `null`; anderes Secret → `null`; Lizenz-ID 0 (Gratis) → `0`.
- [ ] **Step 2:** `composer test` → FAIL.
- [ ] **Step 3:** Klassen implementieren (Signatur: Vergleich `$now < $expires` als gültig).
- [ ] **Step 4:** `composer test` → PASS; `composer lint` → 0 Fehler.
- [ ] **Step 5: Commit** `v0.1.0 — feat: host normalisation, license keys and download signatures`

### Task 3: Freischaltung und readme-Parser

**Files:**
- Create: `inc/class-entitlement.php`, `inc/class-readme.php`
- Test: `tests/EntitlementTest.php`, `tests/ReadmeTest.php`

**Interfaces:**
- Produces:
  - `Entitlement::unlocked_slugs( array $license, array $grants, array $package_products, array $free_slugs, int $now ): string[]` — `$license` = `['status' => 'active'|'blocked']` oder `[]` (keine Lizenz); `$grants` = Liste von `['product_slug' => ?string, 'package_id' => ?int, 'expires_at' => ?int]` (Unix-Zeit); `$package_products` = `[package_id => string[] slugs]`. Ergebnis: sortierte, eindeutige Slugs, Gratis-Produkte immer enthalten.
  - `Readme::parse( string $readme ): array` — `['requires' => string, 'requires_php' => string, 'tested' => string, 'changelog' => string]`; Changelog als HTML aus dem Abschnitt `== Changelog ==` (`= x.y.z =`-Überschriften → `<h4>`, `* `-Zeilen → `<ul><li>`), Werte ohne Treffer → `''`.

- [ ] **Step 1: Tests schreiben**
  - `EntitlementTest`: Freischaltung Produkt `a` ohne Ablauf → `['a']`; Ablauf `$now - 1` → `[]`; Ablauf `$now` → `[]`; Ablauf `$now + 1` → `['a']`; Paket 3 mit `['a','b']` → `['a','b']`; Lizenz `blocked` mit gültigen Freischaltungen und Gratis `['f']` → `['f']`; keine Lizenz (`[]`) und Gratis `['f']` → `['f']`; Produkt direkt und über Paket → `a` nur einmal.
  - `ReadmeTest::test_parse_headers`: Fixture mit `Requires at least: 6.4`, `Requires PHP: 8.0`, `Tested up to: 6.7` → Werte; `test_parse_changelog` erwartet `<h4>0.1.0</h4>` und `<li>Erste Version.</li>`; `test_missing_values_are_empty`.
- [ ] **Step 2:** `composer test` → FAIL.
- [ ] **Step 3:** Klassen implementieren. Changelog-Text mit `htmlspecialchars` escapen, bevor die Tags gesetzt werden.
- [ ] **Step 4:** `composer test` → PASS; `composer lint` → 0 Fehler.
- [ ] **Step 5: Commit** `v0.1.0 — feat: entitlement rules and readme parser`

### Task 4: Datenbank und Repository

**Files:**
- Create: `inc/schema.php`, `inc/class-store.php`

**Interfaces:**
- Consumes: `LicenseKey::hash`, `Host::normalize`, `Host::is_dev`.
- Produces:
  - `mlab_ls_install_schema(): void` (Aktivierungshook und bei abweichender Option `mlab_ls_db_version` in `plugins_loaded`), Tabellen per `dbDelta`:
    - `products` (`id`, `slug` UNIQUE, `name`, `description` TEXT, `repo`, `main_file`, `is_private`, `is_free`, `is_active`, `created_at`)
    - `packages` (`id`, `slug` UNIQUE, `name`), `package_products` (`package_id`, `product_id`, PRIMARY KEY beider)
    - `licenses` (`id`, `key_hash` CHAR(64) UNIQUE, `key_hint`, `customer_name`, `customer_email`, `status`, `max_sites`, `note` TEXT, `created_at`)
    - `grants` (`id`, `license_id` INDEX, `product_id` NULL, `package_id` NULL, `expires_at` DATETIME NULL, `source` default `manual`, `external_ref`, `created_at`)
    - `activations` (`id`, `license_id`, `host`, `last_seen`, UNIQUE `license_id`+`host`)
  - `Store` mit statischen Methoden: `product_by_slug( string ): ?array`, `active_products(): array`, `save_product( array ): int`, `packages(): array`, `save_package( array, int[] $product_ids ): int`, `package_products_map(): array` (`package_id => slug[]`), `license_by_key( string $plain ): ?array`, `license( int ): ?array`, `create_license( array ): array` (gibt `['id' => int, 'key' => string]` mit Klartext zurück), `update_license( int, array ): void`, `grants_for( int $license_id ): array` (im Format von `Entitlement`), `add_grant( int, array ): int`, `delete_grant( int ): void`, `touch_activation( int $license_id, string $host ): bool` (`false`, wenn das Limit ohne Dev-Hosts erreicht ist und der Host neu ist), `activations( int ): array`, `delete_activation( int ): void`.

- [ ] **Step 1:** `inc/schema.php` und `inc/class-store.php` implementieren. `touch_activation`: Dev-Host → immer `true`, nichts speichern; bekannter Host → `last_seen` aktualisieren, `true`; neuer Host → zählen, unter `max_sites` einfügen und `true`, sonst `false`.
- [ ] **Step 2:** `php -l` auf beide Dateien, `composer lint` → 0 Fehler.
- [ ] **Step 3: Commit** `v0.1.0 — feat: database schema and data store`

Die Datenbank-Logik wird in Task 7 gegen ein echtes WordPress geprüft.

### Task 5: GitHub-Client und Rate-Limit

**Files:**
- Create: `inc/class-github.php`, `inc/class-rate-limit.php`
- Test: `tests/GitHubTest.php` (mit gestubbten `wp_remote_get`, `wp_remote_retrieve_*`, `get_transient`, `set_transient` im Test-Bootstrap)

**Interfaces:**
- Consumes: `Readme::parse`, `mlab_ls_option`.
- Produces:
  - `GitHub::release( array $product ): ?array` — `['version' => '1.2.0', 'tag' => 'v1.2.0', 'asset_id' => int, 'published_at' => string, 'readme' => Readme::parse-Ergebnis]` für das neueste veröffentlichte Release (`/releases/latest` schliesst Entwürfe und Pre-Releases aus) mit Asset `<slug>.zip`, sonst `null`. Cache-Transient `mlab_ls_rel_<slug>`, auch negative Ergebnisse 300 Sekunden.
  - `GitHub::asset_url( array $product, int $asset_id ): ?string` — Request auf `/releases/assets/<id>` mit `Accept: application/octet-stream`, `redirection` 0; gibt den `Location`-Header zurück.
  - `GitHub::flush( string $slug ): void`.
  - `RateLimit::blocked( string $ip ): bool`, `RateLimit::fail( string $ip ): void` (Transient `mlab_ls_rl_<md5(ip)>`, Werte aus `mlab_ls_option`).
- Header: `Authorization: Bearer MLAB_LS_GITHUB_TOKEN` (wenn gesetzt), `User-Agent: mlab-license-server`, `X-GitHub-Api-Version: 2022-11-28`.

- [ ] **Step 1: Tests schreiben** `test_release_reads_version_and_asset`, `test_release_without_zip_asset_is_null`, `test_release_on_http_error_is_null`, `test_release_is_cached` (zweiter Aufruf ohne HTTP-Request), `test_version_strips_leading_v`.
- [ ] **Step 2:** `composer test` → FAIL.
- [ ] **Step 3:** Klassen implementieren; readme über `/repos/<repo>/contents/readme.txt?ref=<tag>` mit `Accept: application/vnd.github.raw`.
- [ ] **Step 4:** `composer test` → PASS; `composer lint` → 0 Fehler.
- [ ] **Step 5: Commit** `v0.1.0 — feat: github release client and rate limit`

### Task 6: REST-API

**Files:**
- Create: `inc/class-rest.php`

**Interfaces:**
- Consumes: alles aus Task 2–5.
- Produces: Namespace `mlab-ls/v1`, Header `X-MLAB-License`, `X-MLAB-Site`.
  - `GET /catalog` → `200 {"products":[{"slug","name","description","version","main_file","unlocked"}]}`.
  - `GET /update/(?P<slug>[a-z0-9-]+)` → PUC-JSON `{"name","slug","version","download_url","requires","requires_php","tested","last_updated","sections":{"changelog"}}`.
  - `GET /download/(?P<slug>[a-z0-9-]+)?t=<token>` → `302` auf die GitHub-Asset-URL.
  - Fehler als `WP_Error` mit Code und deutscher Meldung: `mlab_ls_invalid_license` 401 «Der Lizenzschlüssel ist ungültig. Bitte prüfe ihn oder melde dich bei MomentumLab.», `mlab_ls_site_limit` 403 «Diese Lizenz ist bereits auf der maximalen Anzahl Websites aktiv. Bitte melde dich bei MomentumLab.», `mlab_ls_not_unlocked` 403, `mlab_ls_rate_limited` 429, `mlab_ls_not_found` 404, `mlab_ls_not_configured` 503.

- [ ] **Step 1:** Permission-Callbacks: Rate-Limit prüfen; Schlüssel vorhanden → Lizenz laden, ungültig → `RateLimit::fail` + 401; Host via `Host::normalize( X-MLAB-Site )` und `Store::touch_activation` → `false` = 403. Ohne Schlüssel: nur Gratis-Produkte. `/download`: nur Signatur prüfen, Ergebnis-Lizenz muss noch aktiv und freigeschaltet sein.
- [ ] **Step 2:** `download_url` in `/update` = `rest_url( "mlab-ls/v1/download/<slug>" )` + `?t=` `Signature::create( slug, license_id oder 0, time() + signature_ttl, MLAB_LS_SIGNING_KEY )`.
- [ ] **Step 3:** `php -l`, `composer lint` → 0 Fehler; `composer test` → PASS.
- [ ] **Step 4: Commit** `v0.1.0 — feat: rest api for catalog, updates and downloads`

### Task 7: Admin-Oberfläche

**Files:**
- Create: `inc/admin/menu.php`, `inc/admin/class-licenses-table.php`, `inc/admin/license-edit.php`, `inc/admin/products.php`, `inc/admin/packages.php`, `inc/admin/notices.php`

**Interfaces:**
- Consumes: `Store`, `LicenseKey`, `GitHub::flush`.
- Produces: Menü «Lizenzen» (Icon `dashicons-admin-network`) mit Unterseiten «Lizenzen», «Plugins», «Pakete». Alle Formulare über `admin-post.php` mit Nonce und `manage_options`, danach Redirect mit Erfolgsmeldung.

- [ ] **Step 1:** Lizenzen: Tabelle (Kunde, E-Mail, Schlüssel `…<hint>`, Status, Websites `n/max`, Freischaltungen), Knopf «Neue Lizenz». Formular: Kunde, E-Mail, Websites (Default `default_max_sites`), Notiz, Status; Freischaltungen hinzufügen (Plugin oder Paket, «gültig bis» leer = unbefristet) und entfernen; Aktivierungen mit «Entfernen». Nach dem Anlegen den Klartext-Schlüssel einmal anzeigen, mit Kopieren-Knopf und Hinweis «Der Schlüssel wird nur jetzt angezeigt.»
- [ ] **Step 2:** Plugins: Liste und Formular (Slug, Name, Beschreibung, Repo `owner/name`, Hauptdatei Default `<slug>/<slug>.php`, privat, gratis, aktiv), Knopf «Release neu laden» (`GitHub::flush`), Spalte «Neueste Version».
- [ ] **Step 3:** Pakete: Name, Slug, Plugins als Checkboxen.
- [ ] **Step 4:** Hinweise, wenn `MLAB_LS_GITHUB_TOKEN` oder `MLAB_LS_SIGNING_KEY` fehlt.
- [ ] **Step 5:** `composer lint` → 0 Fehler.
- [ ] **Step 6: Commit** `v0.1.0 — feat: admin pages for licenses, plugins and packages`

### Task 8: End-to-End-Prüfung auf echtem WordPress

**Files:**
- Create: `tests/e2e/run.sh` (nicht im ZIP)

- [ ] **Step 1:** Skript lädt WordPress (neueste Version) und das SQLite-Integration-Plugin in ein temporäres Verzeichnis, startet `php -S`, installiert WordPress und aktiviert `mlab-license-server` (über WP-CLI-Phar), setzt `MLAB_LS_SIGNING_KEY`.
- [ ] **Step 2:** Per WP-CLI `eval`: ein Gratis-Produkt und ein Bezahl-Produkt (öffentliches Test-Repo `momentumlab-ch/mlab-core` als Quelle), eine Lizenz mit Freischaltung auf das Bezahl-Produkt und `max_sites` 1.
- [ ] **Step 3:** `curl`-Prüfungen, Erwartung jeweils als Statuscode: `/catalog` ohne Schlüssel → 200, nur Gratis `unlocked`; mit Schlüssel → 200, beide `unlocked`; falscher Schlüssel → 401; zweite Website mit gleichem Schlüssel → 403; `staging.kunde.ch` → 200; `/update/<bezahl>` ohne Schlüssel → 403; `/download` mit manipuliertem Token → 403.
- [ ] **Step 4:** Alle Prüfungen grün; Ergebnis in die PR-Beschreibung.
- [ ] **Step 5: Commit** `v0.1.0 — test: end-to-end check on WordPress`

### Task 9: Abschluss

- [ ] **Step 1:** `/simplify` über den ganzen Branch, Funde umsetzen.
- [ ] **Step 2:** Security-Review des ganzen Branches durch das stärkste Modell (Rechte, Eingaben, SQL, Signatur, Secrets), Funde umsetzen.
- [ ] **Step 3:** Version 0.1.0 in Header, Konstante, `Stable tag` geprüft; ZIP lokal wie in `release.yml` bauen und Checkliste aus `release-and-updates.md` prüfen (ein Ordner `mlab-license-server/`, `lib/` enthalten, keine Dev-Dateien).
- [ ] **Step 4:** `composer lint`, `composer test`, `tests/e2e/run.sh` → alles grün.
- [ ] **Step 5:** PR `feature/license-server` → `main`. Kein Merge, kein Tag.
