# Analysis of open-source school management systems

We studied ten open-source projects before designing Madrasa. None of them
combines what we need: **multi-school SaaS, K-12, Arabic-first RTL, Saudi
compliance (Noor, ZATCA, PDPL), a real API and tests**. So we build new, and
use these projects as design references.

> Licensing rule: we may adapt code only from MIT projects (keeping their
> notices). GPL projects and projects without a license are **ideas only**,
> never copied code.

## Comparison

| # | Repo | Stack | Multi-school | Arabic / RTL | API | Tests | License | Activity (Oct 2026) |
|---|---|---|---|---|---|---|---|---|
| 1 | [yungifez/skuul](https://github.com/yungifez/skuul) | Laravel 13, Livewire 4, Jetstream, Spatie Permission | **Yes** — shared DB, `school_id`, memberships, roles per school | No | No | ~50 feature tests | MIT | Active, one maintainer |
| 2 | [francoisjacquet/rosariosis](https://github.com/francoisjacquet/rosariosis) | Procedural PHP, Postgres/MySQL | Yes — school + school year | **ar locale** (gettext) | No | No | GPL-2 | Very active |
| 3 | [frappe/education](https://github.com/frappe/education) | Python, Frappe 15–17, needs ERPNext | Via ERPNext Company + Campus | ar translations, Frappe RTL | Auto REST | Per-doctype | GPL-3 | Very active |
| 4 | [changeweb/Unifiedtransform](https://github.com/changeweb/Unifiedtransform) | Laravel 8, Spatie Permission | No | No | No | Minimal | Unclear (MIT vs GPL-3) | Light |
| 5 | [ictinnovations/ICTSchool](https://github.com/ictinnovations/ICTSchool) | Laravel 11, Passport, jQuery | "Branches" = separate installs | No | Stub | No | MIT (no LICENSE file) | Light |
| 6 | [4jean/lav_sms](https://github.com/4jean/lav_sms) | Laravel 8, Blade | No | No | No | No | MIT | Abandoned (2022) |
| 7 | [TareqMonwer/Django-School-Management](https://github.com/TareqMonwer/Django-School-Management) | Django 4.2, DRF + JWT, Celery, Docker | No | No | **Yes** + Swagger | pytest set up | **None** | Active |
| 8 | [SkyCascade/SkyLearn](https://github.com/SkyCascade/SkyLearn) | Django 4.0 | No | i18n set up, no ar | Removed | Minimal | MIT | Dormant |
| 9 | [Ansarimajid/College-ERP](https://github.com/Ansarimajid/College-ERP) | Django 3.1 | No | No | No | No | MIT | Light |
| 10 | [ProjectsAndPrograms/school-management-system](https://github.com/ProjectsAndPrograms/school-management-system) | Plain PHP, mysqli | No | Google Translate widget | No | No | **None** | Student project |

## Feature coverage

✅ solid · ◐ partial · — missing

| Feature | Skuul | Rosario | Frappe | Unified | ICTSchool | lav_sms | Tareq | SkyLearn | College-ERP | P&P |
|---|---|---|---|---|---|---|---|---|---|---|
| Admissions funnel | ◐ invites | — | ✅ | — | ✅ | — | ✅ | — | — | ◐ |
| Years / terms | ✅ | ✅ | ✅ | ✅ | ✅ | ◐ | ✅ | ✅ | ◐ | — |
| Classes / sections | ✅ | ✅ | ✅ groups | ✅ | ✅ | ✅ | ◐ batches | — | — | ◐ |
| Timetable | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | — | ✅ |
| Attendance | — | ✅ | ✅ | ✅ | ✅ | — | — | ◐ | ✅ | ✅ |
| Exams / gradebook | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ◐ | ✅ | ◐ | ◐ |
| Report cards | ✅ | ✅ | ✅ | ◐ | ✅ | ✅ | — | ✅ | — | — |
| Promotion | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | — | — |
| Fees / invoices | ✅ | ✅ | ✅ | — | ✅ family | ✅ | ✅ | ◐ | — | ◐ |
| Library | — | add-on | — | — | ✅ | ◐ | — | — | ◐ | — |
| Transport / hostel | — | — | — | — | ◐ dorm | ◐ dorm | — | — | — | ◐ bus |
| LMS / quizzes | — | Moodle | ◐ | ◐ assign. | ◐ q-bank | — | — | ✅ | — | ◐ notes |
| Parent portal | ◐ | ✅ | ◐ | — | — | ◐ | — | ◐ | — | — |
| SMS / notifications | — | add-on | ✅ | — | ✅ | — | — | — | ◐ FCM | ◐ |
| Audit log | — | ◐ | ✅ | — | ✅ | — | — | ◐ | — | — |

## What we take from each

| Repo | Design ideas we adopt |
|---|---|
| **Skuul** | Tenancy model: `memberships` (user × school) and Spatie Permission "teams" keyed by `school_id`, so one person can hold different roles in different schools. Tests that prove one school can't reach another's data. |
| **RosarioSIS** | Daily *and* per-period attendance with codes; marking periods; weighted gradebook → report card → transcript; discipline; custom fields; fine-grained "can use / can edit" rights per program. |
| **Frappe Education** | **Grading scale** as validated intervals (no gaps/overlaps, one lookup function used everywhere). **Assessment criteria with weights** → result per criterion → total. **Fee structure → fee schedule → invoices** run as a background job with status and errors. **Student group** as the single anchor for timetable, attendance, assessment and fees. Bulk "tool" screens for attendance and marks entry. Admission windows that open/close on a schedule. |
| **Unifiedtransform** | Everything scoped to an academic year/term. Separate **exam rule** (total/pass marks), **grading system** (per grade + term) and **grade rule** (bands). Calculated mark vs final mark with an admin "marks entry open" switch. Promotion as enrollment history, not overwriting a student's class. A per-school setting for section-wise or subject-wise attendance. |
| **ICTSchool** | **Families**: siblings grouped with family-level vouchers and sibling discounts. Mark components (written / practical / continuous assessment). Late-fee rules. Scheduled invoice generation and due-date reminders. SMS/voice notifications behind a driver interface. |
| **lav_sms** | The exam → marks → tabulation sheet → printable report-card flow; affective/skills ratings on report cards. |
| **TareqMonwer** | Admissions pipeline (apply → pay → counseling notes → approve → enroll); production tooling (Celery-style queues, Sentry, Docker); a documented API from day one. |
| **SkyLearn** | Quiz engine: question types (MCQ, true/false, essay), attempts, pass mark, randomized order; course content uploads. |
| **College-ERP** | Leave requests with approval for students and staff; feedback with replies; push notifications. |
| **P&P school-management-system** | A feature checklist only — and a warning: it has a page that changes student records with no login or CSRF check. Every route we write needs auth, authorization and a test. |

## Gaps none of them fill (our differentiators)

1. Arabic-first UI with proper RTL, Arabic PDFs (report cards, invoices, certificates).
2. Hijri (Umm al-Qura) and Gregorian dates side by side.
3. True multi-school SaaS with isolation enforced in code and tests.
4. Saudi compliance: **ZATCA** Phase 2 e-invoicing (15% VAT), **Noor** data exchange, **PDPL** (consent, data residency in KSA, access/deletion requests).
5. Local payments (mada, Apple Pay, SADAD) and WhatsApp messaging to parents.
6. A versioned, documented REST API for mobile apps and integrations.
7. Boys' and girls' campuses within one school.
