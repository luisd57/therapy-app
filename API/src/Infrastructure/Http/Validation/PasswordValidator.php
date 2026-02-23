<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

final class PasswordValidator
{
    /**
     * @return string|null Error message, or null if valid
     */
    public static function validate(string $password): ?string
    {
        if (strlen($password) < 8 || strlen($password) > 16) {
            return 'Password must be between 8 and 16 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Password must contain at least one special character';
        }

        return null;
    }
}
