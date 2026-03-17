import { ApplicationConfig, provideAppInitializer, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable } from 'rxjs';

import { routes } from './app.routes';
import { authInterceptor } from './auth/data-access/auth.interceptor';
import { AuthService } from './auth/data-access/auth.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideHttpClient(withInterceptors([authInterceptor])),
    provideAppInitializer((): Observable<void> => {
      const authService: AuthService = inject(AuthService);
      return authService.init();
    }),
  ],
};
