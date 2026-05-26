import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../shared/utils/api-response.model';
import { Invitation, InvitePayload } from '../utils/invitation.model';
import { PatientsPage } from '../utils/patient.model';

interface ListInvitationsResponse {
  invitations: Invitation[];
  count: number;
}

interface InvitationActionResponse {
  invitation: Invitation;
  message?: string;
}

@Injectable({ providedIn: 'root' })
export class PatientsService {
  private readonly http: HttpClient = inject(HttpClient);

  listPatients(page: number, limit: number): Observable<PatientsPage> {
    const params: HttpParams = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    return this.http
      .get<ApiResponse<PatientsPage>>(`${environment.apiUrl}/therapist/patients`, { params })
      .pipe(map((response: ApiResponse<PatientsPage>): PatientsPage => this.unwrap(response)));
  }

  listInvitations(): Observable<Invitation[]> {
    return this.http
      .get<ApiResponse<ListInvitationsResponse>>(`${environment.apiUrl}/therapist/invitations`)
      .pipe(
        map(
          (response: ApiResponse<ListInvitationsResponse>): Invitation[] =>
            this.unwrap(response).invitations,
        ),
      );
  }

  invite(payload: InvitePayload): Observable<Invitation> {
    return this.http
      .post<ApiResponse<InvitationActionResponse>>(
        `${environment.apiUrl}/therapist/patients/invite`,
        payload,
      )
      .pipe(
        map(
          (response: ApiResponse<InvitationActionResponse>): Invitation =>
            this.unwrap(response).invitation,
        ),
      );
  }

  resend(id: string): Observable<Invitation> {
    return this.http
      .post<ApiResponse<InvitationActionResponse>>(
        `${environment.apiUrl}/therapist/invitations/${id}/resend`,
        {},
      )
      .pipe(
        map(
          (response: ApiResponse<InvitationActionResponse>): Invitation =>
            this.unwrap(response).invitation,
        ),
      );
  }

  revoke(id: string): Observable<Invitation> {
    return this.http
      .post<ApiResponse<InvitationActionResponse>>(
        `${environment.apiUrl}/therapist/invitations/${id}/revoke`,
        {},
      )
      .pipe(
        map(
          (response: ApiResponse<InvitationActionResponse>): Invitation =>
            this.unwrap(response).invitation,
        ),
      );
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success || !response.data) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data;
  }
}
