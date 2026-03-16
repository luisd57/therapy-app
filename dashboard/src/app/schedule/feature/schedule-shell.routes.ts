import { Routes } from '@angular/router';

export const scheduleRoutes: Routes = [
  {
    path: '',
    loadComponent: () => import('./schedule.page').then((c) => c.SchedulePage),
  },
];
