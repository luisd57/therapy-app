<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\User\DTO\Input\ActivatePatientInputDTO;
use App\Application\User\DTO\Input\RequestPasswordResetInputDTO;
use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Application\User\Handler\ActivatePatientHandler;
use App\Application\User\Handler\LoginHandler;
use App\Application\User\Handler\RequestPasswordResetHandler;
use App\Application\User\Handler\ResetPasswordHandler;
use App\Application\User\Handler\ValidateInvitationHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/therapist/login', name: 'api_therapist_login', methods: ['POST'])]
    public function therapistLogin(Request $request, LoginHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLoginRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->handleTherapistLogin(
                email: $data['email'],
                password: $data['password'],
            );

            return $this->success([
                'token' => $result->token,
                'user' => $result->user->toArray(),
            ]);
        } catch (InvalidCredentialsException) {
            return $this->error('Invalid email or password', 'INVALID_CREDENTIALS', 401);
        } catch (UserNotActiveException) {
            return $this->error('Account is not active', 'USER_NOT_ACTIVE', 401);
        }
    }

    #[Route('/patient/login', name: 'api_patient_login', methods: ['POST'])]
    public function patientLogin(Request $request, LoginHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLoginRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->handlePatientLogin(
                email: $data['email'],
                password: $data['password'],
            );

            return $this->success([
                'token' => $result->token,
                'user' => $result->user->toArray(),
            ]);
        } catch (InvalidCredentialsException) {
            return $this->error('Invalid email or password', 'INVALID_CREDENTIALS', 401);
        } catch (UserNotActiveException) {
            return $this->error('Account is not active', 'USER_NOT_ACTIVE', 401);
        }
    }

    #[Route('/invitation/validate/{token}', name: 'api_validate_invitation', methods: ['GET'])]
    public function validateInvitation(string $token, ValidateInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler->handle($token);

            return $this->success($invitation->toArray());
        } catch (InvalidTokenException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 400);
        }
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, ActivatePatientHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateRegistrationRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $handler->handle(new ActivatePatientInputDTO(
                token: $data['token'],
                password: $data['password'],
            ));

            return $this->created([
                'user' => $user->toArray(),
                'message' => 'Account activated successfully. You can now log in.',
            ]);
        } catch (InvalidTokenException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 400);
        }
    }

    #[Route('/password/forgot', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, RequestPasswordResetHandler $handler): JsonResponse
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

        $handler->handle(new RequestPasswordResetInputDTO(email: $data['email']));

        // Always return success to prevent email enumeration
        return $this->success([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    #[Route('/password/reset', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, ResetPasswordHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateResetPasswordRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $handler->handle(new ResetPasswordInputDTO(
                token: $data['token'],
                newPassword: $data['password'],
            ));

            return $this->success([
                'message' => 'Password has been reset successfully. You can now log in.',
            ]);
        } catch (InvalidTokenException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 400);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateLoginRequest(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRegistrationRequest(array $data): array
    {
        $errors = [];

        if (empty($data['token'])) {
            $errors['token'] = 'Invitation token is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (!empty($data['password_confirmation']) && $data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateResetPasswordRequest(array $data): array
    {
        $errors = [];

        if (empty($data['token'])) {
            $errors['token'] = 'Reset token is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        return $errors;
    }
}
