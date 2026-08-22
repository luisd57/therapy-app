<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\Handler\ListInvitationsHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListInvitationsController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/invitations', name: 'api_therapist_list_invitations', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(ListInvitationsHandler $handler): JsonResponse
    {
        $invitations = $handler->__invoke();

        return $this->success([
            'invitations' => $invitations->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $invitations->count(),
        ]);
    }
}
