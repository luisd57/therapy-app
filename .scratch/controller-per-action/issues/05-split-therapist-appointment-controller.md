# 05 - Split TherapistAppointmentController into one class per action

**What to build:** Each step the Therapist takes on an Appointment - listing,
viewing, confirming, completing, cancelling, booking, and toggling payment -
answers from its own controller class, each enforcing `ROLE_THERAPIST`.

Seven actions, 280 lines. The actions map closely onto the Appointment status
lifecycle, so the resulting directory reads as the set of transitions the
Therapist can perform.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006.

Suggested placement under `Appointment/TherapistAppointment/`, one class per
action: `list`, `show`, `confirm`, `complete`, `cancel`, `book`, `updatePayment`.
`list` and `show` are not usable as class name stems on their own, so name them
for what they return rather than the method they replace.

Frozen route names, all seven: `api_therapist_appointments_list`,
`api_therapist_appointments_show`, `api_therapist_appointments_confirm`,
`api_therapist_appointments_complete`, `api_therapist_appointments_cancel`,
`api_therapist_appointments_book`, `api_therapist_appointments_payment`. Note
that `list` and `book` share the path `/api/therapist/appointments` and are
separated only by verb, so both `#[Route]` attributes must keep their `methods:`.

`validateBookRequest` has a single caller, so it stays private on the book
controller. `ValidationHelperTrait` stays shared and untouched.

Only CONFIRMED appointments block a Slot, and COMPLETED and CANCELLED are
terminal. None of that changes here, but the tests asserting it are the ones
being moved, so keep their assertions intact rather than rewriting them.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each of the seven actions lives in its own `final` class whose only public method is `__invoke()`
- [ ] Every class carries `#[IsGranted('ROLE_THERAPIST')]` on the action
- [ ] The two actions sharing `/api/therapist/appointments` keep their distinct `methods:`
- [ ] `Appointment/TherapistAppointmentController.php` is deleted
- [ ] `debug:router` output is byte-identical before and after
- [ ] The test file is split to mirror the seven classes
- [ ] `TherapistAppointmentController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [ ] Full API suite green with no drop in assertion count
