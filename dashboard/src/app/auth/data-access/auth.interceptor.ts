import { HttpErrorResponse, HttpEvent, HttpHandlerFn, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { CurrentUserService } from '../../shared/data-access/current-user.service';

export const authInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> => {
  const currentUser: CurrentUserService = inject(CurrentUserService);

  const clonedReq: HttpRequest<unknown> = req.clone({ withCredentials: true });

  return next(clonedReq).pipe(
    tap({
      error: (error: HttpErrorResponse): void => {
        if (error.status === 401 && currentUser.isAuthenticated()) {
          currentUser.logout();
        }
      },
    }),
  );
};
