<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

use Symfony\Component\Validator\Constraint;

/** An email field: required, and valid exactly when Email::fromString accepts it. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class EmailAddress extends Constraint
{
    public string $requiredMessage = 'Email is required';
    public string $invalidMessage = 'Invalid email format';
}
