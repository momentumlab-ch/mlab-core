# MomentumLab Core und Lizenzserver – Design

Stand: 2026-10-17

## Warum

MomentumLab installiert bei Kunden inzwischen 8 eigene WordPress-Plugins, jedes einzeln. Ziel: ein einziges Plugin («MomentumLab Core») beim Kunden, über das die übrigen MomentumLab-Plugins per Schalter installiert, aktiviert und aktuell gehalten werden. Gleichzeitig sollen Plugins verkauft werden können, als Abo oder als Einmalkauf, bezahlt per Rechnung (manuelle Freischaltung) oder online.

## Ziele

- Ein Plugin beim Kunden: Lizenzschlüssel eingeben, Plugins per Schalter ein- und ausschalten. Höchstens drei Klicks pro Plugin, auf dem Handy bedienbar.
- Jedes MomentumLab-Plugin behält sein eigenes GitHub-Repo und seine eigenen Releases (`<slug>.zip` aus der Release-Action).
- Öffentliche und private Repos funktionieren gleich. Der GitHub-Token liegt nur auf dem Lizenzserver, nie beim Kunden.
- Freischaltungen pro Plugin oder pro Paket, mit Ablaufdatum (Abo) oder unbefristet (Einmalkauf). Ein Einmalkauf erhält alle Updates, solange das Produkt weitergeführt wird; wird es eingestellt, endet der Update-Anspruch (bestätigt von Chris).
- Updates erscheinen im normalen WordPress-Update-Bereich, Auto-Update bleibt nutzbar.
- Ein neues Plugin anbieten heisst: auf dem Lizenzserver einen Eintrag anlegen. Der Core muss dafür nicht neu released werden.

## Nicht-Ziele (erste Version)

- Online-Kauf mit Stripe. Kommt als eigener Schritt; das Datenmodell ist dafür vorbereitet (siehe «Später»).
- Kundenportal auf momentumlab.ch, in dem Kunden selbst ihre Lizenzen verwalten.
- Löschen von Plugins über den Core. Ausschalten deaktiviert nur; Löschen bleibt in der normalen Plugin-Liste.

## Anti-Ziele

- Ein abgelaufenes Abo darf die Kunden-Website nie kaputt machen. Bei Ablauf gibt es keine Updates und keine Neuinstallation mehr; bereits installierte Plugins laufen weiter.
- Ein Ausfall des Lizenzservers darf die Kunden-Website nie verlangsamen oder blockieren (kurze Timeouts, Cache, kein Aufruf im Frontend).
- Kein GitHub-Token, kein privater Code und kein Lizenz-Klartext in Logs, Repos oder beim Kunden ausserhalb des Lizenzfelds.

## Teile

Zwei Plugins, je eigenes Repo:

| Plugin | Slug | Läuft auf | Repo |
|---|---|---|---|
| MomentumLab Core | `mlab-core` | Kunden-Websites | `momentumlab-ch/mlab-core` (dieses Repo) |
| MomentumLab Lizenzserver | `mlab-license-server` | momentumlab.ch (eigenständig, eigenes WordPress) | eigenes Repo, z. B. `momentumlab-ch/mlab-license-server` |

Beide folgen den MomentumLab-WordPress-Regeln (Struktur `inc/`, Präfix, `inc/options.php`, PHPCS, Release-Action, `.distignore`, übersetzbar, Deutsch als Quelle mit `de_CH`).

## Lizenzserver (`mlab-license-server`)

### Daten (eigene Tabellen über `$wpdb` und `dbDelta`)

- **Produkte:** Slug, Name, Kurzbeschreibung, GitHub-Repo (`owner/name`), Hauptdatei (Standard `<slug>/<slug>.php`), privat ja/nein, gratis ja/nein, aktiv ja/nein.
- **Pakete:** Name und die enthaltenen Produkte.
- **Lizenzen:** Kunde (Name, E-Mail), Schlüssel (nur als Hash gespeichert, Klartext wird einmal beim Anlegen angezeigt), Status (aktiv, gesperrt), maximale Anzahl Websites (Standard 1), Notiz.
- **Freischaltungen:** Lizenz, Produkt oder Paket, gültig bis (leer = unbefristet), Quelle (`manual`, später `stripe`) und externe Referenz.
- **Aktivierungen:** Lizenz, Website-Host, zuletzt gesehen. Der Host wird normalisiert: Kleinbuchstaben, ohne Schema, ohne `www.`, ohne Pfad. Hosts wie `localhost`, `*.local`, `*.test` und `staging.*` zählen nicht zum Website-Limit (Entscheid des Builders, im Admin sichtbar).

Ein Produkt ist für eine Lizenz freigeschaltet, wenn eine aktive Freischaltung das Produkt direkt oder über ein Paket enthält und ihr Datum leer oder in der Zukunft ist. Gratis-Produkte sind für alle freigeschaltet, auch ohne Lizenz.

### Admin (nur `manage_options`)

- Produkte und Pakete anlegen und bearbeiten.
- Lizenz anlegen: Kunde, Freischaltungen, Ablaufdatum. Danach wird der Schlüssel einmal angezeigt, mit Knopf zum Kopieren.
- Lizenz sperren, verlängern, Freischaltungen ändern, Aktivierungen sehen und eine Website entfernen.

### REST-API (`mlab-ls/v1`)

Der Core schickt den Schlüssel im Header `X-MLAB-License` und die Website-URL im Header `X-MLAB-Site`, nie in der URL.

- `GET /catalog`: alle aktiven Produkte mit Name, Beschreibung, neuester Version und ob sie für diese Lizenz freigeschaltet sind. Ohne Schlüssel: nur Gratis-Produkte als freigeschaltet. Registriert die Website als Aktivierung, sofern das Website-Limit es erlaubt; sonst Fehler mit verständlicher Meldung.
- `GET /update/<slug>`: Update-Metadaten im JSON-Format, das Plugin Update Checker v5 erwartet (Version, `download_url`, `requires`, `requires_php`, `tested`, Changelog). Nur für freigeschaltete Produkte; sonst 403.
- `GET /download/<slug>?t=<signatur>`: liefert das Release-ZIP. Die `download_url` aus `/update` enthält eine HMAC-signierte, kurz gültige Signatur (Slug, Lizenz-ID, Ablaufzeit 2 Tage, länger als der 12-Stunden-Abstand der WordPress-Update-Prüfung). So taucht der Schlüssel nie in einer URL oder einem Log auf. Der Server holt das Asset `<slug>.zip` des neuesten Releases über die GitHub-API und streamt es durch.

Gratis-Produkte: `/update` und `/download` funktionieren ohne Schlüssel, die Signatur trägt dann die Lizenz-ID 0.

Alle Routen mit `permission_callback`, die den Schlüssel prüft (bei `/download` die Signatur). Begrenzung gegen Durchprobieren: 10 Fehlversuche pro IP (`REMOTE_ADDR`) in 15 Minuten, danach 15 Minuten Sperre, über Transients.

Als Release gilt das neueste veröffentlichte Release (keine Entwürfe, keine Pre-Releases) mit dem Asset `<slug>.zip`. Fehlt das Asset, gibt es kein Update. `requires`, `requires_php`, `tested` und der Changelog kommen aus `readme.txt` im Repo zum Tag des Releases.

### Secrets (`wp-config.php` auf momentumlab.ch)

- `MLAB_LS_GITHUB_TOKEN`: fine-grained Token, Leserecht *Contents* auf die Plugin-Repos.
- `MLAB_LS_SIGNING_KEY`: Schlüssel für die Download-Signatur.

Release-Infos von GitHub werden pro Produkt eine Stunde gecacht, damit das API-Limit nie erreicht wird.

## Core (`mlab-core`)

### Einstellungsseite «MomentumLab»

- Feld für den Lizenzschlüssel. Alternativ Konstante `MLAB_CORE_LICENSE_KEY` in `wp-config.php`; dann ist das Feld gesperrt und zeigt das an.
- Liste aller Produkte aus `/catalog`, je mit Name, Kurzbeschreibung, Version und Schalter:
  - freigeschaltet und nicht installiert → Schalter installiert und aktiviert in einem Schritt;
  - installiert → Schalter aktiviert oder deaktiviert;
  - nicht freigeschaltet → kein Schalter, Hinweis «Auf Anfrage» mit Kontaktlink zu MomentumLab.
- Nur für `install_plugins` bzw. `activate_plugins`, mit Nonce. Katalog 12 Stunden gecacht, mit Knopf «Neu laden».
- Fehlermeldungen sagen, was zu tun ist (z. B. «Der Lizenzschlüssel ist ungültig. Bitte prüfe ihn oder melde dich bei MomentumLab.»).

### Installieren und Updates

- Installation über die WordPress-API (`Plugin_Upgrader`) mit der `download_url` aus `/update/<slug>`. Keine eigene Download-Routine.
- Updates über Plugin Update Checker v5: Für jedes installierte MomentumLab-Plugin registriert der Core einen Update-Checker mit der Metadaten-URL `/update/<slug>` und hängt die beiden Header an. Das Update erscheint im normalen Update-Bereich.
- Die verwalteten Plugins brauchen eine kleine Anpassung in ihrer `inc/updater.php`: Ist der Core aktiv, registrieren sie keinen eigenen GitHub-Updater und zeigen keinen Token-Hinweis. Ohne Core funktionieren sie wie bisher.
- Der Core selbst aktualisiert sich über sein eigenes GitHub-Repo (Vorlage `templates/updater.php`), unabhängig von der Lizenz.

### Konstanten

- `MLAB_CORE_SERVER_URL`: Standard `https://momentumlab.ch`, nur für Tests überschreiben.
- `MLAB_CORE_LICENSE_KEY`: optional, siehe oben.

Server-Aufrufe nur im Admin oder im Update-Cron von WordPress, Timeout 5 Sekunden. Ein MomentumLab-Plugin erkennt der Core an der Hauptdatei aus dem Katalog. Antwortet der Server mit «Lizenz ungültig oder abgelaufen», verwirft der Core den Katalog-Cache sofort; ist der Server nicht erreichbar, zeigt er den letzten Stand mit Hinweis.

## Reihenfolge

1. Lizenzserver: Daten, Admin, REST-API, Release.
2. Core: Einstellungsseite, Installation, Updates, Release.
3. Die 8 Plugins: Anpassung in `inc/updater.php`, je ein Release.
4. Erster Kunde umstellen, dann alle.
5. Später: Online-Kauf mit Stripe.

## Später: Online-Kauf

Stripe Checkout für Einmalkauf und Abo, in CHF, mit TWINT wo verfügbar. Der Core zeigt bei nicht freigeschalteten Plugins einen Kaufen-Knopf, der zu Stripe Checkout führt. Ein Stripe-Webhook auf dem Lizenzserver legt die Freischaltung an (Quelle `stripe`) und setzt bei Abo-Verlängerung oder Kündigung das Ablaufdatum. Preise werden pro Produkt bzw. Paket in Stripe gepflegt.

## Dem Builder überlassen

- Aufteilung in Dateien unter `inc/`, Namen von Funktionen und Tabellen (Präfix `mlab_ls_` bzw. `mlab_core_`).
- Admin-Oberfläche des Lizenzservers: WordPress-Standardlisten (`WP_List_Table`) und einfache Formulare reichen.
- Form des Fehler-JSON der REST-API.
- Tests: wo sinnvoll PHPUnit für die Freischaltungslogik und die Signatur.
