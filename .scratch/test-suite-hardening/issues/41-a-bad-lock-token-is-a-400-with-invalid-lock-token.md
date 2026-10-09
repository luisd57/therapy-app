# 41 - A bad lock token is a 400 with INVALID_LOCK_TOKEN

**What to build:** tests that fail when an Appointment request carrying a Slot
Lock token the API does not accept stops answering 400 with the code
`INVALID_LOCK_TOKEN`.

A lock token is refused when no Slot Lock holds it, when that lock has
expired, or when it was taken for another Slot or Modality. No HTTP test sends
one (2026-10-09), and the one unit test expects only the exception class. The
error code and the message a client receives are unpinned.

Two endpoints map this error, the public request and the Patient's. The mutant
dies at either. Each endpoint has its own mapping, so the behaviour gets one
case at each.

Ticket 17 reworks the service that raises this error. It is not a blocker: the
assertion is on the HTTP response, which 17 does not change.

**Mutants this should kill:** the kind 1 MethodCallRemoval row for
`src/Domain/Appointment/Exception/InvalidLockTokenException.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A public Appointment request with a lock token no Slot Lock holds gets a 400, with the body asserted whole: envelope, code `INVALID_LOCK_TOKEN` and message
- [ ] The same request from a logged-in Patient gets the same status and code
- [ ] A one-file Infection run no longer reports the row as escaped
