import { Injectable, Signal, WritableSignal, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, map, catchError, EMPTY, finalize } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../utils/api-response.model';
import { CurrentUser } from '../utils/current-user.model';

const USER_KEY: string = 'auth_user';

// In shared/ so layout/'s shell can read it without importing across domains.
@Injectable({ providedIn: 'root' })
export class CurrentUserService {
  private readonly http: HttpClient = inject(HttpClient);
  private readonly router: Router = inject(Router);

  readonly user: WritableSignal<CurrentUser | null> = signal<CurrentUser | null>(this.loadUser());
  readonly isAuthenticated: Signal<boolean> = computed((): boolean => this.user() !== null);
  readonly initialized: WritableSignal<boolean> = signal(false);

  init(): Observable<void> {
    const storedUser: CurrentUser | null = this.loadUser();
    if (!storedUser) {
      this.initialized.set(true);
      return EMPTY;
    }

    return this.http
      .get<ApiResponse<CurrentUser>>(`${environment.apiUrl}/auth/me`)
      .pipe(
        tap((response: ApiResponse<CurrentUser>): void => {
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

  signIn(user: CurrentUser): void {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this.user.set(user);
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

  private loadUser(): CurrentUser | null {
    const stored: string | null = localStorage.getItem(USER_KEY);
    if (!stored) return null;
    try {
      return JSON.parse(stored) as CurrentUser;
    } catch {
      return null;
    }
  }
}
