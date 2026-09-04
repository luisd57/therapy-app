# 21 - One source for the password rules

**What to build:** the six password rules exist once in the API. Whoever changes a
rule changes it in one place, and every caller moves with it.

There are two hand-maintained copies today. `PasswordValidator` is a static class
returning an error string, and the `app:create-therapist` command is its only
caller. `PasswordStrength` plus `PasswordStrengthValidator` is a Symfony
constraint, used by registration and by password reset. Same six conditions, same
length bound, and the five message strings retyped identically in both. Nothing
fails if one copy drifts from the other.

**Collapse onto the constraint and delete `PasswordValidator`.** The command
already lives in the container, so it can take a `ValidatorInterface` the way the
controllers do. That leaves one implementation and one set of message strings, and
the strings are byte-identical today, so no CLI output changes.

**The two copies already disagree, and the difference bites on the way across.**
The constraint returns without a violation for an empty value, because the
controllers put a `NotBlank` in front of it. The static one rejects an empty
password with the length message. Move the command over without adding that
`NotBlank` and an empty password reaches the handler. Ticket 04 pinned the current
CLI behaviour, so the test that goes red is the signal, not a nuisance.

**Not in scope:** the dashboard holds a third copy of the same six rules. Sharing
rules between the API and an Angular front end needs a published contract, which
is a design decision rather than this refactor. Ticket 09 covers that copy where
it lives.

**Blocked by:** None - can start immediately. Ticket 04 covers both copies rule by
rule, so landing after it means the refactor is checked by tests that can already
see every rule.

**Status:** ready-for-agent

- [ ] One implementation of the six rules and their message strings remains, and `PasswordValidator` is deleted
- [ ] `app:create-therapist` validates through it and still refuses each rule with the message it prints today
- [ ] An empty password is still refused at the command line, which the constraint does not do on its own
- [ ] The rule coverage from ticket 04 survives the move rather than being deleted with the class it tested
- [ ] Deleting any single rule fails a test on both the command line path and the HTTP path
- [ ] Full API suite green
