# Madrasa — guidance for contributors and agents

Multi-school K-12 SaaS (Laravel 13, PHP 8.3). Read `docs/architecture.md` first.

## Commands

- `php artisan test` — must pass before every push
- `./vendor/bin/pint` — code style (CI runs `pint --test`)
- `php artisan migrate:fresh --seed` — reset local DB with the demo school

## Non-negotiable rules

1. **Tenant data**: every school-owned model uses `App\Models\Concerns\BelongsToSchool`
   and is added to the data provider in `tests/Feature/TenantIsolationTest.php`.
2. **Foreign ids from requests** are validated with `App\Rules\ExistsInCurrentSchool`.
3. **Tenant routes** sit inside the `school` middleware group and check a permission
   from `App\Enums\Permission` with `can:` middleware or a policy.
4. Code outside a request (seeders, jobs, commands) wraps tenant work in
   `app(CurrentSchool::class)->run($school, fn () => …)`.
5. **Arabic first**: user-facing strings go through `__()` with entries in `lang/ar*`
   and `lang/en*`; bilingual names use `name_ar` / `name_en` + `HasBilingualName`;
   CSS uses logical properties (start/end), never left/right.
6. Dates shown to users go through `App\Support\Dates\SchoolDate`.
7. Never copy code from GPL or unlicensed projects listed in `docs/repo-analysis.md`.
