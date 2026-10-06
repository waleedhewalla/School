# Roadmap

Target: multi-school K-12 SaaS for Saudi Arabia / GCC, Arabic first.
See [repo-analysis.md](repo-analysis.md) for where each idea comes from.

## Phase 0 — Foundation ✅ (this branch)

- [x] Laravel 13 app, PHP 8.3, CI on GitHub Actions (SQLite + PostgreSQL)
- [x] Multi-school tenancy: `schools`, `campuses` (boys / girls / mixed), `memberships`
- [x] `BelongsToSchool` trait: reads filtered to the active school, fail-closed with no school, writes to another school refused
- [x] Active school from `X-School` header (API) or session (web); suspended schools blocked
- [x] Roles per school (Spatie teams): school admin, principal, registrar, teacher, accountant, guardian, student — with default permissions
- [x] Onboarding action: new school gets a campus, Saudi stages (KG, primary, intermediate, secondary) and 15 grade levels, roles, its first admin
- [x] Academic structure: academic years (one current), terms, stages, grade levels, sections, subjects
- [x] Arabic / English with RTL; language from user → school → browser
- [x] Hijri (Umm al-Qura) + Gregorian dates via PHP intl
- [x] REST API v1 with Sanctum tokens
- [x] Audit log (spatie/activitylog) on key models
- [ ] Web UI framework decision (Livewire 4 vs Inertia + Vue) and sign-in screens with two-factor login
- [ ] Platform admin console; school setup wizard UI
- [ ] OpenAPI docs generated in CI (Scribe)

## Phase 1 — Core SIS MVP

- Students and guardians, **families** (siblings) — ICTSchool
- Bulk import from Excel in Noor's column format
- **Enrollments** per year (student × section) and promotion as history — Unifiedtransform
- Staff records, teacher ↔ subject ↔ section assignments
- Timetable: periods, builder, clash detection
- Attendance: daily and per period, absence codes, bulk entry screen, guardian alerts — RosarioSIS, Frappe
- Assessments: **grading scales** (validated intervals), **exam rules**, weighted **assessment components**, marks entry window, calculated vs final mark — Frappe, Unifiedtransform, ICTSchool
- Arabic report cards (PDF)
- Announcements; SMS (Unifonic/Taqnyat), WhatsApp, email behind one notification interface
- Guardian / student portal; dashboards

## Phase 2 — Finance and admissions

- Admissions: intake windows, online application, documents, interview, offer, enroll — Frappe, TareqMonwer
- Fees: **fee structure → fee schedule → invoices** as a queued job — Frappe; installments, sibling/staff discounts, late fees — ICTSchool
- **ZATCA** Phase 2 e-invoicing (UBL XML, signature, QR, API submission)
- Online payment: mada, Apple Pay, SADAD (Moyasar / HyperPay / Tap)
- Subscription billing for schools

## Phase 3 — Extended

- Flutter app for guardians, students and teachers
- Homework, LMS and quizzes — SkyLearn
- Behaviour / discipline — RosarioSIS
- Leave requests — College-ERP
- Transport, library, clinic, inventory
- HR and payroll (GOSI, Mudad export)
- Noor export/import; Qiyas / Nafes tracking; analytics

## Always

PDPL (consent records, retention, access/deletion requests, KSA hosting),
backups, rate limiting, security review before each pilot, and a test for
every new tenant model proving it is scoped by `school_id`.
