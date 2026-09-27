<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Auth;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Application\User\Handler\ResetPasswordHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use App\Infrastructure\Http\Controller\MapsTokenErrorsTrait;
use App\Infrastructure\Http\Validation\PasswordStrength;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ResetPasswordController extends AbstractController
{
    use ApiResponseTrait;
    use MapsTokenErrorsTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/auth/password/reset', name: 'api_reset_password', methods: ['POST'])]
    public function __invoke(Request $request, ResetPasswordHandler $handler): JsonResponse
    {
        $body = JsonBody::fromRequest($request);

        $errors = $this->validateResetPasswordRequest($body);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $handler->__invoke(new ResetPasswordInputDTO(
                token: $body->string('token'),
                newPassword: $body->string('password'),
            ));

            return $this->success([
                'message' => 'Password has been reset successfully. You can now log in.',
            ]);
        } catch (InvalidTokenException $exception) {
            return $this->error($this->mapTokenErrorMessage($exception), $exception->getErrorCode(), 400);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateResetPasswordRequest(JsonBody $body): array
    {
        $errors = [];

        $tokenViolations = $this->validator->validate($body->string('token'), [
            new Assert\NotBlank(message: 'Reset token is required'),
        ]);

        if (count($tokenViolations) > 0) {
            $errors['token'] = (string) $tokenViolations->get(0)->getMessage();
        }

        $passwordViolations = $this->validator->validate($body->string('password'), [
            new Assert\NotBlank(message: 'Password is required'),
            new PasswordStrength(),
        ]);

        if (count($passwordViolations) > 0) {
            $errors['password'] = (string) $passwordViolations->get(0)->getMessage();
        }

        return $errors;
    }
}
