import { Component, OnInit, inject, signal, WritableSignal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from '../../data-access/auth.service';
import { extractErrorMessage } from '../../../shared/utils/api-response.model';
import { passwordStrength, passwordMatch } from '../../utils/password.validators';

@Component({
  selector: 'app-reset-password-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
  ],
  templateUrl: './reset-password.page.html',
  styleUrl: './reset-password.page.scss',
})
export class ResetPasswordPage implements OnInit {
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly route: ActivatedRoute = inject(ActivatedRoute);
  private readonly authService: AuthService = inject(AuthService);

  readonly hidePassword: WritableSignal<boolean> = signal(true);
  readonly hideConfirm: WritableSignal<boolean> = signal(true);
  readonly isLoading: WritableSignal<boolean> = signal(false);
  readonly errorMessage: WritableSignal<string> = signal('');
  readonly resetComplete: WritableSignal<boolean> = signal(false);

  private token: string = '';

  readonly resetForm: FormGroup<{
    password: FormControl<string>;
    password_confirmation: FormControl<string>;
  }> = this.fb.nonNullable.group(
    {
      password: ['', [Validators.required, passwordStrength()]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: [passwordMatch('password', 'password_confirmation')] },
  );

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParams['token'] as string;
  }

  onSubmit(): void {
    if (this.resetForm.invalid) return;

    this.isLoading.set(true);
    this.errorMessage.set('');

    const values: { password: string; password_confirmation: string } =
      this.resetForm.getRawValue();
    this.authService
      .resetPassword({
        token: this.token,
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      .subscribe({
        next: (): void => {
          this.isLoading.set(false);
          this.resetComplete.set(true);
        },
        error: (err: unknown): void => {
          this.isLoading.set(false);
          this.errorMessage.set(
            extractErrorMessage(err, 'Password reset failed. Please try again.'),
          );
        },
      });
  }
}
