<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\Handler\GetUserHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CurrentTherapistController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/me', name: 'api_therapist_me', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(GetUserHandler $handler): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        try {
            $user = $handler->__invoke($currentUser->getId()->getValue());

            return $this->success($user->toArray());
        } catch (UserNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }
}
