<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

use App\Domain\User\ValueObject\Email;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class EmailAddressValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EmailAddress) {
            throw new UnexpectedTypeException($constraint, EmailAddress::class);
        }

        if ($value === null || $value === '') {
            $this->context->buildViolation($constraint->requiredMessage)
                ->addViolation();

            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Delegates so the 422 can never disagree with what the handler will accept.
        try {
            Email::fromString($value);
        } catch (\InvalidArgumentException) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->addViolation();
        }
    }
}
