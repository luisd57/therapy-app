# API surviving mutants

**Evidence, not a backlog.** Emptying this list on request produces assertions pinned to
internal state, which break on the next honest refactor. It is here so the next reader starts
from the list and not from a rerun, and so tickets can be aimed. Gating is on new work only.

| | |
|---|---|
| Date | 2026-10-08, Ticket column updated by hand 2026-10-09 |
| Tree | the ticket 16 branch as merged, on top of `f52f608` |
| Tool | Infection 0.35.6, PHPUnit 10.5.63, PHPStan 2.2.16, pcov 1.0.12, PHP 8.4.26 |
| Command | `vendor/bin/infection --threads=8 --only-covering-test-cases` |
| Config | `API/infection.json5` (timeout 300s, PHPStan on escaped mutants) |
| Wall time | 13m 21s, 8 threads, one test database per thread |
| Mutants | 1375 |
| Ignored | 68 |
| Killed by tests | 1130 |
| Killed by PHPStan | 12 |
| Escaped | 164 |
| Timed out | 1 |
| Errors, skipped | 0, 0 |
| Uncovered | not generated (covered-only is the default, no `--with-uncovered`) |
| MSI, covered MSI | 87.45%, 87.45% |

The two MSI figures are equal because uncovered code was never mutated. Code no test reaches
is absent from this list, not vouched for by it.

Not mutated, by `source.excludes`: `Infrastructure/Http/Controller`, `Infrastructure/Config`,
and the three `Application/*/DTO` directories. Reasons in ADR-0010.

Ignored mutants sit on a line carrying a Doctrine mapping attribute: a column length, a nullable
flag. No test reads the schema, so they could only survive. They count in no figure above but
the total.

Five survivors are an artefact of a cold cache: the `getSubscribedEvents()` lines of the two HTTP
subscribers. They are only covered when the container is compiled during the initial run, and
every mutant then runs against the compiled container, so nothing can kill them. A run on a
warm `var/cache/test` does not generate them (1370 mutants, not 1375).

The Ticket column names the `test-suite-hardening` ticket whose scope the file falls in.
Empty means no ticket covers it.

## Timed out

- `src/Domain/Appointment/Service/AvailabilityComputer.php` line 108, ReturnRemoval: removed `return;`

## Survivors per file

| Survivors | File | Ticket |
|---|---|---|
| 15 | `src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php` | 13, 26, 27 |
| 15 | `src/Infrastructure/Console/Appointment/SeedScheduleCommand.php` | 06 |
| 12 | `src/Domain/Appointment/Entity/TherapistSchedule.php` | 28 |
| 11 | `src/Infrastructure/Email/Appointment/AppointmentEmailSender.php` | 29 |
| 9 | `src/Domain/Appointment/Entity/Appointment.php` | 30 |
| 9 | `src/Domain/Appointment/Service/AvailabilityComputer.php` | 01, 31 |
| 9 | `src/Infrastructure/Security/RedisJwtBlocklist.php` | 32 |
| 7 | `src/Application/Appointment/Service/AppointmentRequestService.php` | 17 |
| 7 | `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineAppointmentRepository.php` | 18, 33 |
| 5 | `src/Infrastructure/Email/User/SymfonyEmailSender.php` |  |
| 4 | `src/Infrastructure/Http/EventSubscriber/RateLimitSubscriber.php` |  |
| 4 | `src/Infrastructure/Http/Validation/PasswordStrengthValidator.php` | 04, 21 |
| 3 | `src/Application/Appointment/Handler/SetTherapistScheduleHandler.php` | 34 |
| 3 | `src/Application/Appointment/Handler/UpdateTherapistScheduleHandler.php` | 34 |
| 3 | `src/Application/User/Handler/InvitePatientHandler.php` | 35 |
| 3 | `src/Application/User/Handler/RequestPasswordResetHandler.php` | 35 |
| 3 | `src/Application/User/Handler/ResendInvitationHandler.php` | 35 |
| 2 | `src/Application/Appointment/Handler/CancelAppointmentHandler.php` |  |
| 2 | `src/Application/Appointment/Handler/ConfirmAppointmentHandler.php` |  |
| 2 | `src/Application/User/Handler/ActivatePatientHandler.php` |  |
| 2 | `src/Domain/Appointment/Entity/ScheduleException.php` | 36, 37 |
| 2 | `src/Domain/Appointment/Service/SlotGenerationRules.php` | 01, 17 |
| 2 | `src/Domain/Exception/DomainException.php` |  |
| 2 | `src/Domain/User/ValueObject/Address.php` | 38 |
| 2 | `src/Infrastructure/Console/User/CreateTherapistCommand.php` | 06, 21 |
| 2 | `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineTherapistScheduleRepository.php` |  |
| 2 | `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineUserRepository.php` | 39 |
| 1 | `src/Application/Appointment/Handler/LockSlotHandler.php` | 17 |
| 1 | `src/Application/Appointment/Handler/PatientRequestAppointmentHandler.php` | 40 |
| 1 | `src/Domain/Appointment/Exception/InvalidLockTokenException.php` | 41 |
| 1 | `src/Domain/User/Entity/User.php` | 42 |
| 1 | `src/Infrastructure/Email/Appointment/RenderedTime.php` | 43 |
| 1 | `src/Infrastructure/Http/EventSubscriber/SecurityHeadersSubscriber.php` |  |
| 1 | `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineSlotLockRepository.php` | 18, 44 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/AppointmentIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/EmailType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/ExceptionIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/PhoneType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/ReadsStringValueTrait.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/ScheduleIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/SlotLockIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/TimezoneType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/TokenIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/UserIdType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/Type/UtcDateTimeImmutableType.php` | 05 |
| 1 | `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineInvitationTokenRepository.php` |  |
| 1 | `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrinePasswordResetTokenRepository.php` |  |
| 1 | `src/Infrastructure/Security/JwtCookieManager.php` | 04 |
| 1 | `src/Infrastructure/Security/SecureTokenGenerator.php` | 04 |

## Survivors

### `src/Application/Appointment/Handler/GetNextAvailableWeekHandler.php`

Ticket: 13, 26, 27

| Line | Mutator | Change |
|---|---|---|
| 72 | LessThan | `for ($week = 0; $week < $this->maxLookaheadWeeks; $week++) {` became `for ($week = 0; $week <= $this->maxLookaheadWeeks; $week++) {` |
| 78 | LessThan | `$exception->getStartDateTime() < $weekEnd` became `$exception->getStartDateTime() <= $weekEnd` |
| 78 | LessThanNegotiation | `$exception->getStartDateTime() < $weekEnd` became `$exception->getStartDateTime() >= $weekEnd` |
| 78 | LogicalAnd | `$exception->getStartDateTime() < $weekEnd && $exception->getEndDateTime() > $weekStart,` became `$exception->getStartDateTime() < $weekEnd \|\| $exception->getEndDateTime() > $weekStart,` |
| 78 | LogicalAndAllSubExprNegation | `$exception->getStartDateTime() < $weekEnd && $exception->getEndDateTime() > $weekStart,` became `!($exception->getStartDateTime() < $weekEnd) && !($exception->getEndDateTime() > $weekStart),` |
| 78 | LogicalAndNegation | `$exception->getStartDateTime() < $weekEnd && $exception->getEndDateTime() > $weekStart,` became `!($exception->getStartDateTime() < $weekEnd && $exception->getEndDateTime() > $weekStart),` |
| 79 | GreaterThan | `&& $exception->getEndDateTime() > $weekStart,` became `&& $exception->getEndDateTime() >= $weekStart,` |
| 79 | GreaterThanNegotiation | `&& $exception->getEndDateTime() > $weekStart,` became `&& $exception->getEndDateTime() <= $weekStart,` |
| 83 | LessThan | `$appointment->getTimeSlot()->getStartTime() < $weekEnd` became `$appointment->getTimeSlot()->getStartTime() <= $weekEnd` |
| 83 | LessThanNegotiation | `$appointment->getTimeSlot()->getStartTime() < $weekEnd` became `$appointment->getTimeSlot()->getStartTime() >= $weekEnd` |
| 83 | LogicalAnd | `$appointment->getTimeSlot()->getStartTime() < $weekEnd && $appointment->getTimeSlot()->getEndTime...` became `$appointment->getTimeSlot()->getStartTime() < $weekEnd \|\| $appointment->getTimeSlot()->getEndTime...` |
| 83 | LogicalAndAllSubExprNegation | `$appointment->getTimeSlot()->getStartTime() < $weekEnd && $appointment->getTimeSlot()->getEndTime...` became `!($appointment->getTimeSlot()->getStartTime() < $weekEnd) && !($appointment->getTimeSlot()->getEn...` |
| 83 | LogicalAndNegation | `$appointment->getTimeSlot()->getStartTime() < $weekEnd && $appointment->getTimeSlot()->getEndTime...` became `!($appointment->getTimeSlot()->getStartTime() < $weekEnd && $appointment->getTimeSlot()->getEndTi...` |
| 84 | GreaterThanNegotiation | `&& $appointment->getTimeSlot()->getEndTime() > $weekStart,` became `&& $appointment->getTimeSlot()->getEndTime() <= $weekStart,` |
| 84 | GreaterThan | `&& $appointment->getTimeSlot()->getEndTime() > $weekStart,` became `&& $appointment->getTimeSlot()->getEndTime() >= $weekStart,` |

### `src/Infrastructure/Console/Appointment/SeedScheduleCommand.php`

Ticket: 06

| Line | Mutator | Change |
|---|---|---|
| 65 | MethodCallRemoval | removed `$this->scheduleRepository->save($schedule);` |
| 67 | MethodCallRemoval | removed `$io->note(sprintf('Deactivated %d existing schedule block(s).', $existing->count()));` |
| 81 | DecrementInteger | `$created = 0;` became `$created = -1;` |
| 94 | Increment | `$created++;` became `$created--;` |
| 97 | MethodCallRemoval | removed `$io->success(sprintf('Created %d schedule blocks for the therapist.', $created));` |
| 99 | ArrayItemRemoval | `$headers = ['Day', 'Start', 'End', 'Online', 'In-Person'];` became `$headers = ['Start', 'End', 'Online', 'In-Person'];` |
| 100 | ArrayItemRemoval | removed `$b[0]->name,` |
| 102 | IncrementInteger | `$b[1],` became `$b[2],` |
| 103 | IncrementInteger | `$b[2],` became `$b[3],` |
| 103 | DecrementInteger | `$b[2],` became `$b[1],` |
| 104 | Ternary | `$b[3] ? 'Yes' : 'No',` became `$b[3] ? 'No' : 'Yes',` |
| 104 | IncrementInteger | `$b[3] ? 'Yes' : 'No',` became `$b[4] ? 'Yes' : 'No',` |
| 105 | DecrementInteger | `$b[4] ? 'Yes' : 'No',` became `$b[3] ? 'Yes' : 'No',` |
| 105 | Ternary | `$b[4] ? 'Yes' : 'No',` became `$b[4] ? 'No' : 'Yes',` |
| 107 | MethodCallRemoval | removed `$io->table($headers, $rows);` |

### `src/Domain/Appointment/Entity/TherapistSchedule.php`

Ticket: 28

| Line | Mutator | Change |
|---|---|---|
| 60 | MethodCallRemoval | removed `self::validateTimeFormat($startTime);` |
| 61 | MethodCallRemoval | removed `self::validateTimeFormat($endTime);` |
| 87 | MethodCallRemoval | removed `self::validateTimeFormat($startTime);` |
| 88 | MethodCallRemoval | removed `self::validateTimeFormat($endTime);` |
| 90 | GreaterThanOrEqualTo | `if ($startTime >= $endTime) {` became `if ($startTime > $endTime) {` |
| 208 | PregMatchRemoveCaret | `if (!preg_match('/^\d{2}:\d{2}$/', $time)) {` became `if (!preg_match('/\d{2}:\d{2}$/', $time)) {` |
| 208 | PregMatchRemoveDollar | `if (!preg_match('/^\d{2}:\d{2}$/', $time)) {` became `if (!preg_match('/^\d{2}:\d{2}/', $time)) {` |
| 213 | CastInt | `if ((int) $hours > 23 \|\| (int) $minutes > 59) {` became `if ($hours > 23 \|\| (int) $minutes > 59) {` |
| 213 | GreaterThan | `if ((int) $hours > 23 \|\| (int) $minutes > 59) {` became `if ((int) $hours >= 23 \|\| (int) $minutes > 59) {` |
| 213 | GreaterThan | `if ((int) $hours > 23 \|\| (int) $minutes > 59) {` became `if ((int) $hours > 23 \|\| (int) $minutes >= 59) {` |
| 213 | CastInt | `if ((int) $hours > 23 \|\| (int) $minutes > 59) {` became `if ((int) $hours > 23 \|\| $minutes > 59) {` |
| 213 | LogicalOr | `if ((int) $hours > 23 \|\| (int) $minutes > 59) {` became `if ((int) $hours > 23 && (int) $minutes > 59) {` |

### `src/Infrastructure/Email/Appointment/AppointmentEmailSender.php`

Ticket: 29

| Line | Mutator | Change |
|---|---|---|
| 77 | BitwiseOr | `$fullName = htmlspecialchars($fullName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$fullName = htmlspecialchars($fullName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 147 | BitwiseOr | `$requesterName = htmlspecialchars($requesterName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$requesterName = htmlspecialchars($requesterName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 148 | BitwiseOr | `$dashboardUrl = htmlspecialchars($dashboardUrl, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$dashboardUrl = htmlspecialchars($dashboardUrl, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 229 | BitwiseOr | `$fullName = htmlspecialchars($fullName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$fullName = htmlspecialchars($fullName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 298 | BitwiseOr | `$fullName = htmlspecialchars($fullName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$fullName = htmlspecialchars($fullName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 420 | BitwiseOr | `$dashboardUrl = htmlspecialchars($dashboardUrl, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$dashboardUrl = htmlspecialchars($dashboardUrl, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 436 | Ternary | `$payment = $appointment->isPaymentVerified() ? 'Verified' : 'Pending';` became `$payment = $appointment->isPaymentVerified() ? 'Pending' : 'Verified';` |
| 437 | Ternary | `$paymentColor = $appointment->isPaymentVerified() ? '#2e7d32' : '#e65100';` became `$paymentColor = $appointment->isPaymentVerified() ? '#e65100' : '#2e7d32';` |
| 520 | IncrementInteger | `$summary = $appointmentCount === 1` became `$summary = $appointmentCount === 2` |
| 520 | Ternary | `$summary = $appointmentCount === 1 ? '1 confirmed appointment' : "{$appointmentCount} confirmed a...` became `$summary = $appointmentCount === 1 ? "{$appointmentCount} confirmed appointments" : '1 confirmed ...` |
| 537 | Ternary | `$payment = $appointment->isPaymentVerified() ? 'Verified' : 'Pending';` became `$payment = $appointment->isPaymentVerified() ? 'Pending' : 'Verified';` |

### `src/Domain/Appointment/Entity/Appointment.php`

Ticket: 30

| Line | Mutator | Change |
|---|---|---|
| 96 | UnwrapTrim | `if (trim($city) === '') {` became `if ($city === '') {` |
| 100 | UnwrapTrim | `if (trim($country) === '') {` became `if ($country === '') {` |
| 132 | UnwrapTrim | `if (trim($fullName) === '') {` became `if ($fullName === '') {` |
| 136 | UnwrapTrim | `if (trim($city) === '') {` became `if ($city === '') {` |
| 140 | UnwrapTrim | `if (trim($country) === '') {` became `if ($country === '') {` |
| 148 | UnwrapTrim | `fullName: trim($fullName),` became `fullName: $fullName,` |
| 151 | UnwrapTrim | `city: trim($city),` became `city: $city,` |
| 152 | UnwrapTrim | `country: trim($country),` became `country: $country,` |
| 288 | FalseValue | `bool $paymentVerified = false,` became `bool $paymentVerified = true,` |

### `src/Domain/Appointment/Service/AvailabilityComputer.php`

Ticket: 01, 31

| Line | Mutator | Change |
|---|---|---|
| 30 | LessThanOrEqualTo | `if ($to <= $from) {` became `if ($to < $from) {` |
| 39 | DecrementInteger | `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 0);` became `$cursor = $from->setTimezone($practiceTimeZone)->setTime(-1, 0);` |
| 39 | IncrementInteger | `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 0);` became `$cursor = $from->setTimezone($practiceTimeZone)->setTime(1, 0);` |
| 39 | DecrementInteger | `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 0);` became `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, -1);` |
| 39 | IncrementInteger | `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 0);` became `$cursor = $from->setTimezone($practiceTimeZone)->setTime(0, 1);` |
| 96 | LessThanOrEqualTo | `if ($blockStart === null \|\| $blockEnd === null \|\| $blockEnd <= $blockStart) {` became `if ($blockStart === null \|\| $blockEnd === null \|\| $blockEnd < $blockStart) {` |
| 112 | LessThan | `&& $slotStart < $to` became `&& $slotStart <= $to` |
| 138 | ConcatOperandRemoval | `$practiceDay->format('Y-m-d') . ' ' . $wallClockTime . ':00',` became `$practiceDay->format('Y-m-d') . $wallClockTime . ':00',` |
| 151 | LessThanOrEqualTo | `return $timeSlot->getStartTime() <= $now;` became `return $timeSlot->getStartTime() < $now;` |

### `src/Infrastructure/Security/RedisJwtBlocklist.php`

Ticket: 32

| Line | Mutator | Change |
|---|---|---|
| 19 | TrueValue | `$item->set(true);` became `$item->set(false);` |
| 19 | MethodCallRemoval | removed `$item->set(true);` |
| 20 | MethodCallRemoval | removed `$item->expiresAfter($ttlSeconds);` |
| 33 | MethodCallRemoval | removed `$item->expiresAfter($ttlSeconds);` |
| 43 | LogicalAnd | `return $item->isHit() && is_int($cutoff) && $issuedAt <= $cutoff;` became `return ($item->isHit() \|\| is_int($cutoff)) && $issuedAt <= $cutoff;` |
| 48 | Concat | `return 'therapy_jwt_revoked_' . $jti;` became `return $jti . 'therapy_jwt_revoked_';` |
| 48 | ConcatOperandRemoval | `return 'therapy_jwt_revoked_' . $jti;` became `return $jti;` |
| 54 | Concat | `return 'therapy_jwt_min_iat_' . hash('sha256', $userIdentifier);` became `return hash('sha256', $userIdentifier) . 'therapy_jwt_min_iat_';` |
| 54 | ConcatOperandRemoval | `return 'therapy_jwt_min_iat_' . hash('sha256', $userIdentifier);` became `return hash('sha256', $userIdentifier);` |

### `src/Application/Appointment/Service/AppointmentRequestService.php`

Ticket: 17

| Line | Mutator | Change |
|---|---|---|
| 77 | LogicalOr | `if ($lock->getTimeSlot()->getStartTime() != $startTime \|\| $lock->getModality() !== $appointmentMo...` became `if ($lock->getTimeSlot()->getStartTime() != $startTime && $lock->getModality() !== $appointmentMo...` |
| 131 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 132 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |
| 150 | DecrementInteger | `$dayStart = $practiceDay->setTime(0, 0);` became `$dayStart = $practiceDay->setTime(-1, 0);` |
| 150 | IncrementInteger | `$dayStart = $practiceDay->setTime(0, 0);` became `$dayStart = $practiceDay->setTime(1, 0);` |
| 150 | DecrementInteger | `$dayStart = $practiceDay->setTime(0, 0);` became `$dayStart = $practiceDay->setTime(0, -1);` |
| 150 | IncrementInteger | `$dayStart = $practiceDay->setTime(0, 0);` became `$dayStart = $practiceDay->setTime(0, 1);` |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineAppointmentRepository.php`

Ticket: 18, 33

| Line | Mutator | Change |
|---|---|---|
| 158 | DecrementInteger | `$dayStart = $date->setTime(0, 0);` became `$dayStart = $date->setTime(0, -1);` |
| 158 | IncrementInteger | `$dayStart = $date->setTime(0, 0);` became `$dayStart = $date->setTime(1, 0);` |
| 158 | IncrementInteger | `$dayStart = $date->setTime(0, 0);` became `$dayStart = $date->setTime(0, 1);` |
| 159 | DecrementInteger | `$dayEnd = $date->modify('+1 day')->setTime(0, 0);` became `$dayEnd = $date->modify('+1 day')->setTime(-1, 0);` |
| 159 | IncrementInteger | `$dayEnd = $date->modify('+1 day')->setTime(0, 0);` became `$dayEnd = $date->modify('+1 day')->setTime(1, 0);` |
| 159 | DecrementInteger | `$dayEnd = $date->modify('+1 day')->setTime(0, 0);` became `$dayEnd = $date->modify('+1 day')->setTime(0, -1);` |
| 159 | IncrementInteger | `$dayEnd = $date->modify('+1 day')->setTime(0, 0);` became `$dayEnd = $date->modify('+1 day')->setTime(0, 1);` |

### `src/Infrastructure/Email/User/SymfonyEmailSender.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 62 | BitwiseOr | `$patientName = htmlspecialchars($patientName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$patientName = htmlspecialchars($patientName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 63 | BitwiseOr | `$registrationUrl = htmlspecialchars($registrationUrl, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$registrationUrl = htmlspecialchars($registrationUrl, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 111 | BitwiseOr | `$resetUrl = htmlspecialchars($resetUrl, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$resetUrl = htmlspecialchars($resetUrl, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 159 | BitwiseOr | `$userName = htmlspecialchars($userName, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$userName = htmlspecialchars($userName, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |
| 160 | BitwiseOr | `$loginUrl = htmlspecialchars($loginUrl, ENT_QUOTES \| ENT_HTML5, 'UTF-8');` became `$loginUrl = htmlspecialchars($loginUrl, ENT_QUOTES & ENT_HTML5, 'UTF-8');` |

### `src/Infrastructure/Http/EventSubscriber/RateLimitSubscriber.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 22 | ArrayItemRemoval | `return [ KernelEvents::REQUEST => ['onKernelRequest', 10], ];` became `return [];` |
| 23 | DecrementInteger | `KernelEvents::REQUEST => ['onKernelRequest', 10],` became `KernelEvents::REQUEST => ['onKernelRequest', 9],` |
| 23 | IncrementInteger | `KernelEvents::REQUEST => ['onKernelRequest', 10],` became `KernelEvents::REQUEST => ['onKernelRequest', 11],` |
| 23 | ArrayItemRemoval | `KernelEvents::REQUEST => ['onKernelRequest', 10],` became `KernelEvents::REQUEST => [10],` |

### `src/Infrastructure/Http/Validation/PasswordStrengthValidator.php`

Ticket: 04, 21

| Line | Mutator | Change |
|---|---|---|
| 32 | ReturnRemoval | removed `return;` |
| 39 | ReturnRemoval | removed `return;` |
| 46 | ReturnRemoval | removed `return;` |
| 53 | ReturnRemoval | removed `return;` |

### `src/Application/Appointment/Handler/SetTherapistScheduleHandler.php`

Ticket: 34

| Line | Mutator | Change |
|---|---|---|
| 73 | LessThan | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 <= $end2 && $start2 < $end1;` |
| 73 | LessThan | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 < $end2 && $start2 <= $end1;` |
| 73 | LogicalAnd | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 < $end2 \|\| $start2 < $end1;` |

### `src/Application/Appointment/Handler/UpdateTherapistScheduleHandler.php`

Ticket: 34

| Line | Mutator | Change |
|---|---|---|
| 76 | LessThan | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 <= $end2 && $start2 < $end1;` |
| 76 | LessThan | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 < $end2 && $start2 <= $end1;` |
| 76 | LogicalAnd | `return $start1 < $end2 && $start2 < $end1;` became `return $start1 < $end2 \|\| $start2 < $end1;` |

### `src/Application/User/Handler/InvitePatientHandler.php`

Ticket: 35

| Line | Mutator | Change |
|---|---|---|
| 68 | UnwrapRtrim | `rtrim($this->frontendUrl, '/'),` became `$this->frontendUrl,` |
| 79 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 80 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Application/User/Handler/RequestPasswordResetHandler.php`

Ticket: 35

| Line | Mutator | Change |
|---|---|---|
| 63 | UnwrapRtrim | `rtrim($this->frontendUrl, '/'),` became `$this->frontendUrl,` |
| 73 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 74 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Application/User/Handler/ResendInvitationHandler.php`

Ticket: 35

| Line | Mutator | Change |
|---|---|---|
| 69 | UnwrapRtrim | `rtrim($this->frontendUrl, '/'),` became `$this->frontendUrl,` |
| 80 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 81 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Application/Appointment/Handler/CancelAppointmentHandler.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 48 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 49 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Application/Appointment/Handler/ConfirmAppointmentHandler.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 48 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 49 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Application/User/Handler/ActivatePatientHandler.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 77 | ArrayItemRemoval | removed `'message' => $e->getMessage(),` |
| 78 | ArrayItem | `'message' => $e->getMessage(),` became `'message' > $e->getMessage(),` |

### `src/Domain/Appointment/Entity/ScheduleException.php`

Ticket: 36, 37

| Line | Mutator | Change |
|---|---|---|
| 65 | UnwrapTrim | `reason: trim($reason),` became `reason: $reason,` |
| 100 | LessThan | `return $this->startDateTime < $slot->getEndTime()` became `return $this->startDateTime <= $slot->getEndTime()` |

### `src/Domain/Appointment/Service/SlotGenerationRules.php`

Ticket: 01, 17

| Line | Mutator | Change |
|---|---|---|
| 27 | LessThanOrEqualTo | `if ($durationMinutes <= 0) {` became `if ($durationMinutes < 0) {` |
| 34 | LessThanOrEqualTo | `if ($increment <= 0) {` became `if ($increment < 0) {` |

### `src/Domain/Exception/DomainException.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 14 | DecrementInteger | `parent::__construct($message, 0, $previous);` became `parent::__construct($message, -1, $previous);` |
| 14 | IncrementInteger | `parent::__construct($message, 0, $previous);` became `parent::__construct($message, 1, $previous);` |

### `src/Domain/User/ValueObject/Address.php`

Ticket: 38

| Line | Mutator | Change |
|---|---|---|
| 54 | UnwrapTrim | `postalCode: $postalCode ? trim($postalCode) : null,` became `postalCode: $postalCode ? $postalCode : null,` |
| 55 | UnwrapTrim | `state: $state ? trim($state) : null,` became `state: $state ? $state : null,` |

### `src/Infrastructure/Console/User/CreateTherapistCommand.php`

Ticket: 06, 21

| Line | Mutator | Change |
|---|---|---|
| 54 | MethodCallRemoval | removed `$io->error($passwordError);` |
| 65 | MethodCallRemoval | removed `$io->success(sprintf( 'Therapist created successfully! ID: %s', $user->id ));` |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineTherapistScheduleRepository.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 46 | ArrayItemRemoval | removed `'therapist' => $therapistId->getValue(),` |
| 57 | ArrayItemRemoval | removed `'therapist' => $therapistId->getValue(),` |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineUserRepository.php`

Ticket: 39

| Line | Mutator | Change |
|---|---|---|
| 87 | TrueValue | `->setParameter('isActive', true)` became `->setParameter('isActive', false)` |
| 103 | TrueValue | `->setParameter('isActive', true);` became `->setParameter('isActive', false);` |

### `src/Application/Appointment/Handler/LockSlotHandler.php`

Ticket: 17

| Line | Mutator | Change |
|---|---|---|
| 50 | IncrementInteger | `lockToken: $this->tokenGenerator->generate(32),` became `lockToken: $this->tokenGenerator->generate(33),` |

### `src/Application/Appointment/Handler/PatientRequestAppointmentHandler.php`

Ticket: 40

| Line | Mutator | Change |
|---|---|---|
| 47 | Coalesce | `requesterTimezone: $dto->requesterTimezone ?? $patient->getTimezone()?->getValue(),` became `requesterTimezone: $patient->getTimezone()?->getValue() ?? $dto->requesterTimezone,` |

### `src/Domain/Appointment/Exception/InvalidLockTokenException.php`

Ticket: 41

| Line | Mutator | Change |
|---|---|---|
| 13 | MethodCallRemoval | removed `parent::__construct( message: 'The slot lock token is invalid or has expired.', errorCode: 'INVAL...` |

### `src/Domain/User/Entity/User.php`

Ticket: 42

| Line | Mutator | Change |
|---|---|---|
| 205 | NotIdentical | `if ($timezone !== null) {` became `if ($timezone === null) {` |

### `src/Infrastructure/Email/Appointment/RenderedTime.php`

Ticket: 43

| Line | Mutator | Change |
|---|---|---|
| 39 | UnwrapStrReplace | `return str_replace('_', ' ', (string) end($parts));` became `return (string) end($parts);` |

### `src/Infrastructure/Http/EventSubscriber/SecurityHeadersSubscriber.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 15 | ArrayItemRemoval | `return [ KernelEvents::RESPONSE => 'onKernelResponse', ];` became `return [];` |

### `src/Infrastructure/Persistence/Doctrine/Appointment/Repository/DoctrineSlotLockRepository.php`

Ticket: 18, 44

| Line | Mutator | Change |
|---|---|---|
| 70 | IncrementInteger | `->setMaxResults(1);` became `->setMaxResults(2);` |

### `src/Infrastructure/Persistence/Doctrine/Type/AppointmentIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/EmailType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/ExceptionIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/PhoneType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/ReadsStringValueTrait.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 18 | ArrayItemRemoval | `throw InvalidType::new($value, static::class, ['null', 'string']);` became `throw InvalidType::new($value, static::class, ['string']);` |

### `src/Infrastructure/Persistence/Doctrine/Type/ScheduleIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/SlotLockIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/TimezoneType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/TokenIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/UserIdType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 39 | ReturnRemoval | removed `return $value->getValue();` |

### `src/Infrastructure/Persistence/Doctrine/Type/UtcDateTimeImmutableType.php`

Ticket: 05

| Line | Mutator | Change |
|---|---|---|
| 48 | ArrayItemRemoval | `throw InvalidType::new($value, static::class, ['null', DateTimeImmutable::class]);` became `throw InvalidType::new($value, static::class, [DateTimeImmutable::class]);` |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrineInvitationTokenRepository.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 59 | IncrementInteger | `->setMaxResults(1);` became `->setMaxResults(2);` |

### `src/Infrastructure/Persistence/Doctrine/User/Repository/DoctrinePasswordResetTokenRepository.php`

Ticket: none

| Line | Mutator | Change |
|---|---|---|
| 57 | IncrementInteger | `->setMaxResults(1);` became `->setMaxResults(2);` |

### `src/Infrastructure/Security/JwtCookieManager.php`

Ticket: 04

| Line | Mutator | Change |
|---|---|---|
| 24 | ConcatOperandRemoval | `expires: new \DateTimeImmutable('+' . $this->jwtTokenTtl . ' seconds'),` became `expires: new \DateTimeImmutable($this->jwtTokenTtl . ' seconds'),` |

### `src/Infrastructure/Security/SecureTokenGenerator.php`

Ticket: 04

| Line | Mutator | Change |
|---|---|---|
| 14 | IncrementInteger | `public function generate(int $length = 64): string` became `public function generate(int $length = 65): string` |
