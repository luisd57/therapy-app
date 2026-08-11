---
paths:
  - dashboard/src/**/*.ts
  - dashboard/src/**/*.html
  - dashboard/src/**/*.scss
---
# Angular Dashboard APIs

## Angular 21 Standards
- All components standalone - NO NgModules, no `.module.ts` files
- Signal APIs: `input()`, `output()`, `model()` - NOT `@Input()`, `@Output()`
- `inject()` function - NOT constructor injection
- State: `signal()`, `computed()`, `linkedSignal()`. The dashboard uses neither `httpResource()` nor `rxResource()` today - services return observables. Introducing a resource API is a decision to raise, not to make silently
- Control flow: `@if`, `@for`, `@switch` - NOT `*ngIf`, `*ngFor`, `*ngSwitch`
- Zoneless by default - no Zone.js
- Functional providers in `app.config.ts`: `provideRouter()`, `provideHttpClient()`
- Vitest for testing - NOT Karma/Jasmine
- Reactive forms (`FormBuilder` / `FormGroup`) - the dashboard does not use Signal Forms. Stay consistent rather than mixing both
- Prefer `[class]` / `[style]` bindings over `NgClass` / `NgStyle`
