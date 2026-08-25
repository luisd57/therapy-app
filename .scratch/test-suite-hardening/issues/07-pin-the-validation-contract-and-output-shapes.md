# 07 - Pin the validation contract and the Output DTO shapes

**What to build:** the response contract every client depends on is asserted
whole, rather than being an emergent property of forty controller tests that each
happen to check one field.

Two gaps, both on the boundary the front ends actually consume.

The traits implementing validation are the first. They produce the 422 body with
its field-to-first-message detail map, the shape `api-conventions.md` commits to,
and nothing asserts that shape as a shape. Roughly forty controller tests depend
on it while checking a field at a time, so a change to the trait is reported as
forty unrelated failures with no single test naming the cause.

The Output DTO shapes are the second. Their serialised form is the API contract.
Several Output DTOs are never named anywhere in the test suite, and a renamed or
dropped key is caught only if some controller test happens to assert that exact
key.

**Both are pinned at the integration seam, on real response bodies.** That is the
highest seam that can see the behaviour, per `testing-policy.md`, and it asserts
the bytes a client receives rather than an intermediate object. The consequence,
accepted deliberately: a renamed key surfaces as a controller test failure rather
than as a failure naming the DTO. Pinning the whole body in one place per
endpoint is what keeps that diagnosable.

**Scope this to the contract, not to the classes.** Testing all forty-three DTO
classes individually would be busywork, since most are constructor-to-array with
nothing to get wrong. What is worth pinning is the serialised shape of what
crosses the wire and the error body the traits build.

**Blocked by:** None - can start immediately. The `controller-per-action` split
that gated this landed in PRs #55 to #63, so the controller test files this work
touches are now in their final shape.

**Status:** ready-for-agent

- [ ] The full 422 body is asserted as a whole at a representative endpoint, keys and structure, not one field at a time
- [ ] A field failing two rules reports the documented single first message rather than a list, since that is the contract clients read
- [ ] Every Output DTO that crosses the wire has the complete key set of its serialised form asserted on a real response body
- [ ] Dropping or renaming any asserted key fails the suite, and the assertion sits close enough to the shape that the cause is readable from the failure
- [ ] Success envelopes and the paginated envelope are covered, not only the error envelope
- [ ] Full API suite green
