import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export function passwordStrength(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value: string = control.value as string;
    if (!value) return null;

    const errors: ValidationErrors = {};

    if (value.length < 8) errors['minLength'] = true;
    if (value.length > 72) errors['maxLength'] = true;
    if (!/[A-Z]/.test(value)) errors['uppercase'] = true;
    if (!/[a-z]/.test(value)) errors['lowercase'] = true;
    if (!/[0-9]/.test(value)) errors['digit'] = true;
    if (!/[^A-Za-z0-9]/.test(value)) errors['special'] = true;

    return Object.keys(errors).length ? { passwordStrength: errors } : null;
  };
}

export function passwordMatch(passwordField: string, confirmField: string): ValidatorFn {
  return (group: AbstractControl): ValidationErrors | null => {
    const password: string = group.get(passwordField)?.value as string;
    const confirm: string = group.get(confirmField)?.value as string;

    if (!password || !confirm) return null;

    return password === confirm ? null : { passwordMatch: true };
  };
}
