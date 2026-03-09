import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { tap } from 'rxjs';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);

  req = req.clone({ withCredentials: true });

  return next(req).pipe(
    tap({
      error: (error: HttpErrorResponse) => {
        // Only auto-logout on 401 if authenticated (avoid loop on login failure)
        if (error.status === 401 && authService.isAuthenticated()) {
          authService.logout();
        }
      },
    }),
  );
};
