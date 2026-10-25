=== MomentumLab Core ===
Contributors: momentumlab
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Installiert, aktiviert und aktualisiert MomentumLab-Plugins mit einem Lizenzschlüssel.

== Description ==

Mit deinem Lizenzschlüssel zeigt MomentumLab Core alle freigeschalteten Plugins an. Ein Klick installiert und aktiviert ein Plugin. Updates erscheinen im normalen WordPress-Update-Bereich.

Der Schlüssel kann auch als Konstante MLAB_CORE_LICENSE_KEY in der wp-config.php stehen.

== Für Entwickler ==

* `mlab_core_product_unlocked( string $slug ): bool` sagt, ob die Lizenz dieser Website ein Produkt freischaltet. Das ist der Fall, wenn das Produkt im gespeicherten Katalog steht und dort als freigeschaltet markiert ist. Die Funktion liest nur den gespeicherten Katalog, ohne Anfrage an den Server, und funktioniert auch im Frontend. Vor dem Aufruf mit `function_exists()` prüfen, falls Core fehlen kann.
* Bekanntes Verhalten: Der Katalog wird nur im Backend neu geladen (spätestens nach 12 Stunden, oder mit «Neu laden»). Nach einer Kündigung oder einer ungültig gewordenen Lizenz bleibt ein Produkt deshalb freigeschaltet, bis der Katalog das nächste Mal abgerufen wird. Erst dann liefert die Funktion false.
* `mlab_core_license_key()` gibt den Lizenzschlüssel zurück, der Filter `mlab_core_manages_plugin` sagt einem Plugin, ob Core seine Updates liefert.

== Changelog ==

= 0.4.2 =
* Für Entwickler: mlab_core_product_unlocked() sagt, ob die Lizenz dieser Website ein Produkt freischaltet, zum Beispiel die Pro-Funktionen von MomentumLab Social Wall.

= 0.4.1 =
* Updates über GitHub funktionieren wieder zuverlässig: Ein Teil der Update-Bibliothek fehlte im Installationspaket.

= 0.4.0 =
* Website-Gesundheit: jeden Morgen um 06:00 prüft Core, ob die Startseite antwortet, wie lange das SSL-Zertifikat gilt, welche Updates anstehen, ob PHP-Fehler im debug.log stehen, wann das letzte Backup lief (UpdraftPlus, Jetpack) und ob auffällig viele 404 auftreten (MomentumLab SEO). Ergebnis auf der Seite MomentumLab, Knopf «Jetzt prüfen». Alarm per E-Mail bei einem neuen Problem, höchstens einmal pro Tag und Problem; Empfänger leer = aus. Für Entwickler: mlab_core_health() und der Filter mlab_core_health_checks.

= 0.3.0 =
* Für Entwickler: verschlüsselter Speicher für Zugangsdaten anderer MomentumLab-Plugins (mlab_core_secret_set, mlab_core_secret_get, mlab_core_secret_exists), gleiche Verschlüsselung wie die GitHub-Tokens. MomentumLab Insights speichert darin den Google-Zugang.

= 0.2.0 =
* GitHub-Zugang für Updates: Lese-Tokens für private Repositories von MomentumLab im Backend eintragen (MomentumLab › GitHub-Zugang), pro Organisation oder pro Repository. Die Tokens werden mit den Sicherheitsschlüsseln der Website verschlüsselt gespeichert und nie angezeigt, nur die letzten vier Zeichen. «Verbindung testen» zeigt, ob GitHub das Token annimmt, und das Ablaufdatum.
* Die Konstante MLAB_GITHUB_TOKEN in der wp-config.php hat Vorrang und gilt für alle Repositories.
* Für Entwickler: Plugins und Themes holen das Token über den Filter mlab_github_token (zweiter Parameter: owner/repo) und behalten ihre eigene Konstante als Ersatz.

= 0.1.0 =
* Erste Version: MomentumLab-Plugins mit einem Lizenzschlüssel installieren, ein- und ausschalten und aktualisieren.
