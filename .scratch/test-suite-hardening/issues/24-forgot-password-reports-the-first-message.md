# 24 - Forgot password reports the first message

**What to build:** the forgot password endpoint builds its 422 details the way
every other endpoint does, keeping each field's first message.

The documented contract is field to first message. Every controller reads the
first violation except forgot password, which loops over all of them and keeps
the last. Today no input can tell the two apart: an empty email fails only
NotBlank, because the Email rule skips empty strings, and a malformed one fails
only Email. So this is a latent inconsistency, not a live bug. It turns into one
the day a second rule is added to that field.

Say it plainly in the change: no test can observe first-vs-last at this endpoint
today, so the pinned body proves the shape, not the choice of message.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] Forgot password takes the first violation per field, matching the other controllers
- [ ] Its 422 body for a missing email is asserted whole, envelope, code, message and details
- [ ] Its 422 body for a malformed email is asserted whole
- [ ] Full API suite green
