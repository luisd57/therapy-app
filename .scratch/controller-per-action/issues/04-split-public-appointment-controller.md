# 04 - Split PublicAppointmentController into one class per action

**What to build:** Slot browsing, next-available-week, Slot locking and public
Appointment requests each answer from their own controller class, still
unauthenticated and on the same URLs.

Four actions, 286 lines, three private validation helpers. This is the first
conversion in the Appointment area and the first with a test file large enough
(415 lines) that splitting it is most of the work.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006.

**Placement.** Use `Appointment/PublicAppointment/`, which mirrors the sibling
`Appointment/TherapistAppointment/` and matches the name the class already has.
`Appointment/Public/` is a legal alternative rather than a forbidden one, so
choose on symmetry: `Public` works as a namespace segment on PHP 8.4, checked as
a declaration, a `use` import and a fully qualified instantiation. It just breaks
the pattern its neighbours follow and reads like a web root.

Whichever you pick, the directory name has to match the namespace segment
exactly, or both the PSR-4 route loader and the DI glob skip the classes in
silence.

Frozen routes: `api_available_slots`, `api_next_available_week`, `api_lock_slot`,
`api_request_appointment`. Two of those are rate limited by name in
`RateLimitSubscriber` (`api_lock_slot`, `api_request_appointment`), so a rename
drops rate limiting without failing a test. There is also a similarly named
`api_patient_request_appointment` on a different controller, so do not confuse
the two.

Helper placement follows the convention: check the caller count for each of
`validateAvailableSlotsRequest`, `validateLockSlotRequest` and
`validateRequestAppointmentRequest`. One caller means a private method on that
controller, two or more means a trait in `Http/Controller/`.
`ValidationHelperTrait` already exists and is shared with the other Appointment
controllers, so leave it alone.

The landing site's Playwright specs exercise the public reservation flow and are
the end-to-end proof for this ticket.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #58](https://github.com/luisd57/therapy-app/pull/58)

- [x] Each of the four actions lives in its own `final` class whose only public method is `__invoke()`
- [x] Every directory name matches its namespace segment exactly, so the route loader and DI glob find all four classes
- [x] `Appointment/PublicAppointmentController.php` is deleted
- [x] `debug:router` lists the same route names, methods and URLs before and after; listing order follows class names and may change
- [x] Every route name `RateLimitSubscriber` keys off still resolves in the router
- [x] The test file is split to mirror the four classes
- [x] `PublicAppointmentController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [x] Full API suite green, with every test that existed before the split still present and passing. Expect the count to fall by 2: the stale-entry loop in `RouteConventionsTest` asserts twice per `PENDING_CONVERSION` entry, and this ticket removes one. Never add assertions to restore the number
- [x] Landing reservation Playwright specs pass
