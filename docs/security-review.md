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

Repeat this review before each major release and after any change to
tenancy, roles or sign-in.
