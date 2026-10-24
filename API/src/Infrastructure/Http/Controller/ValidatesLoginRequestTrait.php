<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Http\Validation\EmailAddress;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidatesLoginRequestTrait
{
    /**
     * @return array<string, string>
     */
    private function validateLoginRequest(ValidatorInterface $validator, JsonBody $jsonBody): array
    {
        $errors = [];

        $emailViolations = $validator->validate($jsonBody->string('email'), new EmailAddress());

        if (count($emailViolations) > 0) {
            $errors['email'] = (string) $emailViolations->get(0)->getMessage();
        }

        $passwordViolations = $validator->validate($jsonBody->string('password'), [
            new Assert\NotBlank(message: 'Password is required'),
        ]);

        if (count($passwordViolations) > 0) {
            $errors['password'] = (string) $passwordViolations->get(0)->getMessage();
        }

        return $errors;
    }
}
