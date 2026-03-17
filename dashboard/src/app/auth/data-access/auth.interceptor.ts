import { HttpErrorResponse, HttpEvent, HttpHandlerFn, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> => {
  const authService: AuthService = inject(AuthService);

  const clonedReq: HttpRequest<unknown> = req.clone({ withCredentials: true });

  return next(clonedReq).pipe(
    tap({
      error: (error: HttpErrorResponse): void => {
        if (error.status === 401 && authService.isAuthenticated()) {
          authService.logout();
        }
      },
    }),
  );
};
