<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Auth;

use App\Application\User\DTO\Input\PatientLoginInputDTO;
use App\Application\User\Handler\PatientLoginHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesLoginRequestTrait;
use App\Infrastructure\Security\JwtCookieManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PatientLoginController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesLoginRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly JwtCookieManager $jwtCookieManager,
    ) {}

    #[Route('/api/auth/patient/login', name: 'api_patient_login', methods: ['POST'])]
    public function __invoke(Request $request, PatientLoginHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLoginRequest($this->validator, $data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new PatientLoginInputDTO(
                email: $data['email'],
                password: $data['password'],
            ));

            $response = $this->success(['user' => $result->user->toArray()]);
            $response->headers->setCookie(
                $this->jwtCookieManager->createCookie($result->token),
            );

            return $response;
        } catch (InvalidCredentialsException $exception) {
            // Intentionally hardcoded: don't leak whether email or password was wrong
            return $this->error('Invalid email or password', $exception->getErrorCode(), 401);
        } catch (UserNotActiveException $exception) {
            // Intentionally hardcoded: consistent generic message for security
            return $this->error('Account is not active', $exception->getErrorCode(), 401);
        }
    }
}
