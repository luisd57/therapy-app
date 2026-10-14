import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { CurrentUserService } from '../../shared/data-access/current-user.service';

export const authGuard: CanActivateFn = (): boolean | UrlTree => {
  const currentUser: CurrentUserService = inject(CurrentUserService);
  const router: Router = inject(Router);

  if (currentUser.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/login']);
};
