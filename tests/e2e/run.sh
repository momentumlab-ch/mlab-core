#!/usr/bin/env bash
# End-to-end check on a real WordPress (SQLite, built-in PHP server) against a mock license server.
# Env: WP_SRC (WordPress checkout), SQLITE_SRC (sqlite-database-integration checkout), WP_CLI (wp-cli.phar).
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="$(mktemp -d)"
PORT=8899
MOCK_PORT=8898
BASE="http://127.0.0.1:${PORT}"
MOCK="http://127.0.0.1:${MOCK_PORT}"
SERVER_PID=""
MOCK_PID=""

cleanup() {
	[ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null
	[ -n "$MOCK_PID" ] && kill "$MOCK_PID" 2>/dev/null
	rm -rf "$WORK"
}
trap cleanup EXIT

if [ -z "${WP_SRC:-}" ]; then
	WP_SRC="$WORK/src-wp"
	git clone --quiet --depth 1 --branch 6.8.3 https://github.com/WordPress/WordPress.git "$WP_SRC" || exit 1
fi
if [ -z "${SQLITE_SRC:-}" ]; then
	SQLITE_SRC="$WORK/src-sqlite"
	git clone --quiet --depth 1 https://github.com/WordPress/sqlite-database-integration.git "$SQLITE_SRC" || exit 1
fi
if [ -z "${WP_CLI:-}" ]; then
	WP_CLI="$WORK/wp-cli.phar"
	curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar || exit 1
fi

SQLITE_PLUGIN_SRC="$SQLITE_SRC"
[ -d "$SQLITE_SRC/packages/plugin-sqlite-database-integration" ] && SQLITE_PLUGIN_SRC="$SQLITE_SRC/packages/plugin-sqlite-database-integration"

# Demo plugin ZIP served by the mock server.
FIX="$WORK/fixtures"
mkdir -p "$FIX/demo-plugin"
cat > "$FIX/demo-plugin/demo-plugin.php" <<'PHP'
<?php
/**
 * Plugin Name: Demo Plugin
 * Version:     1.0.0
 */
PHP
(cd "$FIX" && zip -qr demo-plugin.zip demo-plugin) || { echo "zip failed"; exit 1; }

SITE="$WORK/site"
mkdir -p "$SITE"
cp -r "$WP_SRC/." "$SITE/"
rm -rf "$SITE/.git"
cp -rL "$SQLITE_PLUGIN_SRC" "$SITE/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" \
	-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
	"$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
ln -s "$PLUGIN_DIR" "$SITE/wp-content/plugins/mlab-core"

# Test setup only: WordPress blocks requests to 127.0.0.1 for download_url(). Allow them here, not in the plugin.
mkdir -p "$SITE/wp-content/mu-plugins"
cat > "$SITE/wp-content/mu-plugins/e2e-allow-local.php" <<PHP
<?php
add_filter( 'http_request_host_is_external', function ( \$external, \$host ) {
	return '127.0.0.1' === \$host ? true : \$external;
}, 10, 2 );
add_filter( 'http_allowed_safe_ports', function ( \$ports ) {
	\$ports[] = ${MOCK_PORT};
	return \$ports;
} );
PHP

cat > "$SITE/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'wp' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_DEBUG', false );
define( 'FS_METHOD', 'direct' );
define( 'MLAB_CORE_SERVER_URL', '${MOCK}' );
\$table_prefix = 'wp_';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

wp() { php "$WP_CLI" --path="$SITE" --allow-root "$@"; }
# Runs PHP on the site with the admin-only plugin files loaded.
weval() {
	wp eval 'require_once MLAB_CORE_DIR . "inc/admin/installer.php"; use MomentumLab\Core\Client; use MomentumLab\Core\Row; '"$1"
}

wp core install --url="$BASE" --title=E2E --admin_user=admin --admin_password=admin-e2e-pass --admin_email=admin@example.com --skip-email >/dev/null || { echo "WordPress install failed"; exit 1; }
wp plugin activate mlab-core >/dev/null || { echo "Plugin activation failed"; exit 1; }
wp option update mlab_core_license_key MLAB-GOOD >/dev/null

start_mock() {
	MOCK_ZIP="$FIX/demo-plugin.zip" MOCK_BASE="$MOCK" php -S "127.0.0.1:${MOCK_PORT}" "$PLUGIN_DIR/tests/e2e/mock-server.php" >"$WORK/mock.log" 2>&1 &
	MOCK_PID=$!
	for _ in $(seq 1 50); do
		curl -s -o /dev/null "$MOCK/" && break
		sleep 0.2
	done
}
start_mock

php -S "127.0.0.1:${PORT}" -t "$SITE" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 50); do
	curl -s -o /dev/null "$BASE/" && break
	sleep 0.2
done

FAILS=0
check() { # name, ok(0/1)
	if [ "$2" -eq 0 ]; then echo "PASS  $1"; else echo "FAIL  $1"; FAILS=$((FAILS + 1)); fi
}

# Logged-in admin session for the page checks.
JAR="$WORK/jar"
curl -s -c "$JAR" -b "wordpress_test_cookie=WP%20Cookie%20check" -o /dev/null \
	--data-urlencode "log=admin" --data-urlencode "pwd=admin-e2e-pass" -d "wp-submit=Log+In" "$BASE/wp-login.php"
page() { curl -s -b "$JAR" "$BASE/wp-admin/admin.php?page=mlab-core"; }
# post_action <action> <slug>: reads the nonce from the page like a browser would; prints the HTTP status.
post_action() {
	local nonce
	nonce=$(page | grep -o "name=\"action\" value=\"$1\" /><input type=\"hidden\" name=\"slug\" value=\"$2\" /><input type=\"hidden\" id=\"_wpnonce\" name=\"_wpnonce\" value=\"[a-z0-9]*\"" | sed 's/.*_wpnonce" value="//; s/"$//')
	curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -d "action=$1" -d "slug=$2" -d "_wpnonce=$nonce" "$BASE/wp-admin/admin-post.php"
}

# 1 catalog with a valid key
out=$(weval '$c = (new Client())->catalog( true ); $p = $c["products"]; echo count( $p ) . " " . Row::state( $p[0], false, false ) . " " . Row::state( $p[1], false, false ) . " [" . $c["error"] . "]";')
[ "$out" = "2 install request []" ]; check "1 catalog with valid key: 2 products, install + request (got: $out)" $?

# 7a page before install
page > "$WORK/pageA.html"; grep -q "Installieren und aktivieren" "$WORK/pageA.html"; A=$?

# 2 install
out=$(weval '$r = mlab_core_install_product( (new Client())->product( "demo-plugin" ) ); echo is_wp_error( $r ) ? $r->get_error_message() : "ok";')
active=1
wp plugin is-active demo-plugin >/dev/null 2>&1 && active=0
[ "$out" = "ok" ] && [ "$active" -eq 0 ] && [ -f "$SITE/wp-content/plugins/demo-plugin/demo-plugin.php" ]; check "2 install: demo-plugin installed and active (got: $out)" $?

# 7b page after install
page > "$WORK/pageB.html"; grep -q "Deaktivieren" "$WORK/pageB.html"; B=$?
[ "$A" -eq 0 ] && [ "$B" -eq 0 ]; check "7 admin page: 200 with 'Installieren und aktivieren' before and 'Deaktivieren' after install" $?

# 3 deactivate through the admin-post action
code=$(post_action mlab_core_deactivate demo-plugin)
active=0
wp plugin is-active demo-plugin >/dev/null 2>&1 || active=1
[ "$code" = 302 ] && [ "$active" -eq 1 ] && [ -d "$SITE/wp-content/plugins/demo-plugin" ]; check "3 deactivate: inactive, folder kept (http $code)" $?

# 8 admin-post without nonce is refused
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -d "action=mlab_core_activate" -d "slug=demo-plugin" "$BASE/wp-admin/admin-post.php")
[ "$code" = 403 ] && ! wp plugin is-active demo-plugin >/dev/null 2>&1; check "8 activate without nonce: 403 and still inactive (http $code)" $?

# 6 update checker sees 9.9.9
wp cron event run puc_cron_check_updates-demo-plugin >/dev/null 2>&1
wp eval 'delete_site_transient( "update_plugins" ); wp_update_plugins();' >/dev/null 2>&1
upd=$(wp plugin list --update=available --field=name 2>/dev/null)
echo "$upd" | grep -qx "demo-plugin"; check "6 update checker: demo-plugin update available (got: $upd)" $?

# 4 invalid key
wp option update mlab_core_license_key BAD >/dev/null
out=$(weval '$c = (new Client())->catalog( true ); echo count( $c["products"] ) . " [" . $c["error"] . "]";')
case "$out" in "0 [Der Lizenzschlüssel ist ungültig"*) r=0 ;; *) r=1 ;; esac
check "4 invalid key: empty catalog, server message (got: $out)" $r

# 5 server unreachable keeps the last state
wp option update mlab_core_license_key MLAB-GOOD >/dev/null
weval '(new Client())->catalog( true );' >/dev/null
kill "$MOCK_PID" 2>/dev/null; wait "$MOCK_PID" 2>/dev/null; MOCK_PID=""
out=$(weval '$c = (new Client())->catalog( true ); echo count( $c["products"] ) . " [" . $c["error"] . "]";')
case "$out" in "2 [Der MomentumLab-Server ist gerade nicht erreichbar"*) r=0 ;; *) r=1 ;; esac
check "5 server down: last state kept, standard message (got: $out)" $r

[ "$FAILS" -eq 0 ] || { echo "$FAILS check(s) failed"; tail -n 20 "$WORK/server.log"; exit 1; }
echo "All checks passed"
