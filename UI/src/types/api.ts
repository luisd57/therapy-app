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

export interface SlotsResponse {
  from: string;
  to: string;
  modality: string | null;
  slots_by_date: Record<string, SlotData[]>;
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

export type Modality = 'ONLINE' | 'IN_PERSON';
export type ModalityFilter = Modality | 'ALL';
