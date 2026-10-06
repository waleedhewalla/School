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
- [x] Web UI: Inertia + Vue 3, Tailwind 4 with RTL-safe logical utilities, self-hosted Arabic font
- [x] Sign-in / sign-out, school picker for multi-school users
- [ ] Two-factor login, password reset, account invitations
- [ ] Platform admin console; school setup wizard UI
- [ ] OpenAPI docs generated in CI (Scribe)

## Phase 1 — Core SIS MVP (in progress)

Slice 1 ✅
- [x] Students (Arabic four-part name, Saudi ID/iqama check digit, auto numbers), guardians, **families** (siblings) — ICTSchool
- [x] Admission in one step: student + guardians + first enrollment
- [x] **Enrollments** per year, capacity, section transfer, withdrawal; promotion / repeat / graduation kept as history — Unifiedtransform
- [x] Staff records, teacher ↔ subject ↔ section assignments
- [x] Attendance: daily and per period, codes per school, bulk register, corrections; teachers limited to their sections; event for guardian alerts — RosarioSIS, Frappe
- [x] Guardian portal (API + web): own children and their attendance only
- [x] Web screens: dashboard, students list/profile, attendance register, guardian page

Slice 2 — next
- [ ] Web forms for admission, editing students/guardians, enrollment and promotion
- [ ] Bulk import from Excel in Noor's column format
- [ ] Notifications: SMS (Unifonic/Taqnyat), WhatsApp, email behind one interface, wired to the absence event
- [ ] Timetable: periods, builder, clash detection

Slice 3
- [ ] Assessments: **grading scales** (validated intervals), **exam rules**, weighted **assessment components**, marks entry window, calculated vs final mark — Frappe, Unifiedtransform, ICTSchool
- [ ] Arabic report cards (PDF)
- [ ] Announcements; attendance and grade dashboards

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
