=== MomentumLab Core ===
Contributors: momentumlab
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Installiert, aktiviert und aktualisiert MomentumLab-Plugins mit einem Lizenzschlüssel.

== Description ==

Mit deinem Lizenzschlüssel zeigt MomentumLab Core alle freigeschalteten Plugins an. Ein Klick installiert und aktiviert ein Plugin. Updates erscheinen im normalen WordPress-Update-Bereich.

Der Schlüssel kann auch als Konstante MLAB_CORE_LICENSE_KEY in der wp-config.php stehen.

== Changelog ==

= 0.3.0 =
* Für Entwickler: verschlüsselter Speicher für Zugangsdaten anderer MomentumLab-Plugins (mlab_core_secret_set, mlab_core_secret_get, mlab_core_secret_exists), gleiche Verschlüsselung wie die GitHub-Tokens. MomentumLab Insights speichert darin den Google-Zugang.

= 0.2.0 =
* GitHub-Zugang für Updates: Lese-Tokens für private Repositories von MomentumLab im Backend eintragen (MomentumLab › GitHub-Zugang), pro Organisation oder pro Repository. Die Tokens werden mit den Sicherheitsschlüsseln der Website verschlüsselt gespeichert und nie angezeigt, nur die letzten vier Zeichen. «Verbindung testen» zeigt, ob GitHub das Token annimmt, und das Ablaufdatum.
* Die Konstante MLAB_GITHUB_TOKEN in der wp-config.php hat Vorrang und gilt für alle Repositories.
* Für Entwickler: Plugins und Themes holen das Token über den Filter mlab_github_token (zweiter Parameter: owner/repo) und behalten ihre eigene Konstante als Ersatz.

= 0.1.0 =
* Erste Version: MomentumLab-Plugins mit einem Lizenzschlüssel installieren, ein- und ausschalten und aktualisieren.
