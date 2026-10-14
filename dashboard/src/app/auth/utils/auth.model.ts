import { CurrentUser } from '../../shared/utils/current-user.model';

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginData {
  user: CurrentUser;
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
