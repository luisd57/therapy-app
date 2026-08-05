# Implementation Status

- **API**: DONE. All endpoints implemented and tested; post-review security cleanups 2026-05-28; auth reverted to single-session `THERAPY_JWT` cookie 2026-06-03.
- **Landing**: IN PROGRESS. Slot browser + request form; Playwright E2E suite for the reservation flow, containerized.
- **Dashboard — done**: therapist login, logout, patient login, patient registration, forgot/reset password, role-based navigation, patient manager (invite/resend/revoke + patients list), Playwright E2E suite for invitation + auth flows, containerized.
- **Dashboard — next**: appointment queue, appointment list.
- **Dashboard — todo**: schedule manager, exception manager, therapist profile, patient area.
- **CI**: DONE. GitHub Actions — `test` job (API PHPUnit + dashboard lint/build + landing build); `e2e` job (dashboard + landing Playwright via docker-compose + `docker-compose.ci.yml`, advisory-only). `main` protected, PRs required, `test` check must pass.
