import { Component, inject, signal, WritableSignal } from '@angular/core';
import { FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import {
  MatDialogActions,
  MatDialogContent,
  MatDialogRef,
  MatDialogTitle,
} from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { extractErrorMessage } from '../../shared/utils/api-response.model';
import { PatientsService } from '../data-access/patients.service';
import { Invitation } from '../utils/invitation.model';

@Component({
  selector: 'app-invite-patient-dialog',
  imports: [
    ReactiveFormsModule,
    MatDialogTitle,
    MatDialogContent,
    MatDialogActions,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './invite-patient-dialog.component.html',
  styleUrl: './invite-patient-dialog.component.scss',
})
export class InvitePatientDialogComponent {
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly patientsService: PatientsService = inject(PatientsService);
  private readonly dialogRef: MatDialogRef<InvitePatientDialogComponent, Invitation | undefined> =
    inject(MatDialogRef);

  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');

  readonly inviteForm: FormGroup<{
    email: FormControl<string>;
    patient_name: FormControl<string>;
  }> = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    patient_name: ['', [Validators.required, Validators.maxLength(255)]],
  });

  protected onCancel(): void {
    this.dialogRef.close(undefined);
  }

  protected onSubmit(): void {
    if (this.inviteForm.invalid) return;

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.patientsService.invite(this.inviteForm.getRawValue()).subscribe({
      next: (invitation: Invitation): void => {
        this.isLoading.set(false);
        this.dialogRef.close(invitation);
      },
      error: (err: unknown): void => {
        this.isLoading.set(false);
        this.errorMessage.set(
          extractErrorMessage(err, 'Could not send invitation. Please try again.'),
        );
      },
    });
  }
}
