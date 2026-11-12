# WordPress for Voroa

An optimised, hardened WordPress image for [Voroa](https://getvoroa.com). It runs on Voroa as a web
service next to a managed MySQL database.

## What is inside

- **WordPress 7.1.4** on **PHP 8.4** (php-fpm) behind **nginx**, in one container listening on `$PORT`
  (default 3000).
- **Page cache** in nginx for visitors who are not logged in. Logged-in users, the admin area, the login
  page, search and WooCommerce cart, checkout and account pages are never cached. The cache clears itself
  when you publish or change content.
- **OPcache** tuned for WordPress and WooCommerce. PHP workers are sized from the service's memory and
  capped below the database's connection limit, so a traffic spike does not exhaust MySQL.
- **Scheduled tasks every minute** inside the container (WP-Cron no longer runs on visitor page loads).
  WooCommerce background jobs are run too when WooCommerce is active.
- **Security:** core files are read-only, the theme and plugin file editor is off, `xmlrpc.php` is blocked,
  PHP cannot run from the uploads folder, the login page is rate-limited per visitor, and the platform's
  own hostname is marked `noindex` so only your domain is indexed by search engines.
- **Core updates** come from a new version of this image (redeploy). Plugin, theme and translation updates
  work normally from wp-admin.

## Settings (environment variables)

| Variable | What it does |
|---|---|
| `DATABASE_URL` | `mysql://user:password@host:port/database` - copy it from your MySQL database's Connect tab. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM`, `SMTP_SECURE` | Outgoing email (password resets, contact forms, order emails) through your email provider, for example Zoho Mail, Google Workspace, Amazon SES or Brevo. `SMTP_SECURE` is `tls` (default), `ssl` or `none`. |
| `WP_SITE_TITLE`, `WP_ADMIN_USER`, `WP_ADMIN_EMAIL`, `WP_ADMIN_PASSWORD` | Installs WordPress on first start with this admin account. The web installer is never reachable, so nobody else can claim a new site. |
| `WP_HOME` | Optional. Pins the site to one address. Without it the site answers on any of its own addresses (the Voroa address and your domain) and remembers your own domain for emails. |
| `WP_DEBUG` | Set to `1` to write PHP and WordPress errors to the service logs. They are never shown to visitors. |
| `DB_MAX_CONNECTIONS` | The database's connection limit (default 30). PHP workers stay 6 below it. |

## Storage

Uploads, plugins and themes live in `/var/www/html/wp-content`. **Attach a persistent disk at that path**,
or they are lost when the service is redeployed. Security keys are created once on first start and kept
on the same disk.

## Versions

Each release is a tag such as `wp-7.1.4-php8.4-r1` (WordPress version, PHP version, release number).
Point your service at a tag, not at `main`, so a redeploy never changes WordPress unexpectedly.

## Documentation

Step-by-step guide: [getvoroa.com/docs/wordpress](https://getvoroa.com/docs/wordpress)

## License

The files in this repository are released under the GNU General Public License v2 or later, the same
license as WordPress.
