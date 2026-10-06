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

Slice 2 ✅
- [x] Web forms: admission (with sibling lookup by guardian ID/mobile), edit, move section, withdraw/transfer
- [x] Year-end promotion screen, one section at a time
- [x] Excel import in Noor's column layout: preview with per-row errors, then confirm; Hijri birth dates, Arabic name splitting, siblings linked by guardian ID/mobile, missing sections created
- [x] SMS to guardians on new absence/late (Unifonic or Taqnyat driver, log driver for development), gender-aware Arabic wording, every message logged

Slice 3 ✅
- [x] Bell schedule per school (periods, breaks, school days — Sunday–Thursday by default)
- [x] Section timetables: grid editor, teacher and room double-booking refused, reassigning a teacher moves their lessons only if they're free
- [x] Teacher's week and "my lessons today" on the dashboard, each linking to that period's register
- [x] WhatsApp (Meta Cloud API template) and email alerts alongside SMS; per-school channels, alerting statuses and quiet hours
- [ ] Verify Noor column aliases against real exports from pilot schools (needs a real file)

Slice 4 ✅
- [x] Grading scale per school (bands + pass mark; a starting scale is seeded — review against Ministry rules per stage)
- [x] Assessment components per term × grade × subject with weights totalling 100; typical structure in one click; copy to other subjects
- [x] Marks entry: teachers only for their own subjects and only while the term is open; "غ" for absent; live total and grade; every mark change audited
- [x] Results sheet per section; close/reopen mark entry; publish results to guardians
- [x] Arabic report cards: print or save as PDF from the browser (one A4 page per student); guardians see their child's card once published

Slice 5 ✅
- [x] Announcements to staff, guardians or everyone, optionally one section only; optional SMS to primary guardians (one message per family phone)
- [x] Dashboard: attendance rate over 30 days, students with 3+ absences, latest announcements; guardians see theirs on the portal
- [x] Report cards: term attendance summary and class teacher's remarks (homeroom teacher or grade managers)
- [x] Subject display order used on results, report cards, setup and marks

Slice 6 — next
- [ ] Navigation: grouped sidebar (the top bar now wraps for admins)
- [ ] Server-side PDF for bulk report-card download (Gotenberg or headless Chrome)
- [ ] Per-stage grading scales; two-factor login, password reset and invitations
- [ ] Verify Noor column aliases against a real export

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
