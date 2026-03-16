import { Routes } from '@angular/router';
import { authGuard } from './auth/data-access/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./auth/feature/login.page').then((c) => c.LoginPage),
  },
  {
    path: 'patient-login',
    loadComponent: () =>
      import('./auth/feature/patient-login/patient-login.page').then((c) => c.PatientLoginPage),
  },
  {
    path: 'register',
    loadComponent: () =>
      import('./auth/feature/register/register.page').then((c) => c.RegisterPage),
  },
  {
    path: 'forgot-password',
    loadComponent: () =>
      import('./auth/feature/forgot-password/forgot-password.page').then(
        (c) => c.ForgotPasswordPage,
      ),
  },
  {
    path: 'reset-password',
    loadComponent: () =>
      import('./auth/feature/reset-password/reset-password.page').then(
        (c) => c.ResetPasswordPage,
      ),
  },
  {
    path: '',
    loadComponent: () => import('./layout/shell.component').then((c) => c.ShellComponent),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'appointments', pathMatch: 'full' },
      {
        path: 'appointments',
        loadChildren: () =>
          import('./appointments/feature/appointments-shell.routes').then(
            (r) => r.appointmentsRoutes,
          ),
      },
      {
        path: 'schedule',
        loadChildren: () =>
          import('./schedule/feature/schedule-shell.routes').then((r) => r.scheduleRoutes),
      },
      {
        path: 'patients',
        loadChildren: () =>
          import('./patients/feature/patients-shell.routes').then((r) => r.patientsRoutes),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
