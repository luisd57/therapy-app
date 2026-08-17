# Dashboard

Private portal for the therapist and her patients. Angular 21 + Angular Material, generated with
[Angular CLI](https://github.com/angular/angular-cli) 21.2.1.

## Layout

`src/app/` is organized by domain, not by technical type. Each domain holds `feature/` (routed
pages), `ui/` (presentational), `data-access/` (API services, stores), and `utils/`, creating only
the sub-folders it needs.

| Domain | State |
| ------ | ----- |
| `auth/` | Login (therapist + patient), registration, forgot/reset password |
| `layout/` | Role-based navigation and shell |
| `patients/` | Patient list, invite, resend, revoke |
| `appointments/` | Route stub, not implemented yet |
| `schedule/` | Route stub, not implemented yet |
| `shared/` | Services, guards, interceptors used across domains |

The full conventions (dependency flow, file naming, no NgModules) are in
`.claude/rules/dashboard-angular.md`.

## Development server

To start a local development server, run:

```bash
ng serve
```

Once the server is running, open your browser and navigate to `http://localhost:4200/`. The application will automatically reload whenever you modify any of the source files.

## Code scaffolding

The CLI schematics do not match this project's layout, so generated files need moving and
renaming. Placing files by hand is usually quicker: pages are `<name>.page.ts`, presentational
components are `<name>.component.ts`, and both live under the owning domain rather than a
top-level `components/` folder.

## Linting

```bash
npm run lint
npm run lint:fix
```

The CI `test` job runs `lint` and fails the build on any error, so run it before pushing.

## Building

To build the project run:

```bash
ng build
```

This will compile your project and store the build artifacts in the `dist/` directory. By default, the production build optimizes your application for performance and speed.

## Running unit tests

To execute unit tests with the [Vitest](https://vitest.dev/) test runner, use the following command:

```bash
ng test
```

## Running end-to-end tests

E2E tests use Playwright and run in a dedicated Docker container (not `ng e2e`).
From the repo root:

```bash
docker-compose --profile e2e run --rm playwright
```

The suite also runs in CI (the `e2e` job). See [`e2e/README.md`](e2e/README.md) for
prerequisites, env overrides, reports, and the per-test breakdown.

## Additional Resources

For more information on using the Angular CLI, including detailed command references, visit the [Angular CLI Overview and Command Reference](https://angular.dev/tools/cli) page.
