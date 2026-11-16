<?php
// The database browser's door (Voroa plan 2026-11-13 PR E, phpMyAdmin since plan 2026-11-16). phpMyAdmin is OFF
// unless the Voroa panel opened it: the panel mints a random token, hands this site only its SHA-256 (voroa-wp
// adminer_grant, 10-minute expiry), and the browser arrives with ?voroa_t=<token>. One use only. It becomes a session
// cookie (HttpOnly, Secure, SameSite=Strict, 30 minutes idle). The database login happens server-side
// (pma-signon.php) - the password never reaches the browser. Anything else answers 404, so the page does not exist for
// anyone who was not sent.
declare(strict_types=1);

const STATE = '/var/www/html/wp-content/voroa/adminer'; // naam purana hi: r4/r5 ke grants yahin hain, rename = update ke beech grants orphan
const PMA = '/usr/share/voroa-pma';
const COOKIE = 'voroa_db';
const IDLE = 1800;

function nope(): void { http_response_code(404); header('Content-Type: text/plain'); echo "Not found\n"; exit; }
function h(string $s): string { return hash('sha256', $s); }
// A terminal page: never redirects back into phpMyAdmin, so a failing login cannot loop.
function finish(string $title, string $text): void {
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-store');
	echo '<!doctype html><meta charset="utf-8"><title>' . $title . '</title><p>' . $text . '</p>';
	exit;
}

if (!is_file(PMA . '/index.php') || !is_readable('/data/voroa/db.php')) nope();
@mkdir(STATE . '/grants', 0700, true);
@mkdir(STATE . '/sessions', 0700, true);

// 1) arriving with a one-time token: burn it, start a session, then come back same-site (a Strict cookie is not sent
//    on the cross-site navigation that set it).
if (isset($_GET['voroa_t'])) {
	$t = (string) $_GET['voroa_t'];
	if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $t)) nope();
	$g = STATE . '/grants/' . h($t);
	$exp = is_file($g) ? (int) file_get_contents($g) : 0;
	// unlink sirf ek request ke liye succeed hota hai - do saath aaye to doosra 404 (race-safe one-time)
	if (!@unlink($g) || $exp < time()) nope();
	$sid = bin2hex(random_bytes(32));
	file_put_contents(STATE . '/sessions/' . h($sid), 'new');
	setcookie(COOKIE, $sid, ['path' => '/voroa-db/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-store');
	echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=/voroa-db/"><title>Opening the database</title><p>Opening the database...</p>';
	exit;
}

// 2) every other request needs a live session
$sid = (string) ($_COOKIE[COOKIE] ?? '');
if (!preg_match('/^[0-9a-f]{64}$/', $sid)) nope();
$sf = STATE . '/sessions/' . h($sid);
if (!is_file($sf) || filemtime($sf) < time() - IDLE) { @unlink($sf); nope(); }

// phpMyAdmin's Log out (LogoutURL) and any login failure (SignonURL) land here: end the door session, show a final page.
if (isset($_GET['voroa_logout']) || isset($_GET['voroa_expired'])) {
	@unlink($sf);
	setcookie(COOKIE, '', ['expires' => 1, 'path' => '/voroa-db/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
	if (isset($_GET['voroa_logout'])) finish('Signed out', 'Signed out of the database. Open it again from the Voroa panel.');
	finish('Link expired', 'This database session has ended. Open the database again from the Voroa panel.');
}
file_put_contents($sf, 'live'); // also refreshes the idle clock

// 3) only phpMyAdmin's front controller (5.2 routes everything through index.php?route=...). Any other path - a
//    leftover script, setup/, a .php under js/ - is 404.
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($path !== '/voroa-db/' && $path !== '/voroa-db/index.php') nope();

// phpMyAdmin keeps its OWN session cookie (CSRF token, id regenerated after login) - it opens nothing without the
// door cookie above, because this file 404s first. Credentials come from pma-signon.php (SignonScript).
require '/data/voroa/db.php';
define('VOROA_PMA_DOOR', true); // config.inc.php refuses to load without it
chdir(PMA);
require PMA . '/index.php';
