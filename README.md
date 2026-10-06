# Madrasa — نظام إدارة المدارس

Multi-school management SaaS for K-12 schools in Saudi Arabia and the GCC.
Arabic first (RTL), Hijri and Gregorian dates, built for Noor, ZATCA and PDPL.

نظام سحابي لإدارة مدارس التعليم العام في المملكة العربية السعودية والخليج،
بالعربية أولًا، مع التاريخ الهجري والميلادي.

## Status

Phase 0 (foundation) — see [docs/roadmap.md](docs/roadmap.md).

- Multi-school tenancy with isolation enforced in code and tests
- Roles per school (admin, principal, registrar, teacher, accountant, guardian, student)
- Academic structure: years, terms, Saudi stages and grade levels, sections, subjects
- Arabic / English with RTL, Umm al-Qura Hijri dates
- REST API v1 ([docs/api.md](docs/api.md))

## Run locally

Requires PHP 8.3 with `intl`, `pdo_sqlite` (or `pdo_pgsql`) and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # demo school "demo"; every password is "password"
php artisan serve
```

Demo accounts: `platform@example.com` (platform admin), `admin@example.com`
(school admin), `teacher@example.com` (teacher).

```bash
php artisan test        # test suite
./vendor/bin/pint       # code style
```

## Docs

- [Analysis of 10 open-source systems](docs/repo-analysis.md)
- [Roadmap](docs/roadmap.md)
- [Architecture](docs/architecture.md)
- [API v1](docs/api.md)
