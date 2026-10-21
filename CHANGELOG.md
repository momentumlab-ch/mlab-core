# Changelog

## 0.2.0 — 2026-10-21

- GitHub-Zugang für Updates: Lese-Tokens für private Repositories von MomentumLab im Backend eintragen (MomentumLab › GitHub-Zugang), pro Organisation oder pro Repository. Die Tokens werden mit den Sicherheitsschlüsseln der Website verschlüsselt gespeichert und nie angezeigt, nur die letzten vier Zeichen. «Verbindung testen» zeigt, ob GitHub das Token annimmt, und das Ablaufdatum.
- Die Konstante MLAB_GITHUB_TOKEN in der wp-config.php hat Vorrang und gilt für alle Repositories.
- Für Entwickler: Plugins und Themes holen das Token über den Filter mlab_github_token (zweiter Parameter: owner/repo) und behalten ihre eigene Konstante als Ersatz.

## 0.1.0 — 2026-10-17

- Erste Version: Lizenzschlüssel eingeben und MomentumLab-Plugins als Karten installieren, aktivieren und ausschalten.
- Updates erscheinen im normalen WordPress-Update-Bereich.
- Ein abgelaufenes Abo oder ein nicht erreichbarer Server schaltet nie ein Plugin aus.
