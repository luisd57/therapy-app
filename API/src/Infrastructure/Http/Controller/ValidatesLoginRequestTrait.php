<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidatesLoginRequestTrait
{
    /**
     * @return array<string, string>
     */
    private function validateLoginRequest(ValidatorInterface $validator, array $data): array
    {
        $errors = [];

        $emailViolations = $validator->validate($data['email'] ?? '', [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = $emailViolations[0]->getMessage();
        }

        $passwordViolations = $validator->validate($data['password'] ?? '', [
            new Assert\NotBlank(message: 'Password is required'),
        ]);

        if (count($passwordViolations) > 0) {
            $errors['password'] = $passwordViolations[0]->getMessage();
        }

        return $errors;
    }
}
