# Madrasa — نظام إدارة المدارس

Multi-school management SaaS for K-12 schools in Saudi Arabia and the GCC.
Arabic first (RTL), Hijri and Gregorian dates, built for Noor, ZATCA and PDPL.

نظام سحابي لإدارة مدارس التعليم العام في المملكة العربية السعودية والخليج،
بالعربية أولًا، مع التاريخ الهجري والميلادي.

## Status

All planned phases are built except fees and ZATCA e-invoicing, which are
deferred on purpose. See [docs/roadmap.md](docs/roadmap.md) for the full list
and what is still open.

- **School and SaaS:** multi-school tenancy with isolation tested for every school-owned model;
  platform console; school setup screens; roles per school (admin, principal, registrar, teacher,
  counsellor, nurse, librarian, accountant, guardian, student)
- **Students:** students, guardians and families, Noor-layout Excel import and export, enrolment,
  promotion, admissions with a public Arabic-first form
- **School day:** attendance with guardian SMS / WhatsApp / email, timetables, homework,
  behaviour, absence excuses, staff leave
- **Grades:** assessments, marks, results, Arabic report cards, online quizzes, Nafes / Qiyas results
- **Services:** clinic and health cards, library, transport, inventory
- **Staff:** records, teaching assignments, payroll with GOSI and a register for Mudad
- **Insight:** dashboard, analytics, students to follow up
- **Delivery:** web app (Inertia + Vue, installable on phones), REST API v1, Docker production stack

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
(guardian of two siblings), `student@example.com` (a grade 1 student with a quiz),
`platform@example.com` (platform admin).

```bash
php artisan test        # test suite
./vendor/bin/pint       # code style
```

## Production

`compose.production.yml` runs the app, queue worker, scheduler, PostgreSQL, Redis and nightly
backups — see [docs/deployment.md](docs/deployment.md).

## Docs

- [Analysis of 10 open-source systems](docs/repo-analysis.md)
- [Roadmap](docs/roadmap.md)
- [Market research and decisions](docs/market-research.md)
- [Architecture](docs/architecture.md)
- [Security review](docs/security-review.md), [performance](docs/performance.md), [production checklist](docs/deployment.md)
- [API v1](docs/api.md)
