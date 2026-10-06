# Architecture

## Stack

Laravel 13 (PHP 8.3), PostgreSQL in production (SQLite for tests), Redis for
queues and cache, Sanctum for API tokens, spatie/laravel-permission (teams)
for roles, spatie/laravel-activitylog for the audit trail.

## Multi-school tenancy

One database shared by all schools. Every tenant table has a `school_id`.

| Piece | File | Job |
|---|---|---|
| `CurrentSchool` | `app/Support/Tenancy/CurrentSchool.php` | Holds the active school for the request or job, and keeps Spatie's permission team in sync. `run($school, fn)` for seeders, jobs, commands. |
| `SchoolScope` | `app/Support/Tenancy/SchoolScope.php` | Global scope: `where school_id = current`. **Fails closed** — with no school set it matches nothing. |
| `BelongsToSchool` | `app/Models/Concerns/BelongsToSchool.php` | Adds the scope, fills `school_id` on create, and throws `SchoolContextMismatch` on saving a row for any other school. |
| `ResolveCurrentSchool` | `app/Http/Middleware/ResolveCurrentSchool.php` | `school` route middleware. Reads `X-School` (id or slug) or the session; falls back to the user's only membership; checks membership and school status. |
| `ExistsInCurrentSchool` | `app/Rules/ExistsInCurrentSchool.php` | `exists` validation limited to the active school, so ids from another school are rejected. |

Middleware order (set in `bootstrap/app.php`): **auth → locale → school →
route model binding**. Binding runs after the school is known, so
`/subjects/{id}` for another school's subject is a 404.

`memberships` and `schools` are deliberately **not** tenant-scoped: they are
what we read to decide which schools a user may enter.

### Rule for new code

Every new tenant model uses `BelongsToSchool`, every foreign id in a request
uses `ExistsInCurrentSchool`, and every model is added to the data provider
in `tests/Feature/TenantIsolationTest.php`.

## Roles and permissions

Roles are per school (Spatie teams, `school_id`). Permission names live in
`app/Enums/Permission.php`; default roles and their permissions in
`app/Enums/SchoolRole.php`. `ProvisionSchoolRoles` creates them for each new
school. Routes check permissions with `can:` middleware. Platform admins
(`users.is_platform_admin`) pass every check (`AppServiceProvider`).

Guardians and students get no staff permissions; their access to their own
records will be decided by ownership in policies (Phase 1).

## Language, direction and dates

- Locales in `config/madrasa.php` (`ar`, `en`); Arabic is the default.
- `SetLocale`: `?lang=` → session → user profile → (school default) → `Accept-Language` → app default.
- `Locale::direction()` gives `rtl`/`ltr` for `<html dir>`. Use CSS logical properties (`margin-inline-start`, `ms-*`), never left/right.
- Models with `name_ar`/`name_en` use `HasBilingualName` → `$model->name` in the active language.
- `SchoolDate` formats Gregorian, Hijri (Umm al-Qura via PHP intl) or both.

## API

REST under `/api/v1`, documented in [api.md](api.md). Bearer tokens from
`POST /api/v1/auth/token`. Tenant endpoints need `X-School` unless the user
belongs to exactly one school.

## Web UI

Inertia v3 + Vue 3, built by Vite; Tailwind 4. Pages live in
`resources/js/pages`, the shell in `resources/js/layouts/AppLayout.vue`.

- **Strings**: `useT()` from `resources/js/lib/i18n.js`. Keys are English text;
  Arabic comes from `lang/ar.json`, shared to every page by
  `HandleInertiaRequests`. Enum labels use keys like `status.active`
  (both `lang/ar.json` and `lang/en.json`).
- **Direction**: the root template sets `<html dir>`; components use logical
  utilities only (`ms-*`, `pe-*`, `text-start`). Numbers, phone numbers and IDs
  are wrapped in `dir="ltr"`.
- **Shared props**: `auth.user`, `school`, `schools`, `can` (permission → bool
  in the active school), `locale`, `dir`, `translations`, `flash`. They are
  lazy closures because Inertia shares before the `school` middleware runs.
- **Font**: IBM Plex Sans Arabic from npm (`@fontsource`), so pages never call
  third-party font servers.
- Web controllers (`app/Http/Controllers/Web`) reuse the same actions,
  policies and tenancy as the API.

## Notifications

`RecordAttendance` fires `StudentsMarkedAbsent` (school id + record ids) only
for records whose code changed to one with `notify_guardian`. The queued
listener `NotifyGuardiansOfAbsence` reloads the records inside
`CurrentSchool::run()`, texts the primary guardians (or all guardians with a
mobile if none is primary) and writes a `message_logs` row per attempt.

Channels (SMS, WhatsApp, email), which attendance codes alert, and quiet
hours are per-school settings (`schools.notification_settings`,
`attendance_codes.notify_guardian`). During quiet hours the listener's
`withDelay()` holds the job until they end, so a queue worker
(`php artisan queue:work`) must run in production.

WhatsApp uses the `WhatsAppGateway` interface (`WHATSAPP_DRIVER=log|meta`) and
sends a pre-approved template (`WHATSAPP_ATTENDANCE_TEMPLATE`) with four body
parameters: school, student, status, date.

SMS goes through the `SmsGateway` interface (`app/Support/Messaging`), chosen
by `SMS_DRIVER`: `log` (default, development), `unifonic` or `taqnyat`.
Provider failures are logged as `failed`, never thrown. Check both providers'
request fields against their current API docs before going live.

Phones are stored normalized (`9665XXXXXXXX`, see `App\Support\PhoneNumber`)
so sibling matching and SMS work however a number was typed.

## Noor import

`NoorStudentSheet` finds columns by header text (aliases in
`NoorStudentSheet::COLUMNS`, compared after Arabic normalization), so column
order and extra columns don't matter. `ImportStudents` validates every row
(preview) and then admits the valid ones (commit), using `ArabicName::split`
for the four-part name and `SchoolDate::fromHijri` for Hijri birth dates.
The uploaded file sits in private storage between preview and confirm and is
deleted afterwards. The aliases are based on common Noor export headers and
must be checked against real exports from pilot schools.

## Timetable

`periods` is the school's bell schedule (`is_break` rows can't hold lessons);
an attendance register's period number is the period's `sequence`.
`timetable_entries` holds one lesson per section × day × period, with the
teacher copied from the teaching assignment so a unique index backs the
"no teacher in two places" rule. `PlaceLesson` checks teacher and room
clashes with friendly messages; `AssignTeacher` moves existing lessons when a
subject changes teacher, refusing if the new teacher is busy; `SavePeriods`
won't delete a period that still has lessons.
