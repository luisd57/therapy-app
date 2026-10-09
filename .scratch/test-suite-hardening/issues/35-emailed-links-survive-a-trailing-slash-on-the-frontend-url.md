# 35 - Emailed links survive a trailing slash on the frontend URL

**What to build:** tests that fail when a trailing slash on `APP_FRONTEND_URL`
puts a double slash into a link the API emails.

Three handlers build a link from the configured frontend URL and strip a
trailing slash first: the invitation, the resent invitation and the password
reset. Every test builds them with a base URL that has no trailing slash
(2026-10-09), so the strip can be removed and nothing fails. The link would
read `//register?token=...` after the host, and whether that still opens
depends on whatever serves the dashboard.

The link a handler hands to the email sender is its output. Assert on that
link.

**Mutants this should kill:** the kind 1 UnwrapRtrim row on each of
`src/Application/User/Handler/InvitePatientHandler.php`,
`src/Application/User/Handler/RequestPasswordResetHandler.php` and
`src/Application/User/Handler/ResendInvitationHandler.php`, in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The two log-context rows on each file are kind 2 and stay.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] With a frontend URL ending in a slash, the invitation link has exactly one slash between the host and its path
- [ ] The same holds for the resent invitation link
- [ ] The same holds for the password reset link
- [ ] A one-file Infection run on each handler no longer reports its UnwrapRtrim row as escaped
