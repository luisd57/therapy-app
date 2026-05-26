import { DatePipe } from '@angular/common';
import { Component, inject, OnInit, signal, WritableSignal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { Router } from '@angular/router';
import { PatientsService } from '../data-access/patients.service';
import { Invitation } from '../utils/invitation.model';
import { PaginationMeta, Patient, PatientsPage } from '../utils/patient.model';
import { InvitePatientDialogComponent } from './invite-patient-dialog.component';

const DEFAULT_LIMIT: number = 20;

@Component({
  selector: 'app-patients-list-page',
  imports: [
    DatePipe,
    MatTableModule,
    MatPaginatorModule,
    MatProgressSpinnerModule,
    MatButtonModule,
  ],
  templateUrl: './patients-list.page.html',
  styleUrl: './patients-list.page.scss',
})
export class PatientsListPage implements OnInit {
  private readonly patientsService: PatientsService = inject(PatientsService);
  private readonly dialog: MatDialog = inject(MatDialog);
  private readonly router: Router = inject(Router);
  private readonly snackBar: MatSnackBar = inject(MatSnackBar);

  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');
  readonly patients: WritableSignal<Patient[]> = signal([]);
  readonly pagination: WritableSignal<PaginationMeta> = signal({
    page: 1,
    limit: DEFAULT_LIMIT,
    total: 0,
    total_pages: 0,
  });

  protected readonly displayedColumns: readonly string[] = [
    'full_name',
    'email',
    'phone',
    'activated_at',
  ];

  ngOnInit(): void {
    this.fetch(1, DEFAULT_LIMIT);
  }

  protected onPageChange(event: PageEvent): void {
    this.fetch(event.pageIndex + 1, event.pageSize);
  }

  protected openInviteDialog(): void {
    const ref = this.dialog.open(InvitePatientDialogComponent, { autoFocus: 'first-tabbable' });
    ref.afterClosed().subscribe((result: Invitation | undefined): void => {
      if (result) {
        this.snackBar.open('Invitation sent.', 'Close', { duration: 4000 });
        void this.router.navigate(['/patients/invitations']);
      }
    });
  }

  private fetch(page: number, limit: number): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.patientsService.listPatients(page, limit).subscribe({
      next: (response: PatientsPage): void => {
        this.patients.set(response.patients);
        this.pagination.set(response.pagination);
        this.isLoading.set(false);
      },
      error: (): void => {
        this.errorMessage.set('Could not load patients.');
        this.isLoading.set(false);
      },
    });
  }
}
