import { Injectable, Signal, WritableSignal, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import {
  Observable,
  tap,
  map,
  catchError,
  of,
  EMPTY,
  finalize,
} from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../api/api.models';
import { AuthUser, LoginData, LoginRequest } from './auth.models';

const USER_KEY = 'auth_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http: HttpClient = inject(HttpClient);
  private readonly router: Router = inject(Router);

  readonly user: WritableSignal<AuthUser | null> = signal<AuthUser | null>(this.loadUser());
  readonly isAuthenticated: Signal<boolean> = computed(() => this.user() !== null);
  readonly initialized: WritableSignal<boolean> = signal(false);

  init(): Observable<void> {
    const storedUser = this.loadUser();
    if (!storedUser) {
      this.initialized.set(true);
      return of(void 0);
    }

    return this.http
      .get<ApiResponse<AuthUser>>(`${environment.apiUrl}/auth/me`)
      .pipe(
        tap((response) => {
          if (response.success && response.data) {
            this.user.set(response.data);
            localStorage.setItem(USER_KEY, JSON.stringify(response.data));
          } else {
            this.clearLocal();
          }
        }),
        catchError(() => {
          this.clearLocal();
          return of(void 0);
        }),
        map(() => void 0),
        finalize(() => this.initialized.set(true)),
      );
  }

  login(request: LoginRequest): Observable<AuthUser> {
    return this.http
      .post<ApiResponse<LoginData>>(
        `${environment.apiUrl}/auth/therapist/login`,
        request,
      )
      .pipe(
        map((response) => {
          if (!response.success || !response.data) {
            throw new Error(response.error?.message ?? 'Login failed');
          }
          return response.data;
        }),
        tap((data) => {
          localStorage.setItem(USER_KEY, JSON.stringify(data.user));
          this.user.set(data.user);
          this.router.navigate(['/appointments']);
        }),
        map((data) => data.user),
      );
  }

  logout(): void {
    if (this.user()) {
      this.http
        .post(`${environment.apiUrl}/auth/logout`, {})
        .pipe(catchError(() => EMPTY))
        .subscribe();
    }

    this.clearLocal();
    this.router.navigate(['/login']);
  }

  private clearLocal(): void {
    localStorage.removeItem(USER_KEY);
    this.user.set(null);
  }

  private loadUser(): AuthUser | null {
    const stored = localStorage.getItem(USER_KEY);
    if (!stored) return null;
    try {
      return JSON.parse(stored) as AuthUser;
    } catch {
      return null;
    }
  }
}
