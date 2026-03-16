import { Component, inject, signal, WritableSignal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from '../../data-access/auth.service';

@Component({
  selector: 'app-patient-login-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
  ],
  templateUrl: './patient-login.page.html',
  styleUrl: './patient-login.page.scss',
})
export class PatientLoginPage {
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly authService: AuthService = inject(AuthService);

  readonly hidePassword: WritableSignal<boolean> = signal(true);
  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');

  readonly loginForm = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  onSubmit(): void {
    if (this.loginForm.invalid) return;

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.authService.patientLogin(this.loginForm.getRawValue()).subscribe({
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(
          err?.error?.error?.message ?? err?.message ?? 'Login failed. Please try again.',
        );
      },
    });
  }
}
