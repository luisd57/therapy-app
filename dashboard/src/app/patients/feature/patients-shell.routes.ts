import { Routes } from '@angular/router';

export const patientsRoutes: Routes = [
  {
    path: '',
    loadComponent: () => import('./patients.page').then((c) => c.PatientsPage),
  },
];
