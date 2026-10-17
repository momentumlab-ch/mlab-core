# Verwaltete Plugins anpassen

Damit ein bestehendes MomentumLab-Plugin seine Updates vom Core bekommt, fügst du in `inc/updater.php` ganz am Anfang von `<prefix>_register_updater()` diese Zeilen ein:

```php
if ( apply_filters( 'mlab_core_manages_plugin', false, '<slug>' ) ) {
	return; // Updates kommen über MomentumLab Core.
}
```

Ersetze `<slug>` durch den Plugin-Slug, zum Beispiel `mlab-seo`.

Ist der Core aktiv und kennt das Plugin, richtet das Plugin keinen eigenen GitHub-Updater ein und zeigt keinen Token-Hinweis. Ohne Core läuft alles wie bisher.

Danach ein neues Release des Plugins erstellen.
