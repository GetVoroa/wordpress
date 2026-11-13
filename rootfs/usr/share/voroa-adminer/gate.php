<?php
// The database browser's door (Voroa plan 2026-11-13 PR E, CTO r1 D3). Adminer is OFF unless the Voroa panel opened
// it: the panel mints a random token, hands this site only its SHA-256 (voroa-wp adminer_grant, 10-minute expiry),
// and the browser arrives with ?voroa_t=<token>. One use only. It becomes a session cookie (HttpOnly, Secure,
// SameSite=Strict, 30 minutes idle). The database login happens HERE, server-side - the password never reaches the
// browser. Anything else answers 404, so the page does not exist for anyone who was not sent.
declare(strict_types=1);

const STATE = '/var/www/html/wp-content/voroa/adminer';
const ADMINER = '/usr/share/voroa-adminer/adminer.php';
const COOKIE = 'voroa_db';
const IDLE = 1800;

function nope(): void { http_response_code(404); header('Content-Type: text/plain'); echo "Not found\n"; exit; }
function h(string $s): string { return hash('sha256', $s); }

if (!is_file(ADMINER) || !is_readable('/data/voroa/db.php')) nope();
@mkdir(STATE . '/grants', 0700, true);
@mkdir(STATE . '/sessions', 0700, true);

// 1) arriving with a one-time token: burn it, start a session, then come back same-site (a Strict cookie is not sent
//    on the cross-site navigation that set it).
if (isset($_GET['voroa_t'])) {
	$t = (string) $_GET['voroa_t'];
	if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $t)) nope();
	$g = STATE . '/grants/' . h($t);
	$exp = is_file($g) ? (int) file_get_contents($g) : 0;
	@unlink($g);
	if ($exp < time()) nope();
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
file_put_contents($sf, 'live'); // also refreshes the idle clock

// Adminer's own session rides OUR cookie: the gate starts it (id derived from the gate session, no second cookie),
// and fills in the site's database account on every request - the gate already decided this browser may in. The
// password stays in the server-side session file and in this process; it is never sent to the browser.
require '/data/voroa/db.php';
// Adminer names a unix socket ':/path' (db.php has 'localhost:/path', the form WordPress reads).
define('ADM_SERVER', preg_replace('#^localhost(?=:/)#', '', VOROA_DB_HOST));
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
session_name('adminer_sid');
session_id(substr(h('adminer:' . $sid), 0, 48));
session_start();
$_SESSION['pwds']['server'][ADM_SERVER][VOROA_DB_USER] = VOROA_DB_PASSWORD;
$_SESSION['db']['server'][ADM_SERVER][VOROA_DB_USER][VOROA_DB_NAME] = true;
foreach (['server' => ADM_SERVER, 'username' => VOROA_DB_USER, 'db' => VOROA_DB_NAME] as $k => $v) {
	if (!isset($_GET[$k]) || $_GET[$k] !== $v) $_GET[$k] = $v; // only this site's database, whatever the URL asked for
}
unset($_POST['auth']); // no login form: nobody signs in to another server through this door

function adminer_object() {
	return new class extends \Adminer\Adminer {
		function name() { return 'Database'; }
		function credentials() { return [ADM_SERVER, VOROA_DB_USER, VOROA_DB_PASSWORD]; }
		function login($login, $password) { return true; }
		function databases($flush = true) { return [VOROA_DB_NAME]; }
	};
}
require ADMINER;
