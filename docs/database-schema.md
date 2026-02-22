# Database Schema

PostgreSQL 16. All tables use UUIDs as primary keys (generated application-side). No physical foreign keys — the domain layer enforces referential integrity through its hexagonal architecture.

## Entity Relationship Overview

```text
users
  |
  |-- 1:N --> therapist_schedules    (therapist_id -> users.id)
  |-- 1:N --> schedule_exceptions    (therapist_id -> users.id)
  |-- 1:N --> invitation_tokens      (invited_by   -> users.id)
  |-- 1:N --> password_reset_tokens  (user_id      -> users.id)
  |
  '-- 0:N <-- appointments           (patient_id   -> users.id, nullable)

slot_locks                            (standalone, no FK — ephemeral records)
```

---

## Tables

### `users`

Stores both therapists and patients. Differentiated by `role`.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `email` | VARCHAR(255) | NO | Unique login identifier |
| `full_name` | VARCHAR(255) | NO | Display name |
| `role` | VARCHAR(50) | NO | `ROLE_THERAPIST` or `ROLE_PATIENT` |
| `password` | VARCHAR(255) | YES | Hashed. NULL for invited-but-not-yet-activated patients |
| `phone` | VARCHAR(50) | YES | Patient contact info |
| `address_street` | VARCHAR(255) | YES | Embedded Address VO |
| `address_city` | VARCHAR(100) | YES | Embedded Address VO |
| `address_state` | VARCHAR(100) | YES | Embedded Address VO |
| `address_postal_code` | VARCHAR(20) | YES | Embedded Address VO |
| `address_country` | VARCHAR(100) | YES | Embedded Address VO |
| `is_active` | BOOLEAN | NO | FALSE until patient activates account |
| `created_at` | TIMESTAMP | NO | Immutable |
| `activated_at` | TIMESTAMP | YES | When patient set their password |
| `updated_at` | TIMESTAMP | NO | Last modification |

**Indexes**: `UNIQUE(email)`, `idx_users_email`, `idx_users_role`

**Design notes**:

- Address is stored as flattened columns (not a JSON blob) — the Address value object maps to/from these 5 columns via the Doctrine mapper.
- `password` is nullable because patients are created in an inactive state via invitation. They set a password during registration.
- Single table for both roles avoids joins while the user count is small (single-therapist practice).

---

### `invitation_tokens`

Time-limited tokens for patient registration invitations.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `token` | VARCHAR(255) | NO | SHA-256 hash of the unique random token. Raw token is only returned at creation time |
| `email` | VARCHAR(255) | NO | Invited patient's email |
| `patient_name` | VARCHAR(255) | NO | Display name set by therapist |
| `invited_by` | UUID | NO | Logical FK to `users.id` (the therapist) |
| `is_used` | BOOLEAN | NO | Marked TRUE after successful registration |
| `created_at` | TIMESTAMP | NO | Immutable |
| `expires_at` | TIMESTAMP | NO | `created_at + INVITATION_TOKEN_TTL` |
| `used_at` | TIMESTAMP | YES | When registration completed |

**Indexes**: `UNIQUE(token)`, `idx_invitation_token`, `idx_invitation_email`, `idx_invitation_valid(is_used, expires_at)`

---

### `password_reset_tokens`

Time-limited tokens for the "Forgot Password" flow.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `token` | VARCHAR(255) | NO | SHA-256 hash of the unique random token. Raw token is only returned at creation time |
| `user_id` | UUID | NO | Logical FK to `users.id` |
| `is_used` | BOOLEAN | NO | Marked TRUE after password is reset |
| `created_at` | TIMESTAMP | NO | Immutable |
| `expires_at` | TIMESTAMP | NO | `created_at + PASSWORD_RESET_TOKEN_TTL` |
| `used_at` | TIMESTAMP | YES | When password was reset |

**Indexes**: `UNIQUE(token)`, `idx_password_reset_token`, `idx_password_reset_user`, `idx_password_reset_valid(is_used, expires_at)`

---

### `therapist_schedules`

Recurring weekly availability blocks. A therapist defines their working hours as one or more blocks per day (e.g., Monday 09:00-13:00, Monday 14:00-18:00).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `therapist_id` | UUID | NO | Logical FK to `users.id` |
| `day_of_week` | INT | NO | ISO-8601: 1=Monday ... 7=Sunday |
| `start_time` | VARCHAR(5) | NO | `HH:MM` format (e.g., `"09:00"`) |
| `end_time` | VARCHAR(5) | NO | `HH:MM` format (e.g., `"13:00"`) |
| `supports_online` | BOOLEAN | NO | Whether online appointments can be booked in this block |
| `supports_in_person` | BOOLEAN | NO | Whether in-person appointments can be booked |
| `is_active` | BOOLEAN | NO | Soft-delete. Inactive blocks are ignored by the availability computer |
| `created_at` | TIMESTAMP | NO | Immutable |
| `updated_at` | TIMESTAMP | NO | Last modification |

**Indexes**: `idx_schedule_therapist_day(therapist_id, day_of_week)`

**Design notes**:

- **Why VARCHAR(5) instead of TIME?** These represent recurring wall-clock times ("every Monday at 09:00"), not specific datetime instances. Storing as plain strings keeps the domain model simple — the `AvailabilityComputer` combines them with a concrete date at query time to produce actual `DateTimeImmutable` slot boundaries.
- **Overlap rule**: Two active schedule blocks on the same day cannot overlap in time, regardless of modality. A therapist is one person — they can't be in two places at once. Each block can support both modalities simultaneously (`supports_online=true, supports_in_person=true`).
- **Soft-delete via `is_active`**: Allows deactivating a block without losing its history.

---

### `schedule_exceptions`

One-off time blocks where the therapist is unavailable (holidays, personal time, appointments outside the practice). These override `therapist_schedules` — any slot that falls within an exception's range is excluded from public availability.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `therapist_id` | UUID | NO | Logical FK to `users.id` |
| `start_date_time` | TIMESTAMP | NO | Exception period start |
| `end_date_time` | TIMESTAMP | NO | Exception period end |
| `reason` | VARCHAR(500) | YES | Human-readable note (e.g., "Holiday") |
| `is_all_day` | BOOLEAN | NO | UI hint. The actual blocking uses start/end times |
| `created_at` | TIMESTAMP | NO | Immutable |

**Indexes**: `idx_exception_therapist_range(therapist_id, start_date_time, end_date_time)`

**Design notes**:

- `is_all_day` is a UI convenience flag. The server always checks the actual `start_date_time`/`end_date_time` range for overlap. An all-day exception is stored as `00:00:00` to `23:59:59`.

---

### `appointments`

The core business entity. Tracks appointment requests from submission through completion.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `start_time` | TIMESTAMP | NO | Appointment start (stored from `TimeSlot` VO) |
| `end_time` | TIMESTAMP | NO | Appointment end (start + duration) |
| `modality` | VARCHAR(20) | NO | `ONLINE` or `IN_PERSON` |
| `status` | VARCHAR(20) | NO | `REQUESTED`, `CONFIRMED`, `COMPLETED`, `CANCELLED` |
| `full_name` | VARCHAR(255) | NO | Requester's name |
| `email` | VARCHAR(255) | NO | Requester's email (for notifications) |
| `phone` | VARCHAR(50) | NO | Requester's phone |
| `city` | VARCHAR(100) | NO | Requester's city |
| `country` | VARCHAR(100) | NO | Requester's country |
| `patient_id` | UUID | YES | Logical FK to `users.id`. NULL for public (unauthenticated) requests |
| `created_at` | TIMESTAMP | NO | Immutable |
| `updated_at` | TIMESTAMP | NO | Last status change |

**Indexes**: `idx_appointment_status(status)`, `idx_appointment_time_range(start_time, end_time)`, `idx_appointment_blocking(status, start_time, end_time)`

**Design notes**:

- **Why store contact info directly instead of referencing `users`?** Public appointment requests come from unauthenticated visitors who may not have an account. The contact fields are denormalized intentionally — they capture the requester's info at submission time, independent of any user record.
- **`patient_id` is nullable**: NULL for public requests. Can be linked to a `users` record later if the requester creates an account or is matched to an existing patient.
- **`idx_appointment_blocking` composite index**: Optimized for the most frequent query — `findBlockingByDateRange` — which filters `WHERE status IN ('REQUESTED','CONFIRMED') AND start_time < :to AND end_time > :from`. This query runs on every available-slots request and every appointment submission.

**Status lifecycle**:

```text
REQUESTED ──> CONFIRMED ──> COMPLETED
    |              |
    v              v
CANCELLED      CANCELLED
```

- `REQUESTED`: Initial state from public submission. Visible on therapist dashboard.
- `CONFIRMED`: Therapist manually approves after verifying payment.
- `COMPLETED`: Session took place.
- `CANCELLED`: Rejected or cancelled at any pre-completion stage.
- Both `REQUESTED` and `CONFIRMED` **block** a slot (prevent double-booking).
- `COMPLETED` and `CANCELLED` are terminal states — no further transitions.

---

### `slot_locks`

Ephemeral records for temporary slot reservation during the appointment request flow. See [Slot Lock Token Flow](../Product-Requirements.md#slot-lock-token-flow) for the full explanation.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | NO | PK |
| `start_time` | TIMESTAMP | NO | Locked slot start (from `TimeSlot` VO) |
| `end_time` | TIMESTAMP | NO | Locked slot end |
| `modality` | VARCHAR(20) | NO | `ONLINE` or `IN_PERSON` |
| `lock_token` | VARCHAR(255) | NO | SHA-256 hash of the lock token. Raw token is only returned at creation time |
| `created_at` | TIMESTAMP | NO | Immutable |
| `expires_at` | TIMESTAMP | NO | `created_at + SLOT_LOCK_TTL` |

**Indexes**: `UNIQUE(lock_token)`, `idx_slot_lock_time_expires(start_time, end_time, expires_at)`

**Design notes**:

- **No FK to users**: Locks are created by unauthenticated visitors. There's no user to reference.
- **TTL-based expiry**: The `expires_at` column is checked at query time (`WHERE expires_at > NOW()`). Expired locks are invisible to the availability computer but remain in the DB until the cleanup command runs.
- **Cleanup**: `php bin/console app:cleanup-slot-locks` deletes all rows where `expires_at < NOW()`. Should be scheduled every ~15 minutes.
- **One active lock per slot**: `findActiveByTimeSlot` returns the first non-expired lock overlapping a time range. If one exists, the lock-slot endpoint returns 409.

---

## Why No Physical Foreign Keys?

The project follows hexagonal architecture (Ports & Adapters). The domain layer defines entity relationships and enforces invariants in PHP code — it doesn't rely on DB constraints for correctness.

Benefits of this approach:

- **Testing isolation**: Repository integration tests can insert entities in any order without FK constraint issues.
- **Schema flexibility**: Tables can be created/dropped independently. Migrations are simpler.
- **Domain authority**: Business rules like "a schedule must belong to an existing therapist" are enforced by the handler layer (which loads the therapist first), not by the database.

The tradeoff is that orphaned records are possible if the application has bugs. In practice this hasn't been an issue because all data mutations go through the domain layer.
