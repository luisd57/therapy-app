import { DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, Signal, signal, WritableSignal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialog } from '@angular/material/dialog';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import {
  ConfirmDialogComponent,
  ConfirmDialogData,
} from '../../shared/ui/confirm-dialog/confirm-dialog.component';
import { PatientsService } from '../data-access/patients.service';
import { InvitationStatusChipComponent } from '../ui/invitation-status-chip.component';
import { InvitationsRowActionsComponent } from '../ui/invitations-row-actions.component';
import { Invitation, InvitationStatus } from '../utils/invitation.model';
import { InvitePatientDialogComponent } from './invite-patient-dialog.component';

type FilterValue = InvitationStatus | 'all';

interface FilterOption {
  value: FilterValue;
  label: string;
}

@Component({
  selector: 'app-invitations-list-page',
  imports: [
    DatePipe,
    MatTableModule,
    MatChipsModule,
    MatButtonModule,
    MatProgressSpinnerModule,
    InvitationStatusChipComponent,
    InvitationsRowActionsComponent,
  ],
  templateUrl: './invitations-list.page.html',
  styleUrl: './invitations-list.page.scss',
})
export class InvitationsListPage implements OnInit {
  private readonly patientsService: PatientsService = inject(PatientsService);
  private readonly dialog: MatDialog = inject(MatDialog);
  private readonly snackBar: MatSnackBar = inject(MatSnackBar);

  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');
  readonly invitations: WritableSignal<Invitation[]> = signal([]);
  readonly selectedFilter: WritableSignal<FilterValue> = signal('all');

  protected readonly filterOptions: readonly FilterOption[] = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'expired', label: 'Expired' },
    { value: 'used', label: 'Used' },
    { value: 'revoked', label: 'Revoked' },
  ];

  protected readonly displayedColumns: readonly string[] = [
    'email',
    'patient_name',
    'status',
    'created_at',
    'expires_at',
    'actions',
  ];

  protected readonly filteredInvitations: Signal<Invitation[]> = computed((): Invitation[] => {
    const filter: FilterValue = this.selectedFilter();
    const all: Invitation[] = this.invitations();
    return filter === 'all' ? all : all.filter((inv: Invitation): boolean => inv.status === filter);
  });

  ngOnInit(): void {
    this.fetch();
  }

  protected onFilterChange(value: FilterValue): void {
    this.selectedFilter.set(value);
  }

  protected openInviteDialog(): void {
    const ref = this.dialog.open(InvitePatientDialogComponent, { autoFocus: 'first-tabbable' });
    ref.afterClosed().subscribe((result: Invitation | undefined): void => {
      if (result) {
        this.snackBar.open('Invitation sent.', 'Close', { duration: 4000 });
        this.fetch();
      }
    });
  }

  protected onResend(invitation: Invitation): void {
    const data: ConfirmDialogData = {
      title: 'Resend invitation?',
      message:
        'A new link will be sent and the previous link will be invalidated. The patient will receive a fresh email.',
      confirmLabel: 'Resend',
    };
    const ref = this.dialog.open(ConfirmDialogComponent, { data });
    ref.afterClosed().subscribe((confirmed: boolean | undefined): void => {
      if (!confirmed) return;
      this.patientsService.resend(invitation.id).subscribe({
        next: (): void => {
          this.snackBar.open('Invitation resent.', 'Close', { duration: 4000 });
          this.fetch();
        },
        error: (): void => {
          this.snackBar.open('Could not resend invitation.', 'Close', { duration: 4000 });
        },
      });
    });
  }

  protected onRevoke(invitation: Invitation): void {
    const data: ConfirmDialogData = {
      title: 'Revoke invitation?',
      message:
        'The patient will not be able to use the registration link. This action cannot be undone.',
      confirmLabel: 'Revoke',
      confirmColor: 'warn',
    };
    const ref = this.dialog.open(ConfirmDialogComponent, { data });
    ref.afterClosed().subscribe((confirmed: boolean | undefined): void => {
      if (!confirmed) return;
      this.patientsService.revoke(invitation.id).subscribe({
        next: (): void => {
          this.snackBar.open('Invitation revoked.', 'Close', { duration: 4000 });
          this.fetch();
        },
        error: (): void => {
          this.snackBar.open('Could not revoke invitation.', 'Close', { duration: 4000 });
        },
      });
    });
  }

  private fetch(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.patientsService.listInvitations().subscribe({
      next: (invitations: Invitation[]): void => {
        this.invitations.set(invitations);
        this.isLoading.set(false);
      },
      error: (): void => {
        this.errorMessage.set('Could not load invitations.');
        this.isLoading.set(false);
      },
    });
  }
}
