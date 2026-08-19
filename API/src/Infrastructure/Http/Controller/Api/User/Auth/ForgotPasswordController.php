<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User\Auth;

use App\Application\User\DTO\Input\RequestPasswordResetInputDTO;
use App\Application\User\Handler\RequestPasswordResetHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ForgotPasswordController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/auth/password/forgot', name: 'api_forgot_password', methods: ['POST'])]
    public function __invoke(Request $request, RequestPasswordResetHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data['email'] ?? '', [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors['email'] = $violation->getMessage();
            }

            return $this->validationError($errors);
        }

        $handler->__invoke(new RequestPasswordResetInputDTO(email: $data['email']));

        // Always return success to prevent email enumeration
        return $this->success([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }
}
