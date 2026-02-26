<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\User\DTO\Input\ActivatePatientInputDTO;
use App\Application\User\DTO\Input\PatientLoginInputDTO;
use App\Application\User\DTO\Input\RequestPasswordResetInputDTO;
use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Application\User\DTO\Input\TherapistLoginInputDTO;
use App\Application\User\Handler\ActivatePatientHandler;
use App\Application\User\Handler\PatientLoginHandler;
use App\Application\User\Handler\RequestPasswordResetHandler;
use App\Application\User\Handler\ResetPasswordHandler;
use App\Application\User\Handler\TherapistLoginHandler;
use App\Application\User\Handler\ValidateInvitationHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Service\JwtBlocklistInterface;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use App\Infrastructure\Http\Validation\PasswordStrength;
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
    use ValidatesRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/therapist/login', name: 'api_therapist_login', methods: ['POST'])]
    public function therapistLogin(Request $request, TherapistLoginHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLoginRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new TherapistLoginInputDTO(
                email: $data['email'],
                password: $data['password'],
            ));

            return $this->success([
                'token' => $result->token,
                'user' => $result->user->toArray(),
            ]);
        } catch (InvalidCredentialsException $exception) {
            // Intentionally hardcoded: don't leak whether email or password was wrong
            return $this->error('Invalid email or password', $exception->getErrorCode(), 401);
        } catch (UserNotActiveException $exception) {
            // Intentionally hardcoded: consistent generic message for security
            return $this->error('Account is not active', $exception->getErrorCode(), 401);
        }
    }

    #[Route('/patient/login', name: 'api_patient_login', methods: ['POST'])]
    public function patientLogin(Request $request, PatientLoginHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLoginRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new PatientLoginInputDTO(
                email: $data['email'],
                password: $data['password'],
            ));

            return $this->success([
                'token' => $result->token,
                'user' => $result->user->toArray(),
            ]);
        } catch (InvalidCredentialsException $exception) {
            // Intentionally hardcoded: don't leak whether email or password was wrong
            return $this->error('Invalid email or password', $exception->getErrorCode(), 401);
        } catch (UserNotActiveException $exception) {
            // Intentionally hardcoded: consistent generic message for security
            return $this->error('Account is not active', $exception->getErrorCode(), 401);
        }
    }

    #[Route('/invitation/validate/{token}', name: 'api_validate_invitation', methods: ['GET'])]
    public function validateInvitation(string $token, ValidateInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler->__invoke($token);

            $publicData = $invitation->toArray();
            unset($publicData['email']);

            return $this->success($publicData);
        } catch (InvalidTokenException $exception) {
            return $this->error($this->mapTokenErrorMessage($exception), $exception->getErrorCode(), 400);
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

        $handler->__invoke(new RequestPasswordResetInputDTO(email: $data['email']));

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
            $handler->__invoke(new ResetPasswordInputDTO(
                token: $data['token'],
                newPassword: $data['password'],
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
    private function validateLoginRequest(array $data): array
    {
        $errors = [];

        $emailViolations = $this->validator->validate($data['email'] ?? '', [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = $emailViolations[0]->getMessage();
        }

        $passwordViolations = $this->validator->validate($data['password'] ?? '', [
            new Assert\NotBlank(message: 'Password is required'),
        ]);

        if (count($passwordViolations) > 0) {
            $errors['password'] = $passwordViolations[0]->getMessage();
        }

        return $errors;
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

    /**
     * @return array<string, string>
     */
    private function validateResetPasswordRequest(array $data): array
    {
        $errors = [];

        $tokenViolations = $this->validator->validate($data['token'] ?? '', [
            new Assert\NotBlank(message: 'Reset token is required'),
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

        return $errors;
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(
        Request $request,
        JwtBlocklistInterface $jwtBlocklist,
        \Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface $jwtTokenManager,
        \Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface $jwtEncoder,
    ): JsonResponse {
        $authHeader = $request->headers->get('Authorization', '');
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

        if ($token === '') {
            return $this->error('No token provided', 'NO_TOKEN', 400);
        }

        try {
            $payload = $jwtEncoder->decode($token);
            $jti = $payload['jti'] ?? null;

            if ($jti === null) {
                return $this->error('Token has no JTI claim', 'INVALID_TOKEN', 400);
            }

            $exp = $payload['exp'] ?? 0;
            $ttlSeconds = max(0, $exp - time());
            $jwtBlocklist->revoke($jti, $ttlSeconds);

            return $this->success(['message' => 'Successfully logged out.']);
        } catch (\Exception) {
            return $this->error('Invalid token', 'INVALID_TOKEN', 400);
        }
    }

    private function mapTokenErrorMessage(InvalidTokenException $invalidTokenException): string
    {
        return match ($invalidTokenException->getErrorCode()) {
            'TOKEN_EXPIRED' => 'Token has expired.',
            'TOKEN_ALREADY_USED' => 'Token has already been used.',
            'TOKEN_NOT_FOUND' => 'Invalid token.',
            default => 'Invalid token.',
        };
    }
}
