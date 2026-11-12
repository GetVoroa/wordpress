<?php
/**
 * Voroa WordPress configuration. Everything comes from environment variables:
 *   DATABASE_URL   mysql://user:password@host:port/database   (copy it from your MySQL "Connect" tab)
 *   or WORDPRESS_DB_HOST / WORDPRESS_DB_USER / WORDPRESS_DB_PASSWORD / WORDPRESS_DB_NAME
 *   WP_DEBUG=1     turn on debug logging (to the service logs, never shown to visitors)
 *   SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASSWORD / SMTP_FROM / SMTP_SECURE   outgoing email
 * Security keys are generated once and kept in wp-content/voroa/salts.php.
 */

function voroa_env($name, $default = '') {
	$v = getenv($name);
	return ($v === false || $v === '') ? $default : $v;
}

$voroa_db = array(
	'host' => voroa_env('WORDPRESS_DB_HOST', 'localhost'),
	'user' => voroa_env('WORDPRESS_DB_USER', ''),
	'pass' => voroa_env('WORDPRESS_DB_PASSWORD', ''),
	'name' => voroa_env('WORDPRESS_DB_NAME', 'wordpress'),
);
$voroa_url = voroa_env('DATABASE_URL');
if ($voroa_url !== '') {
	$p = parse_url($voroa_url);
	if (is_array($p) && isset($p['host'])) {
		$voroa_db['host'] = $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
		$voroa_db['user'] = isset($p['user']) ? rawurldecode($p['user']) : '';
		$voroa_db['pass'] = isset($p['pass']) ? rawurldecode($p['pass']) : '';
		$voroa_db['name'] = isset($p['path']) && trim($p['path'], '/') !== '' ? rawurldecode(trim($p['path'], '/')) : $voroa_db['name'];
	}
}

define('DB_NAME', $voroa_db['name']);
define('DB_USER', $voroa_db['user']);
define('DB_PASSWORD', $voroa_db['pass']);
define('DB_HOST', $voroa_db['host']);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
unset($voroa_db, $voroa_url, $p);

$table_prefix = voroa_env('WORDPRESS_TABLE_PREFIX', 'wp_');

if (is_readable(__DIR__ . '/wp-content/voroa/salts.php')) {
	require __DIR__ . '/wp-content/voroa/salts.php';
}

// TLS ends at the platform edge.
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
	$_SERVER['HTTPS'] = 'on';
}

// Scheduled events run every minute inside the container, not on visitor page loads.
define('DISABLE_WP_CRON', true);

// Core comes from the image: updated by redeploying a newer Voroa WordPress version.
define('WP_AUTO_UPDATE_CORE', false);
define('DISALLOW_FILE_EDIT', true);
define('FS_METHOD', 'direct');

// Voroa's own must-use plugins live in the image, so every update patches them.
define('WPMU_PLUGIN_DIR', '/opt/voroa-wp/mu-plugins');

define('WP_POST_REVISIONS', 20);
define('EMPTY_TRASH_DAYS', 30);
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '256M');

$voroa_debug = voroa_env('WP_DEBUG') === '1';
define('WP_DEBUG', $voroa_debug);
define('WP_DEBUG_LOG', $voroa_debug ? 'php://stderr' : false);
define('WP_DEBUG_DISPLAY', false);
unset($voroa_debug);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
