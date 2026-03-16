---
paths:
  - dashboard/src/**/*.ts
  - dashboard/src/**/*.html
  - dashboard/src/**/*.scss
---
# Angular Dashboard Conventions

## Domain-Based Folder Structure

Organize `src/app/` by domain, NOT by technical type. NEVER create top-level `components/`, `services/`, `directives/`, `pipes/`, or `models/` folders.

```
src/app/<domain>/
├── feature/        # smart/routed page components
├── ui/             # dumb/presentational components
├── data-access/    # services (API), stores (state)
└── utils/          # pure helpers, types, constants
```

Only create sub-folders you actually need. The `shared/` folder holds code reused across domains.

## Dependency Flow (STRICT)

```
feature  →  data-access  →  utils
   ↓
   ui     →  utils
```

- Cross-domain imports are FORBIDDEN. Shared needs go in `shared/`.
- `ui/` MUST NOT import from `feature/` or `data-access/`.
- `data-access/` MUST NOT import from `feature/` or `ui/`.

## Naming Conventions

| Type                     | File Pattern               | Export Pattern       |
|--------------------------|----------------------------|----------------------|
| Routed page              | `<n>.page.ts`              | `<N>Page`            |
| Presentational component | `<n>.component.ts`         | `<N>Component`       |
| Service                  | `<n>.service.ts`           | `<N>Service`         |
| Store                    | `<n>.store.ts`             | `<N>Store`           |
| Shell routes             | `<domain>-shell.routes.ts` | `<domain>Routes`     |
| Model / interface        | `<n>.model.ts`             | `<N>`                |

Kebab-case for all files/folders. One component/service/store per file. No `index.ts` barrel files.

## Smart Components (feature/)
- Standalone, no NgModules
- Inject services/stores via `inject()`
- Pass data to dumb components via signal `input()`, receive events via `output()`
- Minimal template logic — delegate display to `ui/` components

## Dumb Components (ui/)
- Standalone, receive data via signal `input()`, emit via `output()`
- MUST NOT inject services, stores, Router, ActivatedRoute, or any app-level dependency

## Shell Routes
- Every domain with multiple routes has a `<domain>-shell.routes.ts` in `feature/`
- Contains ONLY route definitions — no components, no services
- Use `loadComponent` for individual pages, `loadChildren` for domain route sets

## Data-Access
- Services: HTTP/API interaction (`<domain>.service.ts`)
- Stores: Reactive state with signals (`<domain>.store.ts`)
- Only smart components (feature/) may inject from data-access/

## Angular 21 Standards
- All components standalone — NO NgModules, no `.module.ts` files
- Signal APIs: `input()`, `output()`, `model()` — NOT `@Input()`, `@Output()`
- `inject()` function — NOT constructor injection
- State: `signal()`, `computed()`, `linkedSignal()`, `resource()` / `httpResource()`
- Control flow: `@if`, `@for`, `@switch` — NOT `*ngIf`, `*ngFor`, `*ngSwitch`
- Zoneless by default — no Zone.js
- Functional providers in `app.config.ts`: `provideRouter()`, `provideHttpClient()`
- Vitest for testing — NOT Karma/Jasmine
- Signal Forms (`@angular/forms/signals`) for new forms
- Prefer `[class]` / `[style]` bindings over `NgClass` / `NgStyle`
