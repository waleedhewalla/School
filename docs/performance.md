# Performance (October 2026)

Data: `php artisan madrasa:demo-large` on PostgreSQL 16 — 600 students in
18 sections, 18 teachers, 60 school days of attendance (36,000 marks), term
marks for six subjects (10,800 scores). Measure with
`php artisan madrasa:profile` (single requests, median of 3).

| Page (admin unless noted) | ms | queries |
|---|---|---|
| Dashboard | 52 | 15 |
| Students list / search / by section | 66–77 | 13 |
| Attendance register (40 students) | 40 | 13 |
| Results sheet (section × 6 subjects) | 112 | 18 |
| Report cards, whole section | 91 | 18 |
| Teacher dashboard / marks entry | 50 / 61 | 15 / 18 |
| Parent page | 44 | 13 |
| API students (50 per page) | 21 | 7 |

The dashboard first took ~900 ms: it loaded every attendance mark of the
last 30 days into memory. It now counts in SQL and uses a
`(school_id, period, date)` index.

## Concurrency

40 virtual users clicking continuously (half admins, half teachers) for
30 s against PHP's built-in server with 8 workers on 4 CPUs, opcache on:
**54 requests/s, p50 0.65 s, p95 0.97 s, 0 errors**. Without opcache it
was 11 requests/s, so production must run php-fpm (or Octane) with opcache.
A real school's peak (morning registers) is far below 40 users clicking
non-stop, so one modest server handles several schools of this size.
