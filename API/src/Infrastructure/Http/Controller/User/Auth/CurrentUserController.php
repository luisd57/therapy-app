<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Auth;

use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Entity\User;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CurrentUserController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        return $this->success(UserOutputDTO::fromEntity($user)->toArray());
    }
}
