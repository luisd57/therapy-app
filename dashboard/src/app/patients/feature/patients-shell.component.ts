import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTabsModule } from '@angular/material/tabs';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Invitation } from '../utils/invitation.model';
import { InvitePatientDialogComponent } from './invite-patient-dialog.component';

@Component({
  selector: 'app-patients-shell',
  imports: [
    MatTabsModule,
    MatButtonModule,
    MatIconModule,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
  ],
  templateUrl: './patients-shell.component.html',
  styleUrl: './patients-shell.component.scss',
})
export class PatientsShellComponent {
  private readonly dialog: MatDialog = inject(MatDialog);
  private readonly router: Router = inject(Router);
  private readonly snackBar: MatSnackBar = inject(MatSnackBar);

  protected openInviteDialog(): void {
    const ref: MatDialogRef<InvitePatientDialogComponent, Invitation | undefined> =
      this.dialog.open(InvitePatientDialogComponent, { autoFocus: 'first-tabbable' });
    ref.afterClosed().subscribe((result: Invitation | undefined): void => {
      if (result) {
        this.snackBar.open('Invitation sent.', 'Close', { duration: 4000 });
        void this.router.navigate(['/patients/invitations']);
      }
    });
  }
}
