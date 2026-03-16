import { Routes } from '@angular/router';

export const appointmentsRoutes: Routes = [
  {
    path: '',
    loadComponent: () => import('./appointments.page').then((c) => c.AppointmentsPage),
  },
];
