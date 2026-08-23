# One controller class per route action

Status: accepted, implemented on 2026-08-19 for `/api/auth` and completed on 2026-08-23, when the
last grouped controller was split under `.scratch/controller-per-action/`.

## The shape this replaced

Controllers were grouped by resource: one class per area, holding every action for it. The
`Http/Controller` layer had 8 classes covering 37 route actions. `AuthController` was the worst of
them at 343 lines and 8 actions, with three private validation helpers wedged into the middle of the
class and two public routes declared after them. Its tests were a single 400-line `AuthControllerTest`
holding 25 test methods for 8 endpoints.

The repo had no written rule on the subject. The whole specification was one parenthetical in
`.claude/rules/api-architecture.md`: `Http/Controller (thin, delegate to handlers)`. Nothing said
where "thin" stopped, so the files grew by default.

Grouped controllers grow here for a structural reason, not a sloppy one. `## Errors` in
`api-conventions.md` deliberately rejects a kernel exception listener and requires each action to
catch the specific domain exceptions it can produce. Every endpoint therefore arrives with its own
try/catch block, and a grouped class has no natural stopping point.

## Decision

One route action per class, one class per file, one test file per class:

```
src/Infrastructure/Http/Controller/{Group}/{Resource}/{Action}Controller.php
```

A `final` class whose only public method is `__invoke()`, carrying the full URL in its `#[Route]`
attribute and its own `#[IsGranted]` where a role is required. This is the rule the Application layer
already follows for handlers, so both sides of the boundary now read the same way.

The rule splits, it does not rename. A controller already holding exactly one action gains nothing
from a deeper path and a new class name, so `PatientAppointmentController` stays as it is.

## Why `__invoke()` rather than a descriptive method name

A named method reads better in a stack trace, but nothing stops a second one being added next to it,
which is exactly how the grouped controllers formed. `__invoke()` makes "one action" a property of
the class rather than a habit the reviewer has to enforce.

The cost is that `__invoke()` already meant "handler" in this codebase. That overlap is accepted:
both layers now mean the same thing by it, which is one public action per file.

## Considered and rejected

**Leaving the controllers grouped and splitting only the test files.** Cheaper, and it addresses the
actual complaint, which was one test file per feature. Rejected: tests mirror src namespaces
everywhere else in the repo, so this trades a documented convention for an undocumented one.

**A line-count or action-count threshold instead of a hard rule.** Rejected: a threshold is an
argument every time it is approached, and `api-architecture.md` already had a soft word ("thin") that
did nothing.

**Keeping class-level `#[Route]` prefixes via a shared base class.** Rejected: it reintroduces the
grouping through inheritance and hides the real URL from the file that serves it.

## Consequences

Each action now repeats its full URL literal and its `#[IsGranted]`. The second of those is the real
risk, because a forgotten role attribute is silent. Two things cover it. `security.yaml`
`access_control` already enforces the same roles by URL, and `RouteConventionsTest` walks the router
and fails the build when a route under `/api/therapist` or `/api/patient` reaches a controller with no
matching attribute. The same test fails when any controller serves more than one action, with no
exemption list to add to.

Route names and URLs are load-bearing beyond the tests. `RateLimitSubscriber` keys off route names
and `security.yaml` matches on URLs, with `^/api/auth/me$` anchored. A conversion that changes either
silently drops rate limiting or an access rule, so the route table is diffed before and after.

Multi-request behaviour has no single controller to belong to. Cookie transport, single-session
replacement and Bearer fallback moved to `JwtCookieTransportTest`, named for the behaviour instead of
a class.

No routing or DI configuration changed. `config/routes.yaml` scans the controller directory
recursively and `services.yaml` autowires all of `src/`, so new subdirectories are picked up as long
as directory names match namespace segments.
