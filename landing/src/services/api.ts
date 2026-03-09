import {
  ApiError,
  type ApiResponse,
  type SlotsResponse,
  type NextAvailableWeekResponse,
  type LockResponse,
  type AppointmentResponse,
  type Modality,
} from '../types/api';

const API_BASE =
  import.meta.env.PUBLIC_API_BASE_URL ?? 'http://localhost:8080/api';

async function apiRequest<T>(
  path: string,
  options?: RequestInit,
): Promise<T> {
  const url = `${API_BASE}${path}`;

  const res = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options?.headers,
    },
  });

  const json: ApiResponse<T> = await res.json();

  if (!json.success || json.error) {
    throw new ApiError(
      json.error?.code ?? 'UNKNOWN_ERROR',
      json.error?.message ?? 'An unexpected error occurred',
      json.error?.details,
    );
  }

  return json.data as T;
}

export async function fetchAvailableSlots(params: {
  from: string;
  to: string;
  modality?: Modality;
}): Promise<SlotsResponse> {
  const searchParams = new URLSearchParams({
    from: params.from,
    to: params.to,
  });
  if (params.modality) {
    searchParams.set('modality', params.modality);
  }

  return apiRequest<SlotsResponse>(
    `/appointments/available-slots?${searchParams}`,
  );
}

export async function fetchNextAvailableWeek(params: {
  modality?: Modality;
}): Promise<NextAvailableWeekResponse> {
  const searchParams = new URLSearchParams();
  if (params.modality) {
    searchParams.set('modality', params.modality);
  }
  const query = searchParams.toString();
  return apiRequest<NextAvailableWeekResponse>(
    `/appointments/next-available-week${query ? `?${query}` : ''}`,
  );
}

export async function lockSlot(body: {
  slot_start_time: string;
  modality: Modality;
}): Promise<LockResponse> {
  return apiRequest<LockResponse>('/appointments/lock-slot', {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export async function requestAppointment(body: {
  slot_start_time: string;
  modality: string;
  full_name: string;
  phone: string;
  email: string;
  city: string;
  country: string;
  lock_token?: string;
}): Promise<AppointmentResponse> {
  return apiRequest<AppointmentResponse>('/appointments/request', {
    method: 'POST',
    body: JSON.stringify(body),
  });
}
