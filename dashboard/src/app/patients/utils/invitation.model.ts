export type InvitationStatus = 'pending' | 'expired' | 'used' | 'revoked';

export interface Invitation {
  id: string;
  email: string;
  patient_name: string;
  status: InvitationStatus;
  created_at: string;
  expires_at: string;
}

export interface InvitePayload {
  email: string;
  patient_name: string;
}
