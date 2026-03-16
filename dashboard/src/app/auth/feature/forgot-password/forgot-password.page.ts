import { Component, inject, signal, WritableSignal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { AuthService } from '../../data-access/auth.service';

@Component({
  selector: 'app-forgot-password-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
  ],
  templateUrl: './forgot-password.page.html',
  styleUrl: './forgot-password.page.scss',
})
export class ForgotPasswordPage {
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly authService: AuthService = inject(AuthService);

  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly submitted: WritableSignal<boolean> = signal(false);

  readonly forgotForm = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  onSubmit(): void {
    if (this.forgotForm.invalid) return;

    this.isLoading.set(true);

    this.authService.forgotPassword(this.forgotForm.getRawValue()).subscribe({
      next: () => {
        this.isLoading.set(false);
        this.submitted.set(true);
      },
      error: () => {
        this.isLoading.set(false);
        this.submitted.set(true);
      },
    });
  }
}
