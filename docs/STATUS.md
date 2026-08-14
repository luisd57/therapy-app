# Implementation Status

- **API**: DONE. All endpoints implemented and tested; post-review security cleanups 2026-05-28; auth reverted to single-session `THERAPY_JWT` cookie 2026-06-03; timezone management 2026-08-13 (UTC `timestamptz`, practice-zone schedule blocks, instants-only request/response contract).
- **Landing**: IN PROGRESS. Slot browser + request form, timezone-aware (viewer-zone grid, zone banner, dual-time display); Vitest unit suite for date helpers; Playwright E2E suite for the reservation flow, containerized.
- **Dashboard - done**: therapist login, logout, patient login, patient registration, forgot/reset password, role-based navigation, patient manager (invite/resend/revoke + patients list), Playwright E2E suite for invitation + auth flows, containerized.
- **Dashboard - next**: appointment queue, appointment list.
- **Dashboard - todo**: schedule manager, exception manager, therapist profile, patient area, Spanish translation (the therapist speaks no English, so the dashboard is currently unusable by its primary user).
- **Timezone**: 8 of 14 planned steps merged. 11 tickets open in `.scratch/timezone-management/`; decisions in ADR-0001 to ADR-0005. Email times and the daily-agenda schedule are known defects (ADR-0005, ADR-0004).
- **CI**: DONE. GitHub Actions - `test` job (API PHPUnit + dashboard lint/build + landing build); `e2e` job (dashboard + landing Playwright via docker-compose + `docker-compose.ci.yml`, advisory-only). `main` protected, PRs required, `test` check must pass. `e2e` is currently RED on the modality defect (ticket 05) and fails only on days where the first offered slot is in-person only.
