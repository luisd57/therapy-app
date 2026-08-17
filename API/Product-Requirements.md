# Product Requirements

> **Frozen 2026-05-26. Historical record, not a current spec.**
>
> This is the synthesis of the original client discussion, and the checkboxes reflect what was
> believed on that date. Requirements have moved since - session length, slot start increments,
> the therapist's real weekly schedule, and the whole timezone domain all changed after it was
> written, and none of that is here. Do not treat a ticked box as current, and do not update this
> file to match the code.
>
> Current truth lives in:
>
> - [`docs/STATUS.md`](../docs/STATUS.md) - what is implemented, per component
> - [`docs/adr/`](../docs/adr/) - decisions and their reasoning
> - `.scratch/<effort>/spec.md` - requirements for work in flight

## 1. Authentication & User Management (Security Domain) -- DONE

Since the system handles sensitive patient data, security is paramount. The registration flow is unique because it is invitation-only (initiated by the therapist).

### Therapist Authentication, There is only 1 Therapist(Admin)

- [x] **Therapist Setup**: One-time account creation via CLI command `app:create-therapist` (HTTP endpoint removed during security hardening).
- [x] **Secure Admin Login**: Standard email/password login for the therapist to access the private interface.
- [x] **Session Management**: JWT-based authentication via `lexik/jwt-authentication-bundle`. JWT delivered via httpOnly cookie for browser clients; Bearer token also supported for API clients.
- [x] **Logout / JWT Revocation**: Invalidates JWT via Redis-backed blocklist using `jti` claim (`POST /api/auth/logout`).
- [x] **Therapist Profile**: Therapist can view own profile data (`GET /api/therapist/me`).

### Patient Onboarding (Invitation Flow)

- [x] **Invitation Generator**: Therapist enters a patient's email to trigger an invitation (`POST /api/therapist/patients/invite`).
- [x] **Invitation List**: Therapist can view all sent invitations and their status (`GET /api/therapist/invitations`).
- [x] **Signed Registration Link**: Time-limited, signed token sent via email (`INVITATION_TOKEN_TTL`).
- [x] **Token Pre-Validation**: Frontend can verify a token is valid before showing the registration form (`GET /api/auth/invitation/validate/{token}`).
- [x] **Account Activation/Registration Page**: Patient sets password to activate account (`POST /api/auth/register`). Password policy: 8-72 characters, at least 1 uppercase, 1 lowercase, 1 number, 1 special character.
- [x] **Invitation Resend**: `POST /api/therapist/invitations/{id}/resend` - revokes the prior token and issues a fresh one, re-emails.
- [x] **Invitation Revoke**: `POST /api/therapist/invitations/{id}/revoke` - marks a pending invitation revoked; future use is rejected.

### Account Maintenance

- [x] **Patient Login**: Standard login for registered patients (`POST /api/auth/patient/login`).
- [x] **Password Reset Flow**: "Forgot Password" via email (`POST /api/auth/password/forgot` + `/reset`).
- [x] **Patient Profile Read**: Patient can view own profile data (`GET /api/patient/me`).
- [x] **Profile Management**: Patients update phone/address (`PUT /api/patient/profile`).
- [x] **Patient List**: Therapist can view all registered patients (`GET /api/therapist/patients`).

---

## 2. Public Appointment Request System (Unauthenticated Domain) -- PARTIAL(frontend required)

This is the "Storefront" for visitors. Note that users request a time, they do not instantly book it (as per the manual confirmation workflow).

### Availability Interface

- [x] **Real-Time Calendar**: `GET /api/appointments/available-slots?from=...&to=...` returns slots grouped by date, computed from therapist schedules minus exceptions and confirmed appointments. REQUESTED appointments and active locks do **not** reduce visible availability.
- [x] **Modality Toggle**: Optional `modality` query parameter filters slots by `ONLINE` or `IN_PERSON`.
- [x] **Slot Locking (Concurrency Hint)**: `POST /api/appointments/lock-slot` creates an optional concurrency token (configurable TTL via `SLOT_LOCK_TTL`). Locks do **not** hide slots from the browser - they only prevent duplicate lock tokens. See [Slot Lock Token Flow](#slot-lock-token-flow) below.

### Request Intake

- [x] **Slot Selection**: Frontend picks a time from available slots.
- [x] **Intake Form**: `POST /api/appointments/request` collects Full Name, Phone, Email, City, Country.
- [x] **Request Submission**: Creates appointment with `REQUESTED` status. Sends acknowledgment email to requester + alert to therapist.
- [ ] **Submission Feedback**: Frontend "Thank You" page (frontend-only, no API needed).

---

## 2b. Patient Appointment Request (Authenticated Domain) -- DONE

Registered patients can request appointments through an authenticated endpoint. Contact info is auto-filled from their profile, and the appointment is automatically linked to their patient record.

### Patient Self-Booking

- [x] **Authenticated Request**: `POST /api/patient/appointments` accepts `slot_start_time`, `modality`, and optional `lock_token`. Requires `ROLE_PATIENT` JWT. Contact data (name, email, phone, city, country) is resolved from the patient's profile.
- [x] **Profile Completeness Guard**: Returns 422 (`INCOMPLETE_PROFILE`) if the patient has not set phone and address via `PUT /api/patient/profile`.
- [x] **Automatic Patient Linking**: The `patient_id` is set from the authenticated user's identity - no manual linking needed.
- [x] **Shared Business Rules**: Uses the same slot availability verification, optional lock token handling, and email notifications as the public request flow.

---

## 3. Therapist Administration (Scheduling Domain) -- DONE(frontend required)

This is the core workspace for the client.

### Incoming Requests Management

- [x] **Request Dashboard**: `GET /api/therapist/appointments?status=REQUESTED` returns all pending requests. `GET /api/therapist/appointments` lists all appointments with optional status filter.
- [x] **Lead Details**: `GET /api/therapist/appointments/{id}` returns full intake form data (name, phone, email, city, country, modality, time, payment status).
- [x] **Action Buttons**: `POST /api/therapist/appointments/{id}/confirm` and `POST /api/therapist/appointments/{id}/cancel` with email notifications on both actions.

### Calendar Management (The "Schedule")

- [x] **Working Hours Configuration**: CRUD for recurring weekly schedule blocks (`POST/GET/PUT/DELETE /api/therapist/schedule`). Each block defines day of week, time range, and supported modalities. Overlap detection prevents conflicting blocks.
- [x] **Manual Blockers**: Schedule exceptions block specific date/time ranges (`POST/GET/DELETE /api/therapist/schedule/exceptions`). The availability computer automatically excludes these from public slots.
- [x] **Manual Appointment Creation**: `POST /api/therapist/appointments` creates a CONFIRMED appointment directly (for patients who called). Accepts full intake data + optional `patient_id`.

### Appointment Lifecycle

- [x] **Status Model**: Domain entity supports `REQUESTED` -> `CONFIRMED` -> `COMPLETED` or `CANCELLED` transitions with validation.
- [x] **Status Management API**: `POST /api/therapist/appointments/{id}/confirm`, `POST /api/therapist/appointments/{id}/complete`, `POST /api/therapist/appointments/{id}/cancel`. Returns 409 on invalid transitions.
- [x] **Payment Verification Checkbox**: `PATCH /api/therapist/appointments/{id}/payment` with `{"payment_verified": true/false}`. Server-side boolean persisted on the appointment entity. (manually verified by therapist)

---

## 4. Notification System (Infrastructure) -- DONE(frontend required)

Automated communication to reduce administrative friction.

### Therapist Alerts

- [x] **New Request Alert**: Email notification when a visitor submits an appointment request (`AppointmentEmailSender::sendNewRequestAlertToTherapist`).
- [x] **Daily Agenda**: Email summary of the day's confirmed appointments (`app:send-daily-agenda` command + `AppointmentEmailSender::sendDailyAgendaToTherapist`).

### Patient Transactional Emails

- [x] **Request Acknowledgment**: "We received your request" email sent on submission (`AppointmentEmailSender::sendRequestAcknowledgment`).
- [x] **Appointment Confirmation**: Confirmation email sent to patient when therapist confirms (`AppointmentEmailSender::sendConfirmationToPatient`).
- [x] **Appointment Cancellation**: Cancellation email sent to patient when therapist cancels (`AppointmentEmailSender::sendCancellationToPatient`).
- [x] **Welcome Email**: Sent after patient activates their account (`SymfonyEmailSender::sendWelcome`).
- [x] **Account Invitation**: Registration link email (`SymfonyEmailSender::sendInvitation`).
- [x] **Password Reset**: Reset link email (`SymfonyEmailSender::sendPasswordReset`).

### Action Buttons in Emails

- [x] **Invitation**: "Complete Registration" → `{APP_FRONTEND_URL}/register?token=...`.
- [x] **Password Reset**: "Reset Password" → `{APP_FRONTEND_URL}/reset-password?token=...`.
- [x] **Welcome**: "Log in" → `{APP_FRONTEND_URL}/patient-login`.
- [x] **Therapist New Request Alert**: "Open Dashboard" → `{APP_FRONTEND_URL}/login`.
- [x] **Therapist Daily Agenda**: "Open Dashboard" → `{APP_FRONTEND_URL}/login`.

### Out of Scope (Email Notifications)

- **Patient deep links in confirmation/cancellation emails**: No "View Appointment" button - depends on a patient appointment detail page, which is in the Patient Area (TODO). Revisit when that page exists.
- **Appointment reminders** (e.g., 24h pre-appointment): Not in PRD; deferred.
- **Email verification on signup**: Not required - invitation tokens serve the trust role.
- **Twig email templates**: Current heredoc templates are intentional; refactor only if templates grow beyond current complexity.
- **Production SMTP DSN**: Dev uses MailHog (`smtp://mailhog:1025`); production wiring is a deployment concern, not a product requirement.

---

## Slot Lock Token Flow

> **Status (2026-03-16):** The slot lock infrastructure is fully implemented across the stack (entity, repository, handler, endpoint, frontend integration, tests) but is **currently inactive** in the availability computation. All callers of `AvailabilityComputer` pass an empty `activeLocks` collection, so locks never filter slot visibility or block bookings. The code is retained as scaffolding in case the client changes requirements.

The lock token is an **optional** concurrency hint for the appointment request flow. It does **not** affect slot visibility.

**Context**: Multiple visitors may select the same slot simultaneously. This is by design - the therapist resolves conflicts manually during confirmation. The lock provides a soft signal but never hides slots.

**Solution**: Temporary DB-based lock tokens with TTL.

```
Visitor clicks slot → form appears instantly (optimistic UI)
    |
    v
POST /api/appointments/lock-slot fires in background (slot_start_time, modality)
    |
    v
Server creates SlotLock record (unique lock_token, expires in SLOT_LOCK_TTL seconds)
Slot remains visible to all other visitors
    |
    +---> Visitor submits form with lock_token
    |         |
    |         v
    |     Server validates token, deletes lock, creates appointment
    |
    +---> Lock fails (another visitor holds a lock on the same slot)
    |         |
    |         v
    |     Amber warning on form - visitor can still submit without a lock
    |
    +---> Visitor abandons form
              |
              v
          Lock expires after TTL
          (app:cleanup-slot-locks removes expired records)
```

**Design decisions**:

- **Lock is optional**: Appointments can be submitted without a lock token (`lock_token: null`). Multiple visitors CAN submit REQUESTED appointments for the same slot. The therapist resolves conflicts manually during confirmation.
- **Locks do not affect visibility**: The public slot browser only hides slots blocked by CONFIRMED appointments or schedule exceptions. REQUESTED appointments and active locks are invisible to the availability computation.
- **DB-based, not session-based**: The API is stateless (JWT auth), so locks are stored in the `slot_locks` table with an `expires_at` timestamp rather than relying on server sessions.
- **Cleanup command**: `php bin/console app:cleanup-slot-locks` removes expired locks. Should run periodically (e.g., every 15 minutes via cron/scheduler).
