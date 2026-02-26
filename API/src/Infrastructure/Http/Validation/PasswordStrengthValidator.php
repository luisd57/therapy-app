<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $value = (string) $value;

        if (strlen($value) < 8 || strlen($value) > 72) {
            $this->context->buildViolation($constraint->minLengthMessage)
                ->addViolation();

            return;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->uppercaseMessage)
                ->addViolation();

            return;
        }

        if (!preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->lowercaseMessage)
                ->addViolation();

            return;
        }

        if (!preg_match('/[0-9]/', $value)) {
            $this->context->buildViolation($constraint->digitMessage)
                ->addViolation();

            return;
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
            $this->context->buildViolation($constraint->specialCharMessage)
                ->addViolation();
        }
    }
}
