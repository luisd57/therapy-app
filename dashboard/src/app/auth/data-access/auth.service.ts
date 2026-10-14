import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { CurrentUserService } from '../../shared/data-access/current-user.service';
import { ApiResponse } from '../../shared/utils/api-response.model';
import { CurrentUser } from '../../shared/utils/current-user.model';
import {
  ForgotPasswordRequest,
  InvitationData,
  LoginData,
  LoginRequest,
  RegisterRequest,
  ResetPasswordRequest,
} from '../utils/auth.model';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http: HttpClient = inject(HttpClient);
  private readonly router: Router = inject(Router);
  private readonly currentUser: CurrentUserService = inject(CurrentUserService);

  login(request: LoginRequest): Observable<CurrentUser> {
    return this.http
      .post<ApiResponse<LoginData>>(
        `${environment.apiUrl}/auth/therapist/login`,
        request,
      )
      .pipe(
        map((response: ApiResponse<LoginData>): LoginData => {
          if (!response.success || !response.data) {
            throw new Error(response.error?.message ?? 'Login failed');
          }
          return response.data;
        }),
        tap((data: LoginData): void => {
          this.currentUser.signIn(data.user);
          void this.router.navigate(['/appointments']);
        }),
        map((data: LoginData): CurrentUser => data.user),
      );
  }

  patientLogin(request: LoginRequest): Observable<CurrentUser> {
    return this.http
      .post<ApiResponse<LoginData>>(
        `${environment.apiUrl}/auth/patient/login`,
        request,
      )
      .pipe(
        map((response: ApiResponse<LoginData>): LoginData => {
          if (!response.success || !response.data) {
            throw new Error(response.error?.message ?? 'Login failed');
          }
          return response.data;
        }),
        tap((data: LoginData): void => {
          this.currentUser.signIn(data.user);
          void this.router.navigate(['/appointments']);
        }),
        map((data: LoginData): CurrentUser => data.user),
      );
  }

  validateInvitation(token: string): Observable<InvitationData> {
    return this.http
      .get<ApiResponse<InvitationData>>(
        `${environment.apiUrl}/auth/invitation/validate/${token}`,
      )
      .pipe(
        map((response: ApiResponse<InvitationData>): InvitationData => {
          if (!response.success || !response.data) {
            throw new Error(response.error?.message ?? 'Invalid invitation');
          }
          return response.data;
        }),
      );
  }

  register(request: RegisterRequest): Observable<void> {
    return this.http
      .post<ApiResponse<void>>(`${environment.apiUrl}/auth/register`, request)
      .pipe(
        map((response: ApiResponse<void>): void => {
          if (!response.success) {
            throw new Error(response.error?.message ?? 'Registration failed');
          }
        }),
      );
  }

  forgotPassword(request: ForgotPasswordRequest): Observable<void> {
    return this.http
      .post<ApiResponse<void>>(
        `${environment.apiUrl}/auth/password/forgot`,
        request,
      )
      .pipe(map((): undefined => undefined));
  }

  resetPassword(request: ResetPasswordRequest): Observable<void> {
    return this.http
      .post<ApiResponse<void>>(
        `${environment.apiUrl}/auth/password/reset`,
        request,
      )
      .pipe(
        map((response: ApiResponse<void>): void => {
          if (!response.success) {
            throw new Error(response.error?.message ?? 'Password reset failed');
          }
        }),
      );
  }
}
