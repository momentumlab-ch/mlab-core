# Changelog

## 0.4.1 — 2026-10-23

- Updates über GitHub funktionieren wieder zuverlässig: Ein Teil der Update-Bibliothek fehlte im Installationspaket.

## 0.4.0 — 2026-10-22

- Website-Gesundheit: jeden Morgen um 06:00 prüft Core, ob die Startseite antwortet, wie lange das SSL-Zertifikat gilt, welche Updates anstehen, ob PHP-Fehler im debug.log stehen, wann das letzte Backup lief (UpdraftPlus, Jetpack) und ob auffällig viele 404 auftreten (MomentumLab SEO). Ergebnis auf der Seite MomentumLab, Knopf «Jetzt prüfen». Alarm per E-Mail bei einem neuen Problem, höchstens einmal pro Tag und Problem; Empfänger leer = aus. Für Entwickler: mlab_core_health() und der Filter mlab_core_health_checks.

## 0.3.0 — 2026-10-21

- Für Entwickler: verschlüsselter Speicher für Zugangsdaten anderer MomentumLab-Plugins (mlab_core_secret_set, mlab_core_secret_get, mlab_core_secret_exists), gleiche Verschlüsselung wie die GitHub-Tokens. MomentumLab Insights speichert darin den Google-Zugang.

## 0.2.0 — 2026-10-21

- GitHub-Zugang für Updates: Lese-Tokens für private Repositories von MomentumLab im Backend eintragen (MomentumLab › GitHub-Zugang), pro Organisation oder pro Repository. Die Tokens werden mit den Sicherheitsschlüsseln der Website verschlüsselt gespeichert und nie angezeigt, nur die letzten vier Zeichen. «Verbindung testen» zeigt, ob GitHub das Token annimmt, und das Ablaufdatum.
- Die Konstante MLAB_GITHUB_TOKEN in der wp-config.php hat Vorrang und gilt für alle Repositories.
- Für Entwickler: Plugins und Themes holen das Token über den Filter mlab_github_token (zweiter Parameter: owner/repo) und behalten ihre eigene Konstante als Ersatz.

## 0.1.0 — 2026-10-17

- Erste Version: Lizenzschlüssel eingeben und MomentumLab-Plugins als Karten installieren, aktivieren und ausschalten.
- Updates erscheinen im normalen WordPress-Update-Bereich.
- Ein abgelaufenes Abo oder ein nicht erreichbarer Server schaltet nie ein Plugin aus.
