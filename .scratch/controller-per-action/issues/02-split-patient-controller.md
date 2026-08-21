# 02 - Split PatientController into one class per action

**What to build:** A Patient reads and updates their own profile through two
separate controller classes, each enforcing `ROLE_PATIENT` on its own action.

Two actions, 127 lines. This is the first conversion where the role check
actually moves: `PatientController` carries a class-level
`#[IsGranted('ROLE_PATIENT')]` today, and splitting the class means each new
controller has to declare it for itself. That is the part to get right, and it is
why this ticket comes before the larger ones.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006. `User/Auth/` is the worked example.

Suggested placement under `User/Patient/`, following the `CurrentUserController`
naming already used for the `api_auth_me` route:

- `me` becomes `CurrentPatientController`
- `updateProfile` becomes `UpdatePatientProfileController`

Frozen routes: `api_patient_me` on `GET /api/patient/me`, and
`api_patient_update_profile` on `PUT|PATCH /api/patient/profile`. Keep both verbs
on the update route.

`validateProfileUpdateRequest` has a single caller, so per the convention it stays
a private method on the update controller rather than becoming a trait.

Forgetting the role attribute on one of the two will not show up as a failing
request, because `security.yaml` `access_control` still guards the URL pattern
`^/api/patient`. `RouteConventionsTest` is what catches it, so trust that test rather than a
manual check.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each action lives in its own `final` class whose only public method is `__invoke()`
- [ ] Both classes carry `#[IsGranted('ROLE_PATIENT')]` on the action
- [ ] `User/PatientController.php` is deleted
- [ ] `debug:router` output is byte-identical before and after, with `PUT|PATCH` preserved on the profile route
- [ ] The test file is split to mirror the two classes
- [ ] `PatientController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [ ] Full API suite green with no drop in assertion count
