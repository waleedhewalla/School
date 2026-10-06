# Madrasa — نظام إدارة المدارس

Multi-school management SaaS for K-12 schools in Saudi Arabia and the GCC.
Arabic first (RTL), Hijri and Gregorian dates, built for Noor, ZATCA and PDPL.

نظام سحابي لإدارة مدارس التعليم العام في المملكة العربية السعودية والخليج،
بالعربية أولًا، مع التاريخ الهجري والميلادي.

## Status

Phase 0 done, Phase 1 in progress — see [docs/roadmap.md](docs/roadmap.md).

- Multi-school tenancy with isolation enforced in code and tests
- Roles per school (admin, principal, registrar, teacher, accountant, guardian, student)
- Academic structure: years, terms, Saudi stages and grade levels, sections, subjects
- Arabic / English with RTL, Umm al-Qura Hijri dates
- Students, guardians, families, enrollment and promotion, staff and teaching assignments
- Admission forms, Excel import in Noor's layout, year-end promotion
- Bell schedule and timetables with clash checks; teacher's week and today's lessons
- Assessments, marks entry, results and Arabic report cards (print / PDF)
- Daily and per-period attendance; guardian alerts by SMS (Unifonic / Taqnyat), WhatsApp and email; guardian portal
- Web app (Inertia + Vue) and REST API v1 ([docs/api.md](docs/api.md))

## Run locally

Requires PHP 8.3 with `intl`, `pdo_sqlite` (or `pdo_pgsql`) and Composer.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # demo school "demo"; every password is "password"
php artisan serve
```

Open http://localhost:8000/login. Demo accounts: `admin@example.com` (school
admin), `teacher@example.com` (teacher of grade 1 / أ), `parent@example.com`
(guardian of two siblings), `platform@example.com` (platform admin).

```bash
php artisan test        # test suite
./vendor/bin/pint       # code style
```

## Docs

- [Analysis of 10 open-source systems](docs/repo-analysis.md)
- [Roadmap](docs/roadmap.md)
- [Architecture](docs/architecture.md)
- [API v1](docs/api.md)
