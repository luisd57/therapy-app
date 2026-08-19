<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User\Auth;

use App\Application\User\Handler\ValidateInvitationHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\MapsTokenErrorsTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ValidateInvitationController extends AbstractController
{
    use ApiResponseTrait;
    use MapsTokenErrorsTrait;

    #[Route('/api/auth/invitation/validate/{token}', name: 'api_validate_invitation', methods: ['GET'])]
    public function __invoke(string $token, ValidateInvitationHandler $handler): JsonResponse
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
}
