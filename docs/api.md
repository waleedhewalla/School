# API v1

Base path `/api/v1`. JSON in and out. Send `Accept: application/json`.

## Authentication

```http
POST /api/v1/auth/token
{ "email": "admin@example.com", "password": "password", "device_name": "iPhone" }
→ 201 { "token": "1|…", "token_type": "Bearer" }
```

Send `Authorization: Bearer <token>` afterwards. `DELETE /api/v1/auth/token` revokes it.
Login is limited to 10 attempts per minute.

## Choosing the school

Tenant endpoints act inside one school. Send `X-School: <id or slug>`.
Users who belong to exactly one school may omit it. Errors:

| Status | Meaning |
|---|---|
| 400 | Several schools and no `X-School` header |
| 403 | Not a member, unknown school, or school suspended |
| 404 | Record does not exist in this school |

## Language

`?lang=ar|en` → the user's saved language → the school's default → `Accept-Language`.
The response has a `Content-Language` header. `name` fields come in the active
language; `name_ar` / `name_en` are also returned where editable.

## Endpoints

| Method | Path | Permission |
|---|---|---|
| GET | `/me` — profile and schools with roles | signed in |
| POST | `/schools` — onboard a school (`slug`, `name_ar`, `admin_email`, …) | platform admin |
| GET | `/school` — the active school | member |
| GET | `/grade-levels` — stages with grade levels | `academic-structure.view` |
| GET, POST | `/academic-years` (POST accepts `terms[]`, `is_current`) | view / manage |
| GET, PATCH, DELETE | `/academic-years/{id}` | view / manage |
| GET, POST | `/sections` (`?academic_year_id=`) | view / manage |
| GET, PATCH, DELETE | `/sections/{id}` | view / manage |
| GET, POST | `/subjects` | view / manage |
| GET, PATCH, DELETE | `/subjects/{id}` | view / manage |

Academic years also return `starts_on_hijri` / `ends_on_hijri`.
