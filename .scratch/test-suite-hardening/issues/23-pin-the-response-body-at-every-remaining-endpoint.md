# 23 - Pin the response body at every remaining endpoint

> Frozen record, resolved 2026-10-22.

**What to build:** spec story 19 in full. Every endpoint has one test asserting
its whole response body, so a contract change at any endpoint reads as one
failure there.

Ticket 07 pinned one endpoint per wire shape. That catches a change inside an
Output DTO, but not a change in the wrapper one controller puts around it: a
dropped `message`, a renamed wrapper key, or an item list trimmed with
`array_intersect_key`. The code review of 07 confirmed each of those survives the
suite today.

Unpinned as of 2026-09-23. Re-measure before starting, since a neighbouring
ticket may have covered some:

- the five appointment actions returning `{appointment, message}`: book, cancel, complete, confirm, payment status
- the three non-paginated lists and their items: Schedule Blocks, schedule exceptions, invitations
- update Schedule Block, resend and revoke invitation, register
- current patient and current therapist
- the items of the patients list, which needs a patient seeded, since the existing test lists none
- the three `{message}` bodies: logout, forgot password, reset password
- API root and health

Strengthen the existing success test for each endpoint rather than adding a new
one. Read the key lists from ticket 22's shared data.

One unit test sits outside the list: `GetNextAvailableWeekHandlerTest` checks the
week keys with seven `assertArrayHasKey` calls, so an added key passes it. It
predates ticket 07, which is why 22 left it alone.

**Blocked by:** 22

**Status:** resolved

**Resolved by:** [PR #120](https://github.com/luisd57/therapy-app/pull/120)

- [x] Each endpoint listed above has its envelope, wrapper keys and nested key sets asserted on a real response
- [x] Every list is asserted to be a list, and the key set of an item is checked, with at least one item seeded
- [x] For each endpoint, a temporary mutation to its wrapper (drop or rename one key) turns its test red, then is reverted
- [x] No endpoint's pin is satisfied by a lower bound or an `assertArrayHasKey`, only by the exact key set
- [x] `GetNextAvailableWeekHandlerTest` checks the exact key set from `WireShapes::NEXT_AVAILABLE_WEEK` instead of the seven `assertArrayHasKey` calls
- [x] Full API suite green

## Comments

2026-10-22: re-measured before starting, the list above was still exact. Delete Schedule Block and remove schedule exception return 204 with no body, so they have nothing to pin. The review added a `count` value check to the three non-paginated lists, and deleted two tests the body pins cover. A changed key inside `address` still survives at register, current patient, current therapist and the patients list, since their seeded users have no address. Only update patient profile pins `ADDRESS`.
