# Calloway Pharmacy IMS

Inventory management, point-of-sale and online ordering system for Calloway Pharmacy.
Live site: <https://callowaypharmacy.me>

## Features

- **POS** with receipts (QR landing page, ESC/POS thermal printing)
- **Inventory & expiry monitoring**, supplier management and purchase orders
- **Rx enforcement** and pharmacist approval workflow
- **Online ordering** with PayMongo payments and order tracking
- **Medicine locator**, loyalty points / reward QR codes
- **User, role and permission management**, activity logs, backups
- **Email notifications** (PHPMailer / SMTP)

## Tech stack

PHP 8.2 · MySQL / MariaDB · Apache (`.htaccess`) · Composer (PHPMailer, TCPDF) · Azure Web App

## Local setup

1. Install PHP 8.1+, MySQL/MariaDB and [Composer](https://getcomposer.org/).
2. `composer install`
3. Copy `.env.example` to `.env` and fill in your values, or set the same names as
   real environment variables. Set `ENVIRONMENT=development` locally.
4. Create the database and tables:
   ```bash
   php create_db.php
   php setup_database.php
   php setup_permissions.php
   ```
5. Create your first admin user. The script reads the credentials from environment
   variables, so **choose your own password** (PowerShell shown):
   ```powershell
   $env:ADMIN_USERNAME="admin"; $env:ADMIN_PASSWORD="<your-strong-password>"
   $env:ADMIN_EMAIL="you@example.com"; $env:ADMIN_FULLNAME="Your Name"
   php create_admin.php
   ```
6. Serve it: `php -S localhost:8000`

## Configuration

All secrets come from environment variables (see [`.env.example`](.env.example)):
database credentials, SMTP settings and PayMongo keys. Never commit `.env`.

## Maintenance scripts

One-off scripts (`seed_*`, `topup_*`, `migrate_*`, `update_*`, `verify_*`, diagnostics)
are blocked from the web by `.htaccess`. Run them from the command line, e.g.
`php seed_ph_medicines.php`.

## Deployment

Pushing to `main` runs `.github/workflows/main_pharmacycalloway.yml`, which builds the
app and **deploys it straight to the production Azure Web App**. Use a branch and pull
request for changes, and merge only when you want them live.
See [docs/AZURE_DEPLOYMENT_NOTES.md](docs/AZURE_DEPLOYMENT_NOTES.md).

## Documentation

Guides live in [`docs/`](docs/); older status reports and thesis material are in
[`docs/archive/`](docs/archive/). Note that archived reports may mention old default
credentials. Do not reuse them.
