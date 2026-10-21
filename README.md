# Shiksha ERP

Multi-tenant School ERP SaaS for Indian K–12 schools. Laravel 13 (PHP 8.3), Inertia + React + TypeScript, PostgreSQL (one database per school organization). Agent rules live in `.ai/guidelines/`.

## Local setup (Windows + WAMP, no Docker)

1. PHP 8.3 first on `PATH`, with `pdo_pgsql`, `pgsql`, `intl`, `mbstring`, `bcmath`, `openssl`, `sodium`, `fileinfo`, `zip`, `gd`, `exif`, `curl` enabled in both WAMP php.ini files.
2. PostgreSQL 18 with a development role that has `CREATEDB`, and a `shiksha_landlord` database owned by it. Put the role's credentials in `.env` (`DB_USERNAME`, `DB_PASSWORD`).
3. Node 24 LTS (`nvm use` reads `.nvmrc`).
4. Install and build:

    ```bash
    composer install
    npm install
    npx lefthook install
    php artisan migrate
    npm run build
    ```

5. Run: Apache vhost for `shiksha.localhost` / `*.shiksha.localhost` pointing at `public/`, or `php artisan dev` (server, queue worker and Vite). Schools are reached at `http://<school>.shiksha.localhost`.

No Redis locally: queue, cache and session use the database drivers. Horizon and Pail need `pcntl` and only run on Linux.

## Checks

```bash
composer test
npm run check
npm run types:check
npm run build
```
