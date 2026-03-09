import { Routes } from '@angular/router';
import { authGuard } from './core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () =>
      import('./features/login/login').then((c) => c.Login),
  },
  {
    path: '',
    loadComponent: () =>
      import('./layout/shell/shell').then((c) => c.Shell),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'appointments', pathMatch: 'full' },
      {
        path: 'appointments',
        loadComponent: () =>
          import('./features/appointments/appointments').then(
            (c) => c.Appointments,
          ),
      },
      {
        path: 'schedule',
        loadComponent: () =>
          import('./features/schedule/schedule').then((c) => c.Schedule),
      },
      {
        path: 'patients',
        loadComponent: () =>
          import('./features/patients/patients').then((c) => c.Patients),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
