# Pre-pilot security review (October 2026)

Scope: the whole application (tenancy, authorization, mass assignment,
sign-in flows, XSS, uploads, SSRF, cost abuse). Method: code review with
file-level evidence, then fixes with regression tests in
`tests/Feature/SecurityHardeningTest.php`.

**Result: no high-severity issues and no cross-school data leak found.**
Tenant isolation held: the school scope fails closed, writes for another
school are refused, route binding runs after the school is resolved, every
`exists`/`unique` rule is school-limited, and the only scope bypass
(`Invitation::findPending`) is by token hash.

## Findings and fixes

| # | Severity | Finding | Fix |
|---|---|---|---|
| M1 | Medium | API token endpoint throttled only per IP; 2FA code guessable alongside the password; tokens never expired | Per-account (email+IP) and per-user code limits like the web sign-in; tokens expire after 30 days (`SANCTUM_EXPIRATION`) |
| M2 | Medium | "Turn on 2FA" while already on silently disabled it, without the password | Refused while on; turning off still needs the password |
| M3 | Medium | Teachers/accountants could list every guardian's national ID and phone through the API | Guardian directory needs `students.manage`; national IDs only returned to `students.manage` |
| M4 | Medium | Flipping a mark A→P→A, or backdating registers, could send unlimited paid SMS; announcements unthrottled | One alert per student/day/period (`guardian_notified_at`); teachers limited to the last 7 days; throttles on register saves and announcements |
| L1 | Low | Accepting an invitation logged an existing 2FA account in with the password only | Goes through the code step |
| L2 | Low | Password change/reset left other sessions and API tokens alive | Both sign out other sessions and revoke tokens |
| L3 | Low | Report-card remarks allowed by homeroom link alone; term not checked against section | Also needs `grades.record` and an active staff record; term/year mismatch is a 404 |
| L4 | Low | Unreadable uploads left on disk; no row cap; abandoned previews never cleaned | try/finally cleanup, 2,000-row cap, daily `madrasa:prune-imports` |
| L5 | Low | A holder of `members.manage` could grant roles above their own access | Roles can only be granted if their permissions are a subset of the granter's |
| L6 | Low | Platform admins reach every school with a password only | Outside local/testing, platform powers need 2FA |
| L7 | Low | Production settings (debug, cookies, demo passwords) | See [deployment.md](deployment.md) checklist |

## Checked and fine

Tenancy (above); every route in the `school` group has a permission,
policy or ownership check; `is_platform_admin`, 2FA fields, `school_id`,
`user_id`, `token_hash` are not mass-assignable; login, 2FA and invitation
flows throttled, sessions regenerated, invitation tokens hashed, expiring,
single-use and bound to the invited email; no unescaped user content in
Blade or Vue (`v-html` only for server-made paginator labels and QR SVG);
SMS/WhatsApp/Gotenberg URLs come only from env; uploads are type/size
checked and stored under random names on the private disk.

## Admissions (added after the review)

The public admission pages are the first routes open to anyone. What
protects them:
- The school comes from the URL slug, only for active schools; the
  application is found by the hash of a 40-character random token, inside
  that school only. Wrong token or wrong school → 404. The token is also
  kept encrypted (so later messages can repeat the link); message logs
  store "[link]" instead of the URL.
- Throttles: 5 applications, 20 uploads and 10 accept/withdraw actions per
  minute per IP; 60 status-page views.
- Uploads: PDF/JPG/PNG up to 5 MB, only the types the checklist asks for,
  private disk, random names; an accepted document cannot be replaced.
- Status pages send `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex`
  so the private link does not leak to other sites or search engines.
- Families can only accept an offer or withdraw; every other move is staff
  only (`admissions.manage`) and checked against the status map.
- Not done yet: a CAPTCHA on the form (add one if bots appear) and
  malware scanning of uploads (needs a scanner in the hosting setup).

## Second review — all modules (October 2026)

An independent pass over every controller added in phases 2–3 (admissions,
setup, platform, exports, homework, behaviour, requests, clinic, library,
transport, inventory, quizzes, portal invitations, payroll, national tests,
analytics). No high-severity issue and no cross-school leak. Fixed:

| # | Finding | Fix |
|---|---|---|
| M1 | A staff manager could re-link a colleague's staff record to their own login and read the colleague's payslips and leave attachments | Moving a linked staff record to another login needs `school.manage` |
| M2 | A portal invitation could be sent to a staff member's own email, handing them a family's health and behaviour pages | Invitations to staff logins are refused; every portal invitation is audit-logged |
| L1 | The public admission form could be used to send SMS to any number | Saudi mobiles only, a hidden honeypot field, a minimum fill time, 3 applications per mobile and a per-school cap per day |
| L2 | Typing another family's guardian ID alone joined that family (waitlist priority, portal) | Self-service matching needs the mobile, and the ID when both exist; staff see the family before enrolling |
| L3 | Analytics showed grades and behaviour to roles without those permissions | Those parts are sent only with `grades.view` / `behaviour.manage` |
| L4 | Behaviour messages could be repeated | One behaviour message per student per day; lower throttle |
| L5 | Message logs kept the admission private link | Logged as "[link]" |
| L6 | One accountant could edit their own pay and approve their own payroll | Own contract locked; the approver must differ from the preparer |
| — | Teaching rights from past years; leave attachment of a removed staff record | Current year only; handled |

Each fix has a regression test.

Repeat this review before each major release and after any change to
tenancy, roles or sign-in.
