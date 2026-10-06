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
