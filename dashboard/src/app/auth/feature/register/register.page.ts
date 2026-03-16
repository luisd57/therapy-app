import { Component, OnInit, inject, signal, WritableSignal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AuthService } from '../../data-access/auth.service';
import { passwordStrength, passwordMatch } from '../../utils/password.validators';

@Component({
  selector: 'app-register-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './register.page.html',
  styleUrl: './register.page.scss',
})
export class RegisterPage implements OnInit {
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly route: ActivatedRoute = inject(ActivatedRoute);
  private readonly authService: AuthService = inject(AuthService);

  readonly validating: WritableSignal<boolean> = signal(true);
  readonly validationError: WritableSignal<string> = signal('');
  readonly patientName: WritableSignal<string> = signal('');
  readonly hidePassword: WritableSignal<boolean> = signal(true);
  readonly hideConfirm: WritableSignal<boolean> = signal(true);
  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');
  readonly registered: WritableSignal<boolean> = signal(false);

  private token = '';

  readonly registerForm = this.fb.nonNullable.group(
    {
      password: ['', [Validators.required, passwordStrength()]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: [passwordMatch('password', 'password_confirmation')] },
  );

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParams['token'] as string;
    this.authService.validateInvitation(this.token).subscribe({
      next: (data) => {
        this.patientName.set(data.patient_name);
        this.validating.set(false);
      },
      error: (err) => {
        this.validationError.set(
          err?.error?.error?.message ?? err?.message ?? 'Invalid or expired invitation.',
        );
        this.validating.set(false);
      },
    });
  }

  onSubmit(): void {
    if (this.registerForm.invalid) return;

    this.isLoading.set(true);
    this.errorMessage.set('');

    const values = this.registerForm.getRawValue();
    this.authService
      .register({
        token: this.token,
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      .subscribe({
        next: () => {
          this.isLoading.set(false);
          this.registered.set(true);
        },
        error: (err) => {
          this.isLoading.set(false);
          this.errorMessage.set(
            err?.error?.error?.message ?? err?.message ?? 'Registration failed. Please try again.',
          );
        },
      });
  }
}
