# 13 - Make the e2e job a required status check on main

**What to build:** A landing or dashboard regression cannot reach `main`. The
`e2e` job joins `test` in the branch protection required checks, so a red suite
blocks the merge button instead of being an advisory signal nobody reads.

## Why this is a ticket and not a settings tweak

The gate is one API call. The work is earning the right to turn it on.

`e2e` has been advisory since it was added, and in that time it went red twice
without anyone noticing, because an advisory check is a check you learn to scroll
past. Both failures were real user-facing defects (tickets 05 and 12), both
date-dependent, and both sat on `main` for days.

Turning the gate on while the suite is red locks the repository: nothing merges,
including the fixes. So this ticket is deliberately last. It is blocked by every
defect that can currently make the suite red, plus the seed change that decides
which days have availability at all.

## Why 08 is a blocker

The suite's day-to-day behaviour is a function of the seed. The current
`app:seed-schedule` has no weekend blocks and marks Friday in-person only, which
is precisely what makes 05 and 12 fire on some days and not others. Seeding the
therapist's real hours changes which days are quiet, so the gate should only go
on once the suite is running against the schedule it will live with.

## The change

Add `e2e` to the required contexts on `main`, keeping `test` and leaving
`strict` as it is. Reverting is the same call without `e2e`.

```
gh api --method PATCH repos/luisd57/therapy-app/branches/main/protection/required_status_checks \
  -f 'contexts[]=test' -f 'contexts[]=e2e'
```

Verify with `gh api repos/luisd57/therapy-app/branches/main/protection --jq
'.required_status_checks.contexts'`.

**Blocked by:** 12, 05, 08. All three must land first, or turning the gate on
locks the repository.

**Status:** ready-for-agent

- [ ] `e2e` and `test` are both required contexts on `main`
- [ ] A pull request with a failing e2e suite cannot be merged
- [ ] The e2e suite has run green on a Friday afternoon, a Saturday and a Sunday practice-local before the gate goes on, since those are the days 12 and 05 fired on
- [ ] `docs/STATUS.md` no longer describes `e2e` as advisory-only
