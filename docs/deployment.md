# Deployment

The production stack is in `compose.production.yml`: one Docker image
(`Dockerfile`, nginx + php-fpm 8.3) runs three roles — **app** (web, applies
migrations on start), **worker** (queue: guardian messages, admissions,
behaviour and clinic notices) and **scheduler** (daily clean-ups) — next to
PostgreSQL 16, Redis 7, an optional Gotenberg for report-card PDFs, and a
nightly **backup** container.

Host it in a Saudi region (PDPL), e.g. a KSA region of STC Cloud, Oracle
Jeddah, Google Cloud Dammam or Azure Saudi.

## First install

```bash
git clone <repo> madrasa && cd madrasa
cp .env.production.example .env.production
# Fill in APP_URL, DB_PASSWORD, mail and SMS settings, then make a key:
docker compose -f compose.production.yml --env-file .env.production build
docker compose -f compose.production.yml --env-file .env.production run --rm --no-deps --entrypoint php app artisan key:generate --show
# → put the value in APP_KEY in .env.production

docker compose -f compose.production.yml --env-file .env.production up -d
# Optional PDFs: add `--profile pdf` and set PDF_DRIVER=gotenberg.

# The SaaS operator account (asks for a password, min. 12 characters):
docker compose -f compose.production.yml --env-file .env.production exec app \
    gosu application php artisan madrasa:create-platform-admin ops@your-company.sa
```

Sign in as that account, turn on two-factor sign-in (Account page — required
for platform admins in production), open **/platform** and add the first
school. Its admin receives an invitation email and sets up years, sections,
subjects and staff under **School setup**; students come in by Excel import
(Noor layout) or the admission form at `/apply/<school>`.

The app listens on port 8080 (container port 80). Put a TLS terminator in
front (load balancer, Cloudflare, or nginx/Caddy on the host) and keep
`TRUSTED_PROXIES=*` and `SESSION_SECURE_COOKIE=true`.

Behind a TLS-inspecting proxy, give the build its CA:
`docker build --secret id=proxy_ca,src=/path/ca.crt .`

## Upgrades

```bash
git pull
docker compose -f compose.production.yml --env-file .env.production up -d --build
```

The app container runs `migrate --force` on start (`RUN_MIGRATIONS=false`
to turn that off and run `… run --rm app migrate` yourself). Migrations that
add permissions give existing schools' roles the new defaults without
undoing their own role edits.

## Backups and restore

The `backup` service writes `db-<time>.dump` (pg_dump custom format) and
`files-<time>.tar.gz` (uploaded documents) to the `backups` volume every 24
hours and keeps `BACKUP_KEEP_DAYS` days. Copy that volume off the server to
object storage in Saudi Arabia, and test a restore every term:

```bash
docker compose -f compose.production.yml --env-file .env.production exec -T postgres \
    pg_restore --clean --if-exists --no-owner -U madrasa -d madrasa < db-YYYYMMDD-HHMM.dump
docker run --rm -v madrasa_storage:/files -v "$PWD":/b alpine tar -xzf /b/files-YYYYMMDD-HHMM.tar.gz -C /files
```

## Checklist

- `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY` kept safe (it
  also decrypts stored secrets such as admission links and 2FA keys).
- HTTPS only; `SESSION_SECURE_COOKIE=true`; sessions, cache and queue on Redis.
- Opcache on with `validate_timestamps=0` (in the image); new code = new image.
- Worker and scheduler running (`docker compose ps`).
- Messaging: `SMS_DRIVER` (unifonic / taqnyat), WhatsApp (`WHATSAPP_DRIVER=meta`
  with approved templates `attendance_alert`, `admission_update`), SMTP mail.
- GOSI rates in `config/madrasa_payroll.php` checked with GOSI before the
  first payroll; behaviour categories and grading scales checked by each school.
- Never run `db:seed`, `madrasa:demo-large` or `madrasa:profile` in production
  (demo accounts use the password "password"; the commands refuse to run).
- Platform admins must turn on two-factor sign-in.
- Token lifetime for the mobile/API: `SANCTUM_EXPIRATION` (minutes).
- Monitoring: `/up` for uptime checks; container logs go to stdout/stderr.

## Verified

The image was built and the stack started end to end (October 2026): `/up`,
sign-in, school setup, analytics, clinic and payroll pages answered 200;
the queue worker ran a notification job from Redis; the scheduler ran; the
backup container produced a dump and a files archive. Two start-up issues
found that way are fixed in the image (a duplicate nginx directive, and the
IPv6 listener on hosts without IPv6).
