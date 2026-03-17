import { Injectable, Signal, WritableSignal, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import {
  Observable,
  tap,
  map,
  catchError,
  EMPTY,
  finalize,
} from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../shared/utils/api-response.model';
import {
  AuthUser,
  ForgotPasswordRequest,
  InvitationData,
  LoginData,
  LoginRequest,
  RegisterRequest,
  ResetPasswordRequest,
} from '../utils/auth.model';

const USER_KEY: string = 'auth_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http: HttpClient = inject(HttpClient);
  private readonly router: Router = inject(Router);

  readonly user: WritableSignal<AuthUser | null> = signal<AuthUser | null>(this.loadUser());
  readonly isAuthenticated: Signal<boolean> = computed((): boolean => this.user() !== null);
  readonly initialized: WritableSignal<boolean> = signal(false);

  init(): Observable<void> {
    const storedUser: AuthUser | null = this.loadUser();
    if (!storedUser) {
      this.initialized.set(true);
      return EMPTY;
    }

    return this.http
      .get<ApiResponse<AuthUser>>(`${environment.apiUrl}/auth/me`)
      .pipe(
        tap((response: ApiResponse<AuthUser>): void => {
          if (response.success && response.data) {
            this.user.set(response.data);
            localStorage.setItem(USER_KEY, JSON.stringify(response.data));
          } else {
            this.clearLocal();
          }
        }),
        catchError((): Observable<never> => {
          this.clearLocal();
          return EMPTY;
        }),
        map((): undefined => undefined),
        finalize((): void => {
          this.initialized.set(true);
        }),
      );
  }

  login(request: LoginRequest): Observable<AuthUser> {
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
          localStorage.setItem(USER_KEY, JSON.stringify(data.user));
          this.user.set(data.user);
          void this.router.navigate(['/appointments']);
        }),
        map((data: LoginData): AuthUser => data.user),
      );
  }

  patientLogin(request: LoginRequest): Observable<AuthUser> {
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
          localStorage.setItem(USER_KEY, JSON.stringify(data.user));
          this.user.set(data.user);
          void this.router.navigate(['/appointments']);
        }),
        map((data: LoginData): AuthUser => data.user),
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

  logout(): void {
    const isPatient: boolean = this.user()?.role === 'ROLE_PATIENT';

    if (this.user()) {
      this.http
        .post(`${environment.apiUrl}/auth/logout`, {})
        .pipe(catchError((): Observable<never> => EMPTY))
        .subscribe();
    }

    this.clearLocal();
    void this.router.navigate([isPatient ? '/patient-login' : '/login']);
  }

  private clearLocal(): void {
    localStorage.removeItem(USER_KEY);
    this.user.set(null);
  }

  private loadUser(): AuthUser | null {
    const stored: string | null = localStorage.getItem(USER_KEY);
    if (!stored) return null;
    try {
      return JSON.parse(stored) as AuthUser;
    } catch {
      return null;
    }
  }
}
