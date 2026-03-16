export interface LoginRequest {
  email: string;
  password: string;
}

export interface AuthUser {
  id: string;
  email: string;
  full_name: string;
  role: string;
}

export interface LoginData {
  user: AuthUser;
}

export interface RegisterRequest {
  token: string;
  password: string;
  password_confirmation: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  password: string;
  password_confirmation: string;
}

export interface InvitationData {
  patient_name: string;
  status: string;
}
