# 03 - Split TherapistController into one class per action

**What to build:** The Therapist's profile, the Patient list, and the four
invitation actions each answer from their own controller class, every one
enforcing `ROLE_THERAPIST` for itself.

Six actions, 180 lines. Bigger than tickets 01 and 02 but structurally the same
job, and the invitation actions make it the first conversion where a single
directory holds a meaningful group.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006. `User/Auth/` is the worked example.

Suggested placement under `User/Therapist/`, one class per action:
`me`, `listPatients`, `invitePatient`, `listInvitations`, `resendInvitation`,
`revokeInvitation`.

Frozen route names, all six: `api_therapist_me`, `api_therapist_list_patients`,
`api_therapist_invite_patient`, `api_therapist_list_invitations`,
`api_therapist_resend_invitation`, `api_therapist_revoke_invitation`. The URLs do
not follow the route names in any regular way, so read each `#[Route]` rather
than deriving it: `POST /api/therapist/patients/invite` and
`POST /api/therapist/invitations/{id}/resend` are not siblings.

`validateInviteRequest` has a single caller, so it stays a private method on the
invite controller.

Invitation behaviour has Playwright coverage in the dashboard
(`invitation-happy-path`, `invitation-errors`, `invitation-resend-revoke`).
Those specs are the end-to-end proof for this ticket and are worth running.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #57](https://github.com/luisd57/therapy-app/pull/57)

- [x] Each of the six actions lives in its own `final` class whose only public method is `__invoke()`
- [x] Every class carries `#[IsGranted('ROLE_THERAPIST')]` on the action
- [x] `User/TherapistController.php` is deleted
- [x] `debug:router` lists the same route names, methods and URLs before and after; listing order follows class names and may change
- [x] The test file is split to mirror the six classes
- [x] `TherapistController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [x] Full API suite green, with every test that existed before the split still present and passing. Expect the count to fall by 2: the stale-entry loop in `RouteConventionsTest` asserts twice per `PENDING_CONVERSION` entry, and this ticket removes one. Never add assertions to restore the number
- [x] Dashboard invitation Playwright specs pass
