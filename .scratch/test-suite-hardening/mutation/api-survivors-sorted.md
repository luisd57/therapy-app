# API surviving mutants, sorted

The survivors in `api-survivors.md`, outside the files with an open ticket, with a call on what
each one is. Like that list it is evidence, not a backlog (ADR-0010). The call is there for
whoever touches the line next. Sorted by hand, so a rerun of the list does not update this file.

| | |
|---|---|
| Date | 2026-10-09 |
| Sorted from | `api-survivors.md` at `ccfdc24` |
| Source read at | `339b00e`, squashed into `fd6e403`. `API/src` is the same at all three commits |
| Kind 1, real gap | 63 |
| Kind 2, harmless | 81 |
| Kind 3, awkward but real | 9 |
| Sorted | 153 |
| Skipped | 4 |
| Total | 157 |

The timed-out mutant (`AvailabilityComputer.php` line 108) is not a survivor and is not sorted.

Ticket 17 closed on 2026-10-10 and its ten skipped rows were sorted then. Seven were real gaps
and are killed, so they left the total: the lock mismatch check and the four day-start rows in
`AppointmentRequestService.php`, and the two zero guards in `SlotGenerationRules.php`. The other
three are in Kind 2.

- **1, real gap**: output or stored state changes through a public seam, on input the system can
  really receive, and an existing test seam can see it.
- **2, harmless**: equivalent mutant, or it only changes diagnostics or narration (log context,
  console success text, exception int code, inline colour).
- **3, awkward but real**: a true difference that needs input the system does not produce today
  (a second therapist, a race, a config value with a quote in it).

## Kind 1, real gap (63)

### `src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 72 | LessThan | The lookahead bound is unpinned. The not-found test stubs the computer empty for every call, so one extra week goes unseen. |
| 78 | LessThanNegotiation | Drops in-week Schedule Exceptions from the week's context. The unit test stubs the exception repository empty and the controller test seeds none. |
| 78 | LogicalAndAllSubExprNegation | Same, the filter comes back empty. |
| 78 | LogicalAndNegation | Same, only out-of-week exceptions get through. |
| 79 | GreaterThanNegotiation | Same, only exceptions that ended before the week get through. |
| 83 | LessThanNegotiation | Drops in-week confirmed appointments from the week's context. No test puts one in front of this handler. |
| 83 | LogicalAndAllSubExprNegation | Same, the filter comes back empty. |
| 83 | LogicalAndNegation | Same, only out-of-week appointments get through. |
| 84 | GreaterThanNegotiation | Same, only appointments that ended before the week get through. |

### `src/Domain/Appointment/Entity/TherapistSchedule.php`

| Line | Mutator | Reason |
|---|---|---|
| 60 | MethodCallRemoval | The format check on create()'s `$startTime` is masked. '9:00' and '25:00' also trip the start-before-end check, and the tests expect only the exception class. |
| 61 | MethodCallRemoval | No test gives create() a malformed `$endTime`. |
| 87 | MethodCallRemoval | No test gives update() a malformed `$startTime`. |
| 88 | MethodCallRemoval | No test gives update() a malformed `$endTime`. |
| 90 | GreaterThanOrEqualTo | update() with equal start and end is untested. create() pins its twin. |
| 208 | PregMatchRemoveCaret | Leading junk ('x09:00') would pass. No test has it. |
| 208 | PregMatchRemoveDollar | Trailing junk ('09:00x') would pass. No test has it. |
| 213 | GreaterThan | `(int) $hours >= 23`. No test uses a 23:xx value, so rejecting it goes unseen. |
| 213 | GreaterThan | `(int) $minutes >= 59`. No test uses an xx:59 value. |
| 213 | LogicalOr | '25:00' is the only out-of-range case and it is masked like line 60. |

### `src/Infrastructure/Email/Appointment/AppointmentEmailSender.php`

| Line | Mutator | Reason |
|---|---|---|
| 436 | Ternary | The HTML agenda's payment label is never asserted, so Verified and Pending can swap. |
| 520 | IncrementInteger | The text agenda's summary line is never asserted. The HTML one is. |
| 520 | Ternary | Same line, singular and plural can swap in the text body. |
| 537 | Ternary | The text agenda's payment label is never asserted. |

### `src/Domain/Appointment/Entity/Appointment.php`

| Line | Mutator | Reason |
|---|---|---|
| 96 | UnwrapTrim | request() with a whitespace-only city. Only the name has that test. |
| 100 | UnwrapTrim | request() with a whitespace-only country. |
| 132 | UnwrapTrim | book() with a whitespace-only name. book() only has the empty-string case. |
| 136 | UnwrapTrim | book() with a whitespace-only city. |
| 140 | UnwrapTrim | book() with a whitespace-only country. |
| 148 | UnwrapTrim | book() stores the name trimmed. request() pins this, book() does not. |
| 151 | UnwrapTrim | book() stores the city trimmed. |
| 152 | UnwrapTrim | book() stores the country trimmed. |

### `src/Domain/Appointment/Service/AvailabilityComputer.php`

| Line | Mutator | Reason |
|---|---|---|
| 112 | LessThan | The window end is exclusive, and no test has a candidate starting exactly at `to`. |
| 151 | LessThanOrEqualTo | A slot starting exactly at `now` counts as past. No test puts `now` on a slot start. |

### `src/Infrastructure/Security/RedisJwtBlocklist.php`

| Line | Mutator | Reason |
|---|---|---|
| 20 | MethodCallRemoval | A revoked jti never expires, one Redis key per logout for good. Storage growth, not a security hole. ArrayAdapter takes a clock, so a unit test can see it. |
| 33 | MethodCallRemoval | Same for the per-user cutoff key. Bounded by user count, so lower stakes. |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineAppointmentRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 158 | DecrementInteger | `setTime(0, -1)`. Day start is pinned to the hour only. The evening-before fixture sits at 23:00, one at 23:59 would catch it. |
| 158 | IncrementInteger | `setTime(1, 0)`. No fixture in the first hour of the day. |
| 158 | IncrementInteger | `setTime(0, 1)`. No fixture exactly at the day's midnight, so the inclusive start is unpinned. |
| 159 | DecrementInteger | `setTime(-1, 0)`. The latest in-day fixture is 22:00, nothing in the last hour. |
| 159 | IncrementInteger | `setTime(1, 0)`. No confirmed fixture after the day ends, so the next day's first hour leaks in unseen. |
| 159 | DecrementInteger | `setTime(0, -1)`. Nothing in the day's last minute. |
| 159 | IncrementInteger | `setTime(0, 1)`. No fixture exactly at the next midnight, so the exclusive end is unpinned. |

### `src/Application/Appointment/Handler/SetTherapistScheduleHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 73 | LessThan | `$start1 <= $end2`. A block starting exactly when an existing one ends must be allowed. No test has adjacent blocks. |
| 73 | LessThan | `$start2 <= $end1`. A block ending exactly when an existing one starts. Same gap. |
| 73 | LogicalAnd | Any second block on the same day would conflict. The success test has no existing block and the conflict test has a true overlap. |

### `src/Application/Appointment/Handler/UpdateTherapistScheduleHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 76 | LessThan | `$start1 <= $end2`. Same as SetTherapistScheduleHandler line 73, no test has adjacent blocks. |
| 76 | LessThan | `$start2 <= $end1`. Same. |
| 76 | LogicalAnd | Any other block on the same day would conflict. Same gap as the Set handler. |

### `src/Application/User/Handler/InvitePatientHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 68 | UnwrapRtrim | A trailing slash on APP_FRONTEND_URL gives `//register` in the invitation link. No test uses a base URL ending in a slash. |

### `src/Application/User/Handler/RequestPasswordResetHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 63 | UnwrapRtrim | Same as InvitePatientHandler line 68, for the password reset link. |

### `src/Application/User/Handler/ResendInvitationHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 69 | UnwrapRtrim | Same as InvitePatientHandler line 68, for the resent invitation link. |

### `src/Domain/Appointment/Entity/ScheduleException.php`

| Line | Mutator | Reason |
|---|---|---|
| 65 | UnwrapTrim | The reason is stored trimmed. testCreateWithEmptyReasonTrimsToEmpty passes no reason at all, so nothing is trimmed. |
| 100 | LessThan | An exception starting exactly when the slot ends must not block it. The adjacent test covers only the other side. |

### `src/Domain/User/ValueObject/Address.php`

| Line | Mutator | Reason |
|---|---|---|
| 54 | UnwrapTrim | Postal code is stored trimmed. No test passes a padded one. |
| 55 | UnwrapTrim | State is stored trimmed. Same gap. |

### `src/Infrastructure/Console/User/CreateTherapistCommand.php`

| Line | Mutator | Reason |
|---|---|---|
| 54 | MethodCallRemoval | A weak password fails with no message. The test holds only the exit code and defers to ticket 21, whose criteria name this command. |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineUserRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 87 | TrueValue | The patient list would return only inactive patients. No test seeds a patient and reads the list. ListPatientsControllerTest checks shape and paging only. |
| 103 | TrueValue | Same for the total. |

### `src/Application/Appointment/Handler/PatientRequestAppointmentHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 47 | Coalesce | The Requester Timezone sent with the request must win over the patient's profile timezone. No test sets both. |

### `src/Domain/Appointment/Exception/InvalidLockTokenException.php`

| Line | Mutator | Reason |
|---|---|---|
| 13 | MethodCallRemoval | Message and INVALID_LOCK_TOKEN are unpinned. No HTTP test sends a bad lock token, and the one unit test expects only the class. Under the mutant getErrorCode() reads an unset property, so the 400 would become a 500 (deduced, not run). |

### `src/Domain/User/Entity/User.php`

| Line | Mutator | Reason |
|---|---|---|
| 205 | NotIdentical | updateProfile() with a timezone is untested, both setting one and keeping it on null. Phone and address have these tests. |

### `src/Infrastructure/Email/Appointment/RenderedTime.php`

| Line | Mutator | Reason |
|---|---|---|
| 39 | UnwrapStrReplace | A zone like America/New_York must render as "New York". No test uses a zone with an underscore. |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineSlotLockRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 70 | IncrementInteger | With 50-minute sessions on a 30-minute increment, two active locks can overlap one candidate. The limit keeps that from being a NonUniqueResultException. No test has two matching locks. |

## Kind 2, harmless (81)

An ignore covers the whole statement. A row marked (shared) sits on a statement that also has
kind 1 rows, so an ignore there would hide those.

The classes that repeat (log `message` key, Doctrine type fast path, console narration) are
`infection.json5` material per ADR-0010, not a comment per line. The first two are grouped below,
with the reason stated once and a File column.

### `src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 78 | LessThan | Equivalent. The computer re-checks every slot and no slot crosses the week's midnight edge, so a Schedule Exception touching the edge blocks nothing. (shared) |
| 78 | LogicalAnd | Equivalent. It passes a superset and the computer re-checks. (shared) |
| 79 | GreaterThan | Equivalent, as 78 LessThan. (shared) |
| 83 | LessThan | Equivalent, same for appointments. (shared) |
| 83 | LogicalAnd | Equivalent, superset. (shared) |
| 84 | GreaterThan | Equivalent, as 83 LessThan. (shared) |

### `src/Infrastructure/Console/Appointment/SeedScheduleCommand.php`

| Line | Mutator | Reason |
|---|---|---|
| 65 | MethodCallRemoval | Doctrine flushes the managed block on the next save, so the deactivation still lands. Only a call-count assertion could see this. |
| 67 | MethodCallRemoval | Console narration of a dev seed command. |
| 81 | DecrementInteger | The counter only feeds the success line. |
| 94 | Increment | Same. |
| 97 | MethodCallRemoval | Success line of a dev seed command. Exit code and rows are pinned. |
| 99 | ArrayItemRemoval | Summary table header, narration. |
| 100 | ArrayItemRemoval | Summary table cell, narration. |
| 102 | IncrementInteger | Same. |
| 103 | IncrementInteger | Same. |
| 103 | DecrementInteger | Same. |
| 104 | Ternary | Same. |
| 104 | IncrementInteger | Same. |
| 105 | DecrementInteger | Same. |
| 105 | Ternary | Same. |
| 107 | MethodCallRemoval | The table itself, narration. |

### `src/Domain/Appointment/Entity/TherapistSchedule.php`

| Line | Mutator | Reason |
|---|---|---|
| 213 | CastInt | `$hours > 23`. Equivalent. The regex already guarantees two digits and PHP compares a numeric string with an int as numbers. (shared) |
| 213 | CastInt | `$minutes > 59`. Same. (shared) |

### `src/Infrastructure/Email/Appointment/AppointmentEmailSender.php`

| Line | Mutator | Reason |
|---|---|---|
| 77 | BitwiseOr | The name goes into element text. `<`, `>` and `&` are still escaped and a quote needs no escaping there. |
| 147 | BitwiseOr | Same, requester name. |
| 229 | BitwiseOr | Same as line 77. |
| 298 | BitwiseOr | Same as line 77. |
| 437 | Ternary | Inline colour next to the payment label. Presentation only, the label is the kind 1 row. |

### `src/Domain/Appointment/Entity/Appointment.php`

| Line | Mutator | Reason |
|---|---|---|
| 288 | FalseValue | Default on reconstitute(), which only tests call. Dropping the default would remove the mutant. |

### `src/Domain/Appointment/Service/AvailabilityComputer.php`

| Line | Mutator | Reason |
|---|---|---|
| 30 | LessThanOrEqualTo | Equivalent. An empty window yields nothing either way, the early return is a shortcut. |
| 39 | DecrementInteger | `setTime(-1, 0)`. Equivalent. Only the cursor's day is read. This adds one earlier day whose slots all fall before `from`. |
| 39 | IncrementInteger | `setTime(1, 0)`. Equivalent. Same day, the hour and minute are never read. |
| 39 | DecrementInteger | `setTime(0, -1)`. Equivalent, as `setTime(-1, 0)`. |
| 39 | IncrementInteger | `setTime(0, 1)`. Equivalent, as `setTime(1, 0)`. |
| 96 | LessThanOrEqualTo | Equivalent. A zero-length block fits no session, so the loop leaves on its first check. |
| 138 | ConcatOperandRemoval | Equivalent. A space in a createFromFormat pattern also matches no space, so the string parses the same. |

### `src/Infrastructure/Security/RedisJwtBlocklist.php`

| Line | Mutator | Reason |
|---|---|---|
| 19 | TrueValue | Equivalent. isRevoked() reads only whether the key exists. |
| 19 | MethodCallRemoval | Same, the stored value is never read. |
| 43 | LogicalAnd | Differs only when a hit holds a non-int, and the one writer stores an int. A miss fails is_int either way. |
| 48 | Concat | Key text is internal. Read and write build it with the same function. |
| 48 | ConcatOperandRemoval | Same. |
| 54 | Concat | Same for the cutoff key. |
| 54 | ConcatOperandRemoval | Same. |

### `src/Infrastructure/Email/User/SymfonyEmailSender.php`

| Line | Mutator | Reason |
|---|---|---|
| 62 | BitwiseOr | Patient name in element text, as AppointmentEmailSender line 77. |
| 159 | BitwiseOr | Same, user name. |

### `src/Infrastructure/Http/EventSubscriber/RateLimitSubscriber.php`

| Line | Mutator | Reason |
|---|---|---|
| 22 | ArrayItemRemoval | Cold-cache artefact, see the note in api-survivors.md. The compiled container already holds the subscription, so no test can see the mutant. Not generated on a warm cache. |
| 23 | DecrementInteger | Same. |
| 23 | IncrementInteger | Same. |
| 23 | ArrayItemRemoval | Same. |

### Log context `message` key (14)

The case ADR-0010 names. `exception` still carries the message.

| File | Line | Mutator |
|---|---|---|
| `src/Application/User/Handler/InvitePatientHandler.php` | 79 | ArrayItemRemoval |
| `src/Application/User/Handler/InvitePatientHandler.php` | 80 | ArrayItem |
| `src/Application/User/Handler/RequestPasswordResetHandler.php` | 73 | ArrayItemRemoval |
| `src/Application/User/Handler/RequestPasswordResetHandler.php` | 74 | ArrayItem |
| `src/Application/User/Handler/ResendInvitationHandler.php` | 80 | ArrayItemRemoval |
| `src/Application/User/Handler/ResendInvitationHandler.php` | 81 | ArrayItem |
| `src/Application/Appointment/Handler/CancelAppointmentHandler.php` | 48 | ArrayItemRemoval |
| `src/Application/Appointment/Handler/CancelAppointmentHandler.php` | 49 | ArrayItem |
| `src/Application/Appointment/Handler/ConfirmAppointmentHandler.php` | 48 | ArrayItemRemoval |
| `src/Application/Appointment/Handler/ConfirmAppointmentHandler.php` | 49 | ArrayItem |
| `src/Application/User/Handler/ActivatePatientHandler.php` | 77 | ArrayItemRemoval |
| `src/Application/User/Handler/ActivatePatientHandler.php` | 78 | ArrayItem |
| `src/Application/Appointment/Service/AppointmentRequestService.php` | 131 | ArrayItemRemoval |
| `src/Application/Appointment/Service/AppointmentRequestService.php` | 132 | ArrayItem |

### `src/Domain/Exception/DomainException.php`

| Line | Mutator | Reason |
|---|---|---|
| 14 | DecrementInteger | The int code is never read, there is no getCode() call in src. Errors are keyed by getErrorCode(). |
| 14 | IncrementInteger | Same. |

### `src/Infrastructure/Console/User/CreateTherapistCommand.php`

| Line | Mutator | Reason |
|---|---|---|
| 65 | MethodCallRemoval | Success line. Exit code and the stored therapist are pinned. |

### `src/Infrastructure/Http/EventSubscriber/SecurityHeadersSubscriber.php`

| Line | Mutator | Reason |
|---|---|---|
| 15 | ArrayItemRemoval | Cold-cache artefact, same as RateLimitSubscriber. |

### Doctrine type fast path (9)

Equivalent. The value object is Stringable and `__toString()` returns the same value, so the
fall-through to stringValue() gives the same string.

| File | Line | Mutator |
|---|---|---|
| `src/Infrastructure/Persistence/Doctrine/Type/AppointmentIdType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/EmailType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/ExceptionIdType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/PhoneType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/ScheduleIdType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/SlotLockIdType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/TimezoneType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/TokenIdType.php` | 39 | ReturnRemoval |
| `src/Infrastructure/Persistence/Doctrine/Type/UserIdType.php` | 39 | ReturnRemoval |

### `src/Infrastructure/Persistence/Doctrine/Type/ReadsStringValueTrait.php`

| Line | Mutator | Reason |
|---|---|---|
| 18 | ArrayItemRemoval | Only changes the expected-types list in a developer-facing exception message. |

### `src/Infrastructure/Persistence/Doctrine/Type/UtcDateTimeImmutableType.php`

| Line | Mutator | Reason |
|---|---|---|
| 48 | ArrayItemRemoval | Only changes the expected-types list in a developer-facing exception message, as ReadsStringValueTrait line 18. |

### `src/Infrastructure/Security/JwtCookieManager.php`

| Line | Mutator | Reason |
|---|---|---|
| 24 | ConcatOperandRemoval | Equivalent. PHP reads "3600 seconds" as a positive offset, and the expiry-window test still passes. |

### `src/Infrastructure/Security/SecureTokenGenerator.php`

| Line | Mutator | Reason |
|---|---|---|
| 14 | IncrementInteger | Equivalent. intdiv(65, 2) and intdiv(64, 2) are both 32 bytes. |

### `src/Application/Appointment/Handler/LockSlotHandler.php`

| Line | Mutator | Reason |
|---|---|---|
| 51 | IncrementInteger | Equivalent, same intdiv. generate(33) and generate(32) are both 16 bytes. Line 50 in `api-survivors.md`. |

## Kind 3, awkward but real (9)

### `src/Infrastructure/Email/Appointment/AppointmentEmailSender.php`

| Line | Mutator | Reason |
|---|---|---|
| 148 | BitwiseOr | The URL goes into an href, where quote escaping does matter, but it is APP_FRONTEND_URL plus a fixed path, never user input. Pin it if the templates are reworked. |
| 420 | BitwiseOr | Same, the agenda's dashboard link. |

### `src/Infrastructure/Email/User/SymfonyEmailSender.php`

| Line | Mutator | Reason |
|---|---|---|
| 63 | BitwiseOr | Registration URL in an href. APP_FRONTEND_URL plus a hex token, never user input. Pin it if the templates are reworked. |
| 111 | BitwiseOr | Same, reset URL. |
| 160 | BitwiseOr | Same, login URL. |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineTherapistScheduleRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 46 | ArrayItemRemoval | The therapist filter only shows with a second therapist, which the system refuses to create. Test it if that rule ever changes. |
| 57 | ArrayItemRemoval | Same. |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineInvitationTokenRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 59 | IncrementInteger | Needs two valid invitations for one email. Invite returns the existing one and resend revokes the old one, so only a race gets there. |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrinePasswordResetTokenRepository.php`

| Line | Mutator | Reason |
|---|---|---|
| 57 | IncrementInteger | findValidByUserId() has no caller in src, and it needs two valid tokens for one user. Decide on the method before testing it. |

## Skipped (4)

Each file has an open ticket, so its rows wait for that.

| Rows | File | Ticket |
|---|---|---|
| 4 | `src/Infrastructure/Http/Validation/PasswordStrengthValidator.php` | 21 |
