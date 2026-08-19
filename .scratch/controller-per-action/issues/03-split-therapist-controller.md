# 03 - Split TherapistController into one class per action

**What to build:** The Therapist's profile, the Patient list, and the four
invitation actions each answer from their own controller class, every one
enforcing `ROLE_THERAPIST` for itself.

Six actions, 180 lines. Bigger than tickets 01 and 02 but structurally the same
job, and the invitation actions make it the first conversion where a single
directory holds a meaningful group.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, with
the reasoning in ADR-0006. `Api/User/Auth/` is the worked example.

Suggested placement under `Api/User/Therapist/`, one class per action:
`me`, `listPatients`, `invitePatient`, `listInvitations`, `resendInvitation`,
`revokeInvitation`.

Frozen route names, all six: `api_therapist_me`, `api_therapist_list_patients`,
`api_therapist_invite_patient`, `api_therapist_list_invitations`,
`api_therapist_resend_invitation`, `api_therapist_revoke_invitation`. Note the
paths do not follow the names in any regular way, so read each `#[Route]` rather
than deriving it. `/api/therapist/patients/invite` and
`/api/therapist/invitations/{id}/resend` are not siblings.

`validateInviteRequest` has a single caller, so it stays a private method on the
invite controller.

Invitation behaviour has Playwright coverage in the dashboard
(`invitation-happy-path`, `invitation-errors`, `invitation-resend-revoke`).
Those specs are the end-to-end proof for this ticket and are worth running.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each of the six actions lives in its own `final` class whose only public method is `__invoke()`
- [ ] Every class carries `#[IsGranted('ROLE_THERAPIST')]` on the action
- [ ] `Api/User/TherapistController.php` is deleted
- [ ] `debug:router` output is byte-identical before and after
- [ ] The test file is split to mirror the six classes
- [ ] `TherapistController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [ ] Full API suite green with no drop in assertion count
- [ ] Dashboard invitation Playwright specs pass
