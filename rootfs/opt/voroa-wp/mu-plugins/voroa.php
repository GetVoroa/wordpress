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
