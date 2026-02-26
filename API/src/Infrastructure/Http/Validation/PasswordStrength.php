<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PasswordStrength extends Constraint
{
    public string $minLengthMessage = 'Password must be between 8 and 72 characters';
    public string $uppercaseMessage = 'Password must contain at least one uppercase letter';
    public string $lowercaseMessage = 'Password must contain at least one lowercase letter';
    public string $digitMessage = 'Password must contain at least one number';
    public string $specialCharMessage = 'Password must contain at least one special character';
}
