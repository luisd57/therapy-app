<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User\Auth;

use App\Application\User\DTO\Input\ActivatePatientInputDTO;
use App\Application\User\Handler\ActivatePatientHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\MapsTokenErrorsTrait;
use App\Infrastructure\Http\Validation\PasswordStrength;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    use ApiResponseTrait;
    use MapsTokenErrorsTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/auth/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request, ActivatePatientHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateRegistrationRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $handler->__invoke(new ActivatePatientInputDTO(
                token: $data['token'],
                password: $data['password'],
            ));

            return $this->created([
                'user' => $user->toArray(),
                'message' => 'Account activated successfully. You can now log in.',
            ]);
        } catch (InvalidTokenException $exception) {
            return $this->error($this->mapTokenErrorMessage($exception), $exception->getErrorCode(), 400);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateRegistrationRequest(array $data): array
    {
        $errors = [];

        $tokenViolations = $this->validator->validate($data['token'] ?? '', [
            new Assert\NotBlank(message: 'Invitation token is required'),
        ]);

        if (count($tokenViolations) > 0) {
            $errors['token'] = $tokenViolations[0]->getMessage();
        }

        $passwordViolations = $this->validator->validate($data['password'] ?? '', [
            new Assert\NotBlank(message: 'Password is required'),
            new PasswordStrength(),
        ]);

        if (count($passwordViolations) > 0) {
            $errors['password'] = $passwordViolations[0]->getMessage();
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }
}
