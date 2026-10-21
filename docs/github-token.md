# GitHub-Token für private Repositories

MomentumLab Core verwaltet die Lese-Tokens, mit denen eine Website Updates aus privaten Repositories von MomentumLab holt.

## Reihenfolge

1. Konstante `MLAB_GITHUB_TOKEN` in der `wp-config.php` (gilt für alle Repositories, hat Vorrang).
2. Tokens aus «MomentumLab › GitHub-Zugang», pro Organisation (`momentumlab-ch`) oder pro Repository (`momentumlab-ch/sid-events`). Ein Eintrag für ein Repository gewinnt gegen den für die Organisation.
3. Die eigene Konstante des Plugins oder Themes (z. B. `SID_EVENTS_GITHUB_TOKEN`), als Ersatz ohne Core.

## Speicherung

- Verschlüsselt mit sodium (secretbox), sonst AES-256-GCM. Schlüssel: HMAC aus `AUTH_KEY` und `SECURE_AUTH_KEY`.
- Option `mlab_core_github_tokens`, ohne Autoload, nicht in der REST-API, nie im Frontend oder in Logs.
- Ändern sich die Sicherheitsschlüssel der Website, lässt sich das Token nicht mehr lesen; dann neu eintragen.

## In einem Plugin oder Theme nutzen (`inc/updater.php`)

```php
$token = (string) apply_filters( 'mlab_github_token', '', 'momentumlab-ch/<repo>' );
if ( '' === $token && defined( '<PREFIX>_GITHUB_TOKEN' ) ) {
	$token = (string) constant( '<PREFIX>_GITHUB_TOKEN' );
}
```

Ohne Core gibt der Filter einen leeren Text zurück, dann greift die eigene Konstante.

## Token bei GitHub erzeugen

github.com › Profilbild › Settings › Developer settings › Personal access tokens › Fine-grained tokens › Generate new token:

- Resource owner: `momentumlab-ch`
- Expiration: z. B. 1 Jahr
- Repository access: Only select repositories (nur die privaten Repositories der Website)
- Repository permissions: Contents: Read-only (Metadata: Read-only kommt automatisch)

## Weitere Geheimnisse

Andere MomentumLab-Plugins speichern Zugangsdaten über dieselbe Verschlüsselung:

```php
if ( function_exists( 'mlab_core_secret_set' ) ) {
	mlab_core_secret_set( '<plugin-slug>/<name>', $value ); // Leerer Wert löscht.
}
$value = function_exists( 'mlab_core_secret_get' ) ? mlab_core_secret_get( '<plugin-slug>/<name>' ) : '';
```

Option `mlab_core_secrets`, ohne Autoload, Werte nur verschlüsselt.
