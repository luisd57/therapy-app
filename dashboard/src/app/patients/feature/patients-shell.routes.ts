import { Routes } from '@angular/router';

export const patientsRoutes: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./patients-shell.component').then((c) => c.PatientsShellComponent),
    children: [
      {
        path: '',
        loadComponent: () => import('./patients-list.page').then((c) => c.PatientsListPage),
      },
      {
        path: 'invitations',
        loadComponent: () =>
          import('./invitations-list.page').then((c) => c.InvitationsListPage),
      },
    ],
  },
];
