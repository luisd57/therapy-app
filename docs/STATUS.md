# Implementation Status

- **API**: DONE. All endpoints implemented and tested. One controller class per route action (ADR-0006), entities declare their ORM relations (ADR-0007).
- **Landing**: IN PROGRESS. Slot browser and request form, timezone-aware (viewer-zone grid, zone banner, dual-time display), modality chosen before Slots render. Vitest unit suite and containerized Playwright e2e.
- **Dashboard - done**: therapist login, logout, patient login, patient registration, forgot/reset password, role-based navigation, patient manager (invite/resend/revoke + patients list), containerized Playwright e2e for invitation and auth flows.
- **Dashboard - next**: appointment queue, appointment list.
- **Dashboard - todo**: schedule manager, exception manager, therapist profile, patient area, Spanish translation (the therapist speaks no English, so the dashboard is unusable by its primary user until it lands).
- **Timezone**: 5 of 20 tickets resolved in `.scratch/timezone-management/`. Decisions in ADR-0001 to ADR-0005.
- **Test hardening**: 18 of 44 tickets resolved in `.scratch/test-suite-hardening/`. Mutation testing adopted (ADR-0010), survivor lists in `.scratch/test-suite-hardening/mutation/`.
- **CI**: DONE. `test` job (API PHPStan level 10 with the test convention rules, PHPUnit, Infection on the changed lines of a pull request, dashboard lint, e2e typecheck and build, landing lint, `astro check`, unit tests and build, with the Playwright and Vitest test rules in both lint steps) is the only required check on `main`. `e2e` job (dashboard and landing Playwright) is advisory. The dashboard step is intermittently red on an ambiguous locator (timezone ticket 16), and a red dashboard step skips the landing step (timezone ticket 17).
