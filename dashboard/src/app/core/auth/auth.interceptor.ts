import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { tap } from 'rxjs';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const token = authService.token();

  if (token) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    });
  }

  return next(req).pipe(
    tap({
      error: (error: HttpErrorResponse) => {
        // Only auto-logout on 401 if we had a token (avoid loop on login failure)
        if (error.status === 401 && token) {
          authService.logout();
        }
      },
    }),
  );
};
