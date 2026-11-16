<?php
// phpMyAdmin config for the Managed WordPress database browser (Voroa plan 2026-11-16). Loaded only through
// /usr/share/voroa-db/gate.php, which already checked the one-time-link session and required /data/voroa/db.php.
declare(strict_types=1);

if (!defined('VOROA_PMA_DOOR') || !defined('VOROA_DB_NAME')) { http_response_code(404); exit; }

// Fresh 32 random bytes per container start (entrypoint, root:www-data 0640). signon mode encrypts nothing that has
// to outlive a restart; phpMyAdmin's sessions live in /tmp and end with the container anyway.
$cfg['blowfish_secret'] = hex2bin(trim((string) @file_get_contents('/etc/voroa-pma/secret')));

$i = 1;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonScript'] = '/usr/share/voroa-db/pma-signon.php';
$cfg['Servers'][$i]['SignonURL'] = '/voroa-db/?voroa_expired=1'; // final page, never back into index.php (no loop)
$cfg['Servers'][$i]['LogoutURL'] = '/voroa-db/?voroa_logout=1';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['connect_type'] = 'socket';
$cfg['Servers'][$i]['socket'] = '/run/mysqld/mysqld.sock';
$cfg['Servers'][$i]['compress'] = false;
// Cosmetic: the real boundary is the account's GRANT (wordpress.* only, no FILE privilege).
$cfg['Servers'][$i]['only_db'] = VOROA_DB_NAME;
$cfg['Servers'][$i]['AllowNoPassword'] = false;
$cfg['Servers'][$i]['AllowRoot'] = false;

$cfg['AllowArbitraryServer'] = false;
$cfg['NavigationDisplayServers'] = false;
$cfg['LoginCookieValidity'] = 1800;
$cfg['CookieSameSite'] = 'Strict';
$cfg['TempDir'] = '/tmp/voroa-pma';
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['ShowPhpInfo'] = false;
$cfg['ShowServerInfo'] = false;
$cfg['ShowChgPassword'] = false; // WordPress reads this password from db.php - changing it here would take the site down
$cfg['ShowCreateDb'] = false;
$cfg['AllowUserDropDatabase'] = false;
$cfg['VersionCheck'] = false;
$cfg['SendErrorReports'] = 'never';
$cfg['ZeroConf'] = false; // no phpmyadmin config-storage tables in the customer's database
$cfg['ShowGitRevision'] = false;
