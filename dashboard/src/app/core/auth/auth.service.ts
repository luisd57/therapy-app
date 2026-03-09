import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, map, catchError, throwError, EMPTY } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../api/api.models';
import { AuthUser, LoginData, LoginRequest } from './auth.models';

const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  readonly token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  readonly user = signal<AuthUser | null>(this.loadUser());
  readonly isAuthenticated = computed(() => this.token() !== null);

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
          localStorage.setItem(TOKEN_KEY, data.token);
          localStorage.setItem(USER_KEY, JSON.stringify(data.user));
          this.token.set(data.token);
          this.user.set(data.user);
          this.router.navigate(['/appointments']);
        }),
        map((data) => data.user),
      );
  }

  logout(): void {
    if (this.token()) {
      this.http
        .post(`${environment.apiUrl}/auth/logout`, {})
        .pipe(catchError(() => EMPTY))
        .subscribe();
    }

    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.token.set(null);
    this.user.set(null);
    this.router.navigate(['/login']);
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
