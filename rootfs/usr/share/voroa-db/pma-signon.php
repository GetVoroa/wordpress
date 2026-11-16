<?php
// phpMyAdmin SignonScript (auth_type=signon): hands over this site's own database account. Loaded only inside
// gate.php, after the door cookie was checked, and db.php is already required there. Nothing is stored: no signon
// session, no cookie - the password stays in this process.
declare(strict_types=1);

if (!defined('VOROA_PMA_DOOR')) { http_response_code(404); exit; }

if (!function_exists('get_login_credentials')) {
	function get_login_credentials($user) {
		return [VOROA_DB_USER, VOROA_DB_PASSWORD];
	}
}
