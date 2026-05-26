export interface PatientAddress {
  street: string | null;
  city: string | null;
  country: string | null;
}

export interface Patient {
  id: string;
  email: string;
  full_name: string;
  role: string;
  is_active: boolean;
  phone: string | null;
  address: PatientAddress | null;
  created_at: string;
  activated_at: string | null;
}

export interface PaginationMeta {
  page: number;
  limit: number;
  total: number;
  total_pages: number;
}

export interface PatientsPage {
  patients: Patient[];
  pagination: PaginationMeta;
}
