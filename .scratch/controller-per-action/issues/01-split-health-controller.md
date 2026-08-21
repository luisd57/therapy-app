# 01 - Split HealthController into one class per action

**What to build:** The health check and the API root each answer from their own
controller class, with their URLs and route names unchanged.

This is the first conversion after the convention landed, and it is deliberately
the smallest: two actions, 52 lines, no authentication, no constructor
dependencies, one shared trait. Do it first to prove the shape end to end before
the larger controllers.

The convention is `## Controllers` in `.claude/rules/api-architecture.md`, and
the reasoning behind it is ADR-0006. `User/Auth/` is the worked example, so
copy its shape rather than inventing one.

The class names were decided when this ticket was written, because neither
current method name survives the `{Action}Controller` form on its own:

- `health` becomes `Health/HealthCheckController`
- `index` becomes `Health/ApiRootController`

Both routes are frozen. `api_health` serves `GET /api/health` and `api_index`
serves `GET /api/`. The trailing slash on that second URL is load-bearing:
`security.yaml` matches `^/api/$` exactly, so dropping it moves the endpoint
behind authentication.

One difference from the other controllers worth knowing before you start.
`HealthControllerTest` extends Symfony's `WebTestCase` directly rather than
`ApiTestCase`, because these endpoints need no database transaction and no auth
helper. Keep that when splitting the test file. Reaching for `ApiTestCase` here
would add transaction wrapping these tests have no use for.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Each action lives in its own `final` class whose only public method is `__invoke()`
- [ ] `Health/HealthController.php` is deleted
- [ ] `debug:router` output is byte-identical before and after: same route names, methods and URLs
- [ ] The test file is split to mirror the two classes, still extending `WebTestCase`
- [ ] `HealthController` is removed from `PENDING_CONVERSION` in `RouteConventionsTest`
- [ ] `RouteConventionsTest` stays green, including its stale-entry check on that list
- [ ] Full API suite green with no drop in assertion count
