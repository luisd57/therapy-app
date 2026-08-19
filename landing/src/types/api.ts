export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ApiErrorBody;
}

export interface ApiErrorBody {
  code: string;
  message: string;
  details?: Record<string, string>;
}

export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details?: Record<string, string>,
  ) {
    super(message);
  }
}

export interface SlotData {
  start_time: string;
  end_time: string;
  duration_minutes: number;
}

/**
 * Slots arrive as a flat list of instants. They are deliberately not grouped by
 * date server-side: the only calendar the API could group by is the practice's,
 * which is the wrong one for every patient outside Venezuela.
 */
export interface SlotsResponse {
  from: string;
  to: string;
  modality: string | null;
  practice_timezone: string;
  slots: SlotData[];
  total_slots: number;
}

export interface LockResponse {
  lock_token: string;
  slot_start_time: string;
  slot_end_time: string;
  expires_at: string;
}

export interface AppointmentResponse {
  appointment: AppointmentSummary;
  message: string;
}

export interface AppointmentSummary {
  id: string;
  start_time: string;
  end_time: string;
  modality: string;
  status: string;
  created_at: string;
}

/** week_start / week_end are instants (week_end exclusive), not calendar days. */
export interface NextAvailableWeekResponse {
  found: boolean;
  week_start: string | null;
  week_end: string | null;
  modality: string | null;
  practice_timezone: string;
  slots: SlotData[];
  total_slots: number;
}

export type Modality = 'ONLINE' | 'IN_PERSON';
