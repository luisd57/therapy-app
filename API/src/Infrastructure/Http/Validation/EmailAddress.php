<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/** The rules every email field shares, so a caller's 422 matches what Email::fromString accepts. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class EmailAddress extends Compound
{
    /** @param array<string, mixed> $options */
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
            // filter_var's own cap, which Email::fromString relies on. The column is 255.
            new Assert\Length(max: 254, maxMessage: 'Email must not exceed 254 characters'),
        ];
    }
}
