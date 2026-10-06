# Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`.
- HTTPS only; `SESSION_SECURE_COOKIE=true`; `SESSION_DRIVER=database` or `redis`.
- PostgreSQL in a Saudi region (PDPL); daily backups, tested restores.
- php-fpm (or Octane) with **opcache on**; `php artisan config:cache route:cache view:cache`.
- `npm ci && npm run build` on deploy.
- A queue worker (`php artisan queue:work`) for guardian alerts, announcements and quiet hours.
- The scheduler (`php artisan schedule:run` every minute) for `madrasa:prune-imports`.
- Never run `db:seed`, `madrasa:demo-large` or `madrasa:profile` in production
  (the demo accounts use the password "password"; the commands refuse to run in production).
- Platform admin accounts must turn on two-factor sign-in (required outside development).
- Messaging: `SMS_DRIVER`, `WHATSAPP_DRIVER`, mail settings; optional `PDF_DRIVER=gotenberg`.
- Token lifetime: `SANCTUM_EXPIRATION` (minutes, default 30 days).
