# 32 - Blocklist entries expire with their TTL

**What to build:** a test that fails when the JWT blocklist keeps an entry past
the TTL it was stored with.

Logout stores the token's jti and a password reset stores a per-user cutoff,
each with a TTL. Nothing checks the TTL is applied (2026-10-09). Without it
every logout leaves a key in Redis for good. That is storage growth, not a
security hole, which is why nothing has noticed.

The blocklist takes any cache pool, and the in-memory pool its unit test
already uses accepts a clock. So the test can move time and then ask the
blocklist its own two questions: is this jti revoked, is this token cut off.
Do not read the cache item or its expiry.

**Start the clock at the real current time.** The cache item stamps its expiry
from the system clock, and the pool reads its injected clock only when it
compares (checked in the vendored Symfony cache, 2026-10-09). A clock frozen at
a fixture Instant is red on correct code. Start it at now and move it forward.

**Mutants this should kill:** the two kind 1 rows for
`src/Infrastructure/Security/RedisJwtBlocklist.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. The seven kind 2 rows on that file stay.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] A jti revoked with a TTL is reported revoked before the TTL has passed, and not after
- [ ] A per-user cutoff stored with a TTL rejects an older token before the TTL has passed, and not after
- [ ] Time moves through an injected clock, with no sleep, and both answers come from the blocklist's public methods
- [ ] A one-file Infection run no longer reports the two rows as escaped. If a row proves equivalent, move it to kind 2 in the sorted list with the reason instead
