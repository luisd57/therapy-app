# 29 - Daily agenda email states payment and count in both bodies

**What to build:** tests that fail when the daily agenda email gives the
Therapist the wrong payment state for an Appointment, or miscounts the day in
its plain-text body.

Unpinned as of 2026-10-09: the payment label in the HTML body, the payment
label in the text body, and the summary line of the text body. The HTML
summary line is pinned.

Two traps in how the assertions are written:

- **"1 confirmed appointment" is the start of "1 confirmed appointments".** A
  contains-assertion on the singular passes on the wrong plural. Assert the
  sentence through to the words after the count.
- **An agenda with one verified and one unverified Appointment shows both
  labels whichever way round they are.** Tie each label to its own
  Appointment's row or line, or send one Appointment per agenda.

**Mutants this should kill:** the four kind 1 rows for
`src/Infrastructure/Email/Appointment/AppointmentEmailSender.php` in
`.scratch/test-suite-hardening/mutation/api-survivors-sorted.md` as sorted
2026-10-09. Its kind 2 and kind 3 rows stay, the inline colour beside the
payment label among them.

**Blocked by:** None - can start immediately.

**Status:** ready-for-agent

- [ ] HTML body: an Appointment with payment verified reads Verified in its own row, and an unverified one reads Pending
- [ ] Text body: the same two labels, each on its own Appointment's line
- [ ] Text body with one Appointment says "1 confirmed appointment", singular, and with two says "2 confirmed appointments"
- [ ] A one-file Infection run no longer reports the four rows as escaped
