# Market research and decisions (October 2026)

Fees and ZATCA e-invoicing are **deferred** by product decision. This note
covers the other open points: the Noor import, Ministry grading rules, and
online admissions. Research was done from search results; most primary
pages (moe.gov.sa, vendor sites) could not be opened from our build
environment, so every claim carries a confidence level and anything
statutory must be checked against the official documents before a pilot.

## 1. Noor student import

**What we learned**
- Schools mostly export «البيانات الخاصة بالإرشاد الطلابي» (Reports → statistical
  reports, file `StudentGuidance`) or «كشف بأسماء الطلاب». *Medium-high.*
- Fields mentioned: name, class/section, grade, nationality, phone, national ID
  or iqama, date of birth. Gender and guardian columns were not confirmed;
  public schools are single-sex so gender is often absent. *Medium.*
- Exports start with a few merged title rows before the header row; Noor's
  Excel button has at times been missing, so many files are re-saved or
  typed by hand; old `.xls` files circulate. *Medium.*
- Dates of birth are usually Hijri text (`1440/05/12`); IDs are often stored as
  numbers. Sections are commonly numbered (1, 2) rather than lettered. *Medium / low-medium.*

**Our view and what we changed**
Real files will be messier than any spec, so the importer should be forgiving
and transparent rather than strict:
- Finds the header row anywhere in the first 15 rows of any sheet; reports Excel row numbers exactly.
- More Arabic aliases; headers compared without brackets/slashes («تاريخ الميلاد (هـ)», «السجل المدني / الإقامة»).
- Grade names in any common form: «أول متوسط», «الثانية الثانوية», «١ متوسط», «الصف 7», «روضة ثانية».
- Grade and section in one cell («الأول المتوسط / 2»); sections kept as written (numbers or letters).
- IDs stored as numbers or scientific notation; a student's own mobile is never taken as the guardian's.
- Missing gender defaults to the school's when all campuses are boys or all girls.
- `.xls` gets a clear message to re-save as `.xlsx` (no extra library for a legacy format).
- The preview lists the recognised columns and warns when there is no ID column.

**Still needed:** one real `StudentGuidance` file and one «كشف بأسماء الطلاب» file
from a pilot school to confirm the headers. Expect a few aliases to add.

## 2. Ministry grading rules (لائحة تقويم الطالب)

**What we learned**
- From 1447 (2025–26) Saudi general education is back to **two semesters**;
  the three-term weights (25/35/40) no longer apply. *High.*
- Kindergarten: descriptive levels only (مبتدئ / يطوّر المهارة / متمكن), no failing. *High.*
- Grades 1–2: mastery of learning standards — متفوق ≥95, متقدم ≥85, متمكن ≥75, غير مجتاز <75. *Medium.*
- Grades 3–6 and intermediate: out of 100, pass at 50; five bands ممتاز ≥90, جيد جدًا ≥80, جيد ≥70, مقبول ≥50, راسب <50. *Medium-high / medium.*
- Secondary (المسارات): ten bands from ممتاز مرتفع (≥95) to راسب (<50), pass at 50; GPA weighted across years. *Medium-high.*
- Coursework/final splits, a final-exam minimum and re-sit (الدور الثاني) rules exist but could not be confirmed for 2025. *Low-medium.*
- Official 2025 documents: لائحة تقويم الطالب، الإجراءات التنفيذية، دليل توزيع الدرجات (moe.gov.sa RPR library).

**Our view and what we changed**
Ship sensible, Ministry-based defaults and keep everything editable, because
schools will follow the circulars they receive, not our reading of them:
- New schools get five starting scales: school default (grades 3–9), KG
  (descriptive, no fail), grade 1 and grade 2 (mastery, pass 75), secondary
  (ten bands). Defined in `config/madrasa_grading.php`.
- Scales can now apply to a single grade as well as a stage; lookup is grade → stage → school default.
- The app keeps no built-in coursework/final split or term weights — schools set
  weights per subject; demo data now uses two semesters.
- Grades 1–2 are really judged per standard; a single percentage band is an
  approximation we accept for now. Per-standard assessment is a later feature.
- Deferred until verified: final-exam minimum, second-round (re-sit) workflow, rounding rules.

## 3. Online admissions (next phase)

**What we learned**
- Typical Saudi private-school flow: online booking form → accept rules →
  placement test/interview slot → result in ~2 days → seat reservation (fee) →
  documents → Noor transfer. *Medium.*
- Documents: family card or national ID/iqama, birth certificate, vaccination
  record (young ages), health report, photos, last report card, financial
  clearance (مخالصة مالية) from a previous private school, Noor transfer. *High.*
- Age rules change yearly: e.g. for 1448 grade 1 = six years at year start with a
  90-day exception after a full KG year; KG1–3 by birth-date windows. *Medium.*
- Noor transfer needs both schools' approval and an updated national address; no public API. *Medium.*
- International schools use OpenApply; global products (Finalsite, Classter,
  OpenEduCat, Gibbon) offer forms, document checklists, pipelines, waitlists and
  one-click conversion to a student record. Saudi vendors lean on WhatsApp and
  mobile. *High / medium.*

**Our view: MVP without fees**
Most Saudi private schools still run admissions on paper, WhatsApp and Google
Forms. The gap is not a fancier CRM; it is a clean Arabic, phone-first form
that knows Saudi rules and turns an accepted applicant into an enrolled
student without retyping. Build, in order:
1. **Intake windows** per year and grade with seat counts.
2. **Public application** (Arabic first, phone-friendly, resumable): student,
   guardian, target grade, current school, sibling flag.
3. **Age check** against a per-year cutoff table edited by the school (the
   90-day exception is a staff decision, not a hard block).
4. **Document checklist** per grade and nationality, with uploads and
   accept/reject with a reason.
5. **Pipeline**: submitted → under review → assessment booked → assessed →
   offered / waitlisted / rejected → accepted → enrolled (Noor transfer pending).
6. **Notifications** on each status change through our existing SMS / WhatsApp / email channels.
7. **One-click enrol** into the student, guardian and family records we already have; sibling linking by guardian ID/mobile.
8. **Waitlist** ordered by date with sibling priority as a sort, not a rule.

Defer: application and seat-reservation fees (with fees), lotteries, events
and inquiry CRM, e-signature, re-enrolment, analytics. Differentiators to keep:
Saudi age rules editable each year, Noor-transfer and financial-clearance
steps in the checklist, iqama-expiry checks, duplicate-ID detection across
applications, and PDPL-conscious handling of uploaded documents (private
storage, retention limit, access log).
