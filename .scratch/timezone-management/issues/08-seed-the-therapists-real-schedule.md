# 08 — Seed the Therapist's real schedule

**What to build:** Every seeded environment carries the Therapist's actual
working hours and Modality split, so development, continuous integration and
end-to-end tests all exercise the real shape rather than a placeholder.

The seed currently holds a generic Monday-to-Friday pattern that matches neither
her hours nor her Modality split. Her real schedule, confirmed with her:

| Day | Schedule Blocks | Last start | Modality |
|---|---|---|---|
| Monday | 08:00–12:00, 13:30–19:30 | 18:00 | In-Person |
| Tuesday | 06:30–10:30 | 09:00 | Both |
| Wednesday–Sunday | 07:00–12:00, 13:30–20:30 | 19:00 | Both |

Every Schedule Block end means **sessions must finish by**, not last session
starts at. That reading is what makes her stated finishing times and her stated
capacity consistent at 90 minutes. Lunch is the gap between the two blocks and
applies every working day, Monday included. She expects weekend bookings.

Her "only two consultations fit" on Tuesday describes **capacity**, not offered
starts: with 30-minute Start Increments that window offers six candidate starts
and still caps at two non-overlapping sessions.

Blocked by ticket 04 because the block boundaries are designed around 90-minute
sessions — seeding these hours at the old length produces a grid matching neither
her capacity nor the expectations the end-to-end suites will be written against.

**Blocked by:** 04 — Switch sessions to 90 minutes.

**Status:** ready-for-agent

- [ ] Seeded Schedule Blocks match the table above, including the lunch gap on every working day
- [ ] Monday is In-Person only; Tuesday through Sunday support both Modalities
- [ ] Seeding is idempotent — re-running does not duplicate Schedule Blocks
- [ ] The Tuesday window yields six candidate starts and two non-overlapping sessions
- [ ] The Wednesday-to-Sunday afternoon window's last start is 19:00, finishing at 20:30
- [ ] Full API suite green
