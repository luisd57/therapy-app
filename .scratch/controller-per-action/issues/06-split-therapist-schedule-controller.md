# 06 - Split TherapistScheduleController into one class per action

**What to build:** Managing Schedule Blocks and Schedule Exceptions happens
through one controller class per action, each enforcing `ROLE_THERAPIST`.

Seven actions, 331 lines, three private validation helpers. The largest
controller in the codebase and the last conversion, so leave it until the pattern
is settled by the earlier tickets.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006.

Suggested placement under `Appointment/TherapistSchedule/`, one class per
action: `listSchedules`, `createSchedule`, `updateSchedule`, `deleteSchedule`,
`listExceptions`, `addException`, `removeException`.

Frozen route names, all seven: `api_therapist_schedule_list`,
`api_therapist_schedule_create`, `api_therapist_schedule_update`,
`api_therapist_schedule_delete`, `api_therapist_schedule_exceptions_list`,
`api_therapist_schedule_exceptions_create`,
`api_therapist_schedule_exceptions_delete`. Watch the two `access_control` rules
matching these URLs: the pattern `^/api/therapist/schedule` sits above the
broader `^/api/therapist`, and both must keep matching.

This is the ticket where helper placement actually bites. `validateScheduleRequest`
looks shared between create and update, and `validateDateRange` looks shared
between the two list actions. Confirm the caller count for each of the three
helpers before moving them. Two or more callers means a trait in
`Http/Controller/`, one caller means a private method. Do not park a
single-caller helper in a trait just because its neighbours went there.

Use Schedule Block and Schedule Exception in class and test names, per
`CONTEXT.md`. Recurrence is anchored to practice-local time (ADR-0002) and
instants are stored UTC (ADR-0001); neither changes here, but the tests being
moved assert both, so keep their expectations as absolute instants rather than
rederiving them from fixtures.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each of the seven actions lives in its own `final` class whose only public method is `__invoke()`
- [ ] Every class carries `#[IsGranted('ROLE_THERAPIST')]` on the action
- [ ] Each of the three helpers is placed by caller count, not by convenience
- [ ] `Appointment/TherapistScheduleController.php` is deleted
- [ ] `debug:router` output is byte-identical before and after
- [ ] The test file is split to mirror the seven classes, with timezone assertions unchanged
- [ ] `TherapistScheduleController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [ ] Full API suite green with no drop in assertion count
