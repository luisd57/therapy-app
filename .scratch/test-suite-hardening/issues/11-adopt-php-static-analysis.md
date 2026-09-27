# 11 - Adopt PHP static analysis

> Frozen record, resolved 2026-09-27.

**What to build:** the API has a static analysis gate, so the class of bug that
survives a green test suite is caught before review.

There is none today. No PHPStan, no Psalm, no CS-Fixer, and no PHP lint step in
continuous integration at all. The front ends both have strict, type-aware
linting. The API, which holds most of the code and all of the domain logic, has
none.

**This is a decision, not a gap fix, which is why it is its own ticket.** Two
choices have to be made deliberately and then lived with:

**The level.** Starting at the maximum on an existing codebase produces a wall of
findings and the usual outcome is that the level quietly drops later. Starting
low and ratcheting means the gate is real from day one. Either is defensible.
Pick one and write down why.

**The baseline.** A baseline lets the gate go on immediately by grandfathering
everything already there. It also means the gate protects new code only, and a
large baseline is indistinguishable from not having the tool. If a baseline is
used, decide what shrinks it over time, or accept that it will not shrink.

Record both in an ADR under `docs/adr/`. A level chosen without a recorded reason
gets "helpfully" adjusted by the next person who hits a red build, which is the
failure mode `documentation-style.md` warns about for bare rules.

Expect interesting findings around the hexagonal boundaries: the repositories
return domain types the ORM knows nothing about, and the DTOs hand arrays across
layers. Those are the places where analysis pays for itself.

**Blocked by:** None - can start immediately.

**Status:** resolved

**Resolved by:** [PR #97](https://github.com/luisd57/therapy-app/pull/97)

- [x] A static analysis tool is installed and configured for the API
- [x] The chosen level and the baseline stance are recorded in an ADR, with the reasoning
- [x] The analysis runs in continuous integration and a violation fails the build
- [x] The tests directory is in scope or is deliberately excluded with a stated reason
- [x] Introducing a deliberate violation fails the pipeline, proving the gate is connected
- [x] Full pipeline green

## Comments

**2026-09-03** - This ticket now unblocks two things rather than one. It already blocks 15.
It also changes ticket 16, which takes `--static-analysis-tool=phpstan` and explains what it
does there. Short version: mutants that cannot typecheck stop counting as survivors, so that
run gets cheaper to read and stronger at the same time.

That bears on the level decision this ticket asks for without settling it. A higher level
kills more mutants for free, which is an argument for starting high that the framing above
does not have. It says nothing about the baseline question, since a baseline grandfathers
findings in committed code and this effect runs over generated mutants instead.

**2026-09-27** - PHPStan 2.2.16 at level 10, no baseline, `src` and `tests` both in scope, with the
Symfony, Doctrine and PHPUnit extensions. ADR-0008 records the level, the baseline stance and the
per-level counts. First run at level 10: 309 findings in `src`, 369 in `tests`, 678 in all. All
fixed, none baselined. Seven inline ignores remain, all in `tests/`, each carrying its reason.

Most of the wall was two patterns: controllers indexing `json_decode()` output, and integration
tests doing the same with response bodies. `JsonBody` in `Http/Controller` and `Json` in
`tests/Helper` replace both.

The real defects it found, each with a test shown to fail against the old code:

- A JSON value of the wrong type reached a typed DTO constructor and answered 500. A number as
  `phone` on the public request endpoint, a string as `supports_online`. Now 422.
- An optional field of the wrong type would have been dropped. A numeric `patient_id` on an
  Appointment the therapist enters would have left it with no Patient attached. The profile update
  dropped a non-object `address` and a wrong-typed `postal_code` or `state`. Now 422.
- `day_of_week: 3.5` was accepted and cast to Wednesday. Now 422.
- A JWT claim of the wrong type threw inside `JwtDecodedListener`. Now fails closed.
- The Doctrine types cast any value to a string. They now throw `InvalidType` for a value that is
  neither a string nor `Stringable`.

It also flagged 31 test assertions it could prove always true. Details in ADR-0008.

Gate checked locally: a planted `return.type` error in `src`, an offset read on `mixed` in
`tests`, and a bare `@phpstan-ignore` without a reason each fail the analysis.

Review ran the new guards as mutants. Fourteen left the suite green, and each now has a test that
kills it. One more cannot be reached: `LogoutController` checks `is_string($jti)`, but the logout
firewall has `JwtDecodedListener` reject a non-string jti with a 401 first.

**2026-09-27** - Gate proven connected in CI on PR #97. A planted `return.type` error in
`tests/Unit/PlantedViolation.php` failed the `test` job at "Run PHPStan", and PHPUnit was skipped
(run 36347971610). It went in `tests/` because the skill gate blocks `src/` edits once a PR is open,
and PHPStan analyses both in the same step. Reverted. The revert leaves the tree identical to
`8ad95a4`, where both jobs passed (run 36347925500).
