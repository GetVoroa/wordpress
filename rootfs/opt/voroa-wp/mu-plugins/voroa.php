<?php
/**
 * Plugin Name: Voroa platform
 * Description: Core updates come from the image, outgoing email over SMTP, page cache purge on change.
 */

if (!defined('ABSPATH')) {
	exit;
}

// ---- Core updates come from the image (redeploy a newer Voroa WordPress version) --------------
add_filter('map_meta_cap', function ($caps, $cap) {
	if ($cap === 'update_core') {
		return array('do_not_allow');
	}
	return $caps;
}, 10, 2);
add_filter('pre_site_transient_update_core', function () {
	global $wp_version;
	return (object) array('last_checked' => time(), 'version_checked' => $wp_version, 'updates' => array());
});

// A plugin or theme update must take effect at once, not after the OPcache revalidate window.
add_action('upgrader_process_complete', function () {
	if (function_exists('opcache_reset')) {
		opcache_reset();
	}
	voroa_purge_page_cache();
});

// ---- Outgoing email over the customer's SMTP provider ----------------------------------------
add_action('phpmailer_init', function ($mailer) {
	$host = getenv('SMTP_HOST');
	if (!$host) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host = $host;
	$mailer->Port = (int) (getenv('SMTP_PORT') ?: 587);
	$user = getenv('SMTP_USER');
	if ($user) {
		$mailer->SMTPAuth = true;
		$mailer->Username = $user;
		$mailer->Password = (string) getenv('SMTP_PASSWORD');
	}
	$secure = getenv('SMTP_SECURE') ?: 'tls';
	$mailer->SMTPSecure = $secure === 'none' ? '' : $secure;
	$from = getenv('SMTP_FROM');
	if ($from) {
		$mailer->setFrom($from, get_bloginfo('name'), false);
	}
});

// ---- Page cache (nginx) purge when content changes -------------------------------------------
function voroa_purge_page_cache() {
	$dir = '/var/cache/nginx/wp';
	if (!is_dir($dir)) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $f) {
		// A file nginx is still writing may vanish under us - nothing to report.
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
}
foreach (array('save_post', 'deleted_post', 'trashed_post', 'edit_post', 'comment_post', 'wp_set_comment_status',
	'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'update_option_sidebars_widgets') as $voroa_hook) {
	add_action($voroa_hook, 'voroa_purge_page_cache');
}
unset($voroa_hook);

// ---- Keep the stored site address useful for emails and scheduled jobs ------------------------
// Pages answer on whichever address they are opened on (wp-config). Scheduled jobs have no address, so they use
// the stored one: keep it on the customer's own domain once one is used, else on the Voroa address.
add_action('init', function () {
	if (!defined('WP_HOME') || getenv('WP_HOME') || empty($_SERVER['HTTP_HOST'])) {
		return;
	}
	global $wpdb;
	// The raw stored value: get_option('home') returns the WP_HOME constant once it is defined.
	$stored = (string) $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'home'");
	$voroa = '/\.(onvoroa|getvoroa)\.com(:\d+)?$/i';
	$stored_host = (string) parse_url($stored, PHP_URL_HOST);
	$current_host = (string) parse_url(WP_HOME, PHP_URL_HOST);
	// A health probe or a loopback call (127.0.0.1, localhost, a bare container name) is not a real address.
	if ($current_host === '' || strpos($current_host, '.') === false || filter_var($current_host, FILTER_VALIDATE_IP)) {
		return;
	}
	$stale = $stored_host === 'localhost' || $stored_host === ''
		|| (preg_match($voroa, $stored_host) && !preg_match($voroa, $current_host));
	if ($stale && $stored !== WP_HOME) {
		// Direct write: update_option() compares against the constant and would skip it as "unchanged".
		$wpdb->update($wpdb->options, array('option_value' => WP_HOME), array('option_name' => 'home'));
		$wpdb->update($wpdb->options, array('option_value' => WP_HOME), array('option_name' => 'siteurl'));
		wp_cache_delete('alloptions', 'options');
	}
});
